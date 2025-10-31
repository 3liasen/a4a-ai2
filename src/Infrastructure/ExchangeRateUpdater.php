<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

final class ExchangeRateUpdater
{
    private const OPTION_KEY = 'axs4all_ai_exchange_rate';
    private const HOOK = 'axs4all_ai_update_exchange_rate';

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

    public function updateRate(): void
    {
        $settings = get_option('axs4all_ai_settings', []);
        $auto = isset($settings['exchange_rate_auto']) ? (bool) $settings['exchange_rate_auto'] : false;
        if (! $auto) {
            return;
        }

        $response = wp_remote_get('https://api.exchangerate.host/latest?base=USD&symbols=DKK', [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (! is_array($data) || empty($data['success']) || empty($data['rates']['DKK'])) {
            return;
        }

        $rate = (float) $data['rates']['DKK'];
        if ($rate <= 0) {
            return;
        }

        self::storeRate($rate);

        if (! is_array($settings)) {
            $settings = [];
        }
        $settings['exchange_rate'] = $rate;
        update_option('axs4all_ai_settings', $settings);
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
