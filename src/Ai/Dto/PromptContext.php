<?php

declare(strict_types=1);

namespace Axs4allAi\Ai\Dto;

final class PromptContext
{
    private string $category;
    private string $content;
    private string $template;
    /**
     * @var array<string, string>
     */
    private array $metadata;

    /**
     * @param array<string, string> $metadata
     */
    public function __construct(string $category, string $content, string $template, array $metadata = [])
    {
        $this->category = $category;
        $this->content = $content;
        $this->template = $template;
        $this->metadata = $metadata;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function template(): string
    {
        return $this->template;
    }

    /**
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
