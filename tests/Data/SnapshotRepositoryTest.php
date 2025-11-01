<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Data;

use Axs4allAi\Data\SnapshotRepository;
use PHPUnit\Framework\TestCase;

final class SnapshotRepositoryTest extends TestCase
{
    public function testStorePersistsSnapshotAndPrunes(): void
    {
        $wpdb = new \wpdb();
        $repository = new SnapshotRepository($wpdb);

        $repository->store(5, 'hash123', '<html>example</html>');

        self::assertCount(1, $wpdb->insertLog);
        $insert = $wpdb->insertLog[0];
        self::assertSame('wp_axs4all_ai_snapshots', $insert['table']);
        self::assertSame(5, $insert['data']['queue_id']);
        self::assertSame('hash123', $insert['data']['content_hash']);

        $prepareLog = array_filter($wpdb->queryLog, static fn(array $entry): bool => ($entry['type'] ?? '') === 'prepare');
        self::assertNotEmpty($prepareLog);
        $firstPrepare = array_shift($prepareLog);
        self::assertStringContainsString('DELETE FROM wp_axs4all_ai_snapshots', $firstPrepare['query']);
        self::assertSame([5, 5, 20], $firstPrepare['args']);
    }

    public function testClearAllDeletesSnapshots(): void
    {
        $wpdb = new \wpdb();
        $repository = new SnapshotRepository($wpdb);

        $repository->clearAll();

        $queries = array_filter($wpdb->queryLog, static fn(array $entry): bool => ($entry['type'] ?? '') === 'query');
        self::assertNotEmpty($queries);
        $delete = array_shift($queries);
        self::assertSame('DELETE FROM wp_axs4all_ai_snapshots', $delete['query']);
    }
}
