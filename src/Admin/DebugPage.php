<?php

declare(strict_types=1);

namespace Axs4allAi\Admin;

use Axs4allAi\Data\SnapshotRepository;

final class DebugPage
{
    private const MENU_SLUG = 'axs4all-ai-debug';
    private const CLEAR_ACTION = 'axs4all_ai_clear_log';
    private const DOWNLOAD_ACTION = 'axs4all_ai_download_log';
    private const NONCE_ACTION = 'axs4all_ai_debug_log_action';

    private ?SnapshotRepository $snapshots;

    public function __construct(?SnapshotRepository $snapshots = null)
    {
        $this->snapshots = $snapshots;
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'axs4all-ai',
            __('Debug Log', 'axs4all-ai'),
            __('Debug Log', 'axs4all-ai'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function registerActions(): void
    {
        add_action('admin_post_' . self::CLEAR_ACTION, [$this, 'handleClear']);
        add_action('admin_post_' . self::DOWNLOAD_ACTION, [$this, 'handleDownload']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $logPath = $this->resolveLogPath();
        $preview = $this->getLogPreview($logPath);
        $message = isset($_GET['debug_log_message'])
            ? sanitize_text_field((string) $_GET['debug_log_message'])
            : null;
        $errorMessage = isset($_GET['debug_log_error'])
            ? sanitize_text_field((string) $_GET['debug_log_error'])
            : null;
        $viewSnapshotId = isset($_GET['snapshot_id']) ? absint((string) $_GET['snapshot_id']) : 0;
        $snapshotDetail = null;
        if ($viewSnapshotId > 0 && $this->snapshots instanceof SnapshotRepository) {
            $snapshotDetail = $this->snapshots->find($viewSnapshotId);
        }
        $recentSnapshots = $this->snapshots instanceof SnapshotRepository ? $this->snapshots->latest(10) : [];

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('axs4all AI Debug Log', 'axs4all-ai'); ?></h1>

            <?php $this->renderNotice($message, $errorMessage); ?>

            <p>
                <strong><?php esc_html_e('Log location:', 'axs4all-ai'); ?></strong>
                <?php echo $logPath !== null ? esc_html($logPath) : esc_html__('Not configured', 'axs4all-ai'); ?>
            </p>

            <p><?php esc_html_e('View the latest entries from the WordPress debug log without leaving the admin area. Use the actions below to download or clear the log file when needed.', 'axs4all-ai'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="button-group">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::DOWNLOAD_ACTION); ?>">
                <button type="submit" class="button"><?php esc_html_e('Download Log', 'axs4all-ai'); ?></button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="button-group" style="margin-top: 0.5rem;">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::CLEAR_ACTION); ?>">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Clear the debug log? This cannot be undone.', 'axs4all-ai')); ?>');">
                    <?php esc_html_e('Clear Log', 'axs4all-ai'); ?>
                </button>
            </form>

            <h2><?php esc_html_e('Recent Entries', 'axs4all-ai'); ?></h2>
            <?php if ($preview['error'] !== null) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html($preview['error']); ?></p>
                </div>
            <?php else : ?>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %d: number of log lines shown */
                        esc_html__('Showing the last %d lines.', 'axs4all-ai'),
                        count($preview['lines'])
                    );
                    ?>
                </p>
                <textarea readonly rows="20" style="width:100%; font-family: Menlo, Consolas, monospace;"><?php echo esc_textarea(implode("\n", $preview['lines'])); ?></textarea>
            <?php endif; ?>

            <?php if ($this->snapshots instanceof SnapshotRepository) : ?>
                <hr>
                <h2><?php esc_html_e('Recent Snapshots', 'axs4all-ai'); ?></h2>
                <p class="description">
                    <?php esc_html_e('These are the last pages captured by the crawler. Use them to double-check what the extractor and classifier received.', 'axs4all-ai'); ?>
                </p>

                <?php if (empty($recentSnapshots)) : ?>
                    <p><?php esc_html_e('No crawl snapshots stored yet.', 'axs4all-ai'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('ID', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Queue ID', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Source URL', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Category', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Fetched At', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Content Hash', 'axs4all-ai'); ?></th>
                                <th><?php esc_html_e('Actions', 'axs4all-ai'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSnapshots as $snapshot) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $snapshot['id']); ?></td>
                                    <td><?php echo esc_html((string) ($snapshot['queue_id'] ?? '—')); ?></td>
                                    <td class="column-primary" style="max-width: 240px; word-break: break-all;">
                                        <?php echo esc_html((string) ($snapshot['source_url'] ?? '')); ?>
                                    </td>
                                    <td><?php echo esc_html((string) ($snapshot['category'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) $snapshot['fetched_at']); ?></td>
                                    <td><code><?php echo esc_html(substr((string) $snapshot['content_hash'], 0, 12)); ?></code></td>
                                    <td>
                                        <?php
                                        $viewUrl = add_query_arg(
                                            [
                                                'page' => self::MENU_SLUG,
                                                'snapshot_id' => (int) $snapshot['id'],
                                            ],
                                            admin_url('admin.php')
                                        );
                                        ?>
                                        <a class="button button-small" href="<?php echo esc_url($viewUrl); ?>">
                                            <?php esc_html_e('View HTML', 'axs4all-ai'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($snapshotDetail !== null) : ?>
                    <hr>
                    <h3>
                        <?php
                        printf(
                            /* translators: %d snapshot id */
                            esc_html__('Snapshot #%d details', 'axs4all-ai'),
                            (int) $snapshotDetail['id']
                        );
                        ?>
                    </h3>
                    <p>
                        <strong><?php esc_html_e('Queue ID:', 'axs4all-ai'); ?></strong>
                        <?php echo esc_html((string) ($snapshotDetail['queue_id'] ?? '—')); ?>
                        <br>
                        <strong><?php esc_html_e('Source URL:', 'axs4all-ai'); ?></strong>
                        <?php echo esc_html((string) ($snapshotDetail['source_url'] ?? '')); ?>
                        <br>
                        <strong><?php esc_html_e('Fetched at:', 'axs4all-ai'); ?></strong>
                        <?php echo esc_html((string) $snapshotDetail['fetched_at']); ?>
                    </p>
                    <textarea readonly rows="20" style="width:100%; font-family: Menlo, Consolas, monospace;"><?php echo esc_textarea((string) $snapshotDetail['content']); ?></textarea>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handleClear(): void
    {
        $redirect = $this->ensureAuthorized();
        if ($redirect instanceof \WP_Error) {
            wp_die($redirect);
        }

        $logPath = $this->resolveLogPath();
        if ($logPath === null) {
            $this->redirectWithMessage('debug_log_error', __('No debug log path is configured.', 'axs4all-ai'));
        }

        if (! file_exists($logPath)) {
            $this->redirectWithMessage('debug_log_message', __('Log already empty (file missing).', 'axs4all-ai'));
        }

        if (! is_writable($logPath)) {
            $this->redirectWithMessage('debug_log_error', __('Unable to clear log: file is not writable.', 'axs4all-ai'));
        }

        $result = @file_put_contents($logPath, '');
        if ($result === false) {
            $this->redirectWithMessage('debug_log_error', __('Failed to clear the debug log.', 'axs4all-ai'));
        }

        $this->redirectWithMessage('debug_log_message', __('Debug log cleared.', 'axs4all-ai'));
    }

    public function handleDownload(): void
    {
        $authorized = $this->ensureAuthorized();
        if ($authorized instanceof \WP_Error) {
            wp_die($authorized);
        }

        $logPath = $this->resolveLogPath();
        if ($logPath === null || ! file_exists($logPath) || ! is_readable($logPath)) {
            $this->redirectWithMessage('debug_log_error', __('Unable to download: log file not found or not readable.', 'axs4all-ai'));
        }

        $handle = fopen($logPath, 'rb');
        if ($handle === false) {
            $this->redirectWithMessage('debug_log_error', __('Failed to open log file for download.', 'axs4all-ai'));
        }

        $size = filesize($logPath);
        nocache_headers();
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . basename($logPath) . '"');
        if ($size !== false) {
            header('Content-Length: ' . (string) $size);
        }

        while (! feof($handle)) {
            echo fread($handle, 8192);
        }

        fclose($handle);
        exit;
    }

    private function resolveLogPath(): ?string
    {
        if (defined('WP_DEBUG_LOG')) {
            if (is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '') {
                return WP_DEBUG_LOG;
            }

            if (WP_DEBUG_LOG === true) {
                $default = trailingslashit(WP_CONTENT_DIR) . 'debug.log';
                return $default;
            }
        }

        $iniPath = ini_get('error_log');
        if (is_string($iniPath) && $iniPath !== '') {
            return $iniPath;
        }

        $fallback = trailingslashit(WP_CONTENT_DIR) . 'debug.log';
        return $fallback;
    }

    /**
     * @return array{lines: array<int, string>, error: string|null}
     */
    private function getLogPreview(?string $path, int $lineCount = 200): array
    {
        if ($path === null) {
            return [
                'lines' => [],
                'error' => __('No debug log path is configured. Enable WP_DEBUG_LOG or set error_log.', 'axs4all-ai'),
            ];
        }

        if (! file_exists($path)) {
            return [
                'lines' => [],
                'error' => __('The debug log file has not been created yet.', 'axs4all-ai'),
            ];
        }

        if (! is_readable($path)) {
            return [
                'lines' => [],
                'error' => __('The debug log file is not readable. Check filesystem permissions.', 'axs4all-ai'),
            ];
        }

        try {
            $lines = $this->tailFile($path, $lineCount);
        } catch (\RuntimeException $exception) {
            return [
                'lines' => [],
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'lines' => $lines,
            'error' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tailFile(string $path, int $lines = 200): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        if ($totalLines === 0 && trim((string) $file->current()) === '') {
            return [];
        }

        $result = [];
        $start = max(0, $totalLines - $lines);

        for ($line = $start; $line <= $totalLines; $line++) {
            $file->seek($line);
            $result[] = rtrim((string) $file->current(), "\r\n");
        }

        return $result;
    }

    private function ensureAuthorized()
    {
        if (! current_user_can('manage_options')) {
            return new \WP_Error('forbidden', __('You are not allowed to manage the debug log.', 'axs4all-ai'));
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce((string) $_POST['_wpnonce'], self::NONCE_ACTION)) {
            return new \WP_Error('invalid_nonce', __('Security check failed. Please try again.', 'axs4all-ai'));
        }

        return true;
    }

    private function redirectWithMessage(string $param, string $value): void
    {
        $url = add_query_arg(
            [
                'page' => self::MENU_SLUG,
                $param => $value,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    private function renderNotice(?string $message, ?string $error): void
    {
        if ($message !== null && $message !== '') {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }

        if ($error !== null && $error !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }
    }
}
