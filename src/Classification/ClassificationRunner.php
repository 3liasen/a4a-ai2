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
    private ?ClassificationQueueRepository $queueRepository;
    private ?int $maxAttempts;
    /** @var array<int, array<string, mixed>|null> */
    private array $categoryCacheById = [];
    /** @var array<string, array<string, mixed>|null> */
    private array $categoryCacheByName = [];

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
        $categoryNameInput = isset($job['category']) ? (string) $job['category'] : 'default';
        $content = isset($job['content']) ? (string) $job['content'] : '';
        $clientId = isset($job['client_id']) ? (int) $job['client_id'] : 0;
        $categoryId = isset($job['category_id']) ? (int) $job['category_id'] : 0;

        if ($content === '') {
            $this->failJob($job, 'Missing classification content.', false);
            return;
        }

        $categoryData = $this->resolveCategoryData($categoryId, $categoryNameInput);
        $resolvedCategoryName = $categoryData['name'] ?? $categoryNameInput;
        $resolvedCategoryId = isset($categoryData['id']) ? (int) $categoryData['id'] : $categoryId;

        $decisionSet = isset($categoryData['decision_set']) ? (string) $categoryData['decision_set'] : DecisionSets::defaultSet();
        $decisionOptions = DecisionSets::options($decisionSet);
        if (empty($decisionOptions)) {
            $decisionSet = DecisionSets::defaultSet();
            $decisionOptions = DecisionSets::options($decisionSet);
        }

        $categoryOptions = $this->sanitizeStringList($categoryData['options'] ?? []);
        $categoryKeywords = $this->sanitizeStringList($categoryData['keywords'] ?? []);
        $categoryPhrases = $this->sanitizeStringList($categoryData['phrases'] ?? []);
        $basePrompt = isset($categoryData['base_prompt']) ? (string) $categoryData['base_prompt'] : '';

        $metadata = $this->buildMetadata(
            $job,
            $resolvedCategoryName,
            $decisionOptions,
            $decisionSet,
            $categoryOptions,
            $categoryKeywords,
            $categoryPhrases,
            $basePrompt
        );

        $template = $this->prompts->getActiveTemplate($resolvedCategoryName);
        $context = new PromptContext(
            $resolvedCategoryName,
            $content,
            $template->template(),
            $metadata
        );

        try {
            $result = $this->client->classify($context);
            $metrics = $result->metrics();
            $model = isset($metrics['model']) && is_string($metrics['model']) ? $metrics['model'] : null;

            if ($this->queueRepository instanceof ClassificationQueueRepository && $jobId > 0) {
                $this->queueRepository->markCompleted(
                    $job,
                    $result,
                    $metrics,
                    $model,
                    [
                        'client_id' => $clientId,
                        'category_id' => $resolvedCategoryId,
                        'decision_value' => $result->decision(),
                        'decision_scale' => $decisionSet,
                        'content_url' => $metadata['url'],
                    ]
                );
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
     * @return array<string, mixed>|null
     */
    private function resolveCategoryData(int $categoryId, string $fallbackName): ?array
    {
        $category = $this->getCategoryById($categoryId);
        if ($category !== null) {
            return $category;
        }

        return $this->getCategoryByName($fallbackName);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCategoryById(int $categoryId): ?array
    {
        if ($categoryId <= 0 || ! $this->categories instanceof CategoryRepository) {
            return null;
        }

        if (! array_key_exists($categoryId, $this->categoryCacheById)) {
            $category = $this->categories->find($categoryId);
            $this->categoryCacheById[$categoryId] = $category;

            if ($category !== null && isset($category['name'])) {
                $nameKey = strtolower((string) $category['name']);
                $this->categoryCacheByName[$nameKey] = $category;
            }
        }

        return $this->categoryCacheById[$categoryId] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCategoryByName(string $name): ?array
    {
        $nameKey = strtolower(trim($name));
        if ($nameKey === '' || ! $this->categories instanceof CategoryRepository) {
            return null;
        }

        if (isset($this->categoryCacheByName[$nameKey])) {
            return $this->categoryCacheByName[$nameKey];
        }

        if (empty($this->categoryCacheByName)) {
            foreach ($this->categories->all() as $category) {
                $id = (int) $category['id'];
                $key = strtolower((string) $category['name']);
                $this->categoryCacheById[$id] = $category;
                $this->categoryCacheByName[$key] = $category;
            }
        }

        return $this->categoryCacheByName[$nameKey] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @param mixed $values
     * @return array<int, string>
     */
    private function sanitizeStringList($values): array
    {
        if (is_string($values)) {
            $values = preg_split('/\r\n|\r|\n|,/', $values) ?: [];
        }

        if (! is_array($values)) {
            return [];
        }

        $clean = [];
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $clean[$text] = $text;
            }
        }

        return array_values($clean);
    }

    /**
     * @param array<string, mixed> $job
     * @param array<int, string> $decisionOptions
     * @param array<int, string> $features
     * @param array<int, string> $keywords
     * @param array<int, string> $phrases
     * @return array<string, string>
     */
    private function buildMetadata(
        array $job,
        string $categoryName,
        array $decisionOptions,
        string $decisionSet,
        array $features,
        array $keywords,
        array $phrases,
        string $categoryPrompt
    ): array {
        $url = '';
        if (! empty($job['content_url'])) {
            $url = (string) $job['content_url'];
        } elseif (! empty($job['url'])) {
            $url = (string) $job['url'];
        } elseif (! empty($job['source_url'])) {
            $url = (string) $job['source_url'];
        }

        $metadata = [
            'category' => $categoryName,
            'url' => $url,
            'previous_decision' => isset($job['previous_decision']) ? (string) $job['previous_decision'] : '',
            'decision_options' => implode(', ', $decisionOptions),
            'decision_options_raw' => implode('|', $decisionOptions),
            'decision_set' => $decisionSet,
            'category_prompt' => $this->composePrompt($categoryPrompt, $decisionOptions),
            'category_keywords' => ! empty($keywords) ? implode(', ', $keywords) : '',
            'category_phrases' => ! empty($phrases) ? implode("\n", $phrases) : '',
        ];

        if (! empty($features)) {
            $metadata['category_options'] = implode(', ', $features);
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $decisionOptions
     */
    private function composePrompt(string $categoryPrompt, array $decisionOptions): string
    {
        $segments = [];

        $categoryPrompt = trim($categoryPrompt);

        if ($categoryPrompt !== '') {
            $segments[] = $categoryPrompt;
        }

        $segments[] = sprintf(
            'Assess the context and choose the single best option from the decision options: %s.',
            implode(', ', $decisionOptions)
        );
        $segments[] = 'Respond with exactly one lowercase value from the decision options.';

        return implode("\n\n", array_filter(array_map('trim', $segments)));
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

        do_action('axs4all_ai_monitor_failure', 'classification', [
            'job_id' => $jobId,
            'message' => $reason,
            'retry' => $retry ? 'yes' : 'no',
        ]);
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
