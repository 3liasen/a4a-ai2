<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

final class Scraper
{
    public function fetch(string $url): ?string
    {
        error_log(sprintf('[axs4all-ai] Scraper stub invoked for URL: %s', $url));

        return null;
    }
}
