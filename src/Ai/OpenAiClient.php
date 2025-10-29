<?php

declare(strict_types=1);

namespace Axs4allAi\Ai;

use Axs4allAi\Ai\Dto\ClassificationResult;
use Axs4allAi\Ai\Dto\PromptContext;
use RuntimeException;

final class OpenAiClient implements AiClientInterface
{
    private const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /** @var object|null */
    private $httpClient;
    private string $apiKey;
    private string $model;
    private float $temperature;
    private string $endpoint;
    private float $timeout;

    public function __construct(
        string $apiKey,
        string $model = 'gpt-4o-mini',
        float $temperature = 0.0,
        $httpClient = null,
        ?string $endpoint = null,
        float $timeout = 30.0
    ) {
        $this->apiKey = trim($apiKey);
        $this->model = $model;
        $this->temperature = $temperature;
        if ($httpClient !== null) {
            $this->httpClient = $httpClient;
        } elseif (class_exists('\GuzzleHttp\Client')) {
            $this->httpClient = new \GuzzleHttp\Client();
        } else {
            $this->httpClient = null;
        }
        $this->endpoint = $endpoint !== null ? rtrim($endpoint, '/') : self::DEFAULT_ENDPOINT;
        $this->timeout = max(1.0, $timeout);
    }

    public function classify(PromptContext $context): ClassificationResult
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $prompt = $this->buildPrompt($context);
        $startedAt = microtime(true);

        if ($this->httpClient !== null) {
            $rawBody = $this->sendWithHttpClient($prompt);
        } else {
            $rawBody = $this->sendWithWpHttp($prompt);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            throw new RuntimeException('OpenAI response could not be decoded.');
        }

        $content = $this->extractContent($data);
        $decision = $this->parseDecision($content);

        $usage = isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : [];
        $metrics = [
            'model' => isset($data['model']) ? (string) $data['model'] : $this->model,
            'tokens_prompt' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            'tokens_completion' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            'duration_ms' => $durationMs,
        ];

        return new ClassificationResult($decision, $rawBody, null, $metrics);
    }

    private function buildPrompt(PromptContext $context): string
    {
        $template = $context->template();
        $replacements = [
            '{{context}}' => $context->content(),
        ];

        foreach ($context->metadata() as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $replacements[$placeholder] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    private function sendWithHttpClient(string $prompt): string
    {
        if (! method_exists($this->httpClient, 'send')) {
            throw new RuntimeException('Configured HTTP client does not support send().');
        }

        if (! class_exists('\GuzzleHttp\Psr7\Request')) {
            throw new RuntimeException('Guzzle PSR-7 classes are not available.');
        }

        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            $this->endpoint,
            [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            $this->buildJsonPayload($prompt)
        );

        try {
            $response = $this->httpClient->send($request, ['timeout' => $this->timeout]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('OpenAI request failed: ' . $exception->getMessage(), 0, $exception);
        }

        return (string) $response->getBody();
    }

    private function sendWithWpHttp(string $prompt): string
    {
        if (! function_exists('wp_remote_post')) {
            throw new RuntimeException('HTTP client not available and wp_remote_post is undefined.');
        }

        $json = $this->buildJsonPayload($prompt);
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => $this->timeout,
            'body' => $json,
        ];

        $response = wp_remote_post($this->endpoint, $args);
        if (is_wp_error($response)) {
            throw new RuntimeException('OpenAI request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || $body === '') {
            throw new RuntimeException('OpenAI response body empty.');
        }

        return $body;
    }

    private function buildJsonPayload(string $prompt): string
    {
        $payload = [
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_completion_tokens' => 10,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an accessibility compliance assistant. Answer strictly with the single word "yes" or "no" in lowercase.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractContent(array $data): string
    {
        if (
            empty($data['choices'])
            || ! is_array($data['choices'])
            || ! isset($data['choices'][0]['message']['content'])
        ) {
            throw new RuntimeException('OpenAI response is missing choices content.');
        }

        $content = (string) $data['choices'][0]['message']['content'];

        if ($content === '') {
            throw new RuntimeException('OpenAI response contained empty content.');
        }

        return $content;
    }

    private function parseDecision(string $content): string
    {
        $normalized = strtolower(trim($content));
        $normalized = preg_replace('/[^a-z]/', ' ', $normalized);
        $normalized = trim((string) $normalized);

        if ($normalized === '') {
            throw new RuntimeException('Unable to determine decision from OpenAI response.');
        }

        $word = strtok($normalized, ' ');
        if ($word === false) {
            throw new RuntimeException('Unable to determine decision from OpenAI response.');
        }

        $word = strtolower($word);
        if ($word !== 'yes' && $word !== 'no') {
            throw new RuntimeException('OpenAI response must be strictly "yes" or "no". Received: ' . $word);
        }

        return $word;
    }
}
