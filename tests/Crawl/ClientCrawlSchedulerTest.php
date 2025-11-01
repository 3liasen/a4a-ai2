<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Crawl;

use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Crawl\ClientCrawlScheduler;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;
use PHPUnit\Framework\TestCase;

final class ClientCrawlSchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_cron_scheduled'] = [];
        $GLOBALS['wp_posts'] = [];
        $GLOBALS['wp_meta'] = [];
        $GLOBALS['wp_posts_next_id'] = 0;
    }

    public function testSyncSchedulesSchedulesActiveClients(): void
    {
        $clientsDb = new \wpdb();
        $clientsDb->getResultsQueue[] = [
            [
                'id' => 1,
                'name' => 'Scheduled Client',
                'status' => 'active',
                'notes' => '',
                'crawl_frequency' => 'daily',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
                'url_count' => 1,
                'category_count' => 1,
            ],
        ];

        $clients = new ClientRepository($clientsDb);
        $queue = new QueueRepository(new \wpdb());
        $categories = new CategoryRepository();

        $scheduler = new ClientCrawlScheduler($clients, $queue, $categories);
        $scheduler->registerIntervals([]);
        $scheduler->syncSchedules();

        self::assertArrayHasKey('axs4all_ai_crawl_client', $GLOBALS['wp_cron_scheduled']);
        $events = array_values($GLOBALS['wp_cron_scheduled']['axs4all_ai_crawl_client']);
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertSame('daily', $event['schedule']);
        self::assertSame([1], $event['args']);
        self::assertGreaterThan(time(), $event['timestamp']);
    }

    public function testSyncSchedulesSkipsManualClients(): void
    {
        $clientsDb = new \wpdb();
        $clientsDb->getResultsQueue[] = [
            [
                'id' => 2,
                'name' => 'Manual Client',
                'status' => 'active',
                'notes' => '',
                'crawl_frequency' => 'manual',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
                'url_count' => 0,
                'category_count' => 0,
            ],
        ];

        $clients = new ClientRepository($clientsDb);
        $queue = new QueueRepository(new \wpdb());
        $categories = new CategoryRepository();

        $scheduler = new ClientCrawlScheduler($clients, $queue, $categories);
        $scheduler->syncSchedules();

        self::assertArrayNotHasKey('axs4all_ai_crawl_client', $GLOBALS['wp_cron_scheduled']);
    }

    public function testHandleCronQueuesClientUrls(): void
    {
        $clientsDb = new \wpdb();
        $clientsDb->getRowQueue[] = [
            'id' => 3,
            'name' => 'Client',
            'status' => 'active',
            'notes' => '',
            'crawl_frequency' => 'daily',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];
        $clientsDb->getResultsQueue[] = [
            [
                'id' => 15,
                'client_id' => 3,
                'url' => 'https://example.com/page',
                'crawl_subpages' => 1,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ];
        $clientsDb->getResultsQueue[] = [
            [
                'client_id' => 3,
                'category_id' => 8,
                'overrides' => null,
            ],
        ];

        $clients = new ClientRepository($clientsDb);
        $queueDb = new \wpdb();
        $queueDb->getVarQueue[] = null;
        $queue = new QueueRepository($queueDb);

        $categoryPost = new \WP_Post(8, [
            'post_title' => 'Accessibility',
            'post_type' => 'axs4all_ai_category',
        ]);
        $GLOBALS['wp_posts'][8] = $categoryPost;

        $categories = new CategoryRepository();

        $scheduler = new ClientCrawlScheduler($clients, $queue, $categories);
        $scheduler->handleCron(3);

        self::assertNotEmpty($queueDb->insertLog);
        $insert = $queueDb->insertLog[0];
        self::assertSame('wp_axs4all_ai_queue', $insert['table']);
        self::assertSame('accessibility', $insert['data']['category']);
        self::assertSame(1, $insert['data']['crawl_subpages']);
    }
}
