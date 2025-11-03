<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;
use Axs4allAi\Data\SnapshotRepository;

final class QueuePage
{
    private const MAX_ATTEMPTS = 3;
    private QueueRepository $repository;
    private ClientRepository $clients;
    private CategoryRepository $categories;
    private ?SnapshotRepository $snapshots;

    public function __construct(
        QueueRepository $repository,
        ClientRepository $clients,
        CategoryRepository $categories,
        ?SnapshotRepository $snapshots = null
    )
    {
        $this->repository = $repository;
        $this->clients = $clients;
        $this->categories = $categories;
        $this->snapshots = $snapshots;
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
        add_action('admin_post_axs4all_ai_run_crawl_now', [$this, 'handleRunCrawlNow']);
        add_action('admin_post_axs4all_ai_requeue_queue', [$this, 'handleRequeueRequest']);
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
        $snapshotMap = $this->snapshots instanceof SnapshotRepository
            ? $this->buildSnapshotMap($recent)
            : [];
        $cronStatus = $this->gatherCronStatus();

        ?>
        <style>
            .axs4all-status-badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
                line-height: 1.4;
            }
            .axs4all-status-pending { background: #21759b; }
            .axs4all-status-processing { background: #6f42c1; }
            .axs4all-status-completed { background: #2f855a; }
            .axs4all-status-failed { background: #c53030; }
            .axs4all-status-unknown { background: #4a5568; }
            .axs4all-queue-meta {
                margin-top: 4px;
                font-size: 12px;
                color: #555d66;
            }
            .axs4all-queue-error {
                margin-top: 4px;
                font-size: 12px;
                color: #c53030;
                max-width: 320px;
            }
        </style>
        <div class="wrap">
            <h1><?php esc_html_e('Crawl Queue', 'axs4all-ai'); ?></h1>

            <?php $this->renderNotice($message); ?>
            <?php if (! empty($cronStatus['next_missing'])) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('The crawler cron event is not scheduled. Run ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“Run Crawl NowÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â or re-activate the plugin to restore scheduling.', 'axs4all-ai'); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width:480px;margin-bottom:1.5rem;">
                <h2><?php esc_html_e('Crawler Schedule', 'axs4all-ai'); ?></h2>
                <p>
                    <strong><?php esc_html_e('Next run:', 'axs4all-ai'); ?></strong>
                    <?php echo esc_html($cronStatus['next'] ?? __('Not scheduled', 'axs4all-ai')); ?><br>
                    <strong><?php esc_html_e('Last completed run:', 'axs4all-ai'); ?></strong>
                    <?php echo esc_html($cronStatus['last'] ?? __('Unknown', 'axs4all-ai')); ?><br>
                    <strong><?php esc_html_e('Pending items:', 'axs4all-ai'); ?></strong>
                    <?php echo esc_html((string) ($cronStatus['pending'] ?? 0)); ?>
                </p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:1rem;">
                <?php wp_nonce_field('axs4all_ai_run_crawl'); ?>
                <input type="hidden" name="action" value="axs4all_ai_run_crawl_now" />
                <?php submit_button(__('Run Crawl Now', 'axs4all-ai'), 'secondary', 'submit', false); ?>
            </form>

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
                            <p class="description"><?php esc_html_e('Use this if you need a category that is not yet configured above. Leave empty to fall back to "default".', 'axs4all-ai'); ?></p>
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
                        <th><?php esc_html_e('Created', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Updated', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Latest Snapshot', 'axs4all-ai'); ?></th>
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
                            <td><?php echo esc_html($clientOptions[$item['client_id']] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($this->formatCategoryLabel($item, $categoryOptions)); ?></td>
                            <td><?php echo ! empty($item['crawl_subpages']) ? esc_html__('Yes', 'axs4all-ai') : esc_html__('No', 'axs4all-ai'); ?></td>
                            <td><?php echo $this->renderStatusCell($item); ?></td>
                            <td><?php echo esc_html((string) $item['priority']); ?></td>
                            <td><?php echo esc_html($this->formatSimpleDate($item['created_at'] ?? null)); ?></td>
                            <td><?php echo esc_html($this->formatSimpleDate($item['updated_at'] ?? null)); ?></td>
                        <td>
                            <?php
                            $snapshot = $snapshotMap[$item['id']] ?? null;
                            if ($snapshot === null) {
                                    echo '&mdash;';
                                } else {
                                    $snapshotUrl = add_query_arg(
                                        [
                                            'page' => 'axs4all-ai-debug',
                                            'snapshot_id' => (int) $snapshot['id'],
                                        ],
                                        admin_url('admin.php')
                                    );
                                    printf(
                                        '<a href="%s">%s</a>',
                                        esc_url($snapshotUrl),
                                        esc_html((string) $snapshot['fetched_at'])
                                    );
                                }
                                ?>
                        </td>
                        <td>
                                <?php if ($item['status'] === 'failed') : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('axs4all_ai_requeue_queue_' . (int) $item['id']); ?>
                                        <input type="hidden" name="action" value="axs4all_ai_requeue_queue">
                                        <input type="hidden" name="queue_id" value="<?php echo esc_attr((string) $item['id']); ?>">
                                        <button type="submit" class="button button-secondary"><?php esc_html_e('Requeue', 'axs4all-ai'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this queue item?', 'axs4all-ai')); ?>');" style="display:inline;">
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
                                    <?php
                                    $firstChoice = $row['category_choices'][0] ?? null;
                                    if ($firstChoice !== null) :
                                        $payload = $firstChoice['id'] . ':' . $firstChoice['slug'];
                                    ?>
                                        <input type="hidden" name="queue_category_payload" value="<?php echo esc_attr($payload); ?>">
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

        $priority = isset($_POST['queue_priority']) ? (int) $_POST['queue_priority'] : 5;
        $priority = max(1, min(9, $priority));
        $crawlSubpages = ! empty($_POST['queue_crawl_subpages']);

        $success = $this->repository->enqueue(
            $url,
            $categorySlug,
            $priority,
            $crawlSubpages,
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

    public function handleRequeueRequest(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You are not allowed to manage the crawl queue.', 'axs4all-ai'));
        }

        $id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
        check_admin_referer('axs4all_ai_requeue_queue_' . $id);

        $success = $id > 0 ? $this->repository->requeue($id) : false;

        $redirectUrl = add_query_arg(
            [
                'page' => 'axs4all-ai-queue',
                'message' => $success ? 'requeued' : 'requeue_error',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    public function handleRunCrawlNow(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You are not allowed to run the crawler.', 'axs4all-ai'));
        }

        check_admin_referer('axs4all_ai_run_crawl');

        $message = 'crawl_error';
        try {
            do_action('axs4all_ai_process_queue');
            $message = 'crawl_started';
        } catch (\Throwable $exception) {
            error_log('[axs4all-ai] Crawl run error: ' . $exception->getMessage());
            $message = 'crawl_error';
        }

        $redirectUrl = add_query_arg(
            [
                'page' => 'axs4all-ai-queue',
                'message' => $message,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * @return array{next:string,last:string,pending:int}
     */
    private function gatherCronStatus(): array
    {
        $nextTimestamp = wp_next_scheduled('axs4all_ai_process_queue');
        $nextMissing = $nextTimestamp === false;
        $next = $nextMissing ? __('Not scheduled', 'axs4all-ai') : $this->formatTimestamp((int) $nextTimestamp);

        $lastRaw = get_option('axs4all_ai_last_crawl', '');
        if (is_string($lastRaw) && $lastRaw !== '') {
            $last = $this->formatUtcString($lastRaw);
        } else {
            $last = __('Never', 'axs4all-ai');
        }

        return [
            'next' => $next,
            'last' => $last,
            'pending' => $this->repository->countPending(),
            'next_missing' => $nextMissing,
        ];
    }

    private function formatTimestamp(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp) . ' UTC';
    }

    private function formatUtcString(string $value): string
    {
        $time = strtotime($value . ' UTC');
        if ($time === false) {
            return $value;
        }

        return $this->formatTimestamp($time);
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
        } elseif ($message === 'crawl_started') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Crawler triggered. Check the log for progress.', 'axs4all-ai') . '</p></div>';
        } elseif ($message === 'crawl_error') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Unable to trigger crawler. Please check logs and try again.', 'axs4all-ai') . '</p></div>';
        } elseif ($message === 'requeued') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Queue item requeued for processing.', 'axs4all-ai') . '</p></div>';
        } elseif ($message === 'requeue_error') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Unable to requeue item. Please try again.', 'axs4all-ai') . '</p></div>';
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
     * @param array<int, array<string, mixed>> $recent
     * @return array<int, array<string, mixed>>
     */
    private function buildSnapshotMap(array $recent): array
    {
        if ($this->snapshots === null) {
            return [];
        }

        $map = [];
        foreach ($recent as $item) {
            $queueId = isset($item['id']) ? (int) $item['id'] : 0;
            if ($queueId <= 0 || isset($map[$queueId])) {
                continue;
            }

            $latest = $this->snapshots->findByQueue($queueId, 1);
            if (! empty($latest)) {
                $map[$queueId] = $latest[0];
            }
        }

        return $map;
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
     */
    private function renderStatusCell(array $item): string
    {
        $status = strtolower(trim((string) ($item['status'] ?? '')));
        $map = [
            'pending' => ['class' => 'pending', 'label' => __('Pending', 'axs4all-ai')],
            'processing' => ['class' => 'processing', 'label' => __('Processing', 'axs4all-ai')],
            'completed' => ['class' => 'completed', 'label' => __('Completed', 'axs4all-ai')],
            'failed' => ['class' => 'failed', 'label' => __('Failed', 'axs4all-ai')],
        ];
        $badge = $map[$status] ?? ['class' => 'unknown', 'label' => __('Unknown', 'axs4all-ai')];

        $attempts = max(0, (int) ($item['attempts'] ?? 0));
        $attemptLabel = $attempts > 0
            ? sprintf(
                __('Attempt %1$d of %2$d', 'axs4all-ai'),
                min($attempts, self::MAX_ATTEMPTS),
                self::MAX_ATTEMPTS
            )
            : __('No attempts yet', 'axs4all-ai');

        $lastAttempt = $this->formatDateWithDiff($item['last_attempted_at'] ?? null);
        $metaParts = [$attemptLabel, sprintf(__('Last attempt: %s', 'axs4all-ai'), $lastAttempt)];
        $meta = implode(' | ', $metaParts);

        $errorText = $this->trimError($item['last_error'] ?? null);

        ob_start();
        ?>
        <span class="axs4all-status-badge <?php echo esc_attr('axs4all-status-' . $badge['class']); ?>">
            <?php echo esc_html($badge['label']); ?>
        </span>
        <div class="axs4all-queue-meta"><?php echo esc_html($meta); ?></div>
        <?php if ($errorText !== '') : ?>
            <div class="axs4all-queue-error"><?php echo esc_html($errorText); ?></div>
        <?php endif; ?>
        <?php
        return trim((string) ob_get_clean());
    }

    private function formatSimpleDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return __('n/a', 'axs4all-ai');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date(get_option('date_format', 'M j') . ' ' . get_option('time_format', 'H:i'), $timestamp);
    }

    private function formatDateWithDiff(?string $value): string
    {
        if ($value === null || $value === '') {
            return __('Never', 'axs4all-ai');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        $formatted = wp_date(get_option('date_format', 'M j') . ' ' . get_option('time_format', 'H:i'), $timestamp);
        $now = current_time('timestamp');
        if ($now <= 0) {
            return $formatted;
        }

        $diff = human_time_diff($timestamp, $now);
        $relative = $timestamp > $now
            ? sprintf(__('in %s', 'axs4all-ai'), $diff)
            : sprintf(__('%s ago', 'axs4all-ai'), $diff);

        return sprintf('%1$s | %2$s', $formatted, $relative);
    }

    private function trimError(?string $error): string
    {
        if ($error === null) {
            return '';
        }

        $error = preg_replace('/\s+/', ' ', trim($error)) ?? '';
        if ($error === '') {
            return '';
        }

        $limit = 160;
        $length = function_exists('mb_strlen') ? mb_strlen($error) : strlen($error);
        if ($length > $limit) {
            $slice = function_exists('mb_substr') ? mb_substr($error, 0, $limit - 3) : substr($error, 0, $limit - 3);
            return $slice . '...';
        }

        return $error;
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



