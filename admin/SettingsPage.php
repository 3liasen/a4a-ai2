<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

final class SettingsPage
{
    private const OPTION_GROUP = 'axs4all_ai_settings_group';
    private const OPTION_NAME = 'axs4all_ai_settings';

    public function registerMenu(): void
    {
        add_menu_page(
            __('axs4all AI', 'axs4all-ai'),
            __('axs4all AI', 'axs4all-ai'),
            'manage_options',
            'axs4all-ai',
            [$this, 'render'],
            'dashicons-universal-access-alt',
            56
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'sanitize_callback' => [$this, 'sanitize'],
            ]
        );

        add_settings_section(
            'axs4all_ai_api_section',
            __('AI Provider Credentials', 'axs4all-ai'),
            static function (): void {
                echo '<p>' . esc_html__('Store OpenAI API credentials securely. Keys are required before enabling automated classification.', 'axs4all-ai') . '</p>';
            },
            'axs4all-ai'
        );

        add_settings_field(
            'axs4all_ai_api_key',
            __('OpenAI API Key', 'axs4all-ai'),
            [$this, 'renderApiKeyField'],
            'axs4all-ai',
            'axs4all_ai_api_section'
        );
    }

    public function sanitize(array $input): array
    {
        $output = get_option(self::OPTION_NAME, []);
        $output['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $output['api_key_source'] = $this->resolveApiKeySource($output['api_key']);

        return $output;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('axs4all AI Settings', 'axs4all-ai'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections('axs4all-ai');
                submit_button(__('Save Settings', 'axs4all-ai'));
                ?>
            </form>
        </div>
        <?php
    }

    public function renderApiKeyField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $stored  = $options['api_key'] ?? '';
        $source  = $options['api_key_source'] ?? 'database';

        $prefill = '';
        if ($source === 'env' && getenv('OPENAI_API_KEY')) {
            $prefill = '********';
        } elseif ($stored !== '') {
            $prefill = '********';
        }

        printf(
            '<input type="password" name="%1$s[api_key]" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($prefill)
        );
        echo '<p class="description">' . esc_html__('Paste your OpenAI API key. Leave blank to continue using the existing stored key or .env value.', 'axs4all-ai') . '</p>';
        if ($source === 'env' && getenv('OPENAI_API_KEY')) {
            echo '<p class="description">' . esc_html__('.env file value currently active.', 'axs4all-ai') . '</p>';
        } elseif ($stored !== '') {
            echo '<p class="description">' . esc_html__('Database-stored key currently active.', 'axs4all-ai') . '</p>';
        }
    }

    private function resolveApiKeySource(string $submitted): string
    {
        if ($submitted !== '') {
            return 'database';
        }

        if (getenv('OPENAI_API_KEY')) {
            return 'env';
        }

        return 'unset';
    }
}
