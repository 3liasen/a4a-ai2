<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Category\CategoryRepository;

final class CategoryPage
{
    private const MENU_SLUG = 'axs4all-ai-categories';
    private const SAVE_ACTION = 'axs4all_ai_save_category';
    private const DELETE_ACTION = 'axs4all_ai_delete_category';
    private const NONCE_SAVE = 'axs4all_ai_category_save';
    private const NONCE_DELETE = 'axs4all_ai_category_delete';

    private CategoryRepository $repository;

    public function __construct(CategoryRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Categories', 'axs4all-ai'),
            __('Categories', 'axs4all-ai'),
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

        $message = isset($_GET['category_message']) ? sanitize_text_field((string) $_GET['category_message']) : null;
        $error = isset($_GET['category_error']) ? sanitize_text_field((string) $_GET['category_error']) : null;

        $editId = isset($_GET['edit_category']) ? (int) $_GET['edit_category'] : 0;
        $editCategory = $editId > 0 ? $this->repository->find($editId) : null;

        $categories = $this->repository->all();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Categories', 'axs4all-ai'); ?></h1>

            <?php $this->renderNotices($message, $error); ?>

            <div style="display:flex; flex-wrap:wrap; gap:2rem; align-items:flex-start;">
                <div style="flex:2 1 480px; min-width:320px;">
                    <h2><?php esc_html_e('Existing Categories', 'axs4all-ai'); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Options', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Decision Set', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Phrases', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Actions', 'axs4all-ai'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($categories)) : ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No categories defined yet.', 'axs4all-ai'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($categories as $category) : ?>
                                <tr>
                                    <td><?php echo esc_html($category['name']); ?></td>
                                    <td>
                                        <?php if (empty($category['options'])) : ?>
                                            <em><?php esc_html_e('None', 'axs4all-ai'); ?></em>
                                        <?php else : ?>
                                            <ul style="margin:0; padding-left:1.2rem;">
                                                <?php foreach ($category['options'] as $option) : ?>
                                                    <li><?php echo esc_html($option); ?></li>
                                            <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html((string) ($category['decision_set'] ?? 'binary')); ?></td>
                                    <td>
                                        <?php if (empty($category['phrases'])) : ?>
                                            <em><?php esc_html_e('None', 'axs4all-ai'); ?></em>
                                        <?php else : ?>
                                            <ul style="margin:0; padding-left:1.2rem;">
                                                <?php foreach (array_slice($category['phrases'], 0, 3) as $phrase) : ?>
                                                    <li><?php echo esc_html($phrase); ?></li>
                                                <?php endforeach; ?>
                                                <?php if (count($category['phrases']) > 3) : ?>
                                                    <li><em><?php printf(esc_html__('and %d more…', 'axs4all-ai'), count($category['phrases']) - 3); ?></em></li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="button-link" href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG, 'edit_category' => $category['id']])); ?>">
                                            <?php esc_html_e('Edit', 'axs4all-ai'); ?>
                                        </a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                            <?php wp_nonce_field(self::NONCE_DELETE); ?>
                                            <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                            <input type="hidden" name="category_id" value="<?php echo esc_attr((string) $category['id']); ?>">
                                            <button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js(__('Delete this category? This cannot be undone.', 'axs4all-ai')); ?>');">
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
                <div style="flex:1 1 360px; min-width:320px;">
                    <h2><?php echo esc_html($editCategory ? __('Edit Category', 'axs4all-ai') : __('Add Category', 'axs4all-ai')); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::NONCE_SAVE); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                        <?php if ($editCategory) : ?>
                            <input type="hidden" name="category_id" value="<?php echo esc_attr((string) $editCategory['id']); ?>">
                        <?php endif; ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-category-name"><?php esc_html_e('Name', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <input type="text" class="regular-text" name="category_name" id="axs4all-ai-category-name" value="<?php echo esc_attr($editCategory['name'] ?? ''); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-category-options"><?php esc_html_e('Options', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <textarea name="category_options" id="axs4all-ai-category-options" rows="8" class="large-text code" placeholder="<?php esc_attr_e("Ramp\nAccessible toilet\nReserved parking", 'axs4all-ai'); ?>"><?php
                                        echo esc_textarea(isset($editCategory['options']) ? implode("\n", $editCategory['options']) : '');
                                    ?></textarea>
                                    <p class="description"><?php esc_html_e('One option per line. Empty lines are ignored.', 'axs4all-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-category-base-prompt"><?php esc_html_e('Base Prompt', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <textarea name="category_base_prompt" id="axs4all-ai-category-base-prompt" rows="6" class="large-text code" placeholder="<?php esc_attr_e('Provide detailed accessibility analysis instructions…', 'axs4all-ai'); ?>"><?php echo esc_textarea($editCategory['base_prompt'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-category-keywords"><?php esc_html_e('Keywords', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <textarea name="category_keywords" id="axs4all-ai-category-keywords" rows="3" class="large-text code" placeholder="<?php esc_attr_e("wheelchair access\nstep-free entrance", 'axs4all-ai'); ?>"><?php
                                        echo esc_textarea(isset($editCategory['keywords']) ? implode("\n", $editCategory['keywords']) : '');
                                    ?></textarea>
                                    <p class="description"><?php esc_html_e('One keyword or phrase per line.', 'axs4all-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-category-phrases"><?php esc_html_e('Real-world Phrases', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <textarea name="category_phrases" id="axs4all-ai-category-phrases" rows="6" class="large-text code" placeholder="<?php esc_attr_e("Entrance has two steps\nElevator requires staff assistance", 'axs4all-ai'); ?>"><?php
                                        echo esc_textarea(isset($editCategory['phrases']) ? implode("\n", $editCategory['phrases']) : '');
                                    ?></textarea>
                                    <p class="description"><?php esc_html_e('Add phrases you want the AI to factor in. One per line.', 'axs4all-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-category-decision"><?php esc_html_e('Decision Set', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <select name="category_decision_set" id="axs4all-ai-category-decision">
                                        <?php
                                        $selectedDecision = $editCategory['decision_set'] ?? 'binary';
                                        $decisionSets = [
                                            'binary' => __('Binary (yes/no)', 'axs4all-ai'),
                                            'accessibility' => __('Accessibility scale (none/limited/full)', 'axs4all-ai'),
                                        ];
                                        foreach ($decisionSets as $value => $label) :
                                        ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($selectedDecision, $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="axs4all-ai-category-snippet-limit"><?php esc_html_e('Snippet Limit', 'axs4all-ai'); ?></label>
                                </th>
                                <td>
                                    <input type="number" min="1" max="10" name="category_snippet_limit" id="axs4all-ai-category-snippet-limit" value="<?php echo isset($editCategory['snippet_limit']) && $editCategory['snippet_limit'] !== null ? esc_attr((string) $editCategory['snippet_limit']) : ''; ?>" class="small-text">
                                    <p class="description"><?php esc_html_e('Maximum number of snippets sent to the AI for this category (leave blank to use defaults).', 'axs4all-ai'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button($editCategory ? __('Update Category', 'axs4all-ai') : __('Create Category', 'axs4all-ai')); ?>
                        <?php if ($editCategory) : ?>
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

        $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $name = isset($_POST['category_name']) ? (string) wp_unslash($_POST['category_name']) : '';
        $optionsRaw = isset($_POST['category_options']) ? (string) wp_unslash($_POST['category_options']) : '';
        $options = $this->splitLines($optionsRaw);
        $basePrompt = isset($_POST['category_base_prompt']) ? (string) wp_unslash($_POST['category_base_prompt']) : '';
        $keywordsRaw = isset($_POST['category_keywords']) ? (string) wp_unslash($_POST['category_keywords']) : '';
        $phrasesRaw = isset($_POST['category_phrases']) ? (string) wp_unslash($_POST['category_phrases']) : '';
        $decisionSet = isset($_POST['category_decision_set']) ? (string) wp_unslash($_POST['category_decision_set']) : 'binary';
        $snippetLimitInput = isset($_POST['category_snippet_limit']) ? (int) $_POST['category_snippet_limit'] : 0;
        $snippetLimit = $snippetLimitInput > 0 ? min(10, max(1, $snippetLimitInput)) : null;

        if ($name === '') {
            $this->redirectWithMessage('category_error', __('Category name is required.', 'axs4all-ai'));
        }

        $options = $this->repository->sanitizeOptions($options);
        $keywords = $this->repository->sanitizeOptions($this->splitLines($keywordsRaw));
        $phrases = $this->repository->sanitizeOptions($this->splitLines($phrasesRaw));

        $meta = [
            'base_prompt' => $basePrompt,
            'keywords' => $keywords,
            'phrases' => $phrases,
            'decision_set' => $decisionSet,
            'snippet_limit' => $snippetLimit,
        ];

        if ($id > 0) {
            $success = $this->repository->update($id, $name, $options, $meta);
            $this->redirectWithMessage(
                $success ? 'category_message' : 'category_error',
                $success ? __('Category updated.', 'axs4all-ai') : __('Failed to update category.', 'axs4all-ai'),
                $success ? ['edit_category' => null] : ['edit_category' => $id]
            );
        } else {
            $newId = $this->repository->create($name, $options, $meta);
            $this->redirectWithMessage(
                $newId ? 'category_message' : 'category_error',
                $newId ? __('Category created.', 'axs4all-ai') : __('Failed to create category.', 'axs4all-ai')
            );
        }
    }

    public function handleDelete(): void
    {
        $authorized = $this->ensureAuthorized(self::NONCE_DELETE);
        if ($authorized instanceof \WP_Error) {
            wp_die($authorized);
        }

        $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

        if ($id <= 0) {
            $this->redirectWithMessage('category_error', __('Invalid category ID.', 'axs4all-ai'));
        }

        $success = $this->repository->delete($id);
        $this->redirectWithMessage(
            $success ? 'category_message' : 'category_error',
            $success ? __('Category deleted.', 'axs4all-ai') : __('Unable to delete category.', 'axs4all-ai')
        );
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $raw): array
    {
        $lines = preg_split("/\r\n|\r|\n/", $raw) ?: [];
        return array_map('trim', $lines);
    }

    private function ensureAuthorized(string $nonceAction)
    {
        if (! current_user_can('manage_options')) {
            return new \WP_Error('forbidden', __('You are not allowed to manage categories.', 'axs4all-ai'));
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
            array_filter($extra, static fn($v) => $v !== null)
        );

        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
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
}
