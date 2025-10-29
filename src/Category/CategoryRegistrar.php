<?php

declare(strict_types=1);

namespace Axs4allAi\Category;

final class CategoryRegistrar
{
    public const POST_TYPE = 'axs4all_ai_category';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
    }

    public function registerPostType(): void
    {
        $labels = [
            'name' => __('Categories', 'axs4all-ai'),
            'singular_name' => __('Category', 'axs4all-ai'),
            'add_new' => __('Add New', 'axs4all-ai'),
            'add_new_item' => __('Add New Category', 'axs4all-ai'),
            'edit_item' => __('Edit Category', 'axs4all-ai'),
            'new_item' => __('New Category', 'axs4all-ai'),
            'view_item' => __('View Category', 'axs4all-ai'),
            'search_items' => __('Search Categories', 'axs4all-ai'),
            'not_found' => __('No categories found.', 'axs4all-ai'),
            'not_found_in_trash' => __('No categories found in Trash.', 'axs4all-ai'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ];

        register_post_type(self::POST_TYPE, $args);
    }
}
