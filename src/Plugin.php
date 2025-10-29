<?php

declare(strict_types=1);

namespace Axs4allAi;

use Axs4allAi\Admin\SettingsPage;

final class Plugin
{
    public function boot(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
    }

    public function onPluginsLoaded(): void
    {
        load_plugin_textdomain('axs4all-ai', false, dirname(plugin_basename(AXS4ALL_AI_PLUGIN_FILE)) . '/languages');

        $this->registerAdminHooks();
    }

    private function registerAdminHooks(): void
    {
        $settings = new SettingsPage();

        add_action('admin_menu', [$settings, 'registerMenu']);
        add_action('admin_init', [$settings, 'registerSettings']);
    }
}
