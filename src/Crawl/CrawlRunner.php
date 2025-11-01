<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;
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
    /** @var array<int, array<string, mixed>> */
    private array $categoryCacheById = [];
    /** @var array<string, array<string, mixed>> */
    private array $categoryCacheByName = [];

    public function __construct(
        QueueRepository $repository,
        ?PromptRepository $promptRepository = null,
        ?ClassificationQueueRepository $classificationQueue = null,
        ?ClientRepository $clients = null,
        ?CategoryRepository $categories = null,
        ?Scraper $scraper = null,
        ?Extractor $extractor = null
    ) {
        $this->repository = $repository;
        $this->promptRepository = $promptRepository;
        $this->classificationQueue = $classificationQueue;
        $this->clients = $clients;
        $this->categories = $categories;
        $this->scraper = $scraper ?? new Scraper();
        $this->extractor = $extractor ?? new Extractor();
    }

    public function run(int $batchSize = 5): void
    {
        $pendingCount = $this->repository->countPending();
        error_log(sprintf('[axs4all-ai] Crawl runner stub starting. Pending items: %d.', $pendingCount));

        $items = $this->repository->getPending($batchSize);
        foreach ($items as $item) {
            $queueId = (int) $item['id'];
            $url = (string) $item['source_url'];
            $category = trim((string) $item['category']);
            $queueClientId = isset($item['client_id']) ? (int) $item['client_id'] : 0;
            $queueCategoryId = isset($item['category_id']) ? (int) $item['category_id'] : 0;
            if ($category === '') {
                $category = 'default';
            }

            error_log(sprintf('[axs4all-ai] Processing queue item #%d (%s).', $queueId, $url));

            $client = null;
            if ($queueClientId > 0 && $this->clients instanceof ClientRepository) {
                $client = $this->clients->find($queueClientId);
            }
            if ($client === null && $this->clients instanceof ClientRepository) {
                $client = $this->clients->findByUrl($url);
            }
            $clientId = $queueClientId > 0 ? $queueClientId : null;
            if ($client !== null && isset($client['id'])) {
                $clientId = (int) $client['id'];
            }

            $html = $this->scraper->fetch($url);
            if ($html === null) {
                error_log(sprintf('[axs4all-ai] Scraper stub returned no HTML for %s.', $url));
                continue;
            }

            $snippets = $this->extractor->extract($html, $category);
            error_log(
                sprintf(
                    '[axs4all-ai] Extractor stub completed for %s. Snippets count: %d.',
                    $url,
                    count($snippets)
                )
            );

            if ($this->classificationQueue !== null && ! empty($snippets)) {
                $assignments = $this->determineCategoryAssignments($category, $client, $queueCategoryId);
                if (empty($assignments)) {
                    error_log(sprintf('[axs4all-ai] No categories resolved for queue item #%d. Skipping classification.', $queueId));
                    continue;
                }

                foreach ($snippets as $index => $snippet) {
                    $snippet = trim((string) $snippet);
                    if ($snippet === '') {
                        continue;
                    }

                    foreach ($assignments as $assignment) {
                        $categoryName = (string) $assignment['name'];
                        $categoryId = isset($assignment['id']) ? (int) $assignment['id'] : null;

                        $promptVersion = 'v1';
                        if ($this->promptRepository !== null) {
                            $template = $this->promptRepository->getActiveTemplate($categoryName);
                            $promptVersion = $template->version();
                        }

                        $jobId = $this->classificationQueue->enqueue(
                            $queueId,
                            null,
                            $categoryName,
                            $promptVersion,
                            $snippet,
                            $clientId,
                            $categoryId
                        );

                        if ($jobId !== null) {
                            error_log(
                                sprintf(
                                    '[axs4all-ai] Enqueued classification job #%d for queue item #%d (snippet %d, category: %s).',
                                    $jobId,
                                    $queueId,
                                    $index + 1,
                                    $categoryName
                                )
                            );
                        } else {
                            error_log(
                                sprintf(
                                    '[axs4all-ai] Failed to enqueue classification job for queue item #%d (snippet %d, category: %s).',
                                    $queueId,
                                    $index + 1,
                                    $categoryName
                                )
                            );
                        }
                    }
                }
            }
        }

        error_log('[axs4all-ai] Crawl runner stub finished.');
    }

    /** @param array<string, mixed>|null $client */
    private function determineCategoryAssignments(string $fallbackCategory, ?array $client, ?int $queueCategoryId = null): array
    {
        $assignments = [];

        if ($queueCategoryId !== null && $queueCategoryId > 0) {
            $category = $this->resolveCategoryById($queueCategoryId);
            if ($category !== null) {
                $assignments[] = [
                    'id' => (int) $category['id'],
                    'name' => (string) $category['name'],
                ];
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

                    $assignments[] = [
                        'id' => (int) $category['id'],
                        'name' => (string) $category['name'],
                    ];
                }
            }
        }

        if (! empty($assignments)) {
            return $assignments;
        }

        $category = $this->resolveCategoryByName($fallbackCategory);
        if ($category !== null) {
            $assignments[] = [
                'id' => (int) $category['id'],
                'name' => (string) $category['name'],
            ];
        } else {
            $assignments[] = [
                'id' => null,
                'name' => $fallbackCategory,
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
}
