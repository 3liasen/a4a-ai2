<?php

declare(strict_types=1);

namespace Axs4allAi\Data;

use wpdb;

final class SnapshotRepository
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'axs4all_ai_snapshots';
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
    }
}

