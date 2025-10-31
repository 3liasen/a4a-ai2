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
                    self::assertSame(3, $data['client_id']);
                    self::assertSame(7, $data['category_id']);
                    return true;
                })
            )
            ->willReturnCallback(function () use ($wpdb) {
                $wpdb->insert_id = 99;
                return true;
            });

        $repository = new ClassificationQueueRepository($wpdb);
        $id = $repository->enqueue(10, null, 'default', 'v1', 'sample', 3, 7);

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
                    self::assertSame('yes', $data['decision_value']);
                    self::assertSame('binary', $data['decision_scale']);
                    self::assertSame(21, $data['client_id']);
                    self::assertSame(4, $data['category_id']);
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
            'gpt',
            [
                'client_id' => 21,
                'category_id' => 4,
                'decision_value' => 'yes',
                'decision_scale' => 'binary',
            ]
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

    public function testGetResultsAppliesFiltersAndPagination(): void
    {
        $wpdb = new \wpdb();
        $wpdb->getResultsQueue[] = [
            [
                'id' => 1,
                'queue_id' => 10,
                'decision' => 'yes',
                'model' => 'gpt',
                'prompt_version' => 'v1',
                'raw_response' => '{"decision":"yes"}',
                'tokens_prompt' => 11,
                'tokens_completion' => 2,
                'duration_ms' => 40,
                'created_at' => '2024-01-15 10:00:00',
                'source_url' => 'https://example.com',
                'category' => 'museum',
                'confidence' => 0.9,
            ],
        ];

        $repository = new ClassificationQueueRepository($wpdb);
        $filters = [
            'decision' => 'yes',
            'model' => 'gpt',
            'prompt_version' => 'v1',
            'created_start' => '2024-01-01',
            'created_end' => '2024-01-31',
            'search' => 'example',
        ];

        $results = $repository->getResults($filters, 15, 2);

        self::assertCount(1, $results);
        self::assertSame('https://example.com', $results[0]['source_url']);

        $prepareLogs = array_values(array_filter(
            $wpdb->queryLog,
            static fn(array $entry): bool => $entry['type'] === 'prepare'
        ));

        self::assertNotEmpty($prepareLogs);
        $lastPrepare = end($prepareLogs);
        self::assertSame('yes', $lastPrepare['args'][0]);
        self::assertSame('gpt', $lastPrepare['args'][1]);
        self::assertSame('v1', $lastPrepare['args'][2]);
        self::assertSame('2024-01-01 00:00:00', $lastPrepare['args'][3]);
        self::assertSame('2024-01-31 23:59:59', $lastPrepare['args'][4]);
        self::assertSame('%example%', $lastPrepare['args'][5]);
        self::assertSame('%example%', $lastPrepare['args'][6]);
        self::assertSame(15, $lastPrepare['args'][7]);
        self::assertSame(15, $lastPrepare['args'][8]);
    }

    public function testCountResultsUsesFilters(): void
    {
        $wpdb = new \wpdb();
        $wpdb->getVarQueue[] = 42;

        $repository = new ClassificationQueueRepository($wpdb);
        $count = $repository->countResults(['decision' => 'no']);

        self::assertSame(42, $count);

        $prepareLogs = array_values(array_filter(
            $wpdb->queryLog,
            static fn(array $entry): bool => $entry['type'] === 'prepare'
        ));

        self::assertNotEmpty($prepareLogs);
        $lastPrepare = end($prepareLogs);
        self::assertSame('no', $lastPrepare['args'][0]);
    }

    public function testGetResultReturnsRow(): void
    {
        $wpdb = new \wpdb();
        $wpdb->getRowQueue[] = [
            'id' => 9,
            'queue_id' => 2,
            'decision' => 'yes',
            'raw_response' => '{"decision":"yes"}',
            'source_url' => 'https://example.com',
        ];

        $repository = new ClassificationQueueRepository($wpdb);
        $row = $repository->getResult(9);

        self::assertNotNull($row);
        self::assertSame(9, $row['id']);
        self::assertSame('https://example.com', $row['source_url']);
    }
}
