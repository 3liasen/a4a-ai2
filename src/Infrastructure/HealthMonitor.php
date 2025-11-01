<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Classification\ClassificationQueueRepository;

final class HealthMonitor
{
    private const HOOK = 'axs4all_ai_health_check';

    private AlertManager $alerts;
    private QueueRepository $queueRepository;
    private ClassificationQueueRepository $classificationQueueRepository;

    public function __construct(
        AlertManager $alerts,
        QueueRepository $queueRepository,
        ClassificationQueueRepository $classificationQueueRepository
    ) {
        $this->alerts = $alerts;
        $this->queueRepository = $queueRepository;
        $this->classificationQueueRepository = $classificationQueueRepository;
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'registerInterval']);
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'run']);
    }

    public function registerInterval(array $schedules): array
    {
        if (! isset($schedules['axs4all_ai_health_5min'])) {
            $schedules['axs4all_ai_health_5min'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display' => __('Every five minutes (axs4all AI health)', 'axs4all-ai'),
            ];
        }

        return $schedules;
    }

    public function schedule(): void
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'axs4all_ai_health_5min', self::HOOK);
        }
    }

    public function run(): void
    {
        $this->alerts->ensureCronScheduled('axs4all_ai_process_queue', 'crawl');
        $this->alerts->ensureCronScheduled('axs4all_ai_run_classifications', 'classification');

        $this->alerts->recordQueueMetrics('crawl', $this->queueRepository->countPending());
        $this->alerts->recordQueueMetrics('classification', $this->classificationQueueRepository->countQueue());
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
    }
}
