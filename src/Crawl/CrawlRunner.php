<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Extraction\Extractor;

final class CrawlRunner
{
    private QueueRepository $repository;
    private ?PromptRepository $promptRepository;
    private ?ClassificationQueueRepository $classificationQueue;
    private Scraper $scraper;
    private Extractor $extractor;

    public function __construct(
        QueueRepository $repository,
        ?PromptRepository $promptRepository = null,
        ?ClassificationQueueRepository $classificationQueue = null,
        ?Scraper $scraper = null,
        ?Extractor $extractor = null
    ) {
        $this->repository = $repository;
        $this->promptRepository = $promptRepository;
        $this->classificationQueue = $classificationQueue;
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
            if ($category === '') {
                $category = 'default';
            }

            error_log(sprintf('[axs4all-ai] Processing queue item #%d (%s).', $queueId, $url));

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
                $promptVersion = 'v1';
                if ($this->promptRepository !== null) {
                    $template = $this->promptRepository->getActiveTemplate($category);
                    $promptVersion = $template->version();
                }

                foreach ($snippets as $index => $snippet) {
                    $snippet = trim((string) $snippet);
                    if ($snippet === '') {
                        continue;
                    }

                    $jobId = $this->classificationQueue->enqueue(
                        $queueId,
                        null,
                        $category,
                        $promptVersion,
                        $snippet
                    );

                    if ($jobId !== null) {
                        error_log(
                            sprintf(
                                '[axs4all-ai] Enqueued classification job #%d for queue item #%d (snippet %d).',
                                $jobId,
                                $queueId,
                                $index + 1
                            )
                        );
                    } else {
                        error_log(
                            sprintf(
                                '[axs4all-ai] Failed to enqueue classification job for queue item #%d (snippet %d).',
                                $queueId,
                                $index + 1
                            )
                        );
                    }
                }
            }
        }

        error_log('[axs4all-ai] Crawl runner stub finished.');
    }
}
