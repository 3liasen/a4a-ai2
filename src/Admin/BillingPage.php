<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Classification\ClassificationQueueRepository;

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

        $totals = $this->repository->getTokenTotals();
        $promptTokens = $totals['prompt_tokens'];
        $completionTokens = $totals['completion_tokens'];
        $totalTokens = $promptTokens + $completionTokens;

        $promptCost = $this->costUsd($promptTokens, 0.15);
        $completionCost = $this->costUsd($completionTokens, 0.60);
        $totalCost = $promptCost + $completionCost;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Usage & Estimated Cost', 'axs4all-ai'); ?></h1>
            <p><?php esc_html_e('Totals include all completed classification jobs. Costs use OpenAI GPT-4o mini list prices.', 'axs4all-ai'); ?></p>

            <table class="widefat striped" style="max-width:640px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Metric', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Value', 'axs4all-ai'); ?></th>
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
                        <td><?php esc_html_e('Total tokens', 'axs4all-ai'); ?></td>
                        <td><?php echo esc_html(number_format($totalTokens)); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Prompt cost (USD)', 'axs4all-ai'); ?></td>
                        <td>$<?php echo esc_html(number_format($promptCost, 4)); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Completion cost (USD)', 'axs4all-ai'); ?></td>
                        <td>$<?php echo esc_html(number_format($completionCost, 4)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total estimated cost (USD)', 'axs4all-ai'); ?></th>
                        <th>$<?php echo esc_html(number_format($totalCost, 4)); ?></th>
                    </tr>
                </tbody>
            </table>

            <p class="description">
                <?php esc_html_e('Estimates use $0.15 per 1M prompt tokens and $0.60 per 1M completion tokens (OpenAI GPT-4o mini, October 2025).', 'axs4all-ai'); ?>
            </p>
        </div>
        <?php
    }

    private function costUsd(int $tokens, float $ratePerMillion): float
    {
        if ($tokens <= 0) {
            return 0.0;
        }

        return ($tokens / 1_000_000) * $ratePerMillion;
    }
}
