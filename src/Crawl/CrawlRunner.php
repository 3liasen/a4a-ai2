<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Data\SnapshotRepository;
use Axs4allAi\Extraction\Extractor;

final class CrawlRunner
{
    private QueueRepository $repository;
    private ?PromptRepository $promptRepository;
    private ?ClassificationQueueRepository $classificationQueue;
    private ?ClientRepository $clients;
    private ?CategoryRepository $categories;
    private Scraper $scraper;
    private Extractor $extractor;
    private ?SnapshotRepository $snapshots;
    /** @var array<string, bool> */
    private array $seenHashes = [];
    /** @var array<string, bool> */
    private array $seenUrls = [];

    public function __construct(
        QueueRepository $repository,
        ?PromptRepository $promptRepository = null,
        ?ClassificationQueueRepository $classificationQueue = null,
        ?ClientRepository $clients = null,
        ?CategoryRepository $categories = null,
        ?Scraper $scraper = null,
        ?Extractor $extractor = null,
        ?SnapshotRepository $snapshots = null
    ) {
        $this->repository = $repository;
        $this->promptRepository = $promptRepository;
        $this->classificationQueue = $classificationQueue;
        $this->clients = $clients;
        $this->categories = $categories;
        $this->scraper = $scraper ?? new Scraper();
        $this->extractor = $extractor ?? new Extractor();
        $this->snapshots = $snapshots;
    }

    public function run(int $batchSize = 5): void
    {
        $this->seenHashes = [];
        $this->seenUrls = [];

        $pendingCount = $this->repository->countPending();
        error_log(sprintf('[axs4all-ai] Crawl runner starting. Pending items: %d.', $pendingCount));

        $items = $this->repository->getPending($batchSize);
        foreach ($items as $item) {
            $queueId = (int) $item['id'];
            $rootUrl = (string) $item['source_url'];
            $category = trim((string) $item['category']);
            $queueClientId = isset($item['client_id']) ? (int) $item['client_id'] : 0;
            $queueCategoryId = isset($item['category_id']) ? (int) $item['category_id'] : 0;
            $crawlSubpages = ! empty($item['crawl_subpages']);

            if ($category === '') {
                $category = 'default';
            }

            error_log(sprintf('[axs4all-ai] Processing queue item #%d (%s).', $queueId, $rootUrl));

            $client = $this->resolveClient($queueClientId, $rootUrl);
            $clientId = $client !== null && isset($client['id']) ? (int) $client['id'] : ($queueClientId > 0 ? $queueClientId : null);

            $pages = $this->collectPages($queueId, $rootUrl, $crawlSubpages);
            if (empty($pages)) {
                error_log(sprintf('[axs4all-ai] No pages fetched for queue item #%d.', $queueId));
                continue;
            }

            if ($this->classificationQueue === null) {
                continue;
            }

            $assignments = $this->determineCategoryAssignments($category, $client, $queueCategoryId);
            if (empty($assignments)) {
                error_log(sprintf('[axs4all-ai] No categories resolved for queue item #%d. Skipping classification.', $queueId));
                continue;
            }

            foreach ($pages as $page) {
                $pageUrl = $page['url'];
                $html = $page['html'];

                foreach ($assignments as $assignment) {
                    $categoryName = (string) $assignment['name'];
                    $categoryId = isset($assignment['id']) ? (int) $assignment['id'] : null;
                    $categoryMeta = $assignment['meta'];

                    $snippets = $this->extractor->extract($html, $categoryName, $categoryMeta);
                    if (empty($snippets)) {
                        continue;
                    }

                    $promptVersion = 'v1';
                    if ($this->promptRepository !== null) {
                        $template = $this->promptRepository->getActiveTemplate($categoryName);
                        $promptVersion = $template->version();
                    }

                    foreach ($snippets as $index => $snippet) {
                        $snippet = trim($snippet);
                        if ($snippet === '') {
                            continue;
                        }

                        $jobId = $this->classificationQueue->enqueue(
                            $queueId,
                            null,
                            $categoryName,
                            $promptVersion,
                            $snippet,
                            $clientId,
                            $categoryId,
                            $pageUrl
                        );

                        if ($jobId !== null) {
                            error_log(
                                sprintf(
                                    '[axs4all-ai] Enqueued classification job #%d for queue item #%d (%s, snippet %d).',
                                    $jobId,
                                    $queueId,
                                    $pageUrl,
                                    $index + 1
                                )
                            );
                        } else {
                            error_log(
                                sprintf(
                                    '[axs4all-ai] Failed to enqueue classification job for queue item #%d (%s, snippet %d).',
                                    $queueId,
                                    $pageUrl,
                                    $index + 1
                                )
                            );
                        }
                    }
                }
            }
        }

        error_log('[axs4all-ai] Crawl runner finished.');
    }

