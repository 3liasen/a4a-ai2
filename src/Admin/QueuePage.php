<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;

final class QueuePage
{
    private QueueRepository $repository;
    private ClientRepository $clients;
    private CategoryRepository $categories;

    public function __construct(QueueRepository $repository, ClientRepository $clients, CategoryRepository $categories)
    {
        $this->repository = $repository;
        $this->clients = $clients;
        $this->categories = $categories;
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
        add_action('admin_post_axs4all_ai_delete_queue', [$this, 'handleDeleteRequest']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $message = isset($_GET['message']) ? sanitize_text_field((string) $_GET['message']) : null;
        $recent = $this->repository->getRecent();
        $clientUrls = $this->gatherClientUrlRows();

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
                        <th><?php esc_html_e('Actions', 'axs4all-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recent)) : ?>
                    <tr>
                        <td colspan="8"><?php esc_html_e('No queue items yet.', 'axs4all-ai'); ?></td>
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
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this queue item?', 'axs4all-ai')); ?>');">
                                    <?php wp_nonce_field('axs4all_ai_delete_queue_' . (int) $item['id']); ?>
                                    <input type="hidden" name="action" value="axs4all_ai_delete_queue">
                                    <input type="hidden" name="queue_id" value="<?php echo esc_attr((string) $item['id']); ?>">
                                    <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete', 'axs4all-ai'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Client URLs', 'axs4all-ai'); ?></h2>
            <p><?php esc_html_e('Use the shortcuts below to queue URLs that belong to configured clients.', 'axs4all-ai'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Client', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('URL', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Categories', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Queue', 'axs4all-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($clientUrls)) : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('No client URLs found.', 'axs4all-ai'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($clientUrls as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row['client']); ?></td>
                            <td><a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($row['url']); ?></a></td>
                            <td>
                                <?php if (empty($row['categories'])) : ?>
                                    <em><?php esc_html_e('No categories selected', 'axs4all-ai'); ?></em>
                                <?php else : ?>
                                    <?php echo esc_html(implode(', ', $row['categories'])); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('axs4all_ai_add_queue'); ?>
                                    <input type="hidden" name="action" value="axs4all_ai_add_queue">
                                    <input type="hidden" name="queue_url" value="<?php echo esc_attr($row['url']); ?>">
                                    <input type="hidden" name="queue_priority" value="5">
                                    <?php if (count($row['category_values']) > 1) : ?>
                                        <select name="queue_category" style="max-width: 180px;">
                                            <?php foreach ($row['category_options'] as $value => $label) : ?>
                                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else : ?>
                                        <input type="hidden" name="queue_category" value="<?php echo esc_attr($row['category_values'][0] ?? ''); ?>">
                                        <?php if (! empty($row['categories'])) : ?>
                                            <span><?php echo esc_html(implode(', ', $row['categories'])); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button type="submit" class="button"><?php esc_html_e('Queue URL', 'axs4all-ai'); ?></button>
                                </form>
                            </td>
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

    public function handleDeleteRequest(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You are not allowed to manage the crawl queue.', 'axs4all-ai'));
        }

        $id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
        check_admin_referer('axs4all_ai_delete_queue_' . $id);

        $success = $id > 0 ? $this->repository->delete($id) : false;

        $redirectUrl = add_query_arg(
            [
                'page' => 'axs4all-ai-queue',
                'message' => $success ? 'deleted' : 'delete_error',
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
        } elseif ($message === 'deleted') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Queue item deleted.', 'axs4all-ai') . '</p></div>';
        } elseif ($message === 'delete_error') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Unable to delete queue item. Please try again.', 'axs4all-ai') . '</p></div>';
        }
    }

    /**
     * @return array<int, array{
     *     client:string,
     *     url:string,
     *     categories:array<int,string>,
     *     category_values:array<int,string>,
     *     category_options:array<string,string>
     * }>
     */
    private function gatherClientUrlRows(): array
    {
        $rows = [];
        $categoryMap = $this->buildCategoryMap();

        foreach ($this->clients->all() as $summary) {
            $client = $this->clients->find($summary['id']);
            if ($client === null || empty($client['urls'])) {
                continue;
            }

            $categoryLabels = [];
            $categoryValues = [];
            foreach ($client['categories'] ?? [] as $categoryId) {
                if (isset($categoryMap[$categoryId])) {
                    $categoryLabels[] = $categoryMap[$categoryId]['name'];
                    $categoryValues[] = $categoryMap[$categoryId]['value'];
                }
            }

            foreach ($client['urls'] as $urlRow) {
                $rows[] = [
                    'client' => $client['name'],
                    'url' => $urlRow['url'],
                    'categories' => $categoryLabels,
                    'category_options' => ! empty($categoryLabels) ? array_combine($categoryValues, $categoryLabels) : [],
                    'category_values' => $categoryValues,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{name:string,value:string}>
     */
    private function buildCategoryMap(): array
    {
        $all = $this->categories->all();
        $map = [];
        foreach ($all as $category) {
            $name = (string) $category['name'];
            $value = sanitize_title($name);
            if ($value === '') {
                $value = sanitize_title_with_dashes($name);
            }
            $map[(int) $category['id']] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        return $map;
    }
}

