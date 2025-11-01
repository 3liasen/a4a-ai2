<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Infrastructure;

use Axs4allAi\Infrastructure\AlertManager;
use Axs4allAi\Infrastructure\DebugLogger;
use PHPUnit\Framework\TestCase;

final class AlertManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_mails'] = [];
        $this->logger = new DebugLogger();
        $this->manager = new AlertManager($this->logger);
    }

    public function testQueueAlertStoresPendingJobs(): void
    {
        $this->manager->recordQueueMetrics('crawl', 12);

        $alert = get_option('axs4all_ai_alert_crawl', []);
        self::assertSame(12, $alert['pending']);
        self::assertNotEmpty($GLOBALS['wp_options']);
    }

    public function testTriggerAlertSendsEmailWhenConfigured(): void
    {
        update_option('axs4all_ai_settings', [
            'alert_email' => 'ops@example.com',
            'alert_queue_threshold' => 5,
        ]);

        $this->manager->recordQueueMetrics('crawl', 10);

        self::assertNotEmpty($GLOBALS['wp_mails']);
        $mail = $GLOBALS['wp_mails'][0];
        self::assertStringContainsString('queue_crawl', $mail['subject']);
    }

    private AlertManager $manager;
    private DebugLogger $logger;
}
