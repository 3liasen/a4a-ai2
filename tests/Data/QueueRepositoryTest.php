<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Data;

use Axs4allAi\Data\QueueRepository;
use PHPUnit\Framework\TestCase;

final class QueueRepositoryTest extends TestCase
{
    public function testEnqueueWithIdUpdatesExistingRow(): void
    {
        $wpdb = new \wpdb();
        $wpdb->getVarQueue[] = 12;

        $repository = new QueueRepository($wpdb);
        $id = $repository->enqueueWithId('https://example.com', 'news', 4, true, 7, 9);

        self::assertSame(12, $id);
        self::assertNotEmpty($wpdb->updateLog);

        $update = $wpdb->updateLog[0];
        self::assertSame('wp_axs4all_ai_queue', $update['table']);
        self::assertSame(1, $update['data']['crawl_subpages']);
        self::assertSame(7, $update['data']['client_id']);
        self::assertSame(9, $update['data']['category_id']);
        self::assertSame(['id' => 12], $update['where']);
    }

    public function testEnqueueWithIdInsertsNewRow(): void
    {
        $wpdb = new \wpdb();
        $wpdb->getVarQueue[] = null;

        $repository = new QueueRepository($wpdb);
        $id = $repository->enqueueWithId('https://example.com/path', 'restaurant', 3, true, 4, 11);

        self::assertSame(1, $id);
        self::assertCount(1, $wpdb->insertLog);

        $insert = $wpdb->insertLog[0];
        self::assertSame('wp_axs4all_ai_queue', $insert['table']);
        self::assertSame(1, $insert['data']['crawl_subpages']);
        self::assertSame('restaurant', $insert['data']['category']);
        self::assertSame(4, $insert['data']['client_id']);
        self::assertSame(11, $insert['data']['category_id']);
    }

    public function testEnqueueReturnsFalseWhenUrlIsInvalid(): void
    {
        $repository = new QueueRepository(new \wpdb());

        self::assertFalse($repository->enqueue('   ', 'default'));
    }
}
