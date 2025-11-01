<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\ClassificationRunner;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;

final class ManualClassificationPage
{
    private const MENU_SLUG = 'axs4all-ai-manual';
    private const ACTION_SUBMIT = 'axs4all_ai_manual_classify';
    private const NONCE = 'axs4all_ai_manual_nonce';

    private ClassificationQueueRepository $classificationQueue;
    private ClassificationRunner $runner;
    private PromptRepository $prompts;
    private ?CategoryRepository $categories;
    private ?ClientRepository $clients;
    private ?QueueRepository $crawlQueue;

    public function __construct(
        ClassificationQueueRepository $classificationQueue,
        ClassificationRunner $runner,
        PromptRepository $prompts,
        ?CategoryRepository $categories = null,
        ?ClientRepository $clients = null,
        ?QueueRepository $crawlQueue = null
    ) {
        $this->classificationQueue = $classificationQueue;
        $this->runner = $runner;
        $this->prompts = $prompts;
        $this->categories = $categories;
        $this->clients = $clients;
        $this->crawlQueue = $crawlQueue;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Manual Classification', 'axs4all-ai'),
            __('Manual Classification', 'axs4all-ai'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function registerActions(): void
    {
        add_action('admin_post_' . self::ACTION_SUBMIT, [$this, 'handleSubmit']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $message = isset($_GET['manual_message']) ? sanitize_text_field((string) $_GET['manual_message']) : null;
        $error = isset($_GET['manual_error']) ? sanitize_text_field((string) $_GET['manual_error']) : null;

        $categories = $this->getCategoryOptions();
        $clients = $this->getClientOptions();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Manual Classification', 'axs4all-ai'); ?></h1>

            <?php if ($message) : ?>
                <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <p><?php esc_html_e('Paste content to classify immediately. Useful for debugging prompts or running ad-hoc checks without the crawler.', 'axs4all-ai'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SUBMIT); ?>">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="axs4all-ai-manual-content"><?php esc_html_e('Content snippet', 'axs4all-ai'); ?></label></th>
                        <td>
                            <textarea name="manual_content" id="axs4all-ai-manual-content" rows="8" class="large-text" required placeholder="<?php esc_attr_e('Skriv et afsnit, der beskriver tilgængeligheden…', 'axs4all-ai'); ?>"></textarea>
                            <p class="description"><?php esc_html_e('Provide the text the AI should analyse (typically extracted HTML/text).', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="axs4all-ai-manual-category"><?php esc_html_e('Category', 'axs4all-ai'); ?></label></th>
                        <td>
                            <select name="manual_category" id="axs4all-ai-manual-category" required>
                                <?php foreach ($categories as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Determines which prompt template and metadata to use.', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="axs4all-ai-manual-client"><?php esc_html_e('Client (optional)', 'axs4all-ai'); ?></label></th>
                        <td>
                            <select name="manual_client" id="axs4all-ai-manual-client">
                                <option value="0"><?php esc_html_e('None', 'axs4all-ai'); ?></option>
                                <?php foreach ($clients as $id => $name) : ?>
                                    <option value="<?php echo esc_attr((string) $id); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Optional: associate the result with a specific client.', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="axs4all-ai-manual-url"><?php esc_html_e('Source URL (optional)', 'axs4all-ai'); ?></label></th>
                        <td>
                            <input type="url" name="manual_url" id="axs4all-ai-manual-url" class="regular-text" placeholder="https://example.com/page">
                            <p class="description"><?php esc_html_e('Save a reference URL and queue it for crawling when needed.', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Crawl subpages', 'axs4all-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="manual_crawl_subpages" value="1">
                                <?php esc_html_e('Queue subpages for crawling as well.', 'axs4all-ai'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Applies only when a source URL is provided.', 'axs4all-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Run immediately', 'axs4all-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="manual_run_now" value="1" checked>
                                <?php esc_html_e('Process the classification right after saving.', 'axs4all-ai'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Classify snippet', 'axs4all-ai')); ?>
            </form>
        </div>
        <?php
    }

    public function handleSubmit(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You are not allowed to run manual classifications.', 'axs4all-ai'));
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce((string) $_POST['_wpnonce'], self::NONCE)) {
            wp_die(__('Security check failed.', 'axs4all-ai'));
        }

        $content = isset($_POST['manual_content']) ? trim((string) wp_unslash($_POST['manual_content'])) : '';
        $categorySlug = isset($_POST['manual_category']) ? sanitize_title((string) wp_unslash($_POST['manual_category'])) : '';
        $clientId = isset($_POST['manual_client']) ? (int) $_POST['manual_client'] : 0;
        $url = isset($_POST['manual_url']) ? trim((string) wp_unslash($_POST['manual_url'])) : '';
        $crawlSubpages = ! empty($_POST['manual_crawl_subpages']);
        $runNow = ! empty($_POST['manual_run_now']);

        if ($content === '') {
            $this->redirect('manual_error', __('Content is required.', 'axs4all-ai'));
        }

        if ($categorySlug === '') {
            $this->redirect('manual_error', __('Category is required.', 'axs4all-ai'));
        }

        $prompt = $this->prompts->getActiveTemplate($categorySlug);
        $categoryId = $this->matchCategoryId($categorySlug);
        $clientId = $clientId > 0 ? $clientId : null;
        $queueId = 0;

        if ($url !== '') {
            $normalizedUrl = esc_url_raw($url);
            if (! $normalizedUrl) {
                $this->redirect('manual_error', __('Please enter a valid URL or leave the field empty.', 'axs4all-ai'));
            }

            if (! $this->crawlQueue instanceof QueueRepository) {
                $this->redirect('manual_error', __('Crawl queue is not available.', 'axs4all-ai'));
            }

            $queueId = $this->crawlQueue->enqueueWithId($normalizedUrl, $categorySlug, 5, $crawlSubpages);
            if ($queueId === null) {
                $this->redirect('manual_error', __('Failed to store the URL in the crawl queue.', 'axs4all-ai'));
            }
        }

        $jobId = $this->classificationQueue->enqueue(
            $queueId,
            null,
            $categorySlug,
            $prompt->version(),
            $content,
            $clientId,
            $categoryId
        );

        if (! $jobId) {
            $this->redirect('manual_error', __('Failed to enqueue manual classification.', 'axs4all-ai'));
        }

        if ($runNow) {
            $job = $this->classificationQueue->findJob($jobId);
            if ($job !== null) {
                $this->runner->process([$job]);
            }
        }

        $this->redirect('manual_message', __('Classification stored successfully.', 'axs4all-ai'));
    }

    /**
     * @return array<string, string>
     */
    private function getCategoryOptions(): array
    {
        $options = [
            'default' => __('default (fallback)', 'axs4all-ai'),
        ];

        if ($this->categories instanceof CategoryRepository) {
            foreach ($this->categories->all() as $category) {
                $name = (string) $category['name'];
                $slug = sanitize_title($name);
                if ($slug === '') {
                    $slug = sanitize_title_with_dashes($name);
                }
                if ($slug === '' || isset($options[$slug])) {
                    continue;
                }
                $options[$slug] = sprintf('%s (%s)', $name, $slug);
            }
        }

        ksort($options);
        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function getClientOptions(): array
    {
        if (! $this->clients instanceof ClientRepository) {
            return [];
        }

        $options = [];
        foreach ($this->clients->all() as $client) {
            $options[(int) $client['id']] = $client['name'];
        }

        asort($options);
        return $options;
    }

    private function matchCategoryId(string $slug): ?int
    {
        if (! $this->categories instanceof CategoryRepository) {
            return null;
        }

        foreach ($this->categories->all() as $category) {
            $name = (string) $category['name'];
            $normalized = sanitize_title($name);
            if ($normalized === '') {
                $normalized = sanitize_title_with_dashes($name);
            }
            if ($normalized === $slug) {
                return (int) $category['id'];
            }
        }

        return null;
    }

    private function redirect(string $param, string $message): void
    {
        $url = add_query_arg(
            [
                'page' => self::MENU_SLUG,
                $param => $message,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
