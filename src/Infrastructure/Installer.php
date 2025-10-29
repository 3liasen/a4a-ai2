<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

use wpdb;

final class Installer
{
    private const DB_VERSION = '1.0.0';
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

        dbDelta([$queueSql, $snapshotsSql, $extractionsSql]);

        update_option(self::OPTION_KEY, self::DB_VERSION);
    }
}
