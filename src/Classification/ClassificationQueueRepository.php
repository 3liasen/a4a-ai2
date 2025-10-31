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

    public function enqueue(int $queueId, ?int $extractionId, string $category, string $promptVersion, string $content): ?int
    {
        $now = current_time('mysql');
        $data = [
            'queue_id' => $queueId,
            'extraction_id' => $extractionId !== null ? $extractionId : null,
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
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
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

    public function markCompleted(array $job, ClassificationResult $result, array $metrics = [], ?string $model = null): void
    {
        $jobId = (int) $job['id'];
        $queueId = isset($job['queue_id']) ? (int) $job['queue_id'] : 0;
        $extractionId = isset($job['extraction_id']) ? (int) $job['extraction_id'] : null;
        $promptVersion = isset($job['prompt_version']) ? (string) $job['prompt_version'] : 'v1';

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
            'decision' => $result->decision(),
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
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
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
}
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentResults(int $limit = 50): array
    {
        $limit = max(1, $limit);
        $sql = $this->wpdb->prepare(
            "SELECT id, queue_id, extraction_id, decision, confidence, prompt_version, model, tokens_prompt, tokens_completion, duration_ms, created_at
             FROM {$this->resultsTable}
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
