<?php

declare(strict_types=1);

namespace Axs4allAi\Tests\Ai;

use Axs4allAi\Ai\Dto\PromptContext;
use Axs4allAi\Ai\OpenAiClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class OpenAiClientTest extends TestCase
{
    public function testClassifyReturnsYesDecision(): void
    {
        $responsePayload = [
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4o-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => "yes\n\nThe venue is fully accessible.",
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 120,
                'completion_tokens' => 5,
            ],
        ];

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('send')
            ->with(self::callback(function (RequestInterface $request): bool {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('https://api.openai.com/v1/chat/completions', (string) $request->getUri());
                self::assertArrayHasKey('Authorization', $request->getHeaders());
                self::assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));

                $payload = json_decode((string) $request->getBody(), true);
                self::assertSame('gpt-4o-mini', $payload['model']);
                self::assertCount(2, $payload['messages']);

                return true;
            }))
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], json_encode($responsePayload, JSON_THROW_ON_ERROR)));

        $context = new PromptContext('default', 'Example context', 'Answer yes or no for {{context}}');
        $openAi = new OpenAiClient('test-key', 'gpt-4o-mini', 0.0, $client);
        $result = $openAi->classify($context);

        self::assertSame('yes', $result->decision());
        self::assertNotEmpty($result->rawResponse());
        self::assertSame(120, $result->metrics()['tokens_prompt']);
        self::assertSame(5, $result->metrics()['tokens_completion']);
        self::assertArrayHasKey('duration_ms', $result->metrics());
    }

    public function testClassifyThrowsForInvalidDecision(): void
    {
        $responsePayload = [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'maybe, it depends.',
                    ],
                ],
            ],
        ];

        $client = $this->createMock(ClientInterface::class);
        $client->method('send')
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], json_encode($responsePayload, JSON_THROW_ON_ERROR)));

        $context = new PromptContext('default', 'Context', '{{context}}');
        $openAi = new OpenAiClient('test-key', 'gpt-4o-mini', 0.0, $client);

        $this->expectException(RuntimeException::class);
        $openAi->classify($context);
    }
}
