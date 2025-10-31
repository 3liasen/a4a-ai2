<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

use wpdb;

final class Installer
{
    private const DB_VERSION = '1.2.0';
    private const OPTION_KEY = 'axs4all_ai_db_version';

    public static function activate(): void
    {
        global $wpdb;

        self::createOrUpdateTables($wpdb);
    }

    public static function maybeUpgrade(): void
    {
        $current = get_option(self::OPTION_KEY);
        if ($current !== self::DB_VERSION) {
            global $wpdb;
            self::createOrUpdateTables($wpdb);
        }
    }

    private static function createOrUpdateTables(wpdb $wpdb): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $queueTable = $wpdb->prefix . 'axs4all_ai_queue';
        $snapshotsTable = $wpdb->prefix . 'axs4all_ai_snapshots';
        $extractionsTable = $wpdb->prefix . 'axs4all_ai_extractions';
        $clientsTable = $wpdb->prefix . 'axs4all_ai_clients';
        $clientUrlsTable = $wpdb->prefix . 'axs4all_ai_client_urls';
        $clientCategoriesTable = $wpdb->prefix . 'axs4all_ai_client_categories';
        $promptsTable = $wpdb->prefix . 'axs4all_ai_prompts';
        $classificationQueueTable = $wpdb->prefix . 'axs4all_ai_classifications_queue';
        $classificationsTable = $wpdb->prefix . 'axs4all_ai_classifications';

        $queueSql = "CREATE TABLE {$queueTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_url TEXT NOT NULL,
            url_hash CHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            category VARCHAR(64) NOT NULL DEFAULT '',
            priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            last_attempted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash),
            KEY status (status),
            KEY category (category)
        ) {$charsetCollate};";

        $snapshotsSql = "CREATE TABLE {$snapshotsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            queue_id BIGINT UNSIGNED NOT NULL,
            content LONGTEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            fetched_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY queue_id (queue_id)
        ) {$charsetCollate};";

        $extractionsSql = "CREATE TABLE {$extractionsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            queue_id BIGINT UNSIGNED NOT NULL,
            extracted_text LONGTEXT NOT NULL,
            language VARCHAR(12) DEFAULT NULL,
            extracted_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY queue_id (queue_id)
        ) {$charsetCollate};";

        $clientsSql = "CREATE TABLE {$clientsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY name (name)
        ) {$charsetCollate};";

        $clientUrlsSql = "CREATE TABLE {$clientUrlsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            url TEXT NOT NULL,
            crawl_subpages TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) {$charsetCollate};";

        $clientCategoriesSql = "CREATE TABLE {$clientCategoriesTable} (
            client_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            overrides LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (client_id, category_id),
            KEY category_id (category_id)
        ) {$charsetCollate};";

        $promptsSql = "CREATE TABLE {$promptsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category VARCHAR(64) NOT NULL DEFAULT '',
            version VARCHAR(32) NOT NULL DEFAULT 'v1',
            template LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY category_version (category, version),
            KEY active_category (is_active, category)
        ) {$charsetCollate};";

        $classificationQueueSql = "CREATE TABLE {$classificationQueueTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            queue_id BIGINT UNSIGNED NOT NULL,
            extraction_id BIGINT UNSIGNED DEFAULT NULL,
            client_id BIGINT UNSIGNED DEFAULT NULL,
            category_id BIGINT UNSIGNED DEFAULT NULL,
            category VARCHAR(64) NOT NULL DEFAULT '',
            prompt_version VARCHAR(32) NOT NULL DEFAULT 'v1',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            content LONGTEXT NOT NULL,
            last_error TEXT NULL,
            locked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY queue_id (queue_id),
            KEY extraction_id (extraction_id),
            KEY client_id (client_id),
            KEY category_id (category_id)
        ) {$charsetCollate};";

        $classificationsSql = "CREATE TABLE {$classificationsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            queue_id BIGINT UNSIGNED NOT NULL,
            extraction_id BIGINT UNSIGNED DEFAULT NULL,
            client_id BIGINT UNSIGNED DEFAULT NULL,
            category_id BIGINT UNSIGNED DEFAULT NULL,
            decision VARCHAR(32) NOT NULL,
            decision_value VARCHAR(64) DEFAULT NULL,
            decision_scale VARCHAR(64) DEFAULT NULL,
            confidence DECIMAL(5,4) DEFAULT NULL,
            prompt_version VARCHAR(32) NOT NULL DEFAULT 'v1',
            model VARCHAR(64) DEFAULT NULL,
            tokens_prompt INT UNSIGNED DEFAULT NULL,
            tokens_completion INT UNSIGNED DEFAULT NULL,
            duration_ms INT UNSIGNED DEFAULT NULL,
            raw_response LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY queue_id (queue_id),
            KEY extraction_id (extraction_id),
            KEY client_id (client_id),
            KEY category_id (category_id),
            KEY decision (decision)
        ) {$charsetCollate};";

        dbDelta([$queueSql, $snapshotsSql, $extractionsSql, $clientsSql, $clientUrlsSql, $clientCategoriesSql, $promptsSql, $classificationQueueSql, $classificationsSql]);

        update_option(self::OPTION_KEY, self::DB_VERSION);
    }
}
