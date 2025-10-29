<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Classification;

use Axs4allAi\Ai\Dto\ClassificationResult;
use Axs4allAi\Classification\ClassificationQueueRepository;
use PHPUnit\Framework\TestCase;

final class ClassificationQueueRepositoryTest extends TestCase
{
    public function testEnqueueReturnsInsertedId(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';

        $wpdb->expects(self::once())
            ->method('insert')
            ->with(
                'wp_axs4all_ai_classifications_queue',
                self::callback(function (array $data): bool {
                    self::assertSame(10, $data['queue_id']);
                    self::assertSame('default', $data['category']);
                    self::assertSame('pending', $data['status']);
                    self::assertSame('sample', $data['content']);
                    return true;
                })
            )
            ->willReturnCallback(function () use ($wpdb) {
                $wpdb->insert_id = 99;
                return true;
            });

        $repository = new ClassificationQueueRepository($wpdb);
        $id = $repository->enqueue(10, null, 'default', 'v1', 'sample');

        self::assertSame(99, $id);
    }

    public function testMarkCompletedUpdatesQueueAndStoresResult(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';

        $wpdb->expects(self::once())
            ->method('update')
            ->with(
                'wp_axs4all_ai_classifications_queue',
                self::callback(function (array $data): bool {
                    self::assertSame('done', $data['status']);
                    self::assertSame('', $data['last_error']);
                    return true;
                }),
                ['id' => 7],
                self::anything(),
                self::anything()
            )
            ->willReturn(1);

        $wpdb->expects(self::once())
            ->method('insert')
            ->with(
                'wp_axs4all_ai_classifications',
                self::callback(function (array $data): bool {
                    self::assertSame(5, $data['queue_id']);
                    self::assertSame('yes', $data['decision']);
                    self::assertSame(42, $data['tokens_prompt']);
                    self::assertSame(8, $data['tokens_completion']);
                    return true;
                })
            )
            ->willReturn(true);

        $repository = new ClassificationQueueRepository($wpdb);
        $result = new ClassificationResult('yes', '{"raw":"value"}', null, [
            'model' => 'gpt',
            'tokens_prompt' => 42,
            'tokens_completion' => 8,
            'duration_ms' => 100,
        ]);

        $repository->markCompleted(
            [
                'id' => 7,
                'queue_id' => 5,
                'extraction_id' => null,
                'prompt_version' => 'v2',
            ],
            $result,
            $result->metrics(),
            'gpt'
        );
    }

    public function testMarkFailedSanitisesError(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';

        $wpdb->expects(self::once())
            ->method('update')
            ->with(
                'wp_axs4all_ai_classifications_queue',
                self::callback(function (array $data): bool {
                    self::assertSame('pending', $data['status']);
                    self::assertSame('alert("bad") Alert script bad', $data['last_error']);
                    return true;
                }),
                ['id' => 3],
                self::anything(),
                self::anything()
            )
            ->willReturn(1);

        $repository = new ClassificationQueueRepository($wpdb);
        $repository->markFailed(['id' => 3], '<script>alert("bad")</script> Alert <b>script</b> bad');
    }
}
