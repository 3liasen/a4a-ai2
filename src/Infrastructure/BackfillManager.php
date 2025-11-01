<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

use Axs4allAi\Data\ClientRepository;
use wpdb;

final class BackfillManager
{
    public function run(wpdb $wpdb): void
    {
        $this->ensureQueueHasSubpageFlag($wpdb);
        $this->ensureClientFrequencies($wpdb);

        $categoryMap = $this->buildCategoryMap($wpdb);
        if (! empty($categoryMap)) {
            $this->backfillClassificationQueueCategories($wpdb, $categoryMap);
            $this->backfillCrawlQueueCategories($wpdb, $categoryMap);
            $queueCategoryMap = $this->buildQueueCategoryMap($wpdb);
            if (! empty($queueCategoryMap)) {
                $this->backfillResultCategories($wpdb, $queueCategoryMap);
            }
        }

        $queueClientMap = $this->buildQueueClientMap($wpdb);
        if (! empty($queueClientMap)) {
            $this->backfillClassificationQueueClients($wpdb, $queueClientMap);
            $this->backfillCrawlQueueClients($wpdb, $queueClientMap);
            $this->backfillResultClients($wpdb, $queueClientMap);
        }

        $this->backfillContentUrls($wpdb);
    }

    private function ensureQueueHasSubpageFlag(wpdb $wpdb): void
    {
        $queueTable = $wpdb->prefix . 'axs4all_ai_queue';
        $wpdb->query("UPDATE {$queueTable} SET crawl_subpages = 0 WHERE crawl_subpages IS NULL");
    }

