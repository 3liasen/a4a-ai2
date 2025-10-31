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

        add_settings_section(
            'axs4all_ai_classification_section',
            __('Classification Automation', 'axs4all-ai'),
            static function (): void {
                echo '<p>' . esc_html__('Configure how automated classification jobs interact with the AI provider.', 'axs4all-ai') . '</p>';
            },
            'axs4all-ai'
        );

        add_settings_field(
            'axs4all_ai_model',
            __('Model', 'axs4all-ai'),
            [$this, 'renderModelField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_field(
            'axs4all_ai_timeout',
            __('Request Timeout (seconds)', 'axs4all-ai'),
            [$this, 'renderTimeoutField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_field(
            'axs4all_ai_batch_size',
            __('Batch Size', 'axs4all-ai'),
            [$this, 'renderBatchSizeField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_field(
            'axs4all_ai_max_attempts',
            __('Max Attempts', 'axs4all-ai'),
            [$this, 'renderMaxAttemptsField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );
    }

    public function sanitize(array $input): array
    {
        $existing = get_option(self::OPTION_NAME, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $output = $existing;

        $submittedKey = isset($input['api_key']) ? (string) $input['api_key'] : '';
        if ($submittedKey === '********' && isset($existing['api_key']) && $existing['api_key'] !== '') {
            $output['api_key'] = (string) $existing['api_key'];
        } else {
            $output['api_key'] = sanitize_text_field($submittedKey);
        }
        $output['api_key_source'] = $this->resolveApiKeySource($output['api_key']);

        $output['model'] = isset($input['model']) && $input['model'] !== ''
            ? sanitize_text_field((string) $input['model'])
            : ($existing['model'] ?? 'gpt-4o-mini');

        $timeout = isset($input['timeout']) ? (int) $input['timeout'] : ($existing['timeout'] ?? 30);
        $output['timeout'] = max(5, min(300, $timeout));

        $batchSize = isset($input['batch_size']) ? (int) $input['batch_size'] : ($existing['batch_size'] ?? 5);
        $output['batch_size'] = max(1, min(50, $batchSize));

        $maxAttempts = isset($input['max_attempts']) ? (int) $input['max_attempts'] : ($existing['max_attempts'] ?? 3);
        $output['max_attempts'] = max(1, min(10, $maxAttempts));

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

    public function renderModelField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $model = isset($options['model']) ? (string) $options['model'] : 'gpt-4o-mini';

        printf(
            '<input type="text" name="%1$s[model]" value="%2$s" class="regular-text" placeholder="%3$s" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($model),
            esc_attr__('e.g. gpt-4o-mini', 'axs4all-ai')
        );
        echo '<p class="description">' . esc_html__('Name of the OpenAI-compatible model used for classification.', 'axs4all-ai') . '</p>';
    }

    public function renderTimeoutField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 30;

        printf(
            '<input type="number" name="%1$s[timeout]" value="%2$d" min="5" max="300" step="1" />',
            esc_attr(self::OPTION_NAME),
            $timeout
        );
        echo '<p class="description">' . esc_html__('Maximum number of seconds to wait for each OpenAI response.', 'axs4all-ai') . '</p>';
    }

    public function renderBatchSizeField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $batchSize = isset($options['batch_size']) ? (int) $options['batch_size'] : 5;

        printf(
            '<input type="number" name="%1$s[batch_size]" value="%2$d" min="1" max="50" step="1" />',
            esc_attr(self::OPTION_NAME),
            $batchSize
        );
        echo '<p class="description">' . esc_html__('Default number of jobs processed per automated batch.', 'axs4all-ai') . '</p>';
    }

    public function renderMaxAttemptsField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $maxAttempts = isset($options['max_attempts']) ? (int) $options['max_attempts'] : 3;

        printf(
            '<input type="number" name="%1$s[max_attempts]" value="%2$d" min="1" max="10" step="1" />',
            esc_attr(self::OPTION_NAME),
            $maxAttempts
        );
        echo '<p class="description">' . esc_html__('How many times a job may be retried after failures before being marked as failed permanently.', 'axs4all-ai') . '</p>';
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