    /** @param array<string, mixed>|null $client */
    private function determineCategoryAssignments(string $fallbackCategory, ?array $client, ?int $queueCategoryId = null): array
    {
        $assignments = [];

        if ($queueCategoryId !== null && $queueCategoryId > 0) {
            $category = $this->resolveCategoryById($queueCategoryId);
            if ($category !== null) {
                $assignments[] = $this->buildAssignment($category);
            }
        }

        if (! empty($assignments)) {
            return $assignments;
        }

        if ($client !== null && $this->clients instanceof ClientRepository) {
            $clientId = isset($client['id']) ? (int) $client['id'] : 0;
            if ($clientId > 0) {
                foreach ($this->clients->getCategoryAssignments($clientId) as $categoryId) {
                    $category = $this->resolveCategoryById($categoryId);
                    if ($category === null) {
                        continue;
                    }

                    $assignments[] = $this->buildAssignment($category);
                }
            }
        }

        if (! empty($assignments)) {
            return $assignments;
        }

        $category = $this->resolveCategoryByName($fallbackCategory);
        if ($category !== null) {
            $assignments[] = $this->buildAssignment($category);
        } else {
            $assignments[] = [
                'id' => null,
                'name' => $fallbackCategory,
                'meta' => $this->extractCategoryMeta(),
            ];
        }

        return $assignments;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCategoryById(int $id): ?array
    {
        if ($id <= 0 || ! $this->categories instanceof CategoryRepository) {
            return null;
        }

        if (! isset($this->categoryCacheById[$id])) {
            $category = $this->categories->find($id);
            if ($category !== null) {
                $this->categoryCacheById[$id] = $category;
                $key = strtolower((string) $category['name']);
                $this->categoryCacheByName[$key] = $category;
            } else {
                $this->categoryCacheById[$id] = null;
            }
        }

        return $this->categoryCacheById[$id] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCategoryByName(string $name): ?array
    {
        $nameKey = strtolower(trim($name));
        if ($nameKey === '' || ! $this->categories instanceof CategoryRepository) {
            return null;
        }

        if (isset($this->categoryCacheByName[$nameKey])) {
            return $this->categoryCacheByName[$nameKey];
        }

        $this->populateCategoryCache();

        return $this->categoryCacheByName[$nameKey] ?? null;
    }

    private function populateCategoryCache(): void
    {
        if (! empty($this->categoryCacheById) || ! $this->categories instanceof CategoryRepository) {
            return;
        }

        foreach ($this->categories->all() as $category) {
            $id = (int) $category['id'];
            $nameKey = strtolower((string) $category['name']);
            $this->categoryCacheById[$id] = $category;
            $this->categoryCacheByName[$nameKey] = $category;
        }
    }

    private function resolveClient(int $queueClientId, string $url): ?array
    {
        if ($this->clients instanceof ClientRepository) {
            if ($queueClientId > 0) {
                $client = $this->clients->find($queueClientId);
                if ($client !== null) {
                    return $client;
                }
            }

            $client = $this->clients->findByUrl($url);
            if ($client !== null) {
                return $client;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{url:string,html:string}>
     */
    private function collectPages(int $queueId, string $rootUrl, bool $crawlSubpages): array
    {
        $pages = [];

        $rootPage = $this->capturePage($queueId, $rootUrl);
        if ($rootPage === null) {
            return [];
        }

        $pages[] = $rootPage;

        if (! $crawlSubpages) {
            return $pages;
        }

        foreach ($this->discoverSubpages($rootPage['html'], $rootUrl) as $subUrl) {
            $subPage = $this->capturePage($queueId, $subUrl);
            if ($subPage !== null) {
                $pages[] = $subPage;
            }
        }

        return $pages;
    }

    /**
     * @return array<int, string>
     */
    private function discoverSubpages(string $html, string $rootUrl, int $limit = 5): array
    {
        $links = [];

        $rootParts = wp_parse_url($rootUrl);
        $rootHost = $rootParts['host'] ?? '';
        $rootScheme = $rootParts['scheme'] ?? 'https';

        if ($rootHost === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (@$dom->loadHTML($html)) {
            $anchors = $dom->getElementsByTagName('a');
            foreach ($anchors as $anchor) {
                $href = trim((string) $anchor->getAttribute('href'));
                if ($href === '' || strpos($href, '#') === 0) {
                    continue;
                }

                $absolute = $this->toAbsoluteUrl($href, $rootScheme, $rootHost, $rootUrl);
                if ($absolute === '') {
                    continue;
                }

                $parts = wp_parse_url($absolute);
                if (($parts['host'] ?? '') !== $rootHost) {
                    continue;
                }

                $normalised = $this->normaliseUrl($absolute);
                if ($normalised === '' || isset($links[$normalised]) || isset($this->seenUrls[$normalised])) {
                    continue;
                }

                $links[$normalised] = $absolute;
                if (count($links) >= $limit) {
                    break;
                }
            }
        }
        libxml_clear_errors();

        return array_values($links);
    }

    private function capturePage(int $queueId, string $url): ?array
    {
        $normalisedUrl = $this->normaliseUrl($url);
        if ($normalisedUrl === '') {
            return null;
        }

        if (isset($this->seenUrls[$normalisedUrl])) {
            return null;
        }

        $html = $this->scraper->fetch($url);
        if ($html === null) {
            return null;
        }

        $hash = hash('sha256', $html);
        if (isset($this->seenHashes[$hash])) {
            error_log(sprintf('[axs4all-ai] Skipping duplicate content at %s.', $url));
            return null;
        }

        $this->seenHashes[$hash] = true;
        $this->seenUrls[$normalisedUrl] = true;

        if ($this->snapshots instanceof SnapshotRepository) {
            $this->snapshots->store($queueId, $hash, $html);
        }

        return [
            'url' => $url,
            'html' => $html,
        ];
    }

    private function toAbsoluteUrl(string $href, string $scheme, string $host, string $rootUrl): string
    {
        if (strpos($href, 'http://') === 0 || strpos($href, 'https://') === 0) {
            return $href;
        }

        if (strpos($href, '//') === 0) {
            return $scheme . ':' . $href;
        }

        if (strpos($href, '/') === 0) {
            return $scheme . '://' . $host . $href;
        }

        $base = rtrim($rootUrl, '/');

        return $base . '/' . ltrim($href, '/');
    }

    private function normaliseUrl(string $url): string
    {
        $clean = esc_url_raw($url);
        if (! $clean) {
            return '';
        }

        return strtolower(rtrim($clean, '/'));
    }

    private function buildAssignment(?array $category): array
    {
        if ($category === null) {
            return [
                'id' => null,
                'name' => 'default',
                'meta' => $this->extractCategoryMeta(),
            ];
        }

        return [
            'id' => isset($category['id']) ? (int) $category['id'] : null,
            'name' => (string) ($category['name'] ?? 'default'),
            'meta' => $this->extractCategoryMeta($category),
        ];
    }

    /**
     * @param array<string, mixed>|null $category
     * @return array<string, array<int, string>>
     */
    private function extractCategoryMeta(?array $category = null): array
    {
        $normalise = static function ($value): array {
            if (is_string($value)) {
                $value = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
            }

            if (! is_array($value)) {
                return [];
            }

            $clean = [];
            foreach ($value as $item) {
                $item = mb_strtolower(trim((string) $item));
                if ($item !== '') {
                    $clean[$item] = $item;
                }
            }

            return array_values($clean);
        };

        if ($category === null) {
            return [
                'phrases' => [],
                'keywords' => [],
                'options' => [],
            ];
        }

        return [
            'phrases' => $normalise($category['phrases'] ?? []),
            'keywords' => $normalise($category['keywords'] ?? []),
            'options' => $normalise($category['options'] ?? []),
        ];
    }
}
