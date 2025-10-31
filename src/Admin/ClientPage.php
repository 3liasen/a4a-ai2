<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Data\ClientRepository;

final class ClientPage
{
    private const MENU_SLUG = 'axs4all-ai-clients';
    private const SAVE_ACTION = 'axs4all_ai_save_client';
    private const DELETE_ACTION = 'axs4all_ai_delete_client';
    private const NONCE_SAVE = 'axs4all_ai_client_save';
    private const NONCE_DELETE = 'axs4all_ai_client_delete';

    private ClientRepository $clients;
    private CategoryRepository $categories;

    public function __construct(ClientRepository $clients, CategoryRepository $categories)
    {
        $this->clients = $clients;
        $this->categories = $categories;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Clients', 'axs4all-ai'),
            __('Clients', 'axs4all-ai'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function registerActions(): void
    {
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'handleSave']);
        add_action('admin_post_' . self::DELETE_ACTION, [$this, 'handleDelete']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $message = isset($_GET['client_message']) ? sanitize_text_field((string) $_GET['client_message']) : null;
        $error = isset($_GET['client_error']) ? sanitize_text_field((string) $_GET['client_error']) : null;

        $editId = isset($_GET['edit_client']) ? (int) $_GET['edit_client'] : 0;
        $client = $editId > 0 ? $this->clients->find($editId) : null;
        $categories = $this->categories->all();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Clients', 'axs4all-ai'); ?></h1>
            <?php $this->renderNotices($message, $error); ?>

            <div style="display:flex; flex-wrap:wrap; gap:2rem; align-items:flex-start;">
                <div style="flex:2 1 480px; min-width:320px;">
                    <h2><?php esc_html_e('Existing Clients', 'axs4all-ai'); ?></h2>
                    <table class="widefat striped">
                        <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'axs4all-ai'); ?></th>
                            <th><?php esc_html_e('Status', 'axs4all-ai'); ?></th>
                            <th><?php esc_html_e('URLs', 'axs4all-ai'); ?></th>
                            <th><?php esc_html_e('Categories', 'axs4all-ai'); ?></th>
                            <th><?php esc_html_e('Actions', 'axs4all-ai'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $rows = $this->clients->all(); ?>
                        <?php if (empty($rows)) : ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No clients created yet.', 'axs4all-ai'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($rows as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row['name']); ?></td>
                                    <td><?php echo esc_html(ucfirst($row['status'])); ?></td>
                                    <td><?php echo esc_html((string) $row['url_count']); ?></td>
                                    <td><?php echo esc_html((string) $row['category_count']); ?></td>
                                    <td>
                                        <a class="button-link" href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG, 'edit_client' => $row['id']])); ?>">
                                            <?php esc_html_e('Edit', 'axs4all-ai'); ?>
                                        </a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                            <?php wp_nonce_field(self::NONCE_DELETE); ?>
                                            <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                            <input type="hidden" name="client_id" value="<?php echo esc_attr((string) $row['id']); ?>">
                                            <button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js(__('Delete this client? This cannot be undone.', 'axs4all-ai')); ?>');">
                                                <?php esc_html_e('Delete', 'axs4all-ai'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex:1 1 420px; min-width:320px;">
                    <h2><?php echo $client ? esc_html__('Edit Client', 'axs4all-ai') : esc_html__('Add Client', 'axs4all-ai'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::NONCE_SAVE); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                        <?php if ($client) : ?>
                            <input type="hidden" name="client_id" value="<?php echo esc_attr((string) $client['id']); ?>">
                        <?php endif; ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="axs4all-ai-client-name"><?php esc_html_e('Name', 'axs4all-ai'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" name="client_name" id="axs4all-ai-client-name" value="<?php echo esc_attr($client['name'] ?? ''); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="axs4all-ai-client-status"><?php esc_html_e('Status', 'axs4all-ai'); ?></label></th>
                                <td>
                                    <select name="client_status" id="axs4all-ai-client-status">
                                        <?php
                                        $currentStatus = $client['status'] ?? 'active';
                                        foreach (['active' => __('Active', 'axs4all-ai'), 'inactive' => __('Inactive', 'axs4all-ai')] as $value => $label) :
                                        ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($currentStatus, $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="axs4all-ai-client-notes"><?php esc_html_e('Notes', 'axs4all-ai'); ?></label></th>
                                <td>
                                    <textarea name="client_notes" id="axs4all-ai-client-notes" rows="4" class="large-text"><?php echo esc_textarea($client['notes'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('URLs', 'axs4all-ai'); ?></th>
                                <td>
                                    <div id="axs4all-ai-client-urls">
                                        <?php
                                        $urls = $client['urls'] ?? [];
                                        if (empty($urls)) {
                                            $urls[] = ['url' => '', 'crawl_subpages' => false];
                                        }
                                        foreach ($urls as $index => $urlRow) :
                                            $urlValue = isset($urlRow['url']) ? $urlRow['url'] : '';
                                            $crawlSubpages = ! empty($urlRow['crawl_subpages']);
                                            ?>
                                            <div class="axs4all-ai-client-url" style="margin-bottom:0.75rem;">
                                                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                                                    <input type="url" name="client_urls[<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr($urlValue); ?>" class="regular-text" placeholder="<?php esc_attr_e('https://example.com', 'axs4all-ai'); ?>" required>
                                                    <label style="display:flex; align-items:center; gap:0.35rem; margin:0;">
                                                        <input type="checkbox" name="client_urls[<?php echo esc_attr((string) $index); ?>][crawl_subpages]" value="1" <?php checked($crawlSubpages); ?>>
                                                        <?php esc_html_e('Crawl subpages', 'axs4all-ai'); ?>
                                                    </label>
                                                    <button type="button" class="button button-secondary axs4all-ai-remove-url"><?php esc_html_e('Remove', 'axs4all-ai'); ?></button>
                                                </div>
                                            </div>
                                            <?php
                                        endforeach;
                                        ?>
                                    </div>
                                    <button type="button" class="button button-secondary" id="axs4all-ai-add-url"><?php esc_html_e('Add URL', 'axs4all-ai'); ?></button>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function () {
                                            const container = document.getElementById('axs4all-ai-client-urls');
                                            const addButton = document.getElementById('axs4all-ai-add-url');
                                            if (!container || !addButton) {
                                                return;
                                            }
                                            const template = (index) => `
                                                <div class="axs4all-ai-client-url" style="margin-bottom:0.75rem;">
                                                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                                                        <input type="url" name="client_urls[${index}][url]" value="" class="regular-text" placeholder="<?php echo esc_js(__('https://example.com', 'axs4all-ai')); ?>" required>
                                                        <label style="display:flex; align-items:center; gap:0.35rem; margin:0;">
                                                            <input type="checkbox" name="client_urls[${index}][crawl_subpages]" value="1">
                                                            <?php echo esc_js(__('Crawl subpages', 'axs4all-ai')); ?>
                                                        </label>
                                                        <button type="button" class="button button-secondary axs4all-ai-remove-url"><?php echo esc_js(__('Remove', 'axs4all-ai')); ?></button>
                                                    </div>
                                                </div>
                                            `;

                                            const bindRemove = (wrapper) => {
                                                wrapper.querySelectorAll('.axs4all-ai-remove-url').forEach(function (button) {
                                                    button.addEventListener('click', function () {
                                                        wrapper.remove();
                                                    });
                                                });
                                            };

                                            // initial rows
                                            container.querySelectorAll('.axs4all-ai-client-url').forEach(bindRemove);

                                            addButton.addEventListener('click', function () {
                                                const index = container.querySelectorAll('.axs4all-ai-client-url').length;
                                                const wrapper = document.createElement('div');
                                                wrapper.innerHTML = template(index);
                                                container.appendChild(wrapper.firstElementChild);
                                                bindRemove(container.lastElementChild);
                                            });
                                        });
                                    </script>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Categories', 'axs4all-ai'); ?></th>
                                <td>
                                    <?php if (empty($categories)) : ?>
                                        <p><?php esc_html_e('No categories available.', 'axs4all-ai'); ?></p>
                                    <?php else : ?>
                                        <fieldset>
                                            <?php
                                            $selectedCategories = $client['categories'] ?? [];
                                            foreach ($categories as $category) :
                                                $isChecked = in_array((int) $category['id'], $selectedCategories, true);
                                                ?>
                                                <label style="display:block; margin-bottom:0.5rem;">
                                                    <input type="checkbox" name="client_categories[]" value="<?php echo esc_attr((string) $category['id']); ?>" <?php checked($isChecked); ?>>
                                                    <?php echo esc_html($category['name']); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </fieldset>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button($client ? __('Update Client', 'axs4all-ai') : __('Create Client', 'axs4all-ai')); ?>
                        <?php if ($client) : ?>
                            <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG])); ?>"><?php esc_html_e('Cancel', 'axs4all-ai'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function handleSave(): void
    {
        $authorized = $this->ensureAuthorized(self::NONCE_SAVE);
        if ($authorized instanceof \WP_Error) {
            wp_die($authorized);
        }

        $id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $name = isset($_POST['client_name']) ? (string) wp_unslash($_POST['client_name']) : '';
        $status = isset($_POST['client_status']) ? (string) wp_unslash($_POST['client_status']) : 'active';
        $notes = isset($_POST['client_notes']) ? (string) wp_unslash($_POST['client_notes']) : '';

        if ($name === '') {
            $this->redirectWithMessage('client_error', __('Client name is required.', 'axs4all-ai'));
        }

        if ($id > 0) {
            $success = $this->clients->update($id, $name, $status, $notes);
            $clientId = $id;
            $messageParam = $success ? 'client_message' : 'client_error';
            $message = $success ? __('Client updated.', 'axs4all-ai') : __('Failed to update client.', 'axs4all-ai');
        } else {
            $clientId = $this->clients->create($name, $status, $notes);
            if (! $clientId) {
                $this->redirectWithMessage('client_error', __('Failed to create client.', 'axs4all-ai'));
            }
            $messageParam = 'client_message';
            $message = __('Client created.', 'axs4all-ai');
        }

        $urlsInput = isset($_POST['client_urls']) && is_array($_POST['client_urls']) ? $_POST['client_urls'] : [];
        $urlPayload = [];
        foreach ($urlsInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = isset($row['url']) ? (string) wp_unslash($row['url']) : '';
            if ($url === '') {
                continue;
            }
            $urlPayload[] = [
                'url' => $url,
                'crawl_subpages' => ! empty($row['crawl_subpages']),
            ];
        }
        $this->clients->saveUrls($clientId, $urlPayload);

        $categoryInput = isset($_POST['client_categories']) && is_array($_POST['client_categories'])
            ? array_map('intval', array_map('wp_unslash', (array) $_POST['client_categories']))
            : [];
        $this->clients->saveCategories($clientId, $categoryInput);

        $this->redirectWithMessage($messageParam, $message, ['edit_client' => null]);
    }

    public function handleDelete(): void
    {
        $authorized = $this->ensureAuthorized(self::NONCE_DELETE);
        if ($authorized instanceof \WP_Error) {
            wp_die($authorized);
        }

        $id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;

        if ($id <= 0) {
            $this->redirectWithMessage('client_error', __('Invalid client ID.', 'axs4all-ai'));
        }

        $success = $this->clients->delete($id);
        $this->redirectWithMessage($success ? 'client_message' : 'client_error', $success ? __('Client deleted.', 'axs4all-ai') : __('Unable to delete client.', 'axs4all-ai'));
    }

    private function ensureAuthorized(string $nonceAction)
    {
        if (! current_user_can('manage_options')) {
            return new \WP_Error('forbidden', __('You are not allowed to manage clients.', 'axs4all-ai'));
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce((string) $_POST['_wpnonce'], $nonceAction)) {
            return new \WP_Error('invalid_nonce', __('Security check failed. Please try again.', 'axs4all-ai'));
        }

        return true;
    }

    private function renderNotices(?string $message, ?string $error): void
    {
        if ($message) {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }

        if ($error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function redirectWithMessage(string $param, string $value, array $extra = []): void
    {
        $args = array_merge(
            [
                'page' => self::MENU_SLUG,
                $param => $value,
            ],
            array_filter($extra, static fn($v) => $v !== null)
        );

        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
