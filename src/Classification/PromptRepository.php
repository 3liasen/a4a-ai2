<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use wpdb;

final class PromptRepository
{
    private wpdb $wpdb;
    private string $table;

    /**
     * @var array<string, array{template: string, version: string}>
     */
    private array $defaults = [
        'default' => [
            'template' => <<<EOT
You are an accessibility compliance assistant.
Review the following context extracted from a hospitality website and answer strictly "yes" or "no" to the question:
"Does this content confirm that the venue is accessible for wheelchair users?"

Context:
{{context}}

Answer (only "yes" or "no"):
EOT,
            'version' => 'v1',
        ],
    ];

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $this->wpdb->prefix . 'axs4all_ai_prompts';
    }

    public function getActiveTemplate(string $category): PromptTemplate
    {
        $categoryKey = $category !== '' ? strtolower($category) : 'default';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE (category = %s OR (category = '' AND %s = 'default')) AND is_active = 1 ORDER BY updated_at DESC LIMIT 1",
                $categoryKey,
                $categoryKey
            ),
            ARRAY_A
        );

        if ($row !== null) {
            return $this->mapRow($row);
        }

        return $this->defaultTemplate($categoryKey);
    }

    /**
     * @return array<int, PromptTemplate>
     */
    public function all(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY category ASC, version DESC",
            ARRAY_A
        );

        if ($rows === null) {
            return [];
        }

        return array_map([$this, 'mapRow'], $rows);
    }

    public function find(int $id): ?PromptTemplate
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function save(string $category, string $template, string $version, bool $active): int
    {
        $now = current_time('mysql');

        if ($active) {
            $this->deactivateCategory($category);
        }

        $result = $this->wpdb->insert(
            $this->table,
            [
                'category' => $category,
                'template' => $template,
                'version' => $version,
                'is_active' => $active ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        if ($result === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $id, string $template, string $version, bool $active): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return false;
        }

        if ($active) {
            $this->deactivateCategory($row->category());
        }

        $updated = $this->wpdb->update(
            $this->table,
            [
                'template' => $template,
                'version' => $version,
                'is_active' => $active ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function deactivate(int $id): bool
    {
        $updated = $this->wpdb->update(
            $this->table,
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function activate(int $id): bool
    {
        $template = $this->find($id);
        if ($template === null) {
            return false;
        }

        $this->deactivateCategory($template->category());

        $updated = $this->wpdb->update(
            $this->table,
            [
                'is_active' => 1,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    private function deactivateCategory(string $category): void
    {
        $this->wpdb->update(
            $this->table,
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ],
            ['category' => $category],
            ['%d', '%s'],
            ['%s']
        );
    }

    private function mapRow(array $row): PromptTemplate
    {
        return new PromptTemplate(
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['category']) ? (string) $row['category'] : 'default',
            isset($row['template']) ? (string) $row['template'] : '',
            isset($row['version']) ? (string) $row['version'] : 'v1',
            isset($row['is_active']) ? ((int) $row['is_active'] === 1) : false,
            isset($row['created_at']) ? (string) $row['created_at'] : current_time('mysql'),
            isset($row['updated_at']) ? (string) $row['updated_at'] : current_time('mysql')
        );
    }

    private function defaultTemplate(string $category): PromptTemplate
    {
        $key = array_key_exists($category, $this->defaults) ? $category : 'default';
        $data = $this->defaults[$key];
        $now = current_time('mysql');

        return new PromptTemplate(
            null,
            $key,
            $data['template'],
            $data['version'],
            true,
            $now,
            $now
        );
    }
}
