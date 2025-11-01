<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

use Axs4allAi\Data\QueueRepository;

final class CrawlScheduler
{
    private QueueRepository $repository;
    private CrawlRunner $runner;

    public function __construct(QueueRepository $repository, ?CrawlRunner $runner = null)
    {
        $this->repository = $repository;
        $this->runner = $runner ?? new CrawlRunner($repository);
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'registerInterval']);
        add_action('init', [$this, 'scheduleEvent']);
        add_action('axs4all_ai_process_queue', [$this, 'processCron']);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('axs4all-ai crawl', [$this, 'processCli']);
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled('axs4all_ai_process_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'axs4all_ai_process_queue');
        }
    }

    public function registerInterval(array $schedules): array
    {
        $schedules['axs4all_ai_5min'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every Five Minutes (axs4all AI)', 'axs4all-ai'),
        ];

        return $schedules;
    }

    public function scheduleEvent(): void
    {
        if (! wp_next_scheduled('axs4all_ai_process_queue')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'axs4all_ai_5min', 'axs4all_ai_process_queue');
        }
    }

    public function processCron(): void
    {
        $this->runner->run();
        update_option('axs4all_ai_last_crawl', current_time('mysql', true));
    }

    /**
     * @param array<int, mixed> $args
     */
    public function processCli(array $args = [], array $assocArgs = []): void
    {
        $batch = isset($assocArgs['batch']) ? (int) $assocArgs['batch'] : 5;
        $this->runner->run($batch);
        if (class_exists('\WP_CLI')) {
            \WP_CLI::success(sprintf('Crawler stub executed with batch size %d.', $batch));
        }
    }
}