    private function ensureClientFrequencies(wpdb $wpdb): void
    {
        $clientsTable = $wpdb->prefix . 'axs4all_ai_clients';
        $default = ClientRepository::DEFAULT_FREQUENCY;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$clientsTable} SET crawl_frequency = %s WHERE crawl_frequency IS NULL OR crawl_frequency = ''",
                $default
            )
        );
    }

    /**
     * @return array<string, int>
     */
    private function buildCategoryMap(wpdb $wpdb): array
    {
        $postsTable = $wpdb->prefix . 'posts';
        $rows = $wpdb->get_results(
            "SELECT ID, post_title FROM {$postsTable} WHERE post_type = 'axs4all_ai_category' AND post_status = 'publish'",
            ARRAY_A
        ) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $slug = sanitize_title((string) $row['post_title']);
            if ($slug === '') {
                $slug = sanitize_title_with_dashes((string) $row['post_title']);
            }
            if ($slug === '' || isset($map[$slug])) {
                continue;
            }

            $map[$slug] = (int) $row['ID'];
        }

        return $map;
    }

    /**
     * @param array<string, int> $categoryMap
     */
    private function backfillClassificationQueueCategories(wpdb $wpdb, array $categoryMap): void
    {
        $table = $wpdb->prefix . 'axs4all_ai_classifications_queue';
        $rows = $wpdb->get_results(
            "SELECT id, category FROM {$table} WHERE (category_id IS NULL OR category_id = 0) AND category <> ''",
            ARRAY_A
        ) ?: [];

        foreach ($rows as $row) {
            $categoryId = $this->findCategoryId($categoryMap, (string) $row['category']);
            if ($categoryId === null) {
                continue;
            }

            $wpdb->update(
                $table,
                ['category_id' => $categoryId],
                ['id' => (int) $row['id']],
                ['%d'],
                ['%d']
            );
        }
    }

    /**
     * @param array<string, int> $categoryMap
     */
    private function backfillCrawlQueueCategories(wpdb $wpdb, array $categoryMap): void
    {
        $table = $wpdb->prefix . 'axs4all_ai_queue';
        $rows = $wpdb->get_results(
            "SELECT id, category FROM {$table} WHERE (category_id IS NULL OR category_id = 0) AND category <> ''",
            ARRAY_A
        ) ?: [];

        foreach ($rows as $row) {
            $categoryId = $this->findCategoryId($categoryMap, (string) $row['category']);
            if ($categoryId === null) {
                continue;
            }

            $wpdb->update(
                $table,
                ['category_id' => $categoryId],
                ['id' => (int) $row['id'], 'category_id' => 0],
                ['%d'],
                ['%d', '%d']
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function buildQueueCategoryMap(wpdb $wpdb): array
    {
        $table = $wpdb->prefix . 'axs4all_ai_classifications_queue';
        $rows = $wpdb->get_results(
            "SELECT queue_id, category_id FROM {$table} WHERE category_id IS NOT NULL AND category_id > 0",
            ARRAY_A
        ) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $queueId = (int) $row['queue_id'];
            $categoryId = (int) $row['category_id'];
            if ($queueId > 0 && $categoryId > 0) {
                $map[$queueId] = $categoryId;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $queueCategoryMap
     */
    private function backfillResultCategories(wpdb $wpdb, array $queueCategoryMap): void
    {
        $table = $wpdb->prefix . 'axs4all_ai_classifications';
        $rows = $wpdb->get_results(
            "SELECT id, queue_id FROM {$table} WHERE category_id IS NULL OR category_id = 0",
            ARRAY_A
        ) ?: [];

        foreach ($rows as $row) {
            $queueId = (int) $row['queue_id'];
            if ($queueId <= 0 || ! isset($queueCategoryMap[$queueId])) {
                continue;
            }

            $wpdb->update(
                $table,
                ['category_id' => $queueCategoryMap[$queueId]],
                ['id' => (int) $row['id'], 'category_id' => 0],
                ['%d'],
                ['%d', '%d']
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function buildQueueClientMap(wpdb $wpdb): array
    {
        $clientUrlsTable = $wpdb->prefix . 'axs4all_ai_client_urls';
        $rows = $wpdb->get_results(
            "SELECT client_id, url FROM {$clientUrlsTable}",
            ARRAY_A
        ) ?: [];

        $urlMap = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeUrl((string) $row['url']);
            if ($normalized === '') {
                continue;
            }
            $urlMap[$normalized] = (int) $row['client_id'];
        }

        if (empty($urlMap)) {
            return [];
        }

        $queueTable = $wpdb->prefix . 'axs4all_ai_queue';
        $queueRows = $wpdb->get_results(
            "SELECT id, source_url FROM {$queueTable}",
            ARRAY_A
        ) ?: [];

        $queueMap = [];
        foreach ($queueRows as $row) {
            $normalized = $this->normalizeUrl((string) $row['source_url']);
            if ($normalized === '' || ! isset($urlMap[$normalized])) {
                continue;
            }

            $queueMap[(int) $row['id']] = $urlMap[$normalized];
        }

        return $queueMap;
    }

    /**
     * @param array<int, int> $queueClientMap
     */
    private function backfillClassificationQueueClients(wpdb $wpdb, array $queueClientMap): void
    {
        $table = $wpdb->prefix . 'axs4all_ai_classifications_queue';
        foreach ($queueClientMap as $queueId => $clientId) {
            $wpdb->update(
                $table,
                ['client_id' => $clientId],
                ['queue_id' => $queueId, 'client_id' => 0],
                ['%d'],
                ['%d', '%d']
            );
        }
    }

    /**
     * @param array<int, int> $queueClientMap
     */
    private function backfillCrawlQueueClients(wpdb $wpdb, array $queueClientMap): void
    {
        $table = $wpdb->prefix . 'axs4all_ai_queue';
        foreach ($queueClientMap as $queueId => $clientId) {
            $wpdb->update(
                $table,
                ['client_id' => $clientId],
                ['id' => $queueId, 'client_id' => 0],
                ['%d'],
                ['%d', '%d']
            );
        }
    }

    /**
     * @param array<int, int> $queueClientMap
     */
    private function backfillResultClients(wpdb $wpdb, array $queueClientMap): void
    {
        $table = $wpdb->prefix . 'axs4all_ai_classifications';
        $rows = $wpdb->get_results(
            "SELECT id, queue_id FROM {$table} WHERE client_id IS NULL OR client_id = 0",
            ARRAY_A
        ) ?: [];

        foreach ($rows as $row) {
            $queueId = (int) $row['queue_id'];
            if ($queueId <= 0 || ! isset($queueClientMap[$queueId])) {
                continue;
            }

            $wpdb->update(
                $table,
                ['client_id' => $queueClientMap[$queueId]],
                ['id' => (int) $row['id'], 'client_id' => 0],
                ['%d'],
                ['%d', '%d']
            );
        }
    }

    private function backfillContentUrls(wpdb $wpdb): void
    {
        $classificationQueueTable = $wpdb->prefix . 'axs4all_ai_classifications_queue';
        $queueTable = $wpdb->prefix . 'axs4all_ai_queue';
        $classificationsTable = $wpdb->prefix . 'axs4all_ai_classifications';

        // Populate classification queue content URLs from the originating crawl queue entry.
        $wpdb->query(
            "
            UPDATE {$classificationQueueTable} cq
            LEFT JOIN {$queueTable} q ON q.id = cq.queue_id
            SET cq.content_url = q.source_url
            WHERE (cq.content_url IS NULL OR cq.content_url = '')
              AND q.source_url IS NOT NULL
              AND q.source_url <> ''
            "
        );

        // Propagate content URLs into the classification results table.
        $wpdb->query(
            "
            UPDATE {$classificationsTable} r
            LEFT JOIN {$classificationQueueTable} cq
                ON cq.queue_id = r.queue_id
                AND (
                    (cq.category_id > 0 AND cq.category_id = r.category_id)
                    OR (cq.category_id = 0 AND (r.category_id IS NULL OR r.category_id = 0))
                )
                AND cq.prompt_version = r.prompt_version
            SET r.content_url = cq.content_url
            WHERE (r.content_url IS NULL OR r.content_url = '')
              AND cq.content_url IS NOT NULL
              AND cq.content_url <> ''
            "
        );

        // Fallback: if classification queue has no content URL, reuse the crawl queue URL.
        $wpdb->query(
            "
            UPDATE {$classificationsTable} r
            LEFT JOIN {$queueTable} q ON q.id = r.queue_id
            SET r.content_url = q.source_url
            WHERE (r.content_url IS NULL OR r.content_url = '')
              AND q.source_url IS NOT NULL
              AND q.source_url <> ''
            "
        );
    }

    /**
     * @param array<string, int> $categoryMap
     */
    private function findCategoryId(array $categoryMap, string $category): ?int
    {
        $category = trim($category);
        if ($category === '') {
            return null;
        }

        $slug = sanitize_title($category);
        if ($slug !== '' && isset($categoryMap[$slug])) {
            return $categoryMap[$slug];
        }

        $slug = sanitize_title_with_dashes($category);
        return $categoryMap[$slug] ?? null;
    }

    private function normalizeUrl(string $url): string
    {
        $normalized = esc_url_raw($url);
        if (! is_string($normalized) || $normalized === '') {
            return '';
        }

        return strtolower($normalized);
    }
}
