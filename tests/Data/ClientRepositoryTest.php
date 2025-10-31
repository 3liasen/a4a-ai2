<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Data;

use Axs4allAi\Data\ClientRepository;
use PHPUnit\Framework\TestCase;

final class ClientRepositoryTest extends TestCase
{
    public function testFindByUrlReturnsClientWithMatchedUrl(): void
    {
        $wpdb = new \wpdb();
        $wpdb->getVarQueue[] = 5;
        $wpdb->getRowQueue[] = [
            'id' => 5,
            'name' => 'Example Client',
            'status' => 'active',
            'notes' => '',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ];
        $wpdb->getResultsQueue[] = [
            [
                'id' => 9,
                'client_id' => 5,
                'url' => 'https://example.com',
                'crawl_subpages' => 1,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ];
        $wpdb->getResultsQueue[] = [
            [
                'client_id' => 5,
                'category_id' => 12,
                'overrides' => json_encode(['prompt' => 'Additional info', 'phrases' => ['foo']]),
            ],
        ];

        $repository = new ClientRepository($wpdb);
        $client = $repository->findByUrl('https://example.com');

        self::assertNotNull($client);
        self::assertSame(5, $client['id']);
        self::assertSame('https://example.com', $client['matched_url']);
        self::assertCount(1, $client['urls']);
        self::assertTrue($client['urls'][0]['crawl_subpages']);
        self::assertContains(12, $client['categories']);
    }

    public function testGetCategoryAssignmentsReturnsDecodedOverrides(): void
    {
        $wpdb = new \wpdb();
        $wpdb->getResultsQueue[] = [
            [
                'category_id' => 3,
                'overrides' => json_encode(['phrases' => ['alpha', 'beta']]),
            ],
            [
                'category_id' => 4,
                'overrides' => null,
            ],
        ];

        $repository = new ClientRepository($wpdb);
        $assignments = $repository->getCategoryAssignments(10);

        self::assertCount(2, $assignments);
        self::assertSame([3, 4], $assignments);
    }
}
