<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Classification\ClassificationQueueRepository;

final class DashboardPage
{
    private const MENU_SLUG = 'axs4all-ai';

    private QueueRepository $queueRepository;
    private ClassificationQueueRepository $classificationQueueRepository;

    public function __construct(
        QueueRepository $queueRepository,
        ClassificationQueueRepository $classificationQueueRepository
    ) {
        $this->queueRepository = $queueRepository;
        $this->classificationQueueRepository = $classificationQueueRepository;
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('axs4all AI', 'axs4all-ai'),
            __('axs4all AI', 'axs4all-ai'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-universal-access-alt',
            56
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $crawlPending = $this->queueRepository->countPending();
        $classificationPending = $this->classificationQueueRepository->countQueue();

        $nextCrawl = wp_next_scheduled('axs4all_ai_process_queue');
        $nextClassification = wp_next_scheduled('axs4all_ai_run_classifications');

        $lastCrawl = (string) get_option('axs4all_ai_last_crawl', '');
        $lastClassification = (string) get_option('axs4all_ai_last_classification', '');

        $recentAlerts = get_option('axs4all_ai_alert_state', []);
        $cronWarnings = $this->collectCronWarnings($nextCrawl, $nextClassification);
        $hasWarnings = ! empty($cronWarnings);

        ?>
        <div class="wrap axs4all-adminlte">
            <div class="content-wrapper" style="margin-left:0;">
                <section class="content-header">
                    <div class="container-fluid">
                        <div class="row mb-2">
                            <div class="col-sm-6">
                                <h1><?php esc_html_e('axs4all AI Dashboard', 'axs4all-ai'); ?></h1>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="content">
                    <div class="container-fluid">
                        <?php if ($hasWarnings) : ?>
                            <div class="row">
                                <div class="col-12">
                                    <?php foreach ($cronWarnings as $warning) : ?>
                                        <?php $this->renderCallout('warning', $warning['title'], $warning['message']); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <?php $this->renderSmallBox(__('Pending Crawl URLs', 'axs4all-ai'), (string) number_format_i18n($crawlPending), 'bg-info', 'fa fa-globe'); ?>
                            <?php $this->renderSmallBox(__('Pending Classifications', 'axs4all-ai'), (string) number_format_i18n($classificationPending), 'bg-success', 'fa fa-robot'); ?>
                            <?php $this->renderScheduleBox(__('Next Crawl', 'axs4all-ai'), $nextCrawl, 'bg-warning', 'fa fa-clock'); ?>
                            <?php $this->renderScheduleBox(__('Next Classification', 'axs4all-ai'), $nextClassification, 'bg-danger', 'fa fa-stream'); ?>
                        </div>

                        <div class="row">
                            <section class="col-lg-6 connectedSortable">
                                <div class="card card-outline card-light">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php esc_html_e('Quick Actions', 'axs4all-ai'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="axs4all-dashboard-actions">
                                            <?php foreach ($this->getQuickLinks() as $link) : ?>
                                                <a class="btn btn-app" href="<?php echo esc_url($link['href']); ?>">
                                                    <i class="<?php echo esc_attr($link['icon']); ?>"></i> <?php echo esc_html($link['label']); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="description">
                                            <?php esc_html_e('Jump straight to daily ops pages for crawl management, classifications, and usage insights.', 'axs4all-ai'); ?>
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section class="col-lg-6 connectedSortable">
                                <div class="card card-outline card-info">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php esc_html_e('Cron Schedules', 'axs4all-ai'); ?></h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-hover mb-0">
                                            <tbody>
                                                <?php $this->renderScheduleRow(__('Crawler', 'axs4all-ai'), $nextCrawl); ?>
                                                <?php $this->renderScheduleRow(__('Classification Runner', 'axs4all-ai'), $nextClassification); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="row">
                            <section class="col-lg-6 connectedSortable">
                                <div class="card card-primary card-outline">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php esc_html_e('Recent Runs', 'axs4all-ai'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tbody>
                                                <tr>
                                                    <th><?php esc_html_e('Last Crawl Run', 'axs4all-ai'); ?></th>
                                                    <td><?php echo esc_html($this->formatSavedTime($lastCrawl)); ?></td>
                                                </tr>
                                                <tr>
                                                    <th><?php esc_html_e('Last Classification Run', 'axs4all-ai'); ?></th>
                                                    <td><?php echo esc_html($this->formatSavedTime($lastClassification)); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <section class="col-lg-6 connectedSortable">
                                <div class="card card-secondary card-outline">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php esc_html_e('Alert Cooldown State', 'axs4all-ai'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($recentAlerts)) : ?>
                                            <p><?php esc_html_e('No alerts have been triggered recently.', 'axs4all-ai'); ?></p>
                                        <?php else : ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($recentAlerts as $key => $timestamp) : ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><?php echo esc_html($key); ?></span>
                                                        <span class="badge badge-primary badge-pill"><?php echo esc_html($this->formatSavedTime((string) gmdate('Y-m-d H:i:s', (int) $timestamp))); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    private function renderSmallBox(string $label, $value, string $colorClass, string $icon): void
    {
        ?>
        <div class="col-lg-3 col-6">
            <div class="small-box <?php echo esc_attr($colorClass); ?>">
                <div class="inner">
                    <h3 class="axs4all-smallbox-headline"><?php echo esc_html(is_string($value) ? $value : number_format_i18n((int) $value)); ?></h3>
                    <p><?php echo esc_html($label); ?></p>
                </div>
                <div class="icon">
                    <i class="<?php echo esc_attr($icon); ?>"></i>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderScheduleBox(string $label, $timestamp, string $colorClass, string $icon): void
    {
        $summary = $this->formatScheduleSummary($timestamp);

        ?>
        <div class="col-lg-3 col-6">
            <div class="small-box <?php echo esc_attr($colorClass); ?>">
                <div class="inner">
                    <h3 class="axs4all-smallbox-headline"><?php echo esc_html($summary['primary']); ?></h3>
                    <span class="axs4all-smallbox-subtitle"><?php echo esc_html($summary['secondary']); ?></span>
                    <p><?php echo esc_html($label); ?></p>
                </div>
                <div class="icon">
                    <i class="<?php echo esc_attr($icon); ?>"></i>
                </div>
            </div>
        </div>
        <?php
    }

    private function formatSavedTime(string $value): string
    {
        if ($value === '') {
            return __('n/a', 'axs4all-ai');
        }

        $time = strtotime($value . ' UTC');
        if ($time === false) {
            return $value;
        }

        return gmdate('M j, H:i', $time) . ' UTC';
    }

    /**
     * @param int|false|null $timestamp
     * @return array{primary:string,secondary:string}
     */
    private function formatScheduleSummary($timestamp): array
    {
        if ($timestamp === false || $timestamp === null) {
            return [
                'primary' => __('Not scheduled', 'axs4all-ai'),
                'secondary' => __('Schedule the cron hook to resume automatic processing.', 'axs4all-ai'),
            ];
        }

        $format = trim(get_option('date_format', 'M j') . ' ' . get_option('time_format', 'H:i'));
        $localTime = wp_date($format, (int) $timestamp);

        $now = current_time('timestamp');
        $target = (int) $timestamp;
        $diffHuman = human_time_diff($now, $target);
        $isFuture = $target >= $now;
        $relative = $isFuture
            ? sprintf(__('in %s', 'axs4all-ai'), $diffHuman)
            : sprintf(__('%s ago', 'axs4all-ai'), $diffHuman);

        return [
            'primary' => $localTime,
            'secondary' => $relative,
        ];
    }

    /**
     * @return array<int, array{href:string,label:string,icon:string}>
     */
    private function getQuickLinks(): array
    {
        return [
            [
                'href' => admin_url('admin.php?page=axs4all-ai-queue'),
                'label' => __('Crawl Queue', 'axs4all-ai'),
                'icon' => 'fas fa-route',
            ],
            [
                'href' => admin_url('admin.php?page=axs4all-ai-classifications'),
                'label' => __('Classification Results', 'axs4all-ai'),
                'icon' => 'fas fa-clipboard-check',
            ],
            [
                'href' => admin_url('admin.php?page=axs4all-ai-billing'),
                'label' => __('Usage & Costs', 'axs4all-ai'),
                'icon' => 'fas fa-chart-line',
            ],
            [
                'href' => admin_url('admin.php?page=axs4all-ai-manual'),
                'label' => __('Manual Classification', 'axs4all-ai'),
                'icon' => 'fas fa-magic',
            ],
        ];
    }

    private function renderScheduleRow(string $label, $timestamp): void
    {
        $isScheduled = $timestamp !== false && $timestamp !== null;
        $statusClass = $isScheduled ? 'badge-success' : 'badge-danger';
        $statusText = $isScheduled ? __('Scheduled', 'axs4all-ai') : __('Missing', 'axs4all-ai');

        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <span class="badge <?php echo esc_attr($statusClass); ?>"><?php echo esc_html($statusText); ?></span>
            </td>
            <td><?php echo esc_html($this->formatScheduleSummary($timestamp)); ?></td>
        </tr>
        <?php
    }

    /**
     * @param int|false|null $nextCrawl
     * @param int|false|null $nextClassification
     * @return array<int, array{title:string,message:string}>
     */
    private function collectCronWarnings($nextCrawl, $nextClassification): array
    {
        $warnings = [];

        if ($nextCrawl === false || $nextCrawl === null) {
            $warnings[] = [
                'title' => __('Crawler is not scheduled', 'axs4all-ai'),
                'message' => __('The crawler cron hook has no upcoming run. Check WP-Cron settings or schedule it manually from the Crawl Queue screen.', 'axs4all-ai'),
            ];
        }

        if ($nextClassification === false || $nextClassification === null) {
            $warnings[] = [
                'title' => __('Classification runner is not scheduled', 'axs4all-ai'),
                'message' => __('The classification cron hook is missing a next run. Verify WP-Cron is working or trigger the runner manually to reschedule.', 'axs4all-ai'),
            ];
        }

        return $warnings;
    }

    private function renderCallout(string $type, string $title, string $message): void
    {
        ?>
        <div class="callout callout-<?php echo esc_attr($type); ?>">
            <h5><?php echo esc_html($title); ?></h5>
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}
