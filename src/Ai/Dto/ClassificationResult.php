<?php

declare(strict_types=1);

namespace Axs4allAi\Ai\Dto;

final class ClassificationResult
{
    private string $decision;
    private string $rawResponse;
    private ?float $confidence;
    /**
     * @var array<string, int|float|string|null>
     */
    private array $metrics;

    /**
     * @param array<string, int|float|string|null> $metrics
     */
    public function __construct(string $decision, string $rawResponse, ?float $confidence = null, array $metrics = [])
    {
        $this->decision = $decision;
        $this->rawResponse = $rawResponse;
        $this->confidence = $confidence;
        $this->metrics = $metrics;
    }

    public function decision(): string
    {
        return $this->decision;
    }

    public function rawResponse(): string
    {
        return $this->rawResponse;
    }

    public function confidence(): ?float
    {
        return $this->confidence;
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public function metrics(): array
    {
        return $this->metrics;
    }
}
