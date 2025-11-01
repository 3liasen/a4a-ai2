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

        $hourlyRangeKey = isset($_GET['hourly_range']) ? sanitize_key((string) $_GET['hourly_range']) : 'today';
        $rangeOptions = $this->getHourlyRangeOptions();
        if (! isset($rangeOptions[$hourlyRangeKey])) {
            $hourlyRangeKey = 'today';
        }
        $selectedRange = $rangeOptions[$hourlyRangeKey];
        $hourlyFilters = [];
        if (! empty($selectedRange['start'])) {
            $hourlyFilters['created_start'] = $selectedRange['start'];
        }
        if (! empty($selectedRange['end'])) {
            $hourlyFilters['created_end'] = $selectedRange['end'];
        }
        $hourlyRows = $this->repository->getTokenTimeline($hourlyFilters, 'hour', (int) $selectedRange['limit']);
        $hourlyData = $this->buildHourlyPlotData($hourlyRows);
        $hourlyJson = wp_json_encode($hourlyData);
        if ($hourlyJson === false) {
            $hourlyJson = wp_json_encode([
                'timestamps' => [],
                'prompt_tokens' => [],
                'completion_tokens' => [],
                'labels' => [],
            ]);
        }
        $hourlyDescription = $selectedRange['description'];

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

            <h2 style="margin-top:2.5rem;"><?php esc_html_e('Hourly usage', 'axs4all-ai'); ?></h2>
            <form method="get" class="axs4all-ai-hourly-form">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
                <label for="axs4all-ai-hourly-range"><?php esc_html_e('Quick range', 'axs4all-ai'); ?></label>
                <select id="axs4all-ai-hourly-range" name="hourly_range" onchange="this.form.submit()">
                    <?php foreach ($rangeOptions as $key => $option) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $hourlyRangeKey); ?>>
                            <?php echo esc_html($option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <p class="description" id="axs4all-ai-hourly-description">
                <?php echo esc_html($hourlyDescription); ?>
            </p>

            <div class="card axs4all-ai-hourly-card">
                <div class="card-header">
                    <h3 class="card-title"><?php esc_html_e('Tokens per hour', 'axs4all-ai'); ?></h3>
                </div>
                <div class="card-body">
                    <div id="axs4all-ai-hourly-chart" class="axs4all-ai-hourly-chart"></div>
                </div>
            </div>

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

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uplot@1.6.24/dist/uPlot.min.css">
        <script src="https://cdn.jsdelivr.net/npm/uplot@1.6.24/dist/uPlot.iife.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-vP4RcVYkxFxM5zuvUl3tme5OzxCAdGsi/jN/NQu5LH8J10NQrThbq0g6U4fW0z2x" crossorigin="anonymous"></script>
        <script>
        (function () {
            const pricing = <?php echo $pricingJson; ?>;
            const daily = <?php echo $dailyJson; ?>;
            const monthly = <?php echo $monthlyJson; ?>;
            const hourly = <?php echo $hourlyJson; ?>;
            const siteTimezone = <?php echo wp_json_encode(wp_timezone_string() ?: 'UTC'); ?>;
            const hourlyStrings = {
                empty: '<?php echo esc_js(__('No usage recorded for this range yet.', 'axs4all-ai')); ?>',
                missingLib: '<?php echo esc_js(__('Unable to load the hourly chart (uPlot missing).', 'axs4all-ai')); ?>'
            };

            function renderHourly(data) {
                const container = document.getElementById('axs4all-ai-hourly-chart');
                if (!container) {
                    return;
                }

                container.innerHTML = '';

                if (typeof uPlot === 'undefined') {
                    container.innerHTML = '<p class="description">' + hourlyStrings.missingLib + '</p>';
                    return;
                }

                if (!data || !Array.isArray(data.timestamps) || data.timestamps.length === 0) {
                    container.innerHTML = '<p class="description">' + hourlyStrings.empty + '</p>';
                    return;
                }

                const initialWidth = container.clientWidth || container.offsetWidth || 600;

                const opts = {
                    width: initialWidth,
                    height: 320,
                    class: 'uplot axs4all-uplot',
                    scales: {
                        x: { time: true },
                        y: { auto: true },
                    },
                    axes: [
                        {
                            stroke: '#6c757d',
                            grid: { stroke: '#e5e7eb' },
                            values: (u, ticks) => ticks.map((value) => uPlot.fmtDate(new Date(value * 1000), '%H:%M')),
                        },
                        {
                            stroke: '#6c757d',
                            grid: { stroke: '#e5e7eb' },
                            values: (u, ticks) => ticks.map((value) => Number(value).toLocaleString()),
                        },
                    ],
                    series: [
                        {},
                        {
                            label: '<?php echo esc_js(__('Prompt tokens', 'axs4all-ai')); ?>',
                            stroke: '#3c8dbc',
                            fill: 'rgba(60, 141, 188, 0.18)',
                            width: 2,
                        },
                        {
                            label: '<?php echo esc_js(__('Completion tokens', 'axs4all-ai')); ?>',
                            stroke: '#48bb78',
                            fill: 'rgba(72, 187, 120, 0.18)',
                            width: 2,
                        },
                    ],
                };

                if (siteTimezone) {
                    opts.tzDate = uPlot.tzDate(siteTimezone);
                }

                const chart = new uPlot(opts, [data.timestamps, data.prompt_tokens, data.completion_tokens], container);

                window.addEventListener('resize', function () {
                    const newWidth = container.clientWidth || container.offsetWidth || initialWidth;
                    chart.setSize({ width: newWidth, height: 320 });
                });
            }

            renderHourly(hourly);

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

        if ($granularity === 'hour') {
            return date_i18n('M j, H:i', $timestamp);
        }

        return date_i18n('M j', $timestamp);
    }

    /**
     * @param array<int, array{label:string,prompt_tokens:int,completion_tokens:int}> $rows
     * @return array{timestamps:array<int,int>,prompt_tokens:array<int,int>,completion_tokens:array<int,int>,labels:array<int,string>}
     */
    private function buildHourlyPlotData(array $rows): array
    {
        $timestamps = [];
        $prompt = [];
        $completion = [];
        $labels = [];

        foreach ($rows as $row) {
            $timestamp = strtotime($row['label']);
            if ($timestamp === false) {
                continue;
            }

            $timestamps[] = $timestamp;
            $prompt[] = (int) $row['prompt_tokens'];
            $completion[] = (int) $row['completion_tokens'];
            $labels[] = date_i18n('H:i', $timestamp);
        }

        return [
            'timestamps' => $timestamps,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, array{label:string,description:string,start:string,end:string,limit:int}>
     */
    private function getHourlyRangeOptions(): array
    {
        $timezone = wp_timezone();
        $now = new \DateTimeImmutable('now', $timezone);

        $todayStart = $now->setTime(0, 0, 0);
        $yesterdayStart = $todayStart->sub(new \DateInterval('P1D'));
        $yesterdayEnd = $yesterdayStart->setTime(23, 59, 59);
        $last24Start = $now->sub(new \DateInterval('PT24H'));
        $last48Start = $now->sub(new \DateInterval('PT48H'));

        return [
            'today' => [
                'label' => sprintf(__('Today (%s)', 'axs4all-ai'), date_i18n('M j', $now->getTimestamp())),
                'description' => $this->describeRange($todayStart, $now),
                'start' => $this->formatMysql($todayStart),
                'end' => $this->formatMysql($now),
                'limit' => 36,
            ],
            'yesterday' => [
                'label' => sprintf(__('Yesterday (%s)', 'axs4all-ai'), date_i18n('M j', $yesterdayStart->getTimestamp())),
                'description' => $this->describeRange($yesterdayStart, $yesterdayEnd),
                'start' => $this->formatMysql($yesterdayStart),
                'end' => $this->formatMysql($yesterdayEnd),
                'limit' => 36,
            ],
            'last_24_hours' => [
                'label' => __('Last 24 hours', 'axs4all-ai'),
                'description' => $this->describeRange($last24Start, $now),
                'start' => $this->formatMysql($last24Start),
                'end' => $this->formatMysql($now),
                'limit' => 48,
            ],
            'last_48_hours' => [
                'label' => __('Last 48 hours', 'axs4all-ai'),
                'description' => $this->describeRange($last48Start, $now),
                'start' => $this->formatMysql($last48Start),
                'end' => $this->formatMysql($now),
                'limit' => 96,
            ],
        ];
    }

    private function formatMysql(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    private function describeRange(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        return sprintf(
            /* translators: 1: start datetime, 2: end datetime */
            __('Range: %1$s - %2$s', 'axs4all-ai'),
            date_i18n('M j H:i', $start->getTimestamp()),
            date_i18n('M j H:i', $end->getTimestamp())
        );
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
