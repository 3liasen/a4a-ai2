<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Classification;

use Axs4allAi\Classification\PromptRepository;
use PHPUnit\Framework\TestCase;

final class PromptRepositoryTest extends TestCase
{
    public function testGetActiveTemplateFallsBackToDefault(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        $wpdb->method('prepare')->willReturn('prepared');
        $wpdb->method('get_row')->willReturn(null);

        $repository = new PromptRepository($wpdb);
        $template = $repository->getActiveTemplate('unknown');

        self::assertSame('default', $template->category());
        self::assertStringContainsString('Context', $template->template());
    }

    public function testGetActiveTemplateUsesDatabaseRow(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        $wpdb->method('prepare')->willReturn('prepared');
        $wpdb->method('get_row')->willReturn([
            'id' => 5,
            'category' => 'restaurant',
            'template' => 'Template {{context}}',
            'version' => 'v3',
            'is_active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ]);

        $repository = new PromptRepository($wpdb);
        $template = $repository->getActiveTemplate('restaurant');

        self::assertSame('restaurant', $template->category());
        self::assertSame('v3', $template->version());
    }

    public function testSaveActivatesTemplate(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        $wpdb->method('prepare')->willReturn('prepared');

        $wpdb->expects(self::once())
            ->method('update')
            ->with(
                'wp_axs4all_ai_prompts',
                self::callback(function (array $data): bool {
                    return $data['is_active'] === 0;
                }),
                ['category' => 'default'],
                self::anything(),
                self::anything()
            )
            ->willReturn(1);

        $wpdb->expects(self::once())
            ->method('insert')
            ->with(
                'wp_axs4all_ai_prompts',
                self::callback(function (array $data): bool {
                    self::assertSame('default', $data['category']);
                    self::assertSame('v2', $data['version']);
                    self::assertSame(1, $data['is_active']);
                    return true;
                })
            )
            ->willReturnCallback(function () use ($wpdb) {
                $wpdb->insert_id = 11;
                return true;
            });

        $repository = new PromptRepository($wpdb);
        $id = $repository->save('default', 'Prompt body', 'v2', true);

        self::assertSame(11, $id);
    }
}
