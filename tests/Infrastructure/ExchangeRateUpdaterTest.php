<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Infrastructure;

use Axs4allAi\Infrastructure\ExchangeRateUpdater;
use PHPUnit\Framework\TestCase;

final class ExchangeRateUpdaterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_cron_scheduled'] = [];
        $GLOBALS['wp_remote_get_queue'] = [];
    }

    public function testScheduleEventRegistersDailyCron(): void
    {
        $updater = new ExchangeRateUpdater();
        $updater->scheduleEvent();

        self::assertArrayHasKey('axs4all_ai_update_exchange_rate', $GLOBALS['wp_cron_scheduled']);
        $events = array_values($GLOBALS['wp_cron_scheduled']['axs4all_ai_update_exchange_rate']);
        self::assertNotEmpty($events);
        $scheduledEvent = $events[0];
        $now = time();
        self::assertGreaterThanOrEqual($now + HOUR_IN_SECONDS, $scheduledEvent['timestamp']);

        $updater->scheduleEvent();
        $eventsAfter = array_values($GLOBALS['wp_cron_scheduled']['axs4all_ai_update_exchange_rate']);
        self::assertSame($scheduledEvent['timestamp'], $eventsAfter[0]['timestamp']);
    }

    public function testUpdateRateFetchesAndStoresLatestRate(): void
    {
        update_option('axs4all_ai_settings', [
            'exchange_rate_auto' => 1,
            'exchange_rate' => 0.0,
        ]);

        $GLOBALS['wp_remote_get_queue'][] = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'success' => true,
                'rates' => ['DKK' => 6.75],
            ]),
        ];

        $updater = new ExchangeRateUpdater();
        self::assertTrue($updater->updateRate());

        $stored = ExchangeRateUpdater::getStoredRate();
        self::assertNotNull($stored);
        self::assertSame(6.75, $stored['rate']);

        $settings = get_option('axs4all_ai_settings');
        self::assertSame(6.75, $settings['exchange_rate']);

        ExchangeRateUpdater::storeRate(0.0);
        self::assertNull(ExchangeRateUpdater::getStoredRate());
    }

    public function testUpdateRateFailureStoresError(): void
    {
        update_option('axs4all_ai_settings', [
            'exchange_rate_auto' => 1,
            'exchange_rate' => 0.0,
        ]);

        $GLOBALS['wp_remote_get_queue'][] = [
            'response' => ['code' => 500],
            'body' => '{}',
        ];

        $updater = new ExchangeRateUpdater();
        self::assertFalse($updater->updateRate());
        self::assertSame('Unexpected response code: 500', $updater->getLastError());

        $GLOBALS['wp_remote_get_queue'][] = [
            'response' => ['code' => 200],
            'body' => '{"foo":"bar"}',
        ];

        self::assertFalse($updater->updateRate(true));
        self::assertStringContainsString('Malformed response from exchangerate.host.', $updater->getLastError());
        self::assertNotNull($updater->getLastResponse());
    }
}
