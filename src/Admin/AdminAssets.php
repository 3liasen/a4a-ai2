<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

final class AdminAssets
{
    /** @var array<int, string> */
    private array $allowedPages;

    public function __construct(array $allowedPages)
    {
        $this->allowedPages = $allowedPages;
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        $screen = get_current_screen();
        if (! $screen instanceof \WP_Screen) {
            return;
        }

        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if ($page === '' || ! in_array($page, $this->allowedPages, true)) {
            return;
        }

        wp_enqueue_style(
            'axs4all-ai-fontawesome',
            'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css',
            [],
            '5.15.4'
        );

        wp_enqueue_style(
            'axs4all-ai-adminlte',
            'https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css',
            [],
            '3.2.0'
        );

        wp_enqueue_style(
            'axs4all-ai-dashboard',
            plugin_dir_url(AXS4ALL_AI_PLUGIN_FILE) . 'assets/css/dashboard.css',
            ['axs4all-ai-adminlte'],
            AXS4ALL_AI_VERSION
        );

        wp_enqueue_script(
            'axs4all-ai-bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js',
            ['jquery'],
            '4.6.2',
            true
        );

        wp_enqueue_script(
            'axs4all-ai-adminlte',
            'https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js',
            ['axs4all-ai-bootstrap'],
            '3.2.0',
            true
        );

        if ($page === 'axs4all-ai-settings') {
            wp_enqueue_script(
                'axs4all-ai-settings',
                plugin_dir_url(AXS4ALL_AI_PLUGIN_FILE) . 'assets/js/settings.js',
                [],
                AXS4ALL_AI_VERSION,
                true
            );
        }
    }
}
