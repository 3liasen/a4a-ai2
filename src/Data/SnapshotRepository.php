<?php

declare(strict_types=1);

namespace Axs4allAi\Data;

use wpdb;

final class SnapshotRepository
{
    private wpdb $wpdb;
    private string $table;
    private string $queueTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'axs4all_ai_snapshots';
        $this->queueTable = $wpdb->prefix . 'axs4all_ai_queue';
    }

    public function store(int $queueId, string $hash, string $content): void
    {
        $this->wpdb->insert(
            $this->table,
            [
                'queue_id' => $queueId,
                'content' => $content,
                'content_hash' => $hash,
                'fetched_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        $this->pruneQueueSnapshots($queueId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $query = $this->wpdb->prepare(
            "SELECT s.id, s.queue_id, s.content_hash, s.fetched_at, q.source_url, q.category, q.client_id
             FROM {$this->table} s
             LEFT JOIN {$this->queueTable} q ON q.id = s.queue_id
             ORDER BY s.fetched_at DESC
             LIMIT %d",
            $limit
        );

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->wpdb->get_results($query, ARRAY_A) ?: [];

        return $rows;
    }

    public function find(int $id): ?array
    {
        $id = max(1, $id);
        $query = $this->wpdb->prepare(
            "SELECT s.id, s.queue_id, s.content, s.content_hash, s.fetched_at, q.source_url, q.category, q.client_id
             FROM {$this->table} s
             LEFT JOIN {$this->queueTable} q ON q.id = s.queue_id
             WHERE s.id = %d",
            $id
        );

        /** @var array<string, mixed>|null $row */
        $row = $this->wpdb->get_row($query, ARRAY_A);

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByQueue(int $queueId, int $limit = 10): array
    {
        $queueId = max(1, $queueId);
        $limit = max(1, min(50, $limit));

        $query = $this->wpdb->prepare(
            "SELECT s.id, s.queue_id, s.content_hash, s.fetched_at
             FROM {$this->table} s
             WHERE s.queue_id = %d
             ORDER BY s.fetched_at DESC
             LIMIT %d",
            $queueId,
            $limit
        );

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->wpdb->get_results($query, ARRAY_A) ?: [];

        return $rows;
    }

    public function clearAll(): void
    {
        $this->wpdb->query("DELETE FROM {$this->table}");
    }

    private function pruneQueueSnapshots(int $queueId, int $retain = 20): void
    {
        $queueId = max(1, $queueId);
        $retain = max(1, $retain);

        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table}
                 WHERE queue_id = %d
                 AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$this->table}
                        WHERE queue_id = %d
                        ORDER BY fetched_at DESC, id DESC
                        LIMIT %d
                    ) AS keepers
                 )",
                $queueId,
                $queueId,
                $retain
            )
        );
    }
}
