<?php

declare(strict_types=1);

namespace Axs4allAi\Data;

use wpdb;

final class ClientRepository
{
    private wpdb $wpdb;
    private string $clientsTable;
    private string $urlsTable;
    private string $pivotTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->clientsTable = $this->wpdb->prefix . 'axs4all_ai_clients';
        $this->urlsTable = $this->wpdb->prefix . 'axs4all_ai_client_urls';
        $this->pivotTable = $this->wpdb->prefix . 'axs4all_ai_client_categories';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT c.*, 
                (SELECT COUNT(*) FROM {$this->urlsTable} WHERE client_id = c.id) AS url_count,
                (SELECT COUNT(*) FROM {$this->pivotTable} WHERE client_id = c.id) AS category_count
             FROM {$this->clientsTable} c
             ORDER BY c.name ASC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map([$this, 'mapClientRow'], $rows);
    }

    public function find(int $id): ?array
    {
        $client = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->clientsTable} WHERE id = %d", $id),
            ARRAY_A
        );

        if (! $client) {
            return null;
        }

        $urls = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->urlsTable} WHERE client_id = %d ORDER BY id ASC", $id),
            ARRAY_A
        ) ?: [];

        $categories = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->pivotTable} WHERE client_id = %d", $id),
            ARRAY_A
        ) ?: [];

        $client = $this->mapClientRow($client);
        $client['urls'] = array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'url' => (string) $row['url'],
                'crawl_subpages' => isset($row['crawl_subpages']) ? ((int) $row['crawl_subpages'] === 1) : false,
            ],
            $urls
        );

        $client['categories'] = array_map(
            static fn(array $row): int => (int) $row['category_id'],
            $categories
        );

        return $client;
    }

    public function create(string $name, string $status, string $notes): ?int
    {
        $name = $this->sanitizeName($name);
        if ($name === '') {
            return null;
        }

        $status = $this->sanitizeStatus($status);
        $notes = wp_kses_post($notes);
        $now = current_time('mysql');

        $inserted = $this->wpdb->insert(
            $this->clientsTable,
            [
                'name' => $name,
                'status' => $status,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return null;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $id, string $name, string $status, string $notes): bool
    {
        $client = $this->find($id);
        if (! $client) {
            return false;
        }

        $name = $this->sanitizeName($name);
        if ($name === '') {
            return false;
        }

        $status = $this->sanitizeStatus($status);
        $notes = wp_kses_post($notes);
        $now = current_time('mysql');

        $updated = $this->wpdb->update(
            $this->clientsTable,
            [
                'name' => $name,
                'status' => $status,
                'notes' => $notes,
                'updated_at' => $now,
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * @param array<int, string> $urls
     */
    public function saveUrls(int $clientId, array $urls): void
    {
        $this->wpdb->delete($this->urlsTable, ['client_id' => $clientId], ['%d']);

        $now = current_time('mysql');
        foreach ($urls as $urlRow) {
            if (! is_array($urlRow)) {
                $urlRow = ['url' => (string) $urlRow];
            }
            $sanitized = $this->normalizeUrl((string) ($urlRow['url'] ?? ''));
            if ($sanitized === '') {
                continue;
            }

            $crawlSubpages = ! empty($urlRow['crawl_subpages']) ? 1 : 0;

            $this->wpdb->insert(
                $this->urlsTable,
                [
                    'client_id' => $clientId,
                    'url' => $sanitized,
                    'crawl_subpages' => $crawlSubpages,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );
        }
    }

    /**
     * @param array<int, int> $categoryIds
     */
    public function saveCategories(int $clientId, array $categoryIds): void
    {
        $this->wpdb->delete($this->pivotTable, ['client_id' => $clientId], ['%d']);

        $now = current_time('mysql');
        $unique = array_unique(array_map('intval', $categoryIds));
        foreach ($unique as $categoryId) {
            if ($categoryId <= 0) {
                continue;
            }

            $this->wpdb->insert(
                $this->pivotTable,
                [
                    'client_id' => $clientId,
                    'category_id' => $categoryId,
                    'overrides' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s', '%s']
            );
        }
    }

    public function delete(int $id): bool
    {
        $this->wpdb->delete($this->urlsTable, ['client_id' => $id], ['%d']);
        $this->wpdb->delete($this->pivotTable, ['client_id' => $id], ['%d']);
        $deleted = $this->wpdb->delete($this->clientsTable, ['id' => $id], ['%d']);

        return $deleted !== false;
    }

    public function findByUrl(string $url): ?array
    {
        $normalized = $this->normalizeUrl($url);
        if ($normalized === '') {
            return null;
        }

        $clientId = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT client_id FROM {$this->urlsTable} WHERE url = %s LIMIT 1",
                $normalized
            )
        );

        if (! $clientId) {
            return null;
        }

        $client = $this->find((int) $clientId);
        if ($client !== null) {
            $client['matched_url'] = $normalized;
        }

        return $client;
    }

    /** @return array<int, int> */
    public function getCategoryAssignments(int $clientId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT category_id, overrides FROM {$this->pivotTable} WHERE client_id = %d",
                $clientId
            ),
            ARRAY_A
        ) ?: [];

        return array_map(
            static fn(array $row): int => (int) $row['category_id'],
            $rows
        );
    }

    /**
     * @return array{id:int,name:string,status:string,notes:string,created:string,updated:string,url_count:int,category_count:int}
     */
    private function mapClientRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'notes' => (string) ($row['notes'] ?? ''),
            'created' => (string) $row['created_at'],
            'updated' => (string) $row['updated_at'],
            'url_count' => isset($row['url_count']) ? (int) $row['url_count'] : 0,
            'category_count' => isset($row['category_count']) ? (int) $row['category_count'] : 0,
        ];
    }

    private function sanitizeName(string $name): string
    {
        return sanitize_text_field(wp_unslash($name));
    }

    private function sanitizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $normalized = esc_url_raw($url);

        return $normalized !== false ? $normalized : '';
    }
}
