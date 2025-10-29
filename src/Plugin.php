<?php

declare(strict_types=1);

namespace Axs4allAi;

use Axs4allAi\Admin\QueuePage;
use Axs4allAi\Admin\SettingsPage;
use Axs4allAi\Crawl\CrawlScheduler;
use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Infrastructure\Installer;

final class Plugin
{
    private QueueRepository $queueRepository;
    private CrawlScheduler $crawlScheduler;

    public function __construct()
    {
        global $wpdb;

        $this->queueRepository = new QueueRepository($wpdb);
        $this->crawlScheduler = new CrawlScheduler($this->queueRepository);
    }

    public function boot(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
    }

    public function onPluginsLoaded(): void
    {
        Installer::maybeUpgrade();

        load_plugin_textdomain('axs4all-ai', false, dirname(plugin_basename(AXS4ALL_AI_PLUGIN_FILE)) . '/languages');

        $this->registerAdminHooks();
        $this->crawlScheduler->register();
    }

    private function registerAdminHooks(): void
    {
        $settings = new SettingsPage();
        $queuePage = new QueuePage($this->queueRepository);

        add_action('admin_menu', [$settings, 'registerMenu']);
        add_action('admin_menu', [$queuePage, 'registerMenu']);
        add_action('admin_init', [$settings, 'registerSettings']);
        add_action('admin_init', [$queuePage, 'registerActions']);
    }
}
