<?php

declare(strict_types=1);

namespace Axs4allAi;

use Axs4allAi\Admin\AdminAssets;
use Axs4allAi\Admin\CategoryPage;
use Axs4allAi\Admin\ClientPage;
use Axs4allAi\Admin\ClassificationResultsPage;
use Axs4allAi\Admin\DashboardPage;
use Axs4allAi\Admin\DebugPage;
use Axs4allAi\Admin\Footer;
use Axs4allAi\Admin\ManualClassificationPage;
use Axs4allAi\Admin\PromptPage;
use Axs4allAi\Admin\QueuePage;
use Axs4allAi\Admin\SettingsPage;
use Axs4allAi\Admin\BillingPage;
use Axs4allAi\Ai\OpenAiClient;
use Axs4allAi\Classification\ClassificationCommand;
use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\ClassificationRunner;
use Axs4allAi\Classification\ClassificationScheduler;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Category\CategoryRegistrar;
use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Crawl\ClientCrawlScheduler;
use Axs4allAi\Crawl\CrawlRunner;
use Axs4allAi\Crawl\CrawlScheduler;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Data\SnapshotRepository;
use Axs4allAi\Infrastructure\Installer;
use Axs4allAi\Infrastructure\ExchangeRateUpdater;
use Axs4allAi\Infrastructure\DebugLogger;
use Axs4allAi\Infrastructure\AlertManager;
use Axs4allAi\Infrastructure\HealthMonitor;
use Axs4allAi\Infrastructure\Monitor;

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
    private ClientCrawlScheduler $clientCrawlScheduler;
    private CrawlRunner $crawlRunner;
    private ClientRepository $clientRepository;
    private SnapshotRepository $snapshotRepository;
    private ExchangeRateUpdater $exchangeRateUpdater;
    private DebugLogger $debugLogger;
    private AlertManager $alertManager;
    private HealthMonitor $healthMonitor;
    private Monitor $monitor;
    private array $settings;
    private string $version;
    public function __construct()
    {
        global $wpdb;

        $this->version = AXS4ALL_AI_VERSION;
        $this->queueRepository = new QueueRepository($wpdb);
        $this->promptRepository = new PromptRepository($wpdb);
        $this->categoryRegistrar = new CategoryRegistrar();
        $this->categoryRepository = new CategoryRepository();
        $this->classificationQueueRepository = new ClassificationQueueRepository($wpdb);
        $this->clientRepository = new ClientRepository($wpdb);
        $this->snapshotRepository = new SnapshotRepository($wpdb);
        $this->exchangeRateUpdater = new ExchangeRateUpdater();
        $this->debugLogger = new DebugLogger();
        $this->alertManager = new AlertManager($this->debugLogger);
        $this->monitor = new Monitor($this->debugLogger);
        $this->settings = $this->loadSettings();
        $apiKey = $this->resolveApiKey();
        $this->aiClient = new OpenAiClient(
            $apiKey,
            $this->settings['model'],
            $this->settings['temperature'],
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
            $this->classificationQueueRepository,
            $this->clientRepository,
            $this->categoryRepository,
            null,
            null,
            $this->snapshotRepository,
            $this->debugLogger
        );
        $this->crawlScheduler = new CrawlScheduler($this->queueRepository, $this->crawlRunner);
        $this->clientCrawlScheduler = new ClientCrawlScheduler(
            $this->clientRepository,
            $this->queueRepository,
            $this->categoryRepository
        );
        $this->healthMonitor = new HealthMonitor($this->alertManager, $this->queueRepository, $this->classificationQueueRepository);
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
        $this->clientCrawlScheduler->register();
        $this->classificationScheduler->register();
        $this->exchangeRateUpdater->register();
        $this->healthMonitor->register();
        $this->monitor->register();
        $this->registerCliCommands();
    }

    private function registerAdminHooks(): void
    {
        $mainSlug = 'axs4all-ai';
        $dashboard = new DashboardPage($this->queueRepository, $this->classificationQueueRepository);
        $settings = new SettingsPage($mainSlug, 'axs4all-ai-settings');
        $queuePage = new QueuePage($this->queueRepository, $this->clientRepository, $this->categoryRepository, $this->snapshotRepository);
        $debugPage = new DebugPage($this->snapshotRepository, $this->debugLogger);
        $promptPage = new PromptPage($this->promptRepository, $this->categoryRepository);
        $manualPage = new ManualClassificationPage(
            $this->classificationQueueRepository,
            $this->classificationRunner,
            $this->promptRepository,
            $this->categoryRepository,
            $this->clientRepository,
            $this->queueRepository
        );
        $billingPage = new BillingPage($this->classificationQueueRepository);
        $categoryPage = new CategoryPage($this->categoryRepository, $this->alertManager);
        $clientPage = new ClientPage($this->clientRepository, $this->categoryRepository);
        $classificationPage = new ClassificationResultsPage($this->classificationQueueRepository, $this->alertManager);
        $footer = new Footer($this->version);
        $adminAssets = new AdminAssets([
            $mainSlug,
            'axs4all-ai-settings',
            'axs4all-ai-queue',
            'axs4all-ai-debug',
            'axs4all-ai-prompts',
            'axs4all-ai-categories',
            'axs4all-ai-clients',
            'axs4all-ai-classifications',
            'axs4all-ai-manual',
            'axs4all-ai-billing',
        ]);

        $adminAssets->register();

        add_action('admin_menu', [$dashboard, 'registerMenu']);
        add_action('admin_menu', [$settings, 'registerMenu']);
        add_action('admin_menu', [$queuePage, 'registerMenu']);
        add_action('admin_menu', [$debugPage, 'registerMenu']);
        add_action('admin_menu', [$promptPage, 'registerMenu']);
        add_action('admin_menu', [$categoryPage, 'registerMenu']);
        add_action('admin_menu', [$clientPage, 'registerMenu']);
        add_action('admin_menu', [$classificationPage, 'registerMenu']);
        add_action('admin_menu', [$manualPage, 'registerMenu']);
        add_action('admin_menu', [$billingPage, 'registerMenu']);
        add_action('admin_init', [$settings, 'registerSettings']);
        add_action('admin_init', [$settings, 'registerActions']);
        add_action('admin_init', [$queuePage, 'registerActions']);
        add_action('admin_init', [$debugPage, 'registerActions']);
        add_action('admin_init', [$promptPage, 'registerActions']);
        add_action('admin_init', [$categoryPage, 'registerActions']);
        add_action('admin_init', [$clientPage, 'registerActions']);
        add_action('admin_init', [$manualPage, 'registerActions']);
        add_action('admin_init', [$classificationPage, 'registerActions']);
        $footer->register();
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
            'temperature' => isset($options['temperature']) ? max(0.0, min(2.0, (float) $options['temperature'])) : 0.0,
            'timeout' => $timeout,
            'batch_size' => $batchSize,
            'max_attempts' => $maxAttempts,
        ];
    }
}

