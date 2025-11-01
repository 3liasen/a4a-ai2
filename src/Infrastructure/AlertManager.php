<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

final class AlertManager
{
    private const OPTION_STATE = 'axs4all_ai_alert_state';
    private const SEVERITY_ORDER = [
        'info' => 0,
        'warning' => 1,
        'critical' => 2,
    ];

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
            $severity = $pending >= ($threshold * 2) ? 'critical' : 'warning';
            $this->triggerAlert('queue_' . $queue, sprintf('Queue "%s" pending count is %d (threshold %d).', $queue, $pending, $threshold), $severity);
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

        $this->triggerAlert('cron_' . $context, sprintf('Cron "%s" (%s) is not scheduled.', $context, $hook), 'critical');
    }

    public function logCategoryUpdate(string $name, ?int $snippetLimit): void
    {
        $this->logger->record('category_update', sprintf('Category "%s" saved with snippet limit %s', $name, $snippetLimit !== null ? (string) $snippetLimit : 'default'), [
            'category' => $name,
            'snippet_limit' => $snippetLimit,
        ]);
    }

    private function triggerAlert(string $key, string $message, string $severity = 'warning'): void
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
        $severity = $this->normalizeSeverity($severity);
        $subject = '[axs4all-ai] Alert: ' . $key;

        $sent = false;
        if (! empty($settings['alert_email']) && $this->severityAllowed($severity, (string) ($settings['alert_email_min_severity'] ?? 'warning'))) {
            $sent = $this->sendEmail((string) $settings['alert_email'], $subject, $message, $severity) || $sent;
        }

        if (! empty($settings['alert_slack_webhook']) && $this->severityAllowed($severity, (string) ($settings['alert_slack_min_severity'] ?? 'warning'))) {
            $sent = $this->sendSlack((string) $settings['alert_slack_webhook'], $subject . "\n" . $message, $severity) || $sent;
        }

        if (! empty($settings['alert_ticket_webhook']) && $this->severityAllowed($severity, (string) ($settings['alert_ticket_min_severity'] ?? 'critical'))) {
            $sent = $this->sendTicket((string) $settings['alert_ticket_webhook'], $subject, $message, $severity) || $sent;
        }

        if ($sent) {
            $state[$key] = $now;
            update_option(self::OPTION_STATE, $state, false);
        }

        $this->logger->record('alert', $message, [
            'key' => $key,
            'sent' => $sent,
            'severity' => $severity,
        ]);
    }

    private function sendEmail(string $recipient, string $subject, string $message, string $severity): bool
    {
        $result = wp_mail($recipient, $subject, $message);
        if (! $result) {
            $this->logger->record('alert_error', 'Failed to send alert email.', [
                'recipient' => $recipient,
                'severity' => $severity,
            ]);
        }

        return (bool) $result;
    }

    private function sendSlack(string $webhook, string $message, string $severity): bool
    {
        $response = wp_remote_post($webhook, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encode(['text' => $message]),
        ]);

        if (is_wp_error($response)) {
            $this->logger->record('alert_error', 'Failed to post alert to Slack: ' . $response->get_error_message(), [
                'webhook' => $webhook,
                'severity' => $severity,
            ]);
            return false;
        }

        return true;
    }

    private function sendTicket(string $endpoint, string $subject, string $message, string $severity): bool
    {
        $payload = [
            'title' => $subject,
            'body' => $message,
            'severity' => $severity,
            'created_at' => current_time('mysql', true),
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->logger->record('alert_error', 'Failed to open ticket: ' . $response->get_error_message(), [
                'endpoint' => $endpoint,
                'severity' => $severity,
            ]);
            return false;
        }

        $this->logger->record('alert_ticket', 'Ticket created for alert.', [
            'endpoint' => $endpoint,
            'severity' => $severity,
        ]);

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

    private function encode(array $payload): string
    {
        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($payload);
        } else {
            $encoded = json_encode($payload);
        }

        return is_string($encoded) ? $encoded : '';
    }

    private function normalizeSeverity(string $severity): string
    {
        $severity = strtolower($severity);
        return array_key_exists($severity, self::SEVERITY_ORDER) ? $severity : 'warning';
    }

    private function severityAllowed(string $candidate, string $minimum): bool
    {
        $candidate = $this->normalizeSeverity($candidate);
        $minimum = $this->normalizeSeverity($minimum);

        return self::SEVERITY_ORDER[$candidate] >= self::SEVERITY_ORDER[$minimum];
    }
}


