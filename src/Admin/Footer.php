<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

final class Footer
{
    private string $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function register(): void
    {
        add_action('admin_footer', [$this, 'render']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || strpos((string) $screen->id, 'axs4all-ai') === false) {
            return;
        }

        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        $html = sprintf(
            '<div class="axs4all-ai-footer" style="margin:2rem 0;text-align:center;color:#777;font-size:12px;">&copy; Developed by SevenYellowMonkeys - v.%s</div>',
            esc_html($this->version)
        );

        echo $html;
    }
}

