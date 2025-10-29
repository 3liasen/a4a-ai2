<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use Axs4allAi\Ai\AiClientInterface;

final class ClassificationCommand
{
    private ClassificationRunner $runner;

    public function __construct(PromptRepository $prompts, AiClientInterface $client)
    {
        $this->runner = new ClassificationRunner($prompts, $client);
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
        $jobs = [
            [
                'id' => 0,
                'category' => $assocArgs['category'] ?? 'default',
                'content' => $assocArgs['content'] ?? 'This venue offers step-free access and wide doorways.',
                'url' => $assocArgs['url'] ?? '',
                'previous_decision' => '',
            ],
        ];

        $this->runner->process($jobs);

        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::success('Classification stub executed. Check debug.log for details.');
        }
    }
}
