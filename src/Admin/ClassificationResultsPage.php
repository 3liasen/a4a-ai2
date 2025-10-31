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

        $request = wp_unslash($_GET);

        $decision = isset($request['decision']) ? sanitize_text_field((string) $request['decision']) : '';
        $decision = in_array($decision, ['yes', 'no'], true) ? $decision : '';
        $model = isset($request['model']) ? sanitize_text_field((string) $request['model']) : '';
        $promptVersion = isset($request['prompt_version']) ? sanitize_text_field((string) $request['prompt_version']) : '';
        $createdStart = isset($request['created_start']) ? sanitize_text_field((string) $request['created_start']) : '';
        $createdEnd = isset($request['created_end']) ? sanitize_text_field((string) $request['created_end']) : '';
        $search = isset($request['search']) ? sanitize_text_field((string) $request['search']) : '';
        $queueIdInput = isset($request['queue_id']) ? sanitize_text_field((string) $request['queue_id']) : '';

        $perPage = isset($request['per_page']) ? (int) $request['per_page'] : 20;
        $perPage = max(5, min(100, $perPage));
        $currentPage = isset($request['paged']) ? (int) $request['paged'] : 1;
        $currentPage = max(1, $currentPage);

        $filters = [
            'decision' => $decision,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'created_start' => $createdStart,
            'created_end' => $createdEnd,
            'search' => $search,
            'queue_id' => ($queueIdInput !== '' && ctype_digit($queueIdInput)) ? (int) $queueIdInput : null,
        ];

        $filters = array_filter(
            $filters,
            static function ($value) {
                return $value !== '' && $value !== null;
            }
        );

        $results = $this->repository->getResults($filters, $perPage, $currentPage);
        $totalResults = $this->repository->countResults($filters);
        $totalPages = (int) max(1, ceil($totalResults / $perPage));

        $detailId = isset($request['detail']) ? absint($request['detail']) : 0;
        $detail = $detailId > 0 ? $this->repository->getResult($detailId) : null;

        $baseArgs = [
            'page' => self::MENU_SLUG,
            'decision' => $decision,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'created_start' => $createdStart,
            'created_end' => $createdEnd,
            'search' => $search,
            'queue_id' => $queueIdInput,
            'per_page' => $perPage,
        ];

        $baseArgs = array_filter(
            $baseArgs,
            static function ($value) {
                return $value !== '' && $value !== null;
            }
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Classification Results', 'axs4all-ai'); ?></h1>
            <p><?php esc_html_e('Filter, inspect, and export AI classification decisions processed by the automation pipeline.', 'axs4all-ai'); ?></p>

            <form method="get" class="axs4all-ai-classification-filters" style="margin-bottom: 1.5rem;">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <label for="axs4all-ai-decision" class="screen-reader-text"><?php esc_html_e('Decision filter', 'axs4all-ai'); ?></label>
                        <select name="decision" id="axs4all-ai-decision">
                            <option value=""><?php esc_html_e('All decisions', 'axs4all-ai'); ?></option>
                            <option value="yes" <?php selected($decision, 'yes'); ?>><?php esc_html_e('Yes', 'axs4all-ai'); ?></option>
                            <option value="no" <?php selected($decision, 'no'); ?>><?php esc_html_e('No', 'axs4all-ai'); ?></option>
                        </select>

                        <label for="axs4all-ai-model" class="screen-reader-text"><?php esc_html_e('Model filter', 'axs4all-ai'); ?></label>
                        <input type="text" name="model" id="axs4all-ai-model" value="<?php echo esc_attr($model); ?>" placeholder="<?php esc_attr_e('Model', 'axs4all-ai'); ?>">

                        <label for="axs4all-ai-prompt" class="screen-reader-text"><?php esc_html_e('Prompt version filter', 'axs4all-ai'); ?></label>
                        <input type="text" name="prompt_version" id="axs4all-ai-prompt" value="<?php echo esc_attr($promptVersion); ?>" placeholder="<?php esc_attr_e('Prompt version', 'axs4all-ai'); ?>">

                        <input type="text" name="queue_id" value="<?php echo esc_attr($queueIdInput); ?>" placeholder="<?php esc_attr_e('Queue ID', 'axs4all-ai'); ?>" style="width: 90px;">

                        <input type="date" name="created_start" value="<?php echo esc_attr($createdStart); ?>">
                        <input type="date" name="created_end" value="<?php echo esc_attr($createdEnd); ?>">

                        <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search raw response or URL', 'axs4all-ai'); ?>" style="width: 220px;">

                        <label for="axs4all-ai-per-page" class="screen-reader-text"><?php esc_html_e('Rows per page', 'axs4all-ai'); ?></label>
                        <select name="per_page" id="axs4all-ai-per-page">
                            <?php foreach ([10, 20, 50, 100] as $option) : ?>
                                <option value="<?php echo esc_attr((string) $option); ?>" <?php selected($perPage, $option); ?>><?php echo esc_html(sprintf(_n('%d result', '%d results', $option, 'axs4all-ai'), $option)); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'axs4all-ai'); ?>">
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>">
                            <?php esc_html_e('Reset', 'axs4all-ai'); ?>
                        </a>
                    </div>
                </div>
            </form>

            <?php if ($detailId > 0) : ?>
                <div class="notice notice-info">
                    <?php if ($detail !== null) : ?>
                        <h2><?php printf(esc_html__('Classification #%d details', 'axs4all-ai'), (int) $detail['id']); ?></h2>
                        <ul>
                            <li><strong><?php esc_html_e('Decision:', 'axs4all-ai'); ?></strong> <?php echo esc_html(strtoupper((string) $detail['decision'])); ?></li>
                            <li><strong><?php esc_html_e('Confidence:', 'axs4all-ai'); ?></strong>
                                <?php
                                if ($detail['confidence'] !== null) {
                                    echo esc_html(number_format((float) $detail['confidence'] * 100, 1)) . '%';
                                } else {
                                    esc_html_e('n/a', 'axs4all-ai');
                                }
                                ?>
                            </li>
                            <li><strong><?php esc_html_e('Model:', 'axs4all-ai'); ?></strong> <?php echo esc_html((string) ($detail['model'] ?? 'n/a')); ?></li>
                            <li><strong><?php esc_html_e('Prompt version:', 'axs4all-ai'); ?></strong> <?php echo esc_html((string) $detail['prompt_version']); ?></li>
                            <li><strong><?php esc_html_e('Queue ID:', 'axs4all-ai'); ?></strong> <?php echo esc_html((string) $detail['queue_id']); ?></li>
                            <li><strong><?php esc_html_e('Category:', 'axs4all-ai'); ?></strong> <?php echo esc_html((string) ($detail['category'] ?? 'n/a')); ?></li>
                            <li><strong><?php esc_html_e('Source URL:', 'axs4all-ai'); ?></strong>
                                <?php if (! empty($detail['source_url'])) : ?>
                                    <a href="<?php echo esc_url((string) $detail['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) $detail['source_url']); ?></a>
                                <?php else : ?>
                                    <?php esc_html_e('n/a', 'axs4all-ai'); ?>
                                <?php endif; ?>
                            </li>
                            <li><strong><?php esc_html_e('Created:', 'axs4all-ai'); ?></strong> <?php echo esc_html((string) $detail['created_at']); ?></li>
                            <li><strong><?php esc_html_e('Tokens (prompt/completion):', 'axs4all-ai'); ?></strong>
                                <?php echo esc_html(sprintf('%s / %s', $detail['tokens_prompt'] ?? '—', $detail['tokens_completion'] ?? '—')); ?>
                            </li>
                            <li><strong><?php esc_html_e('Duration (ms):', 'axs4all-ai'); ?></strong> <?php echo esc_html((string) ($detail['duration_ms'] ?? '—')); ?></li>
                        </ul>
                        <?php if (! empty($detail['content'])) : ?>
                            <p><strong><?php esc_html_e('Snippet content:', 'axs4all-ai'); ?></strong></p>
                            <pre style="white-space: pre-wrap;"><?php echo esc_html((string) $detail['content']); ?></pre>
                        <?php endif; ?>
                        <p><strong><?php esc_html_e('Raw response:', 'axs4all-ai'); ?></strong></p>
                        <pre style="max-height: 320px; overflow:auto;"><?php echo esc_html((string) $detail['raw_response']); ?></pre>
                    <?php else : ?>
                        <p><?php esc_html_e('The requested classification could not be found.', 'axs4all-ai'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Decision', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Queue ID', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Source URL', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Category', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Confidence', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Prompt Version', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Model', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Tokens (P/C)', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Duration (ms)', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Created', 'axs4all-ai'); ?></th>
                        <th><?php esc_html_e('Actions', 'axs4all-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($results)) : ?>
                    <tr>
                        <td colspan="10"><?php esc_html_e('No classifications match the current filters.', 'axs4all-ai'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($results as $result) : ?>
                        <tr>
                            <td><?php echo esc_html(strtoupper((string) $result['decision'])); ?></td>
                            <td><?php echo esc_html((string) $result['queue_id']); ?></td>
                            <td>
                                <?php if (! empty($result['source_url'])) : ?>
                                    <a href="<?php echo esc_url((string) $result['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(wp_trim_words((string) $result['source_url'], 6, '…')); ?></a>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) ($result['category'] ?? '—')); ?></td>
                            <td>
                                <?php
                                if ($result['confidence'] !== null) {
                                    echo esc_html(number_format((float) $result['confidence'] * 100, 1)) . '%';
                                } else {
                                    echo '&mdash;';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html((string) $result['prompt_version']); ?></td>
                            <td><?php echo esc_html((string) ($result['model'] ?? '—')); ?></td>
                            <td><?php echo esc_html(sprintf('%s / %s', $result['tokens_prompt'] ?? '—', $result['tokens_completion'] ?? '—')); ?></td>
                            <td><?php echo esc_html((string) ($result['duration_ms'] ?? '—')); ?></td>
                            <td><?php echo esc_html((string) $result['created_at']); ?></td>
                            <td>
                                <?php
                                $detailUrl = add_query_arg(
                                    array_merge($baseArgs, ['paged' => $currentPage, 'detail' => (int) $result['id']]),
                                    admin_url('admin.php')
                                );
                                ?>
                                <a href="<?php echo esc_url($detailUrl); ?>" class="button-link"><?php esc_html_e('View', 'axs4all-ai'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) : ?>
                <div class="tablenav bottom" style="margin-top:1rem;">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php
                            printf(
                                esc_html(_n('%d result', '%d results', $totalResults, 'axs4all-ai')),
                                $totalResults
                            );
                            ?>
                        </span>
                        <?php
                        $paginationArgs = array_merge($baseArgs, ['detail' => null]);
                        if ($currentPage > 1) {
                            $prevUrl = add_query_arg(array_merge($paginationArgs, ['paged' => $currentPage - 1]), admin_url('admin.php'));
                            echo '<a class="prev-page button" href="' . esc_url($prevUrl) . '">&lsaquo;</a> ';
                        }

                        echo '<span class="paging-input">' . esc_html($currentPage) . ' / ' . esc_html($totalPages) . '</span>';

                        if ($currentPage < $totalPages) {
                            $nextUrl = add_query_arg(array_merge($paginationArgs, ['paged' => $currentPage + 1]), admin_url('admin.php'));
                            echo ' <a class="next-page button" href="' . esc_url($nextUrl) . '">&rsaquo;</a>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
