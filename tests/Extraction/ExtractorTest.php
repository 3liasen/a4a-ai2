<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Extraction;

use Axs4allAi\Extraction\Extractor;
use PHPUnit\Framework\TestCase;

final class ExtractorTest extends TestCase
{
    public function testExtractPrioritisesMetadataMatches(): void
    {
        $html = <<<HTML
        <html>
            <body>
                <p>This venue offers full wheelchair access with elevators and wide doors.</p>
                <p>General information without the target keywords.</p>
                <p>Accessibility for wheelchair users is described in detail.</p>
                <p>A short sentence.</p>
            </body>
        </html>
        HTML;

        $extractor = new Extractor();

        $result = $extractor->extract($html, 'accessibility', [
            'phrases' => ['wheelchair access'],
            'keywords' => ['wheelchair', 'accessibility'],
            'options' => [],
        ]);

        self::assertGreaterThanOrEqual(1, count($result));
        self::assertLessThanOrEqual(3, count($result));
        self::assertStringContainsString('wheelchair access', $result[0]);
        self::assertStringContainsString('wheelchair', implode(' ', $result));
    }

    public function testExtractHonoursSnippetLimitFromMetadata(): void
    {
        $html = '<p>Snippet A mentions wheelchair access.</p><p>Snippet B talks about accessibility.</p><p>Snippet C is general text.</p>';

        $extractor = new Extractor();
        $result = $extractor->extract($html, 'accessibility', [
            'phrases' => ['wheelchair access'],
            'keywords' => ['accessibility'],
            'options' => [],
            'snippet_limit' => 2,
        ]);

        self::assertCount(2, $result);
    }
}
