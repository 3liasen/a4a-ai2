<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Crawl;

use Axs4allAi\Crawl\Scraper;
use PHPUnit\Framework\TestCase;

final class ScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_remote_get_queue'] = [];
    }

    public function testFetchRetriesOnRecoverableError(): void
    {
        $GLOBALS['wp_remote_get_queue'] = [
            new \WP_Error('http_error', 'Temporary failure'),
            [
                'body' => '<html><body>ok</body></html>',
                'response' => ['code' => 200],
                'headers' => [],
            ],
        ];

        $scraper = new Scraper(2, 0);
        $result = $scraper->fetch('https://example.com');

        self::assertNotNull($result);
        self::assertSame('<html><body>ok</body></html>', $result['html']);
        self::assertFalse($result['disallow_index']);
    }

    public function testFetchDetectsNoindexMeta(): void
    {
        $GLOBALS['wp_remote_get_queue'] = [
            [
                'body' => '<html><head><meta name="robots" content="noindex,follow"></head><body>hi</body></html>',
                'response' => ['code' => 200],
                'headers' => [],
            ],
        ];

        $scraper = new Scraper(1, 0);
        $result = $scraper->fetch('https://example.com/robots');

        self::assertNotNull($result);
        self::assertTrue($result['disallow_index']);
    }
}
