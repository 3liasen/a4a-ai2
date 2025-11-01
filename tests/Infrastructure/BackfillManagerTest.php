<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Infrastructure;

use Axs4allAi\Infrastructure\BackfillManager;
use PHPUnit\Framework\TestCase;

final class BackfillManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_options'] = [];
    }

    public function testRunBackfillsCategoriesAndClients(): void
    {
        $wpdb = new \wpdb();

        // 1) Categories
        $wpdb->getResultsQueue[] = [
            ['ID' => 2, 'post_title' => 'Accessibility'],
        ];
        // 2) Classification queue rows missing category_id
        $wpdb->getResultsQueue[] = [
            ['id' => 7, 'category' => 'accessibility'],
        ];
        // 3) Crawl queue rows missing category_id
        $wpdb->getResultsQueue[] = [
            ['id' => 100, 'category' => 'accessibility', 'category_id' => 0],
        ];
        // 4) Classification queue map (queue_id -> category_id)
        $wpdb->getResultsQueue[] = [
            ['queue_id' => 100, 'category_id' => 2],
        ];
        // 5) Classification results missing category_id
        $wpdb->getResultsQueue[] = [
            ['id' => 200, 'queue_id' => 100],
        ];
        // 6) Client URLs
        $wpdb->getResultsQueue[] = [
            ['client_id' => 5, 'url' => 'https://example.com'],
        ];
        // 7) Crawl queue rows (for client backfill)
        $wpdb->getResultsQueue[] = [
            ['id' => 100, 'source_url' => 'https://example.com', 'client_id' => 0],
        ];
        // 8) Classification results missing client_id
        $wpdb->getResultsQueue[] = [
            ['id' => 200, 'queue_id' => 100],
        ];

        $manager = new BackfillManager();
        $manager->run($wpdb);

        self::assertNotEmpty($wpdb->queryLog);
        self::assertSame('UPDATE wp_axs4all_ai_queue SET crawl_subpages = 0 WHERE crawl_subpages IS NULL', $wpdb->queryLog[0]['query']);
        self::assertSame('prepare', $wpdb->queryLog[1]['type']);
        self::assertStringContainsString('UPDATE wp_axs4all_ai_clients SET crawl_frequency = %s', $wpdb->queryLog[1]['query']);
        self::assertSame('query', $wpdb->queryLog[2]['type']);

        self::assertCount(6, $wpdb->updateLog);

        $queueCategoryUpdate = $wpdb->updateLog[0];
        self::assertSame('wp_axs4all_ai_classifications_queue', $queueCategoryUpdate['table']);
        self::assertSame(['category_id' => 2], $queueCategoryUpdate['data']);
        self::assertSame(['id' => 7], $queueCategoryUpdate['where']);

        $crawlQueueCategoryUpdate = $wpdb->updateLog[1];
        self::assertSame('wp_axs4all_ai_queue', $crawlQueueCategoryUpdate['table']);
        self::assertSame(['category_id' => 2], $crawlQueueCategoryUpdate['data']);
        self::assertSame(['id' => 100, 'category_id' => 0], $crawlQueueCategoryUpdate['where']);

        $resultCategoryUpdate = $wpdb->updateLog[2];
        self::assertSame('wp_axs4all_ai_classifications', $resultCategoryUpdate['table']);
        self::assertSame(['category_id' => 2], $resultCategoryUpdate['data']);
        self::assertSame(['id' => 200, 'category_id' => 0], $resultCategoryUpdate['where']);

        $queueClientUpdate = $wpdb->updateLog[3];
        self::assertSame('wp_axs4all_ai_classifications_queue', $queueClientUpdate['table']);
        self::assertSame(['client_id' => 5], $queueClientUpdate['data']);
        self::assertSame(['queue_id' => 100, 'client_id' => 0], $queueClientUpdate['where']);

        $crawlQueueClientUpdate = $wpdb->updateLog[4];
        self::assertSame('wp_axs4all_ai_queue', $crawlQueueClientUpdate['table']);
        self::assertSame(['client_id' => 5], $crawlQueueClientUpdate['data']);
        self::assertSame(['id' => 100, 'client_id' => 0], $crawlQueueClientUpdate['where']);

        $resultClientUpdate = $wpdb->updateLog[5];
        self::assertSame('wp_axs4all_ai_classifications', $resultClientUpdate['table']);
        self::assertSame(['client_id' => 5], $resultClientUpdate['data']);
        self::assertSame(['id' => 200, 'client_id' => 0], $resultClientUpdate['where']);

        $contentUrlQueries = array_filter($wpdb->queryLog, static fn(array $entry): bool => isset($entry['query']) && strpos($entry['query'], 'content_url') !== false);
        self::assertNotEmpty($contentUrlQueries);
    }
}
