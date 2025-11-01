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
                        <div class="row">
                            <?php $this->renderSmallBox(__('Pending Crawl URLs', 'axs4all-ai'), $crawlPending, 'bg-info', 'fa fa-globe'); ?>
                            <?php $this->renderSmallBox(__('Pending Classifications', 'axs4all-ai'), $classificationPending, 'bg-success', 'fa fa-robot'); ?>
                            <?php $this->renderSmallBox(__('Next Crawl', 'axs4all-ai'), $this->formatTimestamp($nextCrawl), 'bg-warning', 'fa fa-clock'); ?>
                            <?php $this->renderSmallBox(__('Next Classification', 'axs4all-ai'), $this->formatTimestamp($nextClassification), 'bg-danger', 'fa fa-stream'); ?>
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
                    <h3><?php echo esc_html(is_string($value) ? $value : number_format_i18n((int) $value)); ?></h3>
                    <p><?php echo esc_html($label); ?></p>
                </div>
                <div class="icon">
                    <i class="<?php echo esc_attr($icon); ?>"></i>
                </div>
            </div>
        </div>
        <?php
    }

    private function formatTimestamp($timestamp): string
    {
        if ($timestamp === false || $timestamp === null) {
            return __('n/a', 'axs4all-ai');
        }

        return gmdate('M j, H:i', (int) $timestamp) . ' UTC';
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
}
