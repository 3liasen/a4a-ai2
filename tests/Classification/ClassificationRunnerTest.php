<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Classification;

use Axs4allAi\Ai\AiClientInterface;
use Axs4allAi\Ai\Dto\ClassificationResult;
use Axs4allAi\Classification\ClassificationQueueRepository;
use Axs4allAi\Classification\ClassificationRunner;
use Axs4allAi\Classification\PromptRepository;
use Axs4allAi\Classification\PromptTemplate;
use Axs4allAi\Category\CategoryRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClassificationRunnerTest extends TestCase
{
    public function testProcessMarksCompleted(): void
    {
        $promptDb = new \wpdb();
        $promptDb->getRowQueue[] = [
            'id' => 1,
            'category' => 'default',
            'template' => 'Answer yes or no: {{context}}',
            'version' => 'v2',
            'is_active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];
        $promptRepo = new PromptRepository($promptDb);

        $categoryRepo = null;

        $result = new ClassificationResult('yes', '{"decision":"yes"}', 0.9, [
            'model' => 'gpt',
            'tokens_prompt' => 12,
            'tokens_completion' => 3,
            'duration_ms' => 45,
        ]);

        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->expects(self::once())
            ->method('classify')
            ->willReturn($result);

        $queueDb = new \wpdb();
        $queueRepo = new ClassificationQueueRepository($queueDb);

        $runner = new ClassificationRunner($promptRepo, $aiClient, $categoryRepo, $queueRepo);

        $runner->process([
            [
                'id' => 5,
                'category' => 'default',
                'content' => 'Example snippet about accessibility.',
                'url' => 'https://example.com',
                'attempts' => 1,
            ],
        ]);

        self::assertNotEmpty($queueDb->updateLog);
        self::assertNotEmpty($queueDb->insertLog);
        $update = $queueDb->updateLog[0];
        self::assertSame('wp_axs4all_ai_classifications_queue', $update['table']);
        self::assertSame('done', $update['data']['status']);
        $insert = $queueDb->insertLog[0];
        self::assertSame('wp_axs4all_ai_classifications', $insert['table']);
        self::assertSame('yes', $insert['data']['decision']);
    }

    public function testProcessMarksFailedOnException(): void
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

        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->expects(self::once())
            ->method('classify')
            ->willThrowException(new RuntimeException('<b>invalid</b> response'));

        $queueDb = new \wpdb();
        $queueRepo = new ClassificationQueueRepository($queueDb);

        $runner = new ClassificationRunner($promptRepo, $aiClient, null, $queueRepo);

        $runner->process([
            [
                'id' => 6,
                'category' => 'default',
                'content' => 'Some snippet',
                'attempts' => 1,
            ],
        ]);

        self::assertNotEmpty($queueDb->updateLog);
        $update = $queueDb->updateLog[0];
        self::assertSame('pending', $update['data']['status']);
        self::assertSame('invalid response', $update['data']['last_error']);
    }

    public function testProcessStopsRetryWhenMaxAttemptsReached(): void
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

        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->expects(self::once())
            ->method('classify')
            ->willThrowException(new RuntimeException('failure'));

        $queueDb = new \wpdb();
        $queueRepo = new ClassificationQueueRepository($queueDb);

        $runner = new ClassificationRunner($promptRepo, $aiClient, null, $queueRepo, 2);

        $runner->process([
            [
                'id' => 7,
                'category' => 'default',
                'content' => 'Snippet content',
                'attempts' => 2,
            ],
        ]);

        self::assertNotEmpty($queueDb->updateLog);
        $update = $queueDb->updateLog[0];
        self::assertSame('failed', $update['data']['status']);
    }
}
