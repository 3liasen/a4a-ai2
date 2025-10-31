<?php

declare(strict_types=1);

namespace Axs4allAi\Data;

use wpdb;

final class QueueRepository
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'axs4all_ai_queue';
    }

    public function enqueue(string $url, string $category, int $priority = 5): bool
    {
        $normalizedUrl = $this->normalizeUrl($url);
        if ($normalizedUrl === null) {
            return false;
        }

        $category = sanitize_text_field($category);
        $priority = max(1, min(9, $priority));
        $hash = hash('sha256', strtolower($normalizedUrl));
        $now = current_time('mysql');

        $existingId = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE url_hash = %s",
                $hash
            )
        );

        if ($existingId) {
            $updated = $this->wpdb->update(
                $this->table,
                [
                    'source_url' => $normalizedUrl,
                    'category' => $category,
                    'priority' => $priority,
                    'status' => 'pending',
                    'attempts' => 0,
                    'last_error' => null,
                    'updated_at' => $now,
                ],
                ['id' => (int) $existingId],
                ['%s', '%s', '%d', '%s', '%d', '%s', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'source_url' => $normalizedUrl,
                'url_hash' => $hash,
                'status' => 'pending',
                'category' => $category,
                'priority' => $priority,
                'attempts' => 0,
                'last_error' => null,
                'last_attempted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $inserted !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 50): array
    {
        $limit = max(1, $limit);
        $query = $this->wpdb->prepare(
            "SELECT id, source_url, category, status, priority, attempts, created_at, updated_at
             FROM {$this->table}
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        );

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->wpdb->get_results($query, ARRAY_A) ?: [];

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPending(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $query = $this->wpdb->prepare(
            "SELECT id, source_url, category
             FROM {$this->table}
             WHERE status = %s
             ORDER BY priority ASC, created_at ASC
             LIMIT %d",
            'pending',
            $limit
        );

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->wpdb->get_results($query, ARRAY_A) ?: [];

        return $rows;
    }

    public function countPending(): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(id) FROM {$this->table} WHERE status = %s",
            'pending'
        );

        return (int) $this->wpdb->get_var($sql);
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $deleted = $this->wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $deleted !== false;
    }

    public function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $normalized = esc_url_raw($url);
        if (! $normalized) {
            return null;
        }

        return $normalized;
    }
}
