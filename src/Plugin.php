<?php

declare(strict_types=1);

namespace Axs4allAi;

use Axs4allAi\Admin\CategoryPage;
use Axs4allAi\Admin\ClassificationResultsPage;
use Axs4allAi\Admin\DebugPage;
use Axs4allAi\Admin\PromptPage;
use Axs4allAi\Admin\QueuePage;
use Axs4allAi\Admin\SettingsPage;
use Axs4allAi\Ai\OpenAiClient;
use Axs4allAi\Classification\ClassificationCommand;
use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\ClassificationRunner;
use Axs4allAi\Classification\ClassificationScheduler;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Category\CategoryRegistrar;
use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Crawl\CrawlRunner;
use Axs4allAi\Crawl\CrawlScheduler;
use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Infrastructure\Installer;

final class Plugin
{
    private QueueRepository $queueRepository;
    private CrawlScheduler $crawlScheduler;
    private PromptRepository $promptRepository;
    private CategoryRegistrar $categoryRegistrar;
    private CategoryRepository $categoryRepository;
    private ClassificationQueueRepository $classificationQueueRepository;
    private OpenAiClient $aiClient;
    private ClassificationRunner $classificationRunner;
    private ClassificationScheduler $classificationScheduler;
    private CrawlRunner $crawlRunner;
    private array $settings;
    public function __construct()
    {
        global $wpdb;

        $this->queueRepository = new QueueRepository($wpdb);
        $this->promptRepository = new PromptRepository($wpdb);
        $this->categoryRegistrar = new CategoryRegistrar();
        $this->categoryRepository = new CategoryRepository();
        $this->classificationQueueRepository = new ClassificationQueueRepository($wpdb);
        $this->settings = $this->loadSettings();
        $apiKey = $this->resolveApiKey();
        $this->aiClient = new OpenAiClient(
            $apiKey,
            $this->settings['model'],
            0.0,
            null,
            null,
            (float) $this->settings['timeout']
        );
        $this->classificationRunner = new ClassificationRunner(
            $this->promptRepository,
            $this->aiClient,
            $this->categoryRepository,
            $this->classificationQueueRepository,
            $this->settings['max_attempts']
        );
        $this->classificationScheduler = new ClassificationScheduler(
            $this->classificationQueueRepository,
            $this->classificationRunner,
            $this->settings['batch_size']
        );
        $this->crawlRunner = new CrawlRunner(
            $this->queueRepository,
            $this->promptRepository,
            $this->classificationQueueRepository
        );
        $this->crawlScheduler = new CrawlScheduler($this->queueRepository, $this->crawlRunner);
    }

    public function boot(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
    }

    public function onPluginsLoaded(): void
    {
        Installer::maybeUpgrade();

        load_plugin_textdomain('axs4all-ai', false, dirname(plugin_basename(AXS4ALL_AI_PLUGIN_FILE)) . '/languages');

        $this->categoryRegistrar->register();
        $this->registerAdminHooks();
        $this->crawlScheduler->register();
        $this->classificationScheduler->register();
        $this->registerCliCommands();
    }

    private function registerAdminHooks(): void
    {
        $settings = new SettingsPage();
        $queuePage = new QueuePage($this->queueRepository);
        $debugPage = new DebugPage();
        $promptPage = new PromptPage($this->promptRepository);
        $categoryPage = new CategoryPage($this->categoryRepository);
        $classificationPage = new ClassificationResultsPage($this->classificationQueueRepository);

        add_action('admin_menu', [$settings, 'registerMenu']);
        add_action('admin_menu', [$queuePage, 'registerMenu']);
        add_action('admin_menu', [$debugPage, 'registerMenu']);
        add_action('admin_menu', [$promptPage, 'registerMenu']);
        add_action('admin_menu', [$categoryPage, 'registerMenu']);
        add_action('admin_menu', [$classificationPage, 'registerMenu']);
        add_action('admin_init', [$settings, 'registerSettings']);
        add_action('admin_init', [$queuePage, 'registerActions']);
        add_action('admin_init', [$debugPage, 'registerActions']);
        add_action('admin_init', [$promptPage, 'registerActions']);
        add_action('admin_init', [$categoryPage, 'registerActions']);
    }

    private function registerCliCommands(): void
    {
        $command = new ClassificationCommand(
            $this->promptRepository,
            $this->aiClient,
            $this->categoryRepository,
            $this->classificationQueueRepository,
            $this->classificationRunner,
            $this->settings['batch_size']
        );
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

    /**
     * @return array<string, mixed>
     */
    private function loadSettings(): array
    {
        $options = get_option('axs4all_ai_settings', []);

        $model = isset($options['model']) && $options['model'] !== ''
            ? sanitize_text_field((string) $options['model'])
            : 'gpt-4o-mini';

        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 30;
        $timeout = max(5, min(300, $timeout));

        $batchSize = isset($options['batch_size']) ? (int) $options['batch_size'] : 5;
        $batchSize = max(1, min(50, $batchSize));

        $maxAttempts = isset($options['max_attempts']) ? (int) $options['max_attempts'] : 3;
        $maxAttempts = max(1, min(10, $maxAttempts));

        return [
            'model' => $model,
            'timeout' => $timeout,
            'batch_size' => $batchSize,
            'max_attempts' => $maxAttempts,
        ];
    }
}

