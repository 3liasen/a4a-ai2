<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use Axs4allAi\Ai\AiClientInterface;
use Axs4allAi\Ai\Dto\PromptContext;

final class ClassificationRunner
{
    private PromptRepository $prompts;
    private AiClientInterface $client;

    public function __construct(PromptRepository $prompts, AiClientInterface $client)
    {
        $this->prompts = $prompts;
        $this->client = $client;
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     */
    public function process(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->handleJob($job);
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function handleJob(array $job): void
    {
        $category = isset($job['category']) ? (string) $job['category'] : 'default';
        $content = isset($job['content']) ? (string) $job['content'] : '';

        $template = $this->prompts->getActiveTemplate($category);

        $context = new PromptContext(
            $category,
            $content,
            $template->template(),
            [
                'category' => $category,
                'url' => isset($job['url']) ? (string) $job['url'] : '',
                'previous_decision' => isset($job['previous_decision']) ? (string) $job['previous_decision'] : '',
            ]
        );

        $result = $this->client->classify($context);

        // TODO: Persist result to classification tables.
        \error_log(
            sprintf(
                '[axs4all-ai] Classification stub processed job #%s. Decision: %s',
                isset($job['id']) ? (string) $job['id'] : 'unknown',
                $result->decision()
            )
        );
    }
}
