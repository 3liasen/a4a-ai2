<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Classification\ClassificationQueueRepository;

final class ClassificationResultsPage
{
    private const MENU_SLUG = 'axs4all-ai-classifications';

    private ClassificationQueueRepository $repository;

    public function __construct(ClassificationQueueRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Classifications', 'axs4all-ai'),
            __('Classifications', 'axs4all-ai'),
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

        $results = $this->repository->getRecentResults();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Classification Results', 'axs4all-ai'); ?></h1>
            <p><?php esc_html_e('Most recent AI classification decisions. Use CLI or cron automation to process pending jobs.', 'axs4all-ai'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Decision', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Queue ID', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Prompt Version', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Model', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Tokens (P/C)', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Duration (ms)', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Created', 'axs4all-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($results)) : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No classifications recorded yet.', 'axs4all-ai'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($results as $result) : ?>
                        <tr>
                            <td><?php echo esc_html(strtoupper((string) $result['decision'])); ?></td>
                            <td><?php echo esc_html((string) $result['queue_id']); ?></td>
                            <td><?php echo esc_html((string) $result['prompt_version']); ?></td>
                            <td><?php echo esc_html((string) ($result['model'] ?? '—')); ?></td>
                            <td><?php echo esc_html(sprintf('%s / %s', $result['tokens_prompt'] ?? '—', $result['tokens_completion'] ?? '—')); ?></td>
                            <td><?php echo esc_html((string) ($result['duration_ms'] ?? '—')); ?></td>
                            <td><?php echo esc_html((string) $result['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
