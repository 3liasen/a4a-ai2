<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Data\QueueRepository;

final class QueuePage
{
    private QueueRepository $repository;

    public function __construct(QueueRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Crawl Queue', 'axs4all-ai'),
            __('Crawl Queue', 'axs4all-ai'),
            'manage_options',
            'axs4all-ai-queue',
            [$this, 'render']
        );
    }

    public function registerActions(): void
    {
        add_action('admin_post_axs4all_ai_add_queue', [$this, 'handleAddRequest']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $message = isset($_GET['message']) ? sanitize_text_field((string) $_GET['message']) : null;
        $recent = $this->repository->getRecent();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Crawl Queue', 'axs4all-ai'); ?></h1>

            <?php $this->renderNotice($message); ?>

            <h2><?php esc_html_e('Add URL to Queue', 'axs4all-ai'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('axs4all_ai_add_queue'); ?>
                <input type="hidden" name="action" value="axs4all_ai_add_queue" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="axs4all-ai-queue-url"><?php esc_html_e('URL', 'axs4all-ai'); ?></label>
                        </th>
                        <td>
                            <input type="url" class="regular-text" name="queue_url" id="axs4all-ai-queue-url" required placeholder="https://example.com">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="axs4all-ai-queue-category"><?php esc_html_e('Category', 'axs4all-ai'); ?></label>
                        </th>
                        <td>
                            <input type="text" class="regular-text" name="queue_category" id="axs4all-ai-queue-category" placeholder="<?php esc_attr_e('restaurant', 'axs4all-ai'); ?>">
                            <p class="description"><?php esc_html_e('Category maps to extraction selectors and AI prompts.', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="axs4all-ai-queue-priority"><?php esc_html_e('Priority', 'axs4all-ai'); ?></label>
                        </th>
                        <td>
                            <input type="number" min="1" max="9" step="1" name="queue_priority" id="axs4all-ai-queue-priority" value="5">
                            <p class="description"><?php esc_html_e('Lower numbers run earlier.', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Queue URL', 'axs4all-ai')); ?>
            </form>

            <h2><?php esc_html_e('Recent Queue Items', 'axs4all-ai'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('URL', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Category', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Status', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Priority', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Attempts', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Created', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Updated', 'axs4all-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recent)) : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No queue items yet.', 'axs4all-ai'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recent as $item) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url($item['source_url']); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($item['source_url']); ?></a></td>
                            <td><?php echo esc_html($item['category']); ?></td>
                            <td><?php echo esc_html($item['status']); ?></td>
                            <td><?php echo esc_html((string) $item['priority']); ?></td>
                            <td><?php echo esc_html((string) $item['attempts']); ?></td>
                            <td><?php echo esc_html($item['created_at']); ?></td>
                            <td><?php echo esc_html($item['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handleAddRequest(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You are not allowed to manage the crawl queue.', 'axs4all-ai'));
        }

        check_admin_referer('axs4all_ai_add_queue');

        $url = isset($_POST['queue_url']) ? (string) wp_unslash($_POST['queue_url']) : '';
        $category = isset($_POST['queue_category']) ? (string) wp_unslash($_POST['queue_category']) : '';
        $priority = isset($_POST['queue_priority']) ? (int) $_POST['queue_priority'] : 5;

        $success = $this->repository->enqueue($url, $category, $priority);

        $redirectUrl = add_query_arg(
            [
                'page' => 'axs4all-ai-queue',
                'message' => $success ? 'added' : 'error',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    private function renderNotice(?string $message): void
    {
        if ($message === 'added') {
            echo '<div class="notice notice-success"><p>' . esc_html__('URL queued successfully.', 'axs4all-ai') . '</p></div>';
        } elseif ($message === 'error') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Unable to queue URL. Please verify the address and try again.', 'axs4all-ai') . '</p></div>';
        }
    }
}
