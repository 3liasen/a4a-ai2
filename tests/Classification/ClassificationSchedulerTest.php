<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Classification;

use Axs4allAi\Ai\AiClientInterface;
use Axs4allAi\Ai\Dto\ClassificationResult;
use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\ClassificationRunner;
use Axs4allAi\Classification\ClassificationScheduler;
use Axs4allAi\Classification\PromptRepository;
use PHPUnit\Framework\TestCase;

final class ClassificationSchedulerTest extends TestCase
{
    public function testProcessCliRunsSingleBatch(): void
    {
        $promptDb = new \wpdb();
        $promptDb->getRowQueue[] = [
            'id' => 1,
            'category' => 'default',
            'template' => 'Answer yes or no: {{context}}',
            'version' => 'v1',
            'is_active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];
        $promptRepo = new PromptRepository($promptDb);

        $queueDb = new \wpdb();
        $queueDb->getColQueue[] = [10];
        $queueDb->getRowQueue[] = [
            'id' => 10,
            'category' => 'default',
            'content' => 'Snippet',
            'prompt_version' => 'v1',
            'attempts' => 1,
        ];
        $queueRepo = new ClassificationQueueRepository($queueDb);

        $aiClient = $this->createStub(AiClientInterface::class);
        $aiClient->method('classify')->willReturn(
            new ClassificationResult('yes', '{"decision":"yes"}')
        );

        $runner = new ClassificationRunner($promptRepo, $aiClient, null, $queueRepo);
        $scheduler = new ClassificationScheduler($queueRepo, $runner, 3);
        $scheduler->processCli([], ['batch' => 3]);

        self::assertNotEmpty($queueDb->updateLog);
        self::assertNotEmpty($queueDb->insertLog);
    }

    public function testProcessCliDrainContinuesUntilEmpty(): void
    {
        $promptDb = new \wpdb();
        $promptDb->getRowQueue[] = [
            'id' => 1,
            'category' => 'default',
            'template' => 'Answer yes or no: {{context}}',
            'version' => 'v1',
            'is_active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];
        $promptRepo = new PromptRepository($promptDb);

        $queueDb = new \wpdb();
        $queueDb->getColQueue[] = [11];
        $queueDb->getColQueue[] = [];
        $queueDb->getRowQueue[] = [
            'id' => 11,
            'category' => 'default',
            'content' => 'Snippet',
            'prompt_version' => 'v1',
            'attempts' => 1,
        ];
        $queueRepo = new ClassificationQueueRepository($queueDb);

        $aiClient = $this->createStub(AiClientInterface::class);
        $aiClient->method('classify')->willReturn(
            new ClassificationResult('no', '{"decision":"no"}')
        );

        $runner = new ClassificationRunner($promptRepo, $aiClient, null, $queueRepo);
        $scheduler = new ClassificationScheduler($queueRepo, $runner, 2);
        $scheduler->processCli([], ['batch' => 2, 'drain' => true]);

        self::assertCount(1, $queueDb->insertLog);
        self::assertGreaterThanOrEqual(1, count($queueDb->updateLog));
    }
}


