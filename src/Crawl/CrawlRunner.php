<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Extraction\Extractor;

final class CrawlRunner
{
    private QueueRepository $repository;
    private Scraper $scraper;
    private Extractor $extractor;

    public function __construct(
        QueueRepository $repository,
        ?Scraper $scraper = null,
        ?Extractor $extractor = null
    ) {
        $this->repository = $repository;
        $this->scraper = $scraper ?? new Scraper();
        $this->extractor = $extractor ?? new Extractor();
    }

    public function run(int $batchSize = 5): void
    {
        $pendingCount = $this->repository->countPending();
        error_log(sprintf('[axs4all-ai] Crawl runner stub starting. Pending items: %d.', $pendingCount));

        $items = $this->repository->getPending($batchSize);
        foreach ($items as $item) {
            $url = (string) $item['source_url'];
            $category = (string) $item['category'];

            error_log(sprintf('[axs4all-ai] Processing queue item #%d (%s).', (int) $item['id'], $url));

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
        }

        error_log('[axs4all-ai] Crawl runner stub finished.');
    }
}
