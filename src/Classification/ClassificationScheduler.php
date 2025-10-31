<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

final class ClassificationScheduler
{
    private ClassificationQueueRepository $queueRepository;
    private ClassificationRunner $runner;

    public function __construct(
        ClassificationQueueRepository $queueRepository,
        ClassificationRunner $runner
    ) {
        $this->queueRepository = $queueRepository;
        $this->runner = $runner;
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'registerInterval']);
        add_action('init', [$this, 'scheduleEvent']);
        add_action('axs4all_ai_run_classifications', [$this, 'processCron']);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('axs4all-ai classify-runner', [$this, 'processCli']);
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled('axs4all_ai_run_classifications');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'axs4all_ai_run_classifications');
        }
    }

    public function registerInterval(array $schedules): array
    {
        if (! isset($schedules['axs4all_ai_classify_5min'])) {
            $schedules['axs4all_ai_classify_5min'] = [
                'interval' => $this->intervalSeconds(),
                'display' => __('Every Five Minutes (axs4all AI classifications)', 'axs4all-ai'),
            ];
        }

        return $schedules;
    }

    public function scheduleEvent(): void
    {
        if (! wp_next_scheduled('axs4all_ai_run_classifications')) {
            wp_schedule_event(time() + $this->intervalSeconds(), 'axs4all_ai_classify_5min', 'axs4all_ai_run_classifications');
        }
    }

    public function processCron(): void
    {
        $this->runBatch();
    }

    /**
     * @param array<int, mixed> $args
     */
    public function processCli(array $args = [], array $assocArgs = []): void
    {
        $batch = isset($assocArgs['batch']) ? max(1, (int) $assocArgs['batch']) : 5;
        $drain = isset($assocArgs['drain']);

        $totalProcessed = 0;
        do {
            $processed = $this->runBatch($batch);
            $totalProcessed += $processed;
        } while ($drain && $processed > 0);

        if (defined('WP_CLI') && WP_CLI) {
            if ($totalProcessed === 0) {
                \WP_CLI::success('No classification jobs were processed.');
            } else {
                \WP_CLI::success(sprintf('Processed %d classification job(s).', $totalProcessed));
            }
        }
    }

    private function runBatch(int $batchSize = 5): int
    {
        $jobs = $this->queueRepository->claimBatch($batchSize);
        if (empty($jobs)) {
            return 0;
        }

        $this->runner->process($jobs);

        return count($jobs);
    }

    private function intervalSeconds(): int
    {
        $minute = defined('MINUTE_IN_SECONDS') ? (int) MINUTE_IN_SECONDS : 60;
        return 5 * $minute;
    }
}
