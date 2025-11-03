<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

final class Monitor
{
    private const OPTION_KEY = 'axs4all_ai_monitor_state';
    private const META_LIMIT = 12;
    private const META_VALUE_LIMIT = 200;

    private DebugLogger $logger;

    public function __construct(DebugLogger $logger)
    {
        $this->logger = $logger;
    }

    public function register(): void
    {
        add_action('axs4all_ai_monitor_start', [$this, 'onStart'], 10, 2);
        add_action('axs4all_ai_monitor_finish', [$this, 'onFinish'], 10, 2);
        add_action('axs4all_ai_monitor_failure', [$this, 'onFailure'], 10, 2);
        add_action('axs4all_ai_monitor_metrics', [$this, 'onMetrics'], 10, 2);
    }

    public static function getState(): array
    {
        $state = get_option(self::OPTION_KEY, []);
        return is_array($state) ? $state : [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function onStart(string $context, array $meta = []): void
    {
        $state = $this->loadState();
        $entry = $state[$context] ?? [];

        $entry['last_start'] = current_time('mysql', true);
        $entry['last_start_ts'] = microtime(true);
        $entry['last_status'] = 'running';
        $entry['meta'] = $this->mergeMeta($entry['meta'] ?? [], $meta);
        $entry['run_count'] = isset($entry['run_count']) ? (int) $entry['run_count'] + 1 : 1;

        $state[$context] = $entry;
        $this->saveState($state);

        $this->logger->record('monitor_start', sprintf('%s run started', $context), array_merge(['context' => $context], $meta));
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function onFinish(string $context, array $meta = []): void
    {
        $state = $this->loadState();
        $entry = $state[$context] ?? [];

        $durationMs = null;
        if (isset($entry['last_start_ts'])) {
            $durationMs = (int) round((microtime(true) - (float) $entry['last_start_ts']) * 1000);
            unset($entry['last_start_ts']);
        }

        $entry['last_finish'] = current_time('mysql', true);
        $entry['last_status'] = 'success';
        $entry['last_duration_ms'] = $durationMs;
        $entry['last_error'] = '';
        $entry['failures_consecutive'] = 0;
        $entry['meta'] = $this->mergeMeta($entry['meta'] ?? [], $meta);

        $state[$context] = $entry;
        $this->saveState($state);

        $logContext = array_merge(['context' => $context, 'duration_ms' => $durationMs], $meta);
        $this->logger->record('monitor_finish', sprintf('%s run finished', $context), $logContext);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function onFailure(string $context, array $meta = []): void
    {
        $state = $this->loadState();
        $entry = $state[$context] ?? [];

        $entry['last_failure'] = current_time('mysql', true);
        $entry['last_status'] = 'failure';
        $entry['last_error'] = isset($meta['message']) ? (string) $meta['message'] : '';
        $entry['failures_consecutive'] = isset($entry['failures_consecutive']) ? (int) $entry['failures_consecutive'] + 1 : 1;
        $entry['meta'] = $this->mergeMeta($entry['meta'] ?? [], $meta);

        $state[$context] = $entry;
        $this->saveState($state);

        $this->logger->record('monitor_failure', sprintf('%s run failed: %s', $context, $entry['last_error']), array_merge(['context' => $context], $meta));
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function onMetrics(string $type, array $meta = []): void
    {
        $state = $this->loadState();
        if (! isset($state['metrics'])) {
            $state['metrics'] = [];
        }

        $state['metrics'][$this->normaliseMetaKey($type)] = [
            'updated_at' => current_time('mysql', true),
            'data' => $this->sanitiseMetaPayload($meta),
        ];

        $this->saveState($state);

        $this->logger->record('monitor_metrics', sprintf('Metrics update: %s', $type), array_merge(['type' => $type], $meta));
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $additional
     * @return array<string, mixed>
     */
    private function mergeMeta(array $existing, array $additional): array
    {
        $sanitisedExisting = $this->sanitiseMetaPayload($existing);
        $sanitisedAdditional = $this->sanitiseMetaPayload($additional);

        foreach ($sanitisedAdditional as $key => $value) {
            $sanitisedExisting[$key] = $value;
        }

        if (count($sanitisedExisting) > self::META_LIMIT) {
            $sanitisedExisting = array_slice($sanitisedExisting, -1 * self::META_LIMIT, null, true);
        }

        return $sanitisedExisting;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function sanitiseMetaPayload(array $meta): array
    {
        $clean = [];

        foreach ($meta as $key => $value) {
            if (count($clean) >= self::META_LIMIT) {
                break;
            }

            $normalisedKey = $this->normaliseMetaKey((string) $key);
            if ($normalisedKey === '') {
                continue;
            }

            if (is_bool($value)) {
                $clean[$normalisedKey] = $value ? 'yes' : 'no';
            } elseif (is_int($value)) {
                $clean[$normalisedKey] = $value;
            } elseif (is_float($value)) {
                $clean[$normalisedKey] = round($value, 4);
            } elseif (is_scalar($value)) {
                $clean[$normalisedKey] = $this->truncateMetaValue((string) $value);
            }
        }

        return $clean;
    }

    private function normaliseMetaKey(string $key): string
    {
        $sanitised = \sanitize_key($key);
        if ($sanitised !== '') {
            return $sanitised;
        }

        $fallback = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($key));
        if ($fallback === null || $fallback === '') {
            return '';
        }

        return substr($fallback, 0, 32);
    }

    private function truncateMetaValue(string $value): string
    {
        $value = \sanitize_text_field($value);

        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length <= self::META_VALUE_LIMIT) {
            return $value;
        }

        $slice = function_exists('mb_substr') ? mb_substr($value, 0, self::META_VALUE_LIMIT - 1) : substr($value, 0, self::META_VALUE_LIMIT - 1);

        return $slice . '…';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadState(): array
    {
        return self::getState();
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveState(array $state): void
    {
        update_option(self::OPTION_KEY, $state, false);
    }
}
