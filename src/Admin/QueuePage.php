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
        $clientOptions = $this->getClientOptions();
        $categoryOptions = $this->buildCategoryMap();

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
                            <label for="axs4all-ai-queue-client"><?php esc_html_e('Client', 'axs4all-ai'); ?></label>
                        </th>
                        <td>
                            <select name="queue_client_id" id="axs4all-ai-queue-client">
                                <option value="0"><?php esc_html_e('None (manual URL)', 'axs4all-ai'); ?></option>
                                <?php foreach ($clientOptions as $clientId => $clientName) : ?>
                                    <option value="<?php echo esc_attr((string) $clientId); ?>"><?php echo esc_html($clientName); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Associate the queue item with a configured client so downstream jobs know which URLs belong together.', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="axs4all-ai-queue-category"><?php esc_html_e('Category', 'axs4all-ai'); ?></label>
                        </th>
                        <td>
                            <select name="queue_category_payload" id="axs4all-ai-queue-category">
                                <option value="0"><?php esc_html_e('Use manual slug / default', 'axs4all-ai'); ?></option>
                                <?php foreach ($categoryOptions as $categoryId => $categoryMeta) : ?>
                                    <?php
                                    $slug = $categoryMeta['value'];
                                    $label = sprintf('%s (%s)', $categoryMeta['name'], $slug);
                                    ?>
                                    <option value="<?php echo esc_attr($categoryId . ':' . $slug); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Choose a category to attach to the crawl item. Leave on manual to fall back to the slug field or the default prompts.', 'axs4all-ai'); ?></p>
                            <label for="axs4all-ai-queue-category-slug" style="display:block; margin-top:0.5rem;">
                                <?php esc_html_e('Manual category slug (optional)', 'axs4all-ai'); ?>
                            </label>
                            <input type="text" class="regular-text" name="queue_category" id="axs4all-ai-queue-category-slug" placeholder="<?php esc_attr_e('restaurant', 'axs4all-ai'); ?>">
                            <p class="description"><?php esc_html_e('Use this if you need a category that is not yet configured above. Leave empty to fall back to “default”.', 'axs4all-ai'); ?></p>
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
                        <th><?php esc_html_e('Client', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Category', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Subpages', 'axs4all-ai'); ?></th>
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
                        <td colspan="10"><?php esc_html_e('No queue items yet.', 'axs4all-ai'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recent as $item) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url($item['source_url']); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($item['source_url']); ?></a></td>
                            <td><?php echo esc_html($clientOptions[$item['client_id']] ?? '—'); ?></td>
                            <td><?php echo esc_html($this->formatCategoryLabel($item, $categoryOptions)); ?></td>
                            <td><?php echo ! empty($item['crawl_subpages']) ? esc_html__('Yes', 'axs4all-ai') : esc_html__('No', 'axs4all-ai'); ?></td>
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
                                    <input type="hidden" name="queue_client_id" value="<?php echo esc_attr((string) $row['client_id']); ?>">
                                    <?php if (count($row['category_choices']) > 1) : ?>
                                        <select name="queue_category_payload" style="max-width: 220px;">
                                            <?php foreach ($row['category_choices'] as $choice) : ?>
                                                <?php $payload = $choice['id'] . ':' . $choice['slug']; ?>
                                                <option value="<?php echo esc_attr($payload); ?>"><?php echo esc_html($choice['name'] . ' (' . $choice['slug'] . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else : ?>
                                        <?php
                                        $firstChoice = $row['category_choices'][0] ?? null;
                                        if ($firstChoice !== null) :
                                            $payload = $firstChoice['id'] . ':' . $firstChoice['slug'];
                                        ?>
                                            <input type="hidden" name="queue_category_payload" value="<?php echo esc_attr($payload); ?>">
                                            <span><?php echo esc_html($firstChoice['name']); ?></span>
                                        <?php else : ?>
                                            <span><?php esc_html_e('No categories selected', 'axs4all-ai'); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (! empty($row['crawl_subpages'])) : ?>
                                        <input type="hidden" name="queue_crawl_subpages" value="1">
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
        $clientId = isset($_POST['queue_client_id']) ? (int) $_POST['queue_client_id'] : 0;
        $payloadRaw = isset($_POST['queue_category_payload']) ? (string) wp_unslash($_POST['queue_category_payload']) : '';
        $manualSlugInput = isset($_POST['queue_category']) ? (string) wp_unslash($_POST['queue_category']) : '';
        [$payloadCategoryId, $payloadSlug] = $this->parseCategoryPayload($payloadRaw);
        $manualSlug = $this->sanitizeCategorySlug($manualSlugInput);

        $categoryOptions = $this->buildCategoryMap();
        $categoryId = $payloadCategoryId;
        $categorySlug = $payloadSlug;

        if ($categoryId > 0 && $categorySlug === '' && isset($categoryOptions[$categoryId])) {
            $categorySlug = $categoryOptions[$categoryId]['value'];
        }

        if ($categorySlug === '') {
            $categorySlug = $manualSlug !== '' ? $manualSlug : 'default';
        }

        if ($categoryId === 0 && $categorySlug !== '' && $categorySlug !== 'default') {
            $categoryId = $this->resolveCategoryIdFromSlug($categorySlug, $categoryOptions);
        }

        $priority = max(1, min(9, $priority));
        $priority = isset($_POST['queue_priority']) ? (int) $_POST['queue_priority'] : 5;

        $success = $this->repository->enqueue(
            $url,
            $categorySlug,
            $priority,
            false,
            $clientId > 0 ? $clientId : null,
            $categoryId > 0 ? $categoryId : null
        );

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

            $categoryChoices = [];
            foreach ($client['categories'] ?? [] as $categoryId) {
                if (! isset($categoryMap[$categoryId])) {
                    continue;
                }

                $categoryChoices[] = [
                    'id' => (int) $categoryId,
                    'name' => $categoryMap[$categoryId]['name'],
                    'slug' => $categoryMap[$categoryId]['value'],
                ];
            }

            foreach ($client['urls'] as $urlRow) {
                $rows[] = [
                    'client' => $client['name'],
                    'client_id' => (int) $client['id'],
                    'url' => $urlRow['url'],
                    'crawl_subpages' => ! empty($urlRow['crawl_subpages']),
                    'categories' => array_map(
                        static fn(array $choice): string => $choice['name'],
                        $categoryChoices
                    ),
                    'category_choices' => $categoryChoices,
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
            $value = $this->generateSlugFromName($name);
            $map[(int) $category['id']] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function getClientOptions(): array
    {
        $options = [];
        foreach ($this->clients->all() as $client) {
            $options[(int) $client['id']] = (string) $client['name'];
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array{name:string,value:string}> $categoryOptions
     */
    private function formatCategoryLabel(array $item, array $categoryOptions): string
    {
        $categoryId = isset($item['category_id']) ? (int) $item['category_id'] : 0;
        if ($categoryId > 0 && isset($categoryOptions[$categoryId])) {
            $meta = $categoryOptions[$categoryId];
            return sprintf('%s (%s)', $meta['name'], $meta['value']);
        }

        $slug = isset($item['category']) ? (string) $item['category'] : '';
        return $slug !== '' ? $slug : 'default';
    }

    /**
     * @return array{0:int,1:string}
     */
    private function parseCategoryPayload(?string $payload): array
    {
        if (! is_string($payload) || $payload === '' || $payload === '0') {
            return [0, ''];
        }

        $parts = explode(':', $payload, 2);
        if (count($parts) === 2) {
            $id = (int) $parts[0];
            $slug = $this->sanitizeCategorySlug($parts[1]);
            return [$id, $slug];
        }

        return [0, $this->sanitizeCategorySlug($payload)];
    }

    private function sanitizeCategorySlug(string $value): string
    {
        $value = sanitize_text_field($value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $slug = sanitize_title($value);
        if ($slug === '') {
            $slug = sanitize_title_with_dashes($value);
        }

        return $slug;
    }

    /**
     * @param array<int, array{name:string,value:string}> $categoryOptions
     */
    private function resolveCategoryIdFromSlug(string $slug, array $categoryOptions): int
    {
        $slug = $this->sanitizeCategorySlug($slug);
        if ($slug === '') {
            return 0;
        }

        foreach ($categoryOptions as $categoryId => $meta) {
            if (strcasecmp($meta['value'], $slug) === 0) {
                return (int) $categoryId;
            }
        }

        return 0;
    }

    private function generateSlugFromName(string $name): string
    {
        $slug = $this->sanitizeCategorySlug($name);
        return $slug !== '' ? $slug : 'default';
    }
}

