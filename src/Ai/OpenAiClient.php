<?php

declare(strict_types=1);

namespace Axs4allAi\Ai;

use Axs4allAi\Ai\Dto\ClassificationResult;
use Axs4allAi\Ai\Dto\PromptContext;

final class OpenAiClient implements AiClientInterface
{
    private string $apiKey;
    private string $model;
    private float $temperature;

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini', float $temperature = 0.0)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->temperature = $temperature;
    }

    public function classify(PromptContext $context): ClassificationResult
    {
        $prompt = $this->buildPrompt($context);

        // TODO: Implement HTTP call to OpenAI. For now we return a stubbed response.
        $stubDecision = 'pending';
        $rawResponse = json_encode([
            'model' => $this->model,
            'decision' => $stubDecision,
            'prompt_preview' => mb_substr($prompt, 0, 120),
        ]);
        if ($rawResponse === false) {
            $rawResponse = '{"error":"unable to encode stub response"}';
        }

        return new ClassificationResult($stubDecision, $rawResponse, null, [
            'tokens_prompt' => null,
            'tokens_completion' => null,
            'duration_ms' => null,
        ]);
    }

    private function buildPrompt(PromptContext $context): string
    {
        $template = $context->template();
        $replacements = [
            '{{context}}' => $context->content(),
        ];

        foreach ($context->metadata() as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $replacements[$placeholder] = $value;
        }

        return strtr($template, $replacements);
    }
}
