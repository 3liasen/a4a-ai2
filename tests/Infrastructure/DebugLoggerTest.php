<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Infrastructure;

use Axs4allAi\Infrastructure\DebugLogger;
use PHPUnit\Framework\TestCase;

final class DebugLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_options'] = [];
    }

    public function testRecordStoresCappedEvents(): void
    {
        $logger = new DebugLogger();

        for ($i = 0; $i < 205; $i++) {
            $logger->record(
                'Test-Type ' . $i,
                '<strong>Message ' . $i . '</strong>',
                ['queue_id' => $i, 'foo' => ['bar']]
            );
        }

        $stored = get_option('axs4all_ai_debug_events', []);
        self::assertIsArray($stored);
        self::assertCount(200, $stored);

        $latest = array_pop($stored);
        self::assertSame('test-type204', $latest['type']);
        self::assertSame('Message 204', $latest['message']);
        self::assertSame(['queue_id' => '204'], $latest['context']);
    }

    public function testClearRemovesEvents(): void
    {
        $logger = new DebugLogger();
        $logger->record('info', 'Something happened');

        $logger->clear();

        $entries = get_option('axs4all_ai_debug_events', []);
        self::assertSame([], $entries);
    }
}
