<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Classification\PromptRepository;

final class PromptPage
{
    private const MENU_SLUG = 'axs4all-ai-prompts';
    private const SAVE_ACTION = 'axs4all_ai_save_prompt';
    private const ACTIVATE_ACTION = 'axs4all_ai_activate_prompt';
    private const DEACTIVATE_ACTION = 'axs4all_ai_deactivate_prompt';
    private const NONCE_SAVE = 'axs4all_ai_prompt_save';
    private const NONCE_TOGGLE = 'axs4all_ai_prompt_toggle';

    private PromptRepository $repository;
    private ?CategoryRepository $categories;

    public function __construct(PromptRepository $repository, ?CategoryRepository $categories = null)
    {
        $this->repository = $repository;
        $this->categories = $categories;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Prompt Templates', 'axs4all-ai'),
            __('Prompt Templates', 'axs4all-ai'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function registerActions(): void
    {
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'handleSave']);
        add_action('admin_post_' . self::ACTIVATE_ACTION, [$this, 'handleActivate']);
        add_action('admin_post_' . self::DEACTIVATE_ACTION, [$this, 'handleDeactivate']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $message = isset($_GET['prompt_message']) ? sanitize_text_field((string) $_GET['prompt_message']) : null;
        $error = isset($_GET['prompt_error']) ? sanitize_text_field((string) $_GET['prompt_error']) : null;

        $editId = isset($_GET['edit_prompt']) ? (int) $_GET['edit_prompt'] : 0;
        $editPrompt = $editId > 0 ? $this->repository->find($editId) : null;

        $prompts = $this->repository->all();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Prompt Templates', 'axs4all-ai'); ?></h1>

            <?php $this->renderNotices($message, $error); ?>

            <div class="axs4all-ai-two-column" style="display:flex; gap:2rem; align-items:flex-start; flex-wrap:wrap;">
                <div style="flex:2 1 480px; min-width:320px;">
                    <h2><?php esc_html_e('Existing Templates', 'axs4all-ai'); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Category', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Version', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Active', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Updated', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Actions', 'axs4all-ai'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($prompts)) : ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No custom prompts stored yet. The system fallback template will be used.', 'axs4all-ai'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($prompts as $prompt) : ?>
                                <tr>
                                    <td><?php echo esc_html($prompt->category()); ?></td>
                                    <td><?php echo esc_html($prompt->version()); ?></td>
                                    <td>
                                        <?php if ($prompt->isActive()) : ?>
                                            <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                                            <span class="screen-reader-text"><?php esc_html_e('Active', 'axs4all-ai'); ?></span>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-minus" aria-hidden="true"></span>
                                            <span class="screen-reader-text"><?php esc_html_e('Inactive', 'axs4all-ai'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($prompt->updatedAt()); ?></td>
                                    <td>
                                        <a class="button-link" href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG, 'edit_prompt' => $prompt->id()])); ?>">
                                            <?php esc_html_e('Edit', 'axs4all-ai'); ?>
                                        </a>
                                        <?php if (! $prompt->isActive()) : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                <?php wp_nonce_field(self::NONCE_TOGGLE); ?>
                                                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTIVATE_ACTION); ?>">
                                                <input type="hidden" name="prompt_id" value="<?php echo esc_attr((string) $prompt->id()); ?>">
                                                <button type="submit" class="button-link"><?php esc_html_e('Activate', 'axs4all-ai'); ?></button>
                                            </form>
                                        <?php else : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                <?php wp_nonce_field(self::NONCE_TOGGLE); ?>
                                                <input type="hidden" name="action" value="<?php echo esc_attr(self::DEACTIVATE_ACTION); ?>">
                                                <input type="hidden" name="prompt_id" value="<?php echo esc_attr((string) $prompt->id()); ?>">
                                                <button type="submit" class="button-link"><?php esc_html_e('Deactivate', 'axs4all-ai'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="flex:1 1 360px; min-width:320px;">
                    <?php if ($editPrompt !== null) : ?>
                        <h2><?php esc_html_e('Edit Prompt', 'axs4all-ai'); ?></h2>
                    <?php else : ?>
                        <h2><?php esc_html_e('Add Prompt', 'axs4all-ai'); ?></h2>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::NONCE_SAVE); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                        <?php if ($editPrompt !== null) : ?>
                            <input type="hidden" name="prompt_id" value="<?php echo esc_attr((string) $editPrompt->id()); ?>">
                        <?php endif; ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-prompt-category"><?php esc_html_e('Category', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    $categoryOptions = $this->getCategoryOptions();
                                    $currentCategory = $editPrompt ? $editPrompt->category() : 'default';
                                    if ($currentCategory !== '' && ! isset($categoryOptions[$currentCategory])) {
                                        $categoryOptions[$currentCategory] = $currentCategory;
                                    }
                                    ?>
                                    <input type="text" class="regular-text" name="prompt_category" id="axs4all-ai-prompt-category" list="axs4all-ai-prompt-category-list" value="<?php echo esc_attr($currentCategory); ?>" <?php echo $editPrompt ? 'readonly' : ''; ?>>
                                    <datalist id="axs4all-ai-prompt-category-list">
                                        <?php foreach ($categoryOptions as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" label="<?php echo esc_attr($label); ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <p class="description"><?php esc_html_e('Choose a category identifier. Select from existing categories or enter a custom slug. "default" applies when no specific category matches.', 'axs4all-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-prompt-version"><?php esc_html_e('Version', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <input type="text" class="regular-text" name="prompt_version" id="axs4all-ai-prompt-version" value="<?php echo esc_attr($editPrompt ? $editPrompt->version() : 'v1'); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-prompt-template"><?php esc_html_e('Template', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <textarea name="prompt_template" id="axs4all-ai-prompt-template" rows="12" class="large-text code" required><?php echo esc_textarea($editPrompt ? $editPrompt->template() : "You are an accessibility assistant.\nContext:\n{{context}}\n\nAnswer (only \"yes\" or \"no\"):"); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Active', 'axs4all-ai'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="prompt_active" value="1" <?php checked($editPrompt ? $editPrompt->isActive() : true); ?>>
                                        <?php esc_html_e('Use this template for the selected category', 'axs4all-ai'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button($editPrompt ? __('Update Prompt', 'axs4all-ai') : __('Create Prompt', 'axs4all-ai')); ?>
                        <?php if ($editPrompt !== null) : ?>
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
        $redirect = $this->requireCapability(self::NONCE_SAVE);
        if ($redirect instanceof \WP_Error) {
            wp_die($redirect);
        }

        $id = isset($_POST['prompt_id']) ? (int) $_POST['prompt_id'] : 0;
        $category = isset($_POST['prompt_category']) ? sanitize_title_with_dashes((string) wp_unslash($_POST['prompt_category'])) : '';
        $version = isset($_POST['prompt_version']) ? sanitize_text_field((string) wp_unslash($_POST['prompt_version'])) : '';
        $template = isset($_POST['prompt_template']) ? wp_kses_post((string) wp_unslash($_POST['prompt_template'])) : '';
        $active = ! empty($_POST['prompt_active']);

        if ($category === '') {
            $this->redirectWithMessage('prompt_error', __('Category is required.', 'axs4all-ai'));
        }

        if ($version === '' || $template === '') {
            $this->redirectWithMessage('prompt_error', __('Version and template content are required.', 'axs4all-ai'));
        }

        if ($id > 0) {
            $success = $this->repository->update($id, $template, $version, $active);
            $message = $success ? __('Prompt updated.', 'axs4all-ai') : __('Failed to update prompt.', 'axs4all-ai');
            $this->redirectWithMessage($success ? 'prompt_message' : 'prompt_error', $message, ['edit_prompt' => null]);
        } else {
            $insertId = $this->repository->save($category, $template, $version, $active);
            $message = $insertId > 0 ? __('Prompt created.', 'axs4all-ai') : __('Failed to create prompt.', 'axs4all-ai');
            $this->redirectWithMessage($insertId > 0 ? 'prompt_message' : 'prompt_error', $message);
        }
    }

    public function handleActivate(): void
    {
        $redirect = $this->requireCapability(self::NONCE_TOGGLE);
        if ($redirect instanceof \WP_Error) {
            wp_die($redirect);
        }

        $id = isset($_POST['prompt_id']) ? (int) $_POST['prompt_id'] : 0;
        if ($id <= 0) {
            $this->redirectWithMessage('prompt_error', __('Invalid prompt ID.', 'axs4all-ai'));
        }

        $success = $this->repository->activate($id);
        $this->redirectWithMessage($success ? 'prompt_message' : 'prompt_error', $success ? __('Prompt activated.', 'axs4all-ai') : __('Unable to activate prompt.', 'axs4all-ai'));
    }

    public function handleDeactivate(): void
    {
        $redirect = $this->requireCapability(self::NONCE_TOGGLE);
        if ($redirect instanceof \WP_Error) {
            wp_die($redirect);
        }

        $id = isset($_POST['prompt_id']) ? (int) $_POST['prompt_id'] : 0;
        if ($id <= 0) {
            $this->redirectWithMessage('prompt_error', __('Invalid prompt ID.', 'axs4all-ai'));
        }

        $success = $this->repository->deactivate($id);
        $this->redirectWithMessage($success ? 'prompt_message' : 'prompt_error', $success ? __('Prompt deactivated.', 'axs4all-ai') : __('Unable to deactivate prompt.', 'axs4all-ai'));
    }

    private function requireCapability(string $nonceAction)
    {
        if (! current_user_can('manage_options')) {
            return new \WP_Error('forbidden', __('You are not allowed to manage prompts.', 'axs4all-ai'));
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce((string) $_POST['_wpnonce'], $nonceAction)) {
            return new \WP_Error('invalid_nonce', __('Security check failed. Please try again.', 'axs4all-ai'));
        }

        return true;
    }

    private function redirectWithMessage(string $param, string $value, array $extra = []): void
    {
        $args = array_merge(
            [
                'page' => self::MENU_SLUG,
                $param => $value,
            ],
            array_filter($extra, static function ($val) {
                return $val !== null;
            })
        );

        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    /**
     * @return array<string, string>
     */
    private function getCategoryOptions(): array
    {
        $options = [
            'default' => __('default (fallback)', 'axs4all-ai'),
        ];

        $customOptions = [];
        if ($this->categories instanceof CategoryRepository) {
            foreach ($this->categories->all() as $category) {
                $name = (string) $category['name'];
                $slug = sanitize_title($name);
                if ($slug === '') {
                    $slug = sanitize_title_with_dashes($name);
                }
                if ($slug === '' || isset($customOptions[$slug]) || $slug === 'default') {
                    continue;
                }
                $customOptions[$slug] = sprintf('%s (%s)', $name, $slug);
            }
        }

        ksort($customOptions);

        return $options + $customOptions;
    }

    private function renderNotices(?string $message, ?string $error): void
    {
        if ($message !== null && $message !== '') {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }

        if ($error !== null && $error !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }
    }
}

