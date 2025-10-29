<?php

declare(strict_types=1);

namespace Axs4allAi\Category;

use WP_Post;

final class CategoryRepository
{
    public const META_OPTIONS = '_axs4all_ai_category_options';

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

    public function create(string $name, array $options): ?int
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

        return (int) $postId;
    }

    public function update(int $id, string $name, array $options): bool
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
     * @return array{id: int, name: string, options: array<int, string>, created: string, updated: string}
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
            'created' => $post->post_date_gmt,
            'updated' => $post->post_modified_gmt,
        ];
    }
}
