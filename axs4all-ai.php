<?php
/**
 * Plugin Name:       axs4all AI Accessibility
 * Description:       Automates accessibility data collection and AI-driven classification for axs4all.
 * Version:           0.0.41
 * Author:            SevenYellowMonkeys
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Text Domain:       axs4all-ai
 */


use Axs4allAi\Classification\ClassificationScheduler;
use Axs4allAi\Crawl\CrawlScheduler;
use Axs4allAi\Infrastructure\Installer;

if (! defined('ABSPATH')) {
    exit;
}

define('AXS4ALL_AI_VERSION_FILE', __DIR__ . '/version.php');
define('AXS4ALL_AI_VERSION', (string) require AXS4ALL_AI_VERSION_FILE);
define('AXS4ALL_AI_PLUGIN_FILE', __FILE__);
define('AXS4ALL_AI_PLUGIN_PATH', __DIR__);
define('AXS4ALL_AI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader if available.
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (! class_exists(\Axs4allAi\Plugin::class)) {
    spl_autoload_register(
        static function (string $class): void {
            if (strpos($class, 'Axs4allAi\\') !== 0) {
                return;
            }

            $relative = str_replace('Axs4allAi\\', '', $class);
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            $path = __DIR__ . '/src/' . $relativePath;

            if (file_exists($path)) {
                require_once $path;
            }
        }
    );
}

$env = __DIR__ . '/.env';
if (file_exists($env) && class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

register_activation_hook(AXS4ALL_AI_PLUGIN_FILE, [Installer::class, 'activate']);
register_deactivation_hook(
    AXS4ALL_AI_PLUGIN_FILE,
    static function (): void {
        CrawlScheduler::deactivate();
        ClassificationScheduler::deactivate();
        \Axs4allAi\Infrastructure\HealthMonitor::deactivate();
    }
);

if (! class_exists(\Axs4allAi\Plugin::class)) {
    return;
}

(new \Axs4allAi\Plugin())->boot();

