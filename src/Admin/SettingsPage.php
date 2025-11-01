<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Infrastructure\ExchangeRateUpdater;

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
            'axs4all_ai_temperature',
            __('Temperature', 'axs4all-ai'),
            [$this, 'renderTemperatureField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_field(
            'axs4all_ai_prompt_price',
            __('Prompt Cost (USD / 1M tokens)', 'axs4all-ai'),
            [$this, 'renderPromptPriceField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_field(
            'axs4all_ai_completion_price',
            __('Completion Cost (USD / 1M tokens)', 'axs4all-ai'),
            [$this, 'renderCompletionPriceField'],
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

        add_settings_field(
            'axs4all_ai_exchange_rate_auto',
            __('Auto-fetch USD to DKK rate', 'axs4all-ai'),
            [$this, 'renderExchangeRateAutoField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_field(
            'axs4all_ai_exchange_rate_api_key',
            __('FreeCurrencyAPI key', 'axs4all-ai'),
            [$this, 'renderExchangeRateApiKeyField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_field(
            'axs4all_ai_exchange_rate',
            __('USD to DKK rate', 'axs4all-ai'),
            [$this, 'renderExchangeRateField'],
            'axs4all-ai',
            'axs4all_ai_classification_section'
        );

        add_settings_section(
            'axs4all_ai_alerts_section',
            __('Alerts & Monitoring', 'axs4all-ai'),
            static function (): void {
                echo '<p>' . esc_html__('Configure proactive alerts and notification endpoints.', 'axs4all-ai') . '</p>';
            },
            'axs4all-ai'
        );

        add_settings_field(
            'axs4all_ai_alert_email',
            __('Alert Email', 'axs4all-ai'),
            [$this, 'renderAlertEmailField'],
            'axs4all-ai',
            'axs4all_ai_alerts_section'
        );

        add_settings_field(
            'axs4all_ai_alert_slack',
            __('Slack Webhook', 'axs4all-ai'),
            [$this, 'renderAlertSlackField'],
            'axs4all-ai',
            'axs4all_ai_alerts_section'
        );

        add_settings_field(
            'axs4all_ai_alert_queue_threshold',
            __('Queue Threshold', 'axs4all-ai'),
            [$this, 'renderAlertQueueThresholdField'],
            'axs4all-ai',
            'axs4all_ai_alerts_section'
        );

        add_settings_field(
            'axs4all_ai_alert_ticket_webhook',
            __('Ticketing Webhook', 'axs4all-ai'),
            [$this, 'renderAlertTicketField'],
            'axs4all-ai',
            'axs4all_ai_alerts_section'
        );
    }

    public function registerActions(): void
    {
        add_action('admin_post_axs4all_ai_sync_exchange_rate', [$this, 'handleExchangeRateSync']);
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

        $temperature = isset($input['temperature']) ? (float) $input['temperature'] : ($existing['temperature'] ?? 0.0);
        $output['temperature'] = max(0.0, min(2.0, $temperature));

        $promptPrice = isset($input['prompt_price']) ? (float) $input['prompt_price'] : ($existing['prompt_price'] ?? 0.15);
        $output['prompt_price'] = max(0.0, $promptPrice);

        $completionPrice = isset($input['completion_price']) ? (float) $input['completion_price'] : ($existing['completion_price'] ?? 0.60);
        $output['completion_price'] = max(0.0, $completionPrice);

        $timeout = isset($input['timeout']) ? (int) $input['timeout'] : ($existing['timeout'] ?? 30);
        $output['timeout'] = max(5, min(300, $timeout));

        $batchSize = isset($input['batch_size']) ? (int) $input['batch_size'] : ($existing['batch_size'] ?? 5);
        $output['batch_size'] = max(1, min(50, $batchSize));

        $maxAttempts = isset($input['max_attempts']) ? (int) $input['max_attempts'] : ($existing['max_attempts'] ?? 3);
        $output['max_attempts'] = max(1, min(10, $maxAttempts));

        $exchangeRateAuto = ! empty($input['exchange_rate_auto']);
        $output['exchange_rate_auto'] = $exchangeRateAuto ? 1 : 0;

        $manualRate = isset($input['exchange_rate']) ? (float) $input['exchange_rate'] : ($existing['exchange_rate'] ?? 0.0);
        $manualRate = max(0.0, $manualRate);

        if ($exchangeRateAuto) {
            $stored = ExchangeRateUpdater::getStoredRate();
            if (is_array($stored) && isset($stored['rate'])) {
                $manualRate = (float) $stored['rate'];
            }
        } elseif ($manualRate > 0) {
            ExchangeRateUpdater::storeRate($manualRate);
        } else {
            ExchangeRateUpdater::storeRate(0.0);
        }
        $output['exchange_rate'] = $manualRate;

        $apiKeyInput = isset($input['exchange_rate_api_key'])
            ? (string) $input['exchange_rate_api_key']
            : ($existing['exchange_rate_api_key'] ?? '');
        $output['exchange_rate_api_key'] = $this->normalizeExchangeRateAccessKey($apiKeyInput);

        $output['alert_email'] = sanitize_email((string) ($input['alert_email'] ?? ''));
        if ($output['alert_email'] === '') {
            $output['alert_email'] = '';
        }

        $output['alert_slack_webhook'] = esc_url_raw((string) ($input['alert_slack_webhook'] ?? ''));
        $thresholdInput = isset($input['alert_queue_threshold']) ? (int) $input['alert_queue_threshold'] : ($existing['alert_queue_threshold'] ?? 0);
        $output['alert_queue_threshold'] = max(0, $thresholdInput);

        $output['alert_ticket_webhook'] = esc_url_raw((string) ($input['alert_ticket_webhook'] ?? ($existing['alert_ticket_webhook'] ?? '')));

        $output['alert_email_min_severity'] = $this->sanitizeSeverity((string) ($input['alert_email_min_severity'] ?? ($existing['alert_email_min_severity'] ?? 'warning')));
        $output['alert_slack_min_severity'] = $this->sanitizeSeverity((string) ($input['alert_slack_min_severity'] ?? ($existing['alert_slack_min_severity'] ?? 'warning')));
        $output['alert_ticket_min_severity'] = $this->sanitizeSeverity((string) ($input['alert_ticket_min_severity'] ?? ($existing['alert_ticket_min_severity'] ?? 'critical')));

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
            <?php $this->renderExchangeRateNotice(); ?>
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

    public function renderTemperatureField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.0;

        printf(
            '<input type="number" name="%1$s[temperature]" value="%2$s" min="0" max="2" step="0.1" />',
            esc_attr(self::OPTION_NAME),
            esc_attr((string) $temperature)
        );
        echo '<p class="description">' . esc_html__('Controls response randomness. Use 0 for deterministic outputs; higher values increase variation (max 2).', 'axs4all-ai') . '</p>';
    }

    public function renderPromptPriceField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['prompt_price']) ? (float) $options['prompt_price'] : 0.15;

        printf(
            '<input type="number" name="%1$s[prompt_price]" value="%2$s" min="0" step="0.01" />',
            esc_attr(self::OPTION_NAME),
            esc_attr(number_format($value, 4, '.', ''))
        );
        echo '<p class="description">' . esc_html__('Cost per 1 million prompt tokens in USD (default 0.15 for GPT-4o mini).', 'axs4all-ai') . '</p>';
    }

    public function renderCompletionPriceField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['completion_price']) ? (float) $options['completion_price'] : 0.60;

        printf(
            '<input type="number" name="%1$s[completion_price]" value="%2$s" min="0" step="0.01" />',
            esc_attr(self::OPTION_NAME),
            esc_attr(number_format($value, 4, '.', ''))
        );
        echo '<p class="description">' . esc_html__('Cost per 1 million completion tokens in USD (default 0.60 for GPT-4o mini).', 'axs4all-ai') . '</p>';
    }

    public function renderExchangeRateAutoField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $auto = ! empty($options['exchange_rate_auto']);
        $stored = ExchangeRateUpdater::getStoredRate();
        $lastUpdated = $stored['updated_at'] ?? '';
        $lastUpdatedFormatted = '';
        if ($lastUpdated) {
            $timestamp = strtotime($lastUpdated);
            if ($timestamp !== false) {
                $lastUpdatedFormatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
            }
        }

        printf(
            '<label><input type="checkbox" name="%1$s[exchange_rate_auto]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPTION_NAME),
            checked($auto, true, false),
            esc_html__('Automatically fetch USD to DKK rate daily (via FreeCurrencyAPI).', 'axs4all-ai')
        );

        if ($lastUpdatedFormatted !== '') {
            echo '<p class="description">' .
                esc_html(sprintf(__('Last automatic update: %s', 'axs4all-ai'), $lastUpdatedFormatted)) .
                '</p>';
        } else {
            echo '<p class="description">' . esc_html__('Last automatic update: never', 'axs4all-ai') . '</p>';
        }

        $syncUrl = wp_nonce_url(
            admin_url('admin-post.php?action=axs4all_ai_sync_exchange_rate'),
            'axs4all_ai_sync_exchange_rate'
        );
        echo '<p><a class="button" href="' . esc_url($syncUrl) . '">' . esc_html__('Sync now', 'axs4all-ai') . '</a></p>';
    }

    public function renderExchangeRateApiKeyField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['exchange_rate_api_key']) ? (string) $options['exchange_rate_api_key'] : '';

        printf(
            '<input type="text" name="%1$s[exchange_rate_api_key]" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($value)
        );
        echo '<p class="description">' . esc_html__('Required access key from freecurrencyapi.com. Stored locally and used for daily syncs.', 'axs4all-ai') . '</p>';
    }

    public function renderExchangeRateField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['exchange_rate']) ? (float) $options['exchange_rate'] : 0.0;
        $stored = ExchangeRateUpdater::getStoredRate();
        $source = ! empty($options['exchange_rate_auto']) ? __('Automatic', 'axs4all-ai') : __('Manual', 'axs4all-ai');
        $lastUpdated = $stored['updated_at'] ?? '';
        $lastUpdatedFormatted = '';
        if ($lastUpdated) {
            $timestamp = strtotime($lastUpdated);
            if ($timestamp !== false) {
                $lastUpdatedFormatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
            }
        }

        printf(
            '<input type="number" name="%1$s[exchange_rate]" value="%2$s" min="0" step="0.0001" />',
            esc_attr(self::OPTION_NAME),
            esc_attr(number_format($value, 4, '.', ''))
        );
        echo '<p class="description">' . esc_html__('Current USD to DKK conversion rate. Used for cost estimates.', 'axs4all-ai') . '</p>';
        if ($lastUpdatedFormatted !== '') {
            echo '<p class="description">' .
                esc_html(sprintf(__('Rate source: %1$s. Last update: %2$s', 'axs4all-ai'), $source, $lastUpdatedFormatted)) .
                '</p>';
        }
    }

    public function handleExchangeRateSync(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You are not allowed to sync exchange rates.', 'axs4all-ai'));
        }

        check_admin_referer('axs4all_ai_sync_exchange_rate');

        $updater = new ExchangeRateUpdater();
        $success = $updater->updateRate(true);
        $status = $success ? 'success' : 'error';
        $message = $success ? '' : (string) $updater->getLastError();

        $redirectUrl = add_query_arg(
            [
                'page' => 'axs4all-ai',
                'exchange_rate_sync' => $status,
                'exchange_rate_message' => $message !== '' ? rawurlencode($message) : null,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
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

    private function renderExchangeRateNotice(): void
    {
        if (empty($_GET['exchange_rate_sync'])) {
            return;
        }

        $status = sanitize_text_field((string) $_GET['exchange_rate_sync']);
        if ($status === '') {
            return;
        }

        $class = $status === 'success' ? 'notice notice-success' : 'notice notice-error';
        $custom = '';
        if (! empty($_GET['exchange_rate_message'])) {
            $custom = sanitize_text_field(wp_unslash((string) $_GET['exchange_rate_message']));
        }

        $message = $status === 'success'
            ? __('Exchange rate updated successfully.', 'axs4all-ai')
            : ($custom !== '' ? $custom : __('Failed to refresh the exchange rate. Please try again later.', 'axs4all-ai'));

        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
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

    private function normalizeExchangeRateAccessKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // If the user pastes a full URL, extract the query string first.
        $parsed = parse_url($value);
        if (is_array($parsed) && isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            if (isset($params['access_key'])) {
                return sanitize_text_field((string) $params['access_key']);
            }
        }

        // Handle raw query fragments such as "access_key=abc123".
        if (strpos($value, 'access_key=') !== false) {
            parse_str($value, $params);
            if (isset($params['access_key'])) {
                return sanitize_text_field((string) $params['access_key']);
            }
        }

        return sanitize_text_field($value);
    }

    private function sanitizeSeverity(string $value): string
    {
        $value = strtolower(trim($value));
        $allowed = ['info', 'warning', 'critical'];
        return in_array($value, $allowed, true) ? $value : 'warning';
    }

    public function renderAlertEmailField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['alert_email']) ? (string) $options['alert_email'] : '';
        $selected = isset($options['alert_email_min_severity']) ? (string) $options['alert_email_min_severity'] : 'warning';

        printf(
            '<input type="email" name="%1$s[alert_email]" value="%2$s" class="regular-text" placeholder="ops@example.com" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($value)
        );
        echo '<p class="description">' . esc_html__('Email address that should receive critical alerts.', 'axs4all-ai') . '</p>';
        $this->renderSeveritySelect('alert_email_min_severity', $selected, __('Send email when alerts meet or exceed this severity.', 'axs4all-ai'));
    }

    private function renderSeveritySelect(string $field, string $selected, string $description = ''): void
    {
        $options = [
            'info' => __('Info', 'axs4all-ai'),
            'warning' => __('Warning', 'axs4all-ai'),
            'critical' => __('Critical', 'axs4all-ai'),
        ];

        echo '<p><label>' . esc_html__('Minimum severity:', 'axs4all-ai') . ' ';
        echo '<select name="' . esc_attr(self::OPTION_NAME . '[' . $field . ']') . '">';
        foreach ($options as $value => $label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($value),
                selected($selected, $value, false),
                esc_html($label)
            );
        }
        echo '</select></label></p>';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    public function renderAlertSlackField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['alert_slack_webhook']) ? (string) $options['alert_slack_webhook'] : '';
        $selected = isset($options['alert_slack_min_severity']) ? (string) $options['alert_slack_min_severity'] : 'warning';

        printf(
            '<input type="url" name="%1$s[alert_slack_webhook]" value="%2$s" class="regular-text" placeholder="https://hooks.slack.com/..." />',
            esc_attr(self::OPTION_NAME),
            esc_attr($value)
        );
        echo '<p class="description">' . esc_html__('Optional Slack Incoming Webhook URL for alerts.', 'axs4all-ai') . '</p>';
        $this->renderSeveritySelect('alert_slack_min_severity', $selected, __('Send Slack notifications when alerts meet or exceed this severity.', 'axs4all-ai'));
    }

    public function renderAlertQueueThresholdField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['alert_queue_threshold']) ? (int) $options['alert_queue_threshold'] : 0;

        printf(
            '<input type="number" min="0" name="%1$s[alert_queue_threshold]" value="%2$s" class="small-text" />',
            esc_attr(self::OPTION_NAME),
            esc_attr((string) $value)
        );
        echo '<p class="description">' . esc_html__('Trigger alerts when pending jobs meet or exceed this number.', 'axs4all-ai') . '</p>';
    }

    public function renderAlertTicketField(): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = isset($options['alert_ticket_webhook']) ? (string) $options['alert_ticket_webhook'] : '';
        $selected = isset($options['alert_ticket_min_severity']) ? (string) $options['alert_ticket_min_severity'] : 'critical';

        printf(
            '<input type="url" name="%1$s[alert_ticket_webhook]" value="%2$s" class="regular-text" placeholder="https://tickets.example/api" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($value)
        );
        echo '<p class="description">' . esc_html__('Optional webhook that opens tickets or notifies the on-call rota.', 'axs4all-ai') . '</p>';
        $this->renderSeveritySelect('alert_ticket_min_severity', $selected, __('Open tickets/on-call notifications when alerts meet or exceed this severity.', 'axs4all-ai'));
    }
}
