<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Category;

use Axs4allAi\Category\CategoryRegistrar;
use Axs4allAi\Category\CategoryRepository;
use PHPUnit\Framework\TestCase;

final class CategoryRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_posts'] = [];
        $GLOBALS['wp_posts_next_id'] = 0;
        $GLOBALS['wp_meta'] = [];
    }

    public function testSanitizeOptionsRemovesDuplicates(): void
    {
        $repository = new CategoryRepository();
        $options = $repository->sanitizeOptions([' Ramp ', 'Ramp', '']);

        self::assertSame(['Ramp'], $options);
    }

    public function testCreateStoresCategoryAndOptions(): void
    {
        $repository = new CategoryRepository();
        $id = $repository->create('Accessibility', ['Ramp', 'Accessible toilet']);

        self::assertNotNull($id);
        $post = get_post($id);
        self::assertInstanceOf(\WP_Post::class, $post);
        self::assertSame('Accessibility', $post->post_title);

        $meta = get_post_meta($id, CategoryRepository::META_OPTIONS, true);
        self::assertSame(['Ramp', 'Accessible toilet'], $meta);
    }

    public function testUpdateChangesNameAndOptions(): void
    {
        $repository = new CategoryRepository();
        $id = $repository->create('Accessibility', ['Ramp']);
        $success = $repository->update($id, 'Mobility', ['Lift']);

        self::assertTrue($success);
        $post = get_post($id);
        self::assertSame('Mobility', $post->post_title);
        $meta = get_post_meta($id, CategoryRepository::META_OPTIONS, true);
        self::assertSame(['Lift'], $meta);
    }

    public function testDeleteRemovesCategory(): void
    {
        $repository = new CategoryRepository();
        $id = $repository->create('Accessibility', ['Ramp']);
        $repository->delete($id);

        self::assertNull(get_post($id));
    }

    public function testAllReturnsMappedPosts(): void
    {
        $repository = new CategoryRepository();
        $repository->create('Accessibility', ['Ramp']);
        $repository->create('Mobility', ['Lift']);

        $items = $repository->all();

        self::assertCount(2, $items);
        self::assertSame('Accessibility', $items[0]['name']);
        self::assertSame(['Ramp'], $items[0]['options']);
    }
}
