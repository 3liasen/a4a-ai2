<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Infrastructure\ExchangeRateUpdater;

final class BillingPage
{
    private const MENU_SLUG = 'axs4all-ai-billing';

    private ClassificationQueueRepository $repository;

    public function __construct(ClassificationQueueRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Usage & Costs', 'axs4all-ai'),
            __('Usage & Costs', 'axs4all-ai'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('axs4all_ai_settings', []);
        $promptPrice = isset($settings['prompt_price']) ? (float) $settings['prompt_price'] : 0.15;
        $completionPrice = isset($settings['completion_price']) ? (float) $settings['completion_price'] : 0.60;
        $exchangeRate = isset($settings['exchange_rate']) ? (float) $settings['exchange_rate'] : 0.0;
        $exchangeRateAuto = ! empty($settings['exchange_rate_auto']);

        $storedRate = ExchangeRateUpdater::getStoredRate();
        if (($exchangeRate <= 0 || $exchangeRateAuto) && is_array($storedRate) && isset($storedRate['rate'])) {
            $exchangeRate = (float) $storedRate['rate'];
        }
        $exchangeRateUpdatedAt = $storedRate['updated_at'] ?? '';

        $totals = $this->repository->getTokenTotals();
        $promptTokens = $totals['prompt_tokens'];
        $completionTokens = $totals['completion_tokens'];
        $totalTokens = $promptTokens + $completionTokens;

        $promptCostUsd = $this->costUsd($promptTokens, $promptPrice);
        $completionCostUsd = $this->costUsd($completionTokens, $completionPrice);
        $totalCostUsd = $promptCostUsd + $completionCostUsd;
        $promptCostDkk = $this->costDkk($promptCostUsd, $exchangeRate);
        $completionCostDkk = $this->costDkk($completionCostUsd, $exchangeRate);
        $totalCostDkk = $this->costDkk($totalCostUsd, $exchangeRate);

        $dailyData = $this->buildChartData($this->repository->getTokenTimeline([], 'day', 14), 'day');
        $monthlyData = $this->buildChartData($this->repository->getTokenTimeline([], 'month', 12), 'month');

        $pricingJson = wp_json_encode([
            'promptPrice' => $promptPrice,
            'completionPrice' => $completionPrice,
            'exchangeRate' => $exchangeRate,
        ]);
        $dailyJson = wp_json_encode($dailyData);
        $monthlyJson = wp_json_encode($monthlyData);

        $exchangeRateInfo = $this->formatExchangeRateInfo($exchangeRate, $exchangeRateAuto, $exchangeRateUpdatedAt);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Usage & Estimated Cost', 'axs4all-ai'); ?></h1>
            <p><?php esc_html_e('Totals include all completed classification jobs. Pricing reflects your current GPT-4o mini settings.', 'axs4all-ai'); ?></p>

            <h2><?php esc_html_e('Token usage', 'axs4all-ai'); ?></h2>
            <table class="widefat striped" style="max-width:640px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Metric', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Tokens', 'axs4all-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Prompt tokens used', 'axs4all-ai'); ?></td>
                        <td><?php echo esc_html(number_format($promptTokens)); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Completion tokens used', 'axs4all-ai'); ?></td>
                        <td><?php echo esc_html(number_format($completionTokens)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total tokens', 'axs4all-ai'); ?></th>
                        <th><?php echo esc_html(number_format($totalTokens)); ?></th>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:2rem;"><?php esc_html_e('Estimated costs', 'axs4all-ai'); ?></h2>
            <table class="widefat striped" style="max-width:640px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Metric', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('USD', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('DKK', 'axs4all-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Prompt cost', 'axs4all-ai'); ?></td>
                        <td><?php echo esc_html($this->formatMoney($promptCostUsd, '$')); ?></td>
                        <td><?php echo esc_html($this->formatMoneyOrDash($promptCostDkk, 'kr. ', 4)); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Completion cost', 'axs4all-ai'); ?></td>
                        <td><?php echo esc_html($this->formatMoney($completionCostUsd, '$')); ?></td>
                        <td><?php echo esc_html($this->formatMoneyOrDash($completionCostDkk, 'kr. ', 4)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total estimated cost', 'axs4all-ai'); ?></th>
                        <th><?php echo esc_html($this->formatMoney($totalCostUsd, '$')); ?></th>
                        <th><?php echo esc_html($this->formatMoneyOrDash($totalCostDkk, 'kr. ', 4)); ?></th>
                    </tr>
                </tbody>
            </table>

            <p class="description" style="margin-top:0.5rem;">
                <?php
                printf(
                    /* translators: 1: prompt price, 2: completion price */
                    esc_html__('Pricing: $%1$.2f per 1M prompt tokens, $%2$.2f per 1M completion tokens.', 'axs4all-ai'),
                    $promptPrice,
                    $completionPrice
                );
                ?>
                <br>
                <?php echo esc_html($exchangeRateInfo); ?>
            </p>

            <h2 style="margin-top:2.5rem;"><?php esc_html_e('Daily usage (last 14 days)', 'axs4all-ai'); ?></h2>
            <?php if (! empty($dailyData['labels'])) : ?>
                <div style="max-width:960px;">
                    <canvas id="axs4all-ai-daily-tokens" height="220"></canvas>
                    <canvas id="axs4all-ai-daily-costs" height="220" style="margin-top:1.5rem;"></canvas>
                </div>
            <?php else : ?>
                <p><?php esc_html_e('No daily data yet. Run a few classifications to populate this section.', 'axs4all-ai'); ?></p>
            <?php endif; ?>

            <h2 style="margin-top:2.5rem;"><?php esc_html_e('Monthly usage (last 12 months)', 'axs4all-ai'); ?></h2>
            <?php if (! empty($monthlyData['labels'])) : ?>
                <div style="max-width:960px;">
                    <canvas id="axs4all-ai-monthly-tokens" height="220"></canvas>
                    <canvas id="axs4all-ai-monthly-costs" height="220" style="margin-top:1.5rem;"></canvas>
                </div>
            <?php else : ?>
                <p><?php esc_html_e('No monthly data yet.', 'axs4all-ai'); ?></p>
            <?php endif; ?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-vP4RcVYkxFxM5zuvUl3tme5OzxCAdGsi/jN/NQu5LH8J10NQrThbq0g6U4fW0z2x" crossorigin="anonymous"></script>
        <script>
        (function () {
            const pricing = <?php echo $pricingJson; ?>;
            const daily = <?php echo $dailyJson; ?>;
            const monthly = <?php echo $monthlyJson; ?>;

            if (typeof Chart === 'undefined') {
                return;
            }

            function tokenChartConfig(labels, promptTokens, completionTokens) {
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: '<?php echo esc_js(__('Prompt tokens', 'axs4all-ai')); ?>',
                                data: promptTokens,
                                backgroundColor: '#3182ce'
                            },
                            {
                                label: '<?php echo esc_js(__('Completion tokens', 'axs4all-ai')); ?>',
                                data: completionTokens,
                                backgroundColor: '#48bb78'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                ticks: {
                                    callback: (value) => value.toLocaleString()
                                }
                            }
                        }
                    }
                };
            }

            function costChartConfig(labels, promptTokens, completionTokens) {
                const promptUsd = promptTokens.map((t) => (t / 1e6) * pricing.promptPrice);
                const completionUsd = completionTokens.map((t) => (t / 1e6) * pricing.completionPrice);
                const totalUsd = promptUsd.map((value, index) => value + completionUsd[index]);

                const datasets = [
                    {
                        label: '<?php echo esc_js(__('Total cost (USD)', 'axs4all-ai')); ?>',
                        data: totalUsd,
                        borderColor: '#2b6cb0',
                        backgroundColor: 'rgba(43, 108, 176, 0.2)',
                        tension: 0.3
                    }
                ];

                if (pricing.exchangeRate && pricing.exchangeRate > 0) {
                    const totalDkk = totalUsd.map((value) => value * pricing.exchangeRate);
                    datasets.push({
                        label: '<?php echo esc_js(__('Total cost (DKK)', 'axs4all-ai')); ?>',
                        data: totalDkk,
                        borderColor: '#dd6b20',
                        backgroundColor: 'rgba(221, 107, 32, 0.2)',
                        tension: 0.3
                    });
                }

                return {
                    type: 'line',
                    data: {
                        labels,
                        datasets
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                ticks: {
                                    callback: (value) => Number(value).toFixed(2)
                                }
                            }
                        }
                    }
                };
            }

