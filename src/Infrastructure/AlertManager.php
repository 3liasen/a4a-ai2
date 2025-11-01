<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

final class AlertManager
{
    private const OPTION_STATE = 'axs4all_ai_alert_state';

    private DebugLogger $logger;

    public function __construct(DebugLogger $logger)
    {
        $this->logger = $logger;
    }

    public function recordQueueMetrics(string $queue, int $pending): void
    {
        $settings = $this->getSettings();
        $threshold = isset($settings['alert_queue_threshold']) ? (int) $settings['alert_queue_threshold'] : 0;

        $this->logger->record('queue_metrics', sprintf('Queue %s pending=%d threshold=%d', $queue, $pending, $threshold), [
            'queue' => $queue,
            'pending' => $pending,
            'threshold' => $threshold,
        ]);

        if ($threshold > 0 && $pending >= $threshold) {
            $this->triggerAlert('queue_' . $queue, sprintf('Queue "%s" pending count is %d (threshold %d).', $queue, $pending, $threshold));
        }

        update_option('axs4all_ai_alert_' . $queue, [
            'pending' => $pending,
            'checked_at' => current_time('mysql', true),
        ], false);
    }

    public function ensureCronScheduled(string $hook, string $context): void
    {
        $next = wp_next_scheduled($hook);
        if ($next !== false) {
            $this->logger->record('cron_metrics', sprintf('Cron "%s" next run at %s', $context, gmdate('c', (int) $next)), [
                'hook' => $hook,
                'context' => $context,
                'next' => $next,
            ]);
            return;
        }

        $this->logger->record('cron_warning', sprintf('Cron "%s" (%s) is not scheduled.', $context, $hook), [
            'hook' => $hook,
            'context' => $context,
        ]);

        $this->triggerAlert('cron_' . $context, sprintf('Cron "%s" (%s) is not scheduled.', $context, $hook));
    }

    public function logCategoryUpdate(string $name, ?int $snippetLimit): void
    {
        $this->logger->record('category_update', sprintf('Category "%s" saved with snippet limit %s', $name, $snippetLimit !== null ? (string) $snippetLimit : 'default'), [
            'category' => $name,
            'snippet_limit' => $snippetLimit,
        ]);
    }

    private function triggerAlert(string $key, string $message): void
    {
        $state = get_option(self::OPTION_STATE, []);
        if (! is_array($state)) {
            $state = [];
        }

        $now = time();
        $cooldown = 3600; // 1 hour throttle
        if (isset($state[$key]) && ($now - (int) $state[$key]) < $cooldown) {
            return;
        }

        $settings = $this->getSettings();
        $subject = '[axs4all-ai] Alert: ' . $key;

        $sent = false;
        if (! empty($settings['alert_email'])) {
            $sent = $this->sendEmail((string) $settings['alert_email'], $subject, $message) || $sent;
        }

        if (! empty($settings['alert_slack_webhook'])) {
            $sent = $this->sendSlack((string) $settings['alert_slack_webhook'], $subject . "\n" . $message) || $sent;
        }

        if ($sent) {
            $state[$key] = $now;
            update_option(self::OPTION_STATE, $state, false);
        }

        $this->logger->record('alert', $message, [
            'key' => $key,
            'sent' => $sent,
        ]);
    }

    private function sendEmail(string $recipient, string $subject, string $message): bool
    {
        $result = wp_mail($recipient, $subject, $message);
        if (! $result) {
            $this->logger->record('alert_error', 'Failed to send alert email.', [
                'recipient' => $recipient,
            ]);
        }

        return (bool) $result;
    }

    private function sendSlack(string $webhook, string $message): bool
    {
        $response = wp_remote_post($webhook, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['text' => $message]),
        ]);

        if (is_wp_error($response)) {
            $this->logger->record('alert_error', 'Failed to post alert to Slack: ' . $response->get_error_message(), [
                'webhook' => $webhook,
            ]);
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('axs4all_ai_settings', []);
        return is_array($settings) ? $settings : [];
    }
}
