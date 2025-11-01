<?php

declare(strict_types=1);

namespace Axs4allAi\Category;

use WP_Post;

final class CategoryRepository
{
    public const META_OPTIONS = '_axs4all_ai_category_options';
    private const META_BASE_PROMPT = '_axs4all_ai_category_base_prompt';
    private const META_KEYWORDS = '_axs4all_ai_category_keywords';
    private const META_PHRASES = '_axs4all_ai_category_phrases';
    private const META_DECISION_SET = '_axs4all_ai_category_decision_set';
    private const META_SNIPPET_LIMIT = '_axs4all_ai_category_snippet_limit';

    /**
     * @return array<int, array{id: int, name: string, options: array<int, string>, created: string, updated: string}>
     */
    public function all(): array
    {
        $posts = get_posts([
            'post_type' => CategoryRegistrar::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        return array_map([$this, 'mapPost'], $posts);
    }

    public function find(int $id): ?array
    {
        $post = get_post($id);
        if (! $post instanceof WP_Post || $post->post_type !== CategoryRegistrar::POST_TYPE) {
            return null;
        }

        return $this->mapPost($post);
    }

    public function create(string $name, array $options, array $meta = []): ?int
    {
        $name = $this->sanitizeName($name);
        if ($name === '') {
            return null;
        }

        $postId = wp_insert_post([
            'post_type' => CategoryRegistrar::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $name,
        ], true);

        if (is_wp_error($postId)) {
            return null;
        }

        $this->persistOptions((int) $postId, $options);
        $this->persistMeta((int) $postId, $meta);

        return (int) $postId;
    }

    public function update(int $id, string $name, array $options, array $meta = []): bool
    {
        $post = get_post($id);
        if (! $post instanceof WP_Post || $post->post_type !== CategoryRegistrar::POST_TYPE) {
            return false;
        }

        $name = $this->sanitizeName($name);
        if ($name === '') {
            return false;
        }

        $result = wp_update_post([
            'ID' => $id,
            'post_title' => $name,
        ], true);

        if (is_wp_error($result)) {
            return false;
        }

        $this->persistOptions($id, $options);
        $this->persistMeta($id, $meta);

        return true;
    }

    public function delete(int $id): bool
    {
        $post = get_post($id);
        if (! $post instanceof WP_Post || $post->post_type !== CategoryRegistrar::POST_TYPE) {
            return false;
        }

        $deleted = wp_delete_post($id, true);

        return $deleted !== false;
    }

    /**
     * @param array<int, string> $options
     */
    private function persistOptions(int $postId, array $options): void
    {
        update_post_meta($postId, self::META_OPTIONS, $this->sanitizeOptions($options));
    }

    private function persistMeta(int $postId, array $meta): void
    {
        update_post_meta($postId, self::META_BASE_PROMPT, isset($meta['base_prompt']) ? sanitize_textarea_field((string) $meta['base_prompt']) : '');
        update_post_meta($postId, self::META_KEYWORDS, $this->sanitizeKeywords($meta['keywords'] ?? []));
        update_post_meta($postId, self::META_PHRASES, $this->sanitizePhrases($meta['phrases'] ?? []));
        update_post_meta($postId, self::META_DECISION_SET, $this->sanitizeDecisionSet($meta['decision_set'] ?? ''));
        $snippetLimit = $this->sanitizeSnippetLimit($meta['snippet_limit'] ?? null);
        update_post_meta($postId, self::META_SNIPPET_LIMIT, $snippetLimit !== null ? $snippetLimit : '');
    }

    /**
     * @param array<int, string> $options
     * @return array<int, string>
     */
    public function sanitizeOptions(array $options): array
    {
        $sanitised = [];
        foreach ($options as $option) {
            $text = sanitize_text_field(wp_unslash((string) $option));
            if ($text !== '') {
                $sanitised[$text] = $text;
            }
        }

        return array_values($sanitised);
    }

    public function optionsForName(string $category): array
    {
        $category = trim($category);
        if ($category === '') {
            return [];
        }

        foreach ($this->all() as $item) {
            if (strcasecmp($item['name'], $category) === 0) {
                return $item['options'];
            }
        }

        return [];
    }

    private function sanitizeName(string $name): string
    {
        return sanitize_text_field(wp_unslash($name));
    }

    /**
     * @return array{id: int, name: string, options: array<int, string>, base_prompt: string, keywords: array<int,string>, phrases: array<int,string>, decision_set: string, created: string, updated: string}
     */
    private function mapPost(WP_Post $post): array
    {
        $options = get_post_meta($post->ID, self::META_OPTIONS, true);
        if (! is_array($options)) {
            $options = [];
        }

        return [
            'id' => (int) $post->ID,
            'name' => (string) $post->post_title,
            'options' => $this->sanitizeOptions($options),
            'base_prompt' => (string) get_post_meta($post->ID, self::META_BASE_PROMPT, true),
            'keywords' => $this->sanitizeKeywords(get_post_meta($post->ID, self::META_KEYWORDS, true)),
            'phrases' => $this->sanitizePhrases(get_post_meta($post->ID, self::META_PHRASES, true)),
            'decision_set' => $this->sanitizeDecisionSet((string) get_post_meta($post->ID, self::META_DECISION_SET, true)),
            'snippet_limit' => $this->sanitizeSnippetLimit(get_post_meta($post->ID, self::META_SNIPPET_LIMIT, true)),
            'created' => $post->post_date_gmt,
            'updated' => $post->post_modified_gmt,
        ];
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitizeKeywords($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $sanitised = [];
        foreach ($value as $keyword) {
            $text = sanitize_text_field((string) $keyword);
            if ($text !== '') {
                $sanitised[$text] = $text;
            }
        }

        return array_values($sanitised);
    }

    /**
     * @param mixed $value
     */
    private function sanitizeSnippetLimit($value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        $int = (int) $value;
        if ($int <= 0) {
            return null;
        }

        return min(10, max(1, $int));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitizePhrases($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $phrases = [];
        foreach ($value as $phrase) {
            $text = sanitize_text_field((string) $phrase);
            if ($text !== '') {
                $phrases[$text] = $text;
            }
        }

        return array_values($phrases);
    }

    private function sanitizeDecisionSet(string $set): string
    {
        $set = strtolower(trim($set));
        $allowed = ['binary', 'accessibility'];
        return in_array($set, $allowed, true) ? $set : 'binary';
    }
}