            function renderCharts(prefix, data) {
                if (!data.labels.length) {
                    return;
                }

                const tokensCanvas = document.getElementById(prefix + '-tokens');
                const costsCanvas = document.getElementById(prefix + '-costs');
                if (tokensCanvas) {
                    new Chart(tokensCanvas, tokenChartConfig(data.labels, data.prompt_tokens, data.completion_tokens));
                }
                if (costsCanvas) {
                    new Chart(costsCanvas, costChartConfig(data.labels, data.prompt_tokens, data.completion_tokens));
                }
            }

            renderCharts('axs4all-ai-daily', daily);
            renderCharts('axs4all-ai-monthly', monthly);
        })();
        </script>
        <?php
    }

    private function costUsd(int $tokens, float $ratePerMillion): float
    {
        if ($tokens <= 0 || $ratePerMillion <= 0) {
            return 0.0;
        }

        return ($tokens / 1_000_000) * $ratePerMillion;
    }

    private function costDkk(float $usdCost, float $exchangeRate): ?float
    {
        if ($exchangeRate <= 0) {
            return null;
        }

        return $usdCost * $exchangeRate;
    }

    private function formatMoney(float $value, string $symbol, int $decimals = 2): string
    {
        return $symbol . number_format($value, $decimals);
    }

    private function formatMoneyOrDash(?float $value, string $symbol, int $decimals = 2): string
    {
        return $value !== null ? $symbol . number_format($value, $decimals) : '--';
    }

    /**
     * @param array<int, array{label:string,prompt_tokens:int,completion_tokens:int}> $rows
     * @return array{labels:array<int,string>,prompt_tokens:array<int,int>,completion_tokens:array<int,int>}
     */
    private function buildChartData(array $rows, string $granularity): array
    {
        $labels = [];
        $prompt = [];
        $completion = [];

        foreach ($rows as $row) {
            $labels[] = $this->formatLabel($row['label'], $granularity);
            $prompt[] = (int) $row['prompt_tokens'];
            $completion[] = (int) $row['completion_tokens'];
        }

        return [
            'labels' => $labels,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
        ];
    }

    private function formatLabel(string $value, string $granularity): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        if ($granularity === 'month') {
            return date_i18n('M Y', $timestamp);
        }

        return date_i18n('M j', $timestamp);
    }

    private function formatExchangeRateInfo(float $exchangeRate, bool $auto, ?string $updatedAt): string
    {
        if ($exchangeRate <= 0) {
            return __('Exchange rate not set. DKK amounts are hidden.', 'axs4all-ai');
        }

        $timestamp = $updatedAt ? strtotime($updatedAt) : false;
        $formatted = $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : __('unknown', 'axs4all-ai');

        if ($auto) {
            return sprintf(
                /* translators: 1: exchange rate, 2: last update */
                __('USD to DKK rate %.4f (auto-updated, last refresh %s).', 'axs4all-ai'),
                $exchangeRate,
                $formatted
            );
        }

        return sprintf(
            /* translators: 1: exchange rate, 2: last update */
            __('USD to DKK rate %.4f (manual, last update %s).', 'axs4all-ai'),
            $exchangeRate,
            $formatted
        );
    }
}
