<?php

declare(strict_types=1);

namespace Axs4allAi\Extraction;

final class Extractor
{
    /**
     * @return array<int, string>
     */
    public function extract(string $html, string $category): array
    {
        error_log(
            sprintf(
                '[axs4all-ai] Extractor stub invoked. Category: %s, HTML length: %d',
                $category,
                strlen($html)
            )
        );

        return [];
    }
}
