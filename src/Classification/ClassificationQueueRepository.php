<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use Axs4allAi\Ai\Dto\ClassificationResult;
use wpdb;

final class ClassificationQueueRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    private wpdb $wpdb;
    private string $queueTable;
    private string $resultsTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->queueTable = $this->wpdb->prefix . 'axs4all_ai_classifications_queue';
        $this->resultsTable = $this->wpdb->prefix . 'axs4all_ai_classifications';
    }

    public function enqueue(
        int $queueId,
        ?int $extractionId,
        string $category,
        string $promptVersion,
        string $content,
        ?int $clientId = null,
        ?int $categoryId = null
    ): ?int
    {
        $now = current_time('mysql');
        $data = [
            'queue_id' => $queueId,
            'extraction_id' => $extractionId !== null ? $extractionId : null,
            'client_id' => $clientId !== null ? max(0, (int) $clientId) : 0,
            'category_id' => $categoryId !== null ? max(0, (int) $categoryId) : 0,
            'category' => $category,
            'prompt_version' => $promptVersion,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'content' => $content,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $this->wpdb->insert(
            $this->queueTable,
            $data,
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            return null;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function claimBatch(int $limit = 5): array
    {
        $ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->queueTable} WHERE status = %s ORDER BY created_at ASC LIMIT %d",
                self::STATUS_PENDING,
                $limit
            )
        );

        if (empty($ids)) {
            return [];
        }

        $claimed = [];
        $now = current_time('mysql');

        foreach ($ids as $id) {
            $updated = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->queueTable}
                     SET status = %s, locked_at = %s, updated_at = %s, attempts = attempts + 1
                     WHERE id = %d AND status = %s",
                    self::STATUS_PROCESSING,
                    $now,
                    $now,
                    $id,
                    self::STATUS_PENDING
                )
            );

            if ($updated === 1) {
                $row = $this->getRow((int) $id);
                if ($row !== null) {
                    $claimed[] = $row;
                }
            }
        }

        return $claimed;
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function markCompleted(array $job, ClassificationResult $result, array $metrics = [], ?string $model = null, array $extra = []): void
    {
        $jobId = (int) $job['id'];
        $queueId = isset($job['queue_id']) ? (int) $job['queue_id'] : 0;
        $extractionId = isset($job['extraction_id']) ? (int) $job['extraction_id'] : null;
        $promptVersion = isset($job['prompt_version']) ? (string) $job['prompt_version'] : 'v1';
        $clientId = isset($extra['client_id']) ? (int) $extra['client_id'] : (isset($job['client_id']) ? (int) $job['client_id'] : 0);
        $categoryId = isset($extra['category_id']) ? (int) $extra['category_id'] : (isset($job['category_id']) ? (int) $job['category_id'] : 0);
        $decisionValue = isset($extra['decision_value']) ? (string) $extra['decision_value'] : $result->decision();
        $decisionScale = isset($extra['decision_scale']) ? (string) $extra['decision_scale'] : '';

        $this->wpdb->update(
            $this->queueTable,
            [
                'status' => self::STATUS_DONE,
                'locked_at' => null,
                'last_error' => '',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $jobId],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $data = [
            'queue_id' => $queueId,
            'extraction_id' => ($extractionId !== null && $extractionId > 0) ? $extractionId : null,
            'client_id' => $clientId > 0 ? $clientId : null,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'decision' => $result->decision(),
            'decision_value' => $decisionValue,
            'decision_scale' => $decisionScale,
            'confidence' => $result->confidence(),
            'prompt_version' => $promptVersion,
            'model' => $model ?? ($metrics['model'] ?? null),
            'tokens_prompt' => isset($metrics['tokens_prompt']) ? (int) $metrics['tokens_prompt'] : null,
            'tokens_completion' => isset($metrics['tokens_completion']) ? (int) $metrics['tokens_completion'] : null,
            'duration_ms' => isset($metrics['duration_ms']) ? (int) $metrics['duration_ms'] : null,
            'raw_response' => $result->rawResponse(),
            'created_at' => current_time('mysql'),
        ];

        $this->wpdb->insert(
            $this->resultsTable,
            $data,
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );
    }

    public function markFailed(array $job, string $errorMessage, bool $retry = true): void
    {
        $jobId = (int) $job['id'];
        $status = $retry ? self::STATUS_PENDING : self::STATUS_FAILED;
        $cleanMessage = wp_strip_all_tags($errorMessage);
        $cleanMessage = mb_substr($cleanMessage, 0, 5000);

        $this->wpdb->update(
            $this->queueTable,
            [
                'status' => $status,
                'locked_at' => $retry ? null : current_time('mysql'),
                'last_error' => $cleanMessage,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $jobId],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getRow(int $id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->queueTable} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row !== null ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentResults(int $limit = 50): array
    {
        return $this->getResults([], $limit, 1);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getResults(array $filters = [], int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, min(100, (int) $perPage));
        $page = max(1, (int) $page);
        $offset = ($page - 1) * $perPage;

        [$whereClause, $params] = $this->buildFilterClause($filters);
        $params[] = $perPage;
        $params[] = $offset;

        $sql = "
            SELECT r.*, q.source_url, q.category
            FROM {$this->resultsTable} r
            LEFT JOIN {$this->queueTable} q ON q.id = r.queue_id
            WHERE 1=1 {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $prepared = $this->prepare($sql, $params);
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countResults(array $filters = []): int
    {
        [$whereClause, $params] = $this->buildFilterClause($filters);

        $sql = "
            SELECT COUNT(*)
            FROM {$this->resultsTable} r
            LEFT JOIN {$this->queueTable} q ON q.id = r.queue_id
            WHERE 1=1 {$whereClause}
        ";

        $prepared = $this->prepare($sql, $params);
        $count = $this->wpdb->get_var($prepared);

        return (int) $count;
    }

    public function getResult(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "
            SELECT r.*, q.source_url, q.category, q.content
            FROM {$this->resultsTable} r
            LEFT JOIN {$this->queueTable} q ON q.id = r.queue_id
            WHERE r.id = %d
            LIMIT 1
            ",
            $id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return $row !== null ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<int, mixed>}
     */
    private function buildFilterClause(array $filters): array
    {
        $clauses = [];
        $params = [];

        $decision = isset($filters['decision']) ? strtolower((string) $filters['decision']) : '';
        if ($decision !== '') {
            $clauses[] = 'AND r.decision = %s';
            $params[] = $decision;
        }

        $model = isset($filters['model']) ? trim((string) $filters['model']) : '';
        if ($model !== '') {
            $clauses[] = 'AND r.model = %s';
            $params[] = $model;
        }

        $promptVersion = isset($filters['prompt_version']) ? trim((string) $filters['prompt_version']) : '';
        if ($promptVersion !== '') {
            $clauses[] = 'AND r.prompt_version = %s';
            $params[] = $promptVersion;
        }

        if (! empty($filters['queue_id']) && is_numeric($filters['queue_id'])) {
            $clauses[] = 'AND r.queue_id = %d';
            $params[] = (int) $filters['queue_id'];
        }

        $createdStart = isset($filters['created_start']) ? trim((string) $filters['created_start']) : '';
        if ($createdStart !== '') {
            $start = $this->normalizeDate($createdStart, false);
            if ($start !== null) {
                $clauses[] = 'AND r.created_at >= %s';
                $params[] = $start;
            }
        }

        $createdEnd = isset($filters['created_end']) ? trim((string) $filters['created_end']) : '';
        if ($createdEnd !== '') {
            $end = $this->normalizeDate($createdEnd, true);
            if ($end !== null) {
                $clauses[] = 'AND r.created_at <= %s';
                $params[] = $end;
            }
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%' . esc_like($search) . '%';
            $clauses[] = 'AND (r.raw_response LIKE %s OR q.source_url LIKE %s)';
            $params[] = $like;
            $params[] = $like;

            if (ctype_digit($search)) {
                $clauses[] = 'AND r.queue_id = %d';
                $params[] = (int) $search;
            }
        }

        return [implode(' ', $clauses), $params];
    }

    private function normalizeDate(string $date, bool $endOfDay): ?string
    {
        $dateTime = date_create_immutable($date);
        if (! $dateTime) {
            return null;
        }

        if ($endOfDay) {
            $dateTime = $dateTime->setTime(23, 59, 59);
        } else {
            $dateTime = $dateTime->setTime(0, 0, 0);
        }

        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * @param array<int, mixed> $params
     */
    private function prepare(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }

        $arguments = array_merge([$sql], $params);

        return (string) call_user_func_array([$this->wpdb, 'prepare'], $arguments);
    }
}
