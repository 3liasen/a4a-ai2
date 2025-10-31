<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use Axs4allAi\Ai\AiClientInterface;
use Axs4allAi\Ai\Dto\PromptContext;
use Axs4allAi\Category\CategoryRepository;
use RuntimeException;

final class ClassificationRunner
{
    private PromptRepository $prompts;
    private AiClientInterface $client;
    private ?CategoryRepository $categories;
    private ?ClassificationQueueRepository $queueRepository;
    private ?int $maxAttempts;

    public function __construct(
        PromptRepository $prompts,
        AiClientInterface $client,
        ?CategoryRepository $categories = null,
        ?ClassificationQueueRepository $queueRepository = null,
        ?int $maxAttempts = null
    ) {
        $this->prompts = $prompts;
        $this->client = $client;
        $this->categories = $categories;
        $this->queueRepository = $queueRepository;
        $this->maxAttempts = ($maxAttempts !== null && $maxAttempts > 0) ? $maxAttempts : null;
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
        $jobId = isset($job['id']) ? (int) $job['id'] : 0;
        $category = isset($job['category']) ? (string) $job['category'] : 'default';
        $content = isset($job['content']) ? (string) $job['content'] : '';

        if ($content === '') {
            $this->failJob($job, 'Missing classification content.', false);
            return;
        }

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

        try {
            $result = $this->client->classify($context);
            $metrics = $result->metrics();
            $model = isset($metrics['model']) && is_string($metrics['model']) ? $metrics['model'] : null;

            if ($this->queueRepository instanceof ClassificationQueueRepository && $jobId > 0) {
                $this->queueRepository->markCompleted($job, $result, $metrics, $model);
            }

            \error_log(
                sprintf(
                    '[axs4all-ai] Classification processed job #%s. Decision: %s',
                    $jobId > 0 ? (string) $jobId : 'manual',
                    $result->decision()
                )
            );
        } catch (\Throwable $exception) {
            $retry = $this->shouldRetry($job);
            $this->failJob($job, $exception->getMessage(), $retry);
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function failJob(array $job, string $reason, bool $retry = true): void
    {
        $jobId = isset($job['id']) ? (int) $job['id'] : 0;

        if ($this->queueRepository instanceof ClassificationQueueRepository && $jobId > 0) {
            $this->queueRepository->markFailed($job, $reason, $retry);
        }

        \error_log(
            sprintf(
                '[axs4all-ai] Classification job failure #%s: %s',
                $jobId > 0 ? (string) $jobId : 'manual',
                $reason
            )
        );
    }

    /**
     * @param array<string, mixed> $job
     */
    private function shouldRetry(array $job): bool
    {
        if ($this->maxAttempts === null) {
            return true;
        }

        if (! isset($job['attempts'])) {
            return true;
        }

        $attempts = (int) $job['attempts'];

        return $attempts < $this->maxAttempts;
    }
}
