<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

final class ExchangeRateUpdater
{
    private const OPTION_KEY = 'axs4all_ai_exchange_rate';
    private const HOOK = 'axs4all_ai_update_exchange_rate';
    private ?string $lastError = null;
    private ?string $lastResponse = null;

    public function register(): void
    {
        add_action('init', [$this, 'scheduleEvent']);
        add_action(self::HOOK, [$this, 'updateRate']);
    }

    public function scheduleEvent(): void
    {
        if (wp_next_scheduled(self::HOOK)) {
            return;
        }

        $timestamp = time() + HOUR_IN_SECONDS;
        wp_schedule_event($timestamp, 'daily', self::HOOK);
    }

    public function updateRate(bool $force = false): bool
    {
        $this->lastError = null;
        $this->lastResponse = null;
        $settings = get_option('axs4all_ai_settings', []);
        $auto = isset($settings['exchange_rate_auto']) ? (bool) $settings['exchange_rate_auto'] : false;
        if (! $auto && ! $force) {
            $this->lastError = __('Automatic exchange rate updates are disabled.', 'axs4all-ai');
            return false;
        }

        $response = wp_remote_get('https://api.exchangerate.host/latest?base=USD&symbols=DKK', [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $this->lastError = sprintf(
                /* translators: %s: transport error message */
                __('HTTP request failed: %s', 'axs4all-ai'),
                $response->get_error_message()
            );
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->lastError = sprintf(
                /* translators: %d: HTTP status code */
                __('Unexpected response code: %d', 'axs4all-ai'),
                $code
            );
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $this->lastResponse = is_string($body) ? $body : '';

        $rate = null;
        if (is_array($data) && isset($data['rates']['DKK'])) {
            $rate = (float) $data['rates']['DKK'];
        }

        if (! is_array($data) || $rate === null) {
            $snippet = $this->summarizeResponse($this->lastResponse);
            $this->lastError = sprintf(
                /* translators: %s: truncated response payload */
                __('Malformed response from exchangerate.host. Payload: %s', 'axs4all-ai'),
                $snippet
            );
            update_option('axs4all_ai_exchange_rate_debug', $this->lastResponse, false);
            return false;
        }

        if ($rate <= 0) {
            $this->lastError = __('Fetched exchange rate was zero or negative.', 'axs4all-ai');
            return false;
        }

        self::storeRate($rate);

        if (! is_array($settings)) {
            $settings = [];
        }
        $settings['exchange_rate'] = $rate;
        update_option('axs4all_ai_settings', $settings);

        return true;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    private function summarizeResponse(?string $body): string
    {
        if ($body === null || $body === '') {
            return __('(empty response)', 'axs4all-ai');
        }

        $clean = preg_replace('/\s+/', ' ', $body) ?? $body;
        if (strlen($clean) > 180) {
            $clean = substr($clean, 0, 180) . '...';
        }

        return $clean;
    }

    public static function getStoredRate(): ?array
    {
        $value = get_option(self::OPTION_KEY, null);
        return is_array($value) ? $value : null;
    }

    public static function storeRate(float $rate, ?string $timestamp = null): void
    {
        if ($rate <= 0) {
            delete_option(self::OPTION_KEY);
            return;
        }

        update_option(
            self::OPTION_KEY,
            [
                'rate' => $rate,
                'updated_at' => $timestamp ?? current_time('mysql'),
            ],
            false
        );
    }
}
