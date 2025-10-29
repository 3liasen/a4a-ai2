<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use Axs4allAi\Ai\AiClientInterface;
use Axs4allAi\Ai\Dto\PromptContext;
use Axs4allAi\Category\CategoryRepository;

final class ClassificationRunner
{
    private PromptRepository $prompts;
    private AiClientInterface $client;
    private ?CategoryRepository $categories;

    public function __construct(PromptRepository $prompts, AiClientInterface $client, ?CategoryRepository $categories = null)
    {
        $this->prompts = $prompts;
        $this->client = $client;
        $this->categories = $categories;
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
        $metadata = [
            'category' => $category,
            'url' => isset($job['url']) ? (string) $job['url'] : '',
            'previous_decision' => isset($job['previous_decision']) ? (string) $job['previous_decision'] : '',
        ];

        if ($this->categories instanceof CategoryRepository) {
            $options = $this->categories->optionsForName($category);
            if (! empty($options)) {
                $metadata['category_options'] = implode(', ', $options);
            }
        }

        $context = new PromptContext(
            $category,
            $content,
            $template->template(),
            $metadata
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
