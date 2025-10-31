<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use Axs4allAi\Ai\AiClientInterface;
use Axs4allAi\Category\CategoryRepository;

final class ClassificationCommand
{
    private ClassificationRunner $runner;
    private ?ClassificationQueueRepository $queueRepository;
    private int $defaultLimit;

    public function __construct(
        PromptRepository $prompts,
        AiClientInterface $client,
        ?CategoryRepository $categories = null,
        ?ClassificationQueueRepository $queueRepository = null,
        ?ClassificationRunner $runner = null,
        int $defaultLimit = 5
    ) {
        $this->queueRepository = $queueRepository;
        $this->runner = $runner ?? new ClassificationRunner($prompts, $client, $categories, null, $queueRepository);
        $this->defaultLimit = max(1, $defaultLimit);
    }

    public function register(): void
    {
        if (! defined('WP_CLI') || ! \WP_CLI) {
            return;
        }

        \WP_CLI::add_command('axs4all-ai classify', [$this, 'handle']);
    }

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function handle(array $args, array $assocArgs): void
    {
        $limit = isset($assocArgs['limit']) ? max(1, (int) $assocArgs['limit']) : $this->defaultLimit;
        $jobs = [];

        if (! empty($assocArgs['content'])) {
            $jobs[] = [
                'id' => 0,
                'category' => $assocArgs['category'] ?? 'default',
                'content' => (string) $assocArgs['content'],
                'url' => $assocArgs['url'] ?? '',
                'previous_decision' => $assocArgs['previous_decision'] ?? '',
                'prompt_version' => $assocArgs['prompt_version'] ?? 'v1',
            ];
        } elseif ($this->queueRepository instanceof ClassificationQueueRepository) {
            $jobs = $this->queueRepository->claimBatch($limit);
            if (empty($jobs)) {
                if (defined('WP_CLI') && \WP_CLI) {
                    \WP_CLI::log('No pending classification jobs.');
                }
                return;
            }
        } else {
            if (defined('WP_CLI') && \WP_CLI) {
                \WP_CLI::error('No jobs to process. Provide --content or ensure the classification queue is configured.');
            }
            return;
        }

        $this->runner->process($jobs);

        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::success('Classification run completed. Check debug.log for details.');
        }
    }
}
