<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

use Axs4allAi\Ai\AiClientInterface;
use Axs4allAi\Ai\Dto\PromptContext;
use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Data\ClientRepository;
final class ClassificationRunner
{
    private PromptRepository $prompts;
    private AiClientInterface $client;
    private ?CategoryRepository $categories;
    private ?ClientRepository $clients;
    private ?ClassificationQueueRepository $queueRepository;
    private ?int $maxAttempts;
    /** @var array<int, array<string, mixed>|null> */
    private array $categoryCacheById = [];
    /** @var array<string, array<string, mixed>|null> */
    private array $categoryCacheByName = [];
    /** @var array<int, array<string, mixed>|null> */
    private array $clientCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $clientCategoryOverrides = [];

    public function __construct(
        PromptRepository $prompts,
        AiClientInterface $client,
        ?CategoryRepository $categories = null,
        ?ClientRepository $clients = null,
        ?ClassificationQueueRepository $queueRepository = null,
        ?int $maxAttempts = null
    ) {
        $this->prompts = $prompts;
        $this->client = $client;
        $this->categories = $categories;
        $this->clients = $clients;
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

        $clientName = '';
        $clientPrompt = '';
        $clientPhrases = [];
        if ($clientId > 0) {
            $client = $this->getClient($clientId);
            if ($client !== null) {
                $clientName = isset($client['name']) ? (string) $client['name'] : '';
            }
            if ($resolvedCategoryId > 0) {
                $overrides = $this->getClientOverrides($clientId, $resolvedCategoryId);
                if (! empty($overrides['prompt'])) {
                    $clientPrompt = (string) $overrides['prompt'];
                }
                if (! empty($overrides['phrases'])) {
                    $clientPhrases = $this->sanitizeStringList($overrides['phrases']);
                }
            }
        }

        $allPhrases = $this->mergeUniqueLists($categoryPhrases, $clientPhrases);
        $metadata = $this->buildMetadata(
            $job,
            $resolvedCategoryName,
            $decisionOptions,
            $decisionSet,
            $categoryOptions,
            $categoryKeywords,
            $allPhrases,
            $basePrompt,
            $clientPrompt,
            $clientName
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
    private function getClient(int $clientId): ?array
    {
        if ($clientId <= 0 || ! $this->clients instanceof ClientRepository) {
            return null;
        }

        if (! array_key_exists($clientId, $this->clientCache)) {
            $this->clientCache[$clientId] = $this->clients->find($clientId);
        }

        return $this->clientCache[$clientId] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getClientOverrides(int $clientId, int $categoryId): array
    {
        if ($clientId <= 0 || $categoryId <= 0 || ! $this->clients instanceof ClientRepository) {
            return [];
        }

        $cacheKey = $clientId . ':' . $categoryId;
        if (! isset($this->clientCategoryOverrides[$cacheKey])) {
            $this->clientCategoryOverrides[$cacheKey] = $this->clients->getCategoryOverrides($clientId, $categoryId);
        }

        return $this->clientCategoryOverrides[$cacheKey];
    }

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
     * @param array<int, string> $primary
     * @param array<int, string> $secondary
     * @return array<int, string>
     */
    private function mergeUniqueLists(array $primary, array $secondary): array
    {
        $merged = [];
        foreach (array_merge($primary, $secondary) as $value) {
            $merged[$value] = $value;
        }

        return array_values($merged);
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
        string $categoryPrompt,
        string $clientPrompt,
        string $clientName
    ): array {
        $metadata = [
            'category' => $categoryName,
            'url' => isset($job['url']) ? (string) $job['url'] : '',
            'previous_decision' => isset($job['previous_decision']) ? (string) $job['previous_decision'] : '',
            'decision_options' => implode(', ', $decisionOptions),
            'decision_options_raw' => implode('|', $decisionOptions),
            'decision_set' => $decisionSet,
            'category_prompt' => $this->composePrompt($categoryPrompt, $clientPrompt, $decisionOptions),
            'category_keywords' => ! empty($keywords) ? implode(', ', $keywords) : '',
            'category_phrases' => ! empty($phrases) ? implode("\n", $phrases) : '',
        ];

        if (! empty($features)) {
            $metadata['category_options'] = implode(', ', $features);
        }

        if ($clientName !== '') {
            $metadata['client_name'] = $clientName;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $decisionOptions
     */
    private function composePrompt(string $categoryPrompt, string $clientPrompt, array $decisionOptions): string
    {
        $segments = [];

        $categoryPrompt = trim($categoryPrompt);
        $clientPrompt = trim($clientPrompt);

        if ($categoryPrompt !== '') {
            $segments[] = $categoryPrompt;
        }

        if ($clientPrompt !== '') {
            $segments[] = $clientPrompt;
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
