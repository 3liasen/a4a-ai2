<?php

declare(strict_types=1);

namespace Axs4allAi;

use Axs4allAi\Admin\DebugPage;
use Axs4allAi\Admin\PromptPage;
use Axs4allAi\Admin\QueuePage;
use Axs4allAi\Admin\SettingsPage;
use Axs4allAi\Ai\OpenAiClient;
use Axs4allAi\Classification\ClassificationCommand;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Crawl\CrawlScheduler;
use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Infrastructure\Installer;

final class Plugin
{
    private QueueRepository $queueRepository;
    private CrawlScheduler $crawlScheduler;
    private PromptRepository $promptRepository;
    public function __construct()
    {
        global $wpdb;

        $this->queueRepository = new QueueRepository($wpdb);
        $this->crawlScheduler = new CrawlScheduler($this->queueRepository);
        $this->promptRepository = new PromptRepository($wpdb);
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
        $this->registerCliCommands();
    }

    private function registerAdminHooks(): void
    {
        $settings = new SettingsPage();
        $queuePage = new QueuePage($this->queueRepository);
        $debugPage = new DebugPage();
        $promptPage = new PromptPage($this->promptRepository);

        add_action('admin_menu', [$settings, 'registerMenu']);
        add_action('admin_menu', [$queuePage, 'registerMenu']);
        add_action('admin_menu', [$debugPage, 'registerMenu']);
        add_action('admin_menu', [$promptPage, 'registerMenu']);
        add_action('admin_init', [$settings, 'registerSettings']);
        add_action('admin_init', [$queuePage, 'registerActions']);
        add_action('admin_init', [$debugPage, 'registerActions']);
        add_action('admin_init', [$promptPage, 'registerActions']);
    }

    private function registerCliCommands(): void
    {
        $apiKey = $this->resolveApiKey();
        $client = new OpenAiClient($apiKey);
        $command = new ClassificationCommand($this->promptRepository, $client);
        $command->register();
    }

    private function resolveApiKey(): string
    {
        $options = get_option('axs4all_ai_settings', []);
        if (is_array($options) && ! empty($options['api_key'])) {
            return (string) $options['api_key'];
        }

        $envKey = getenv('OPENAI_API_KEY');
        if (is_string($envKey)) {
            return $envKey;
        }

        return '';
    }
}
