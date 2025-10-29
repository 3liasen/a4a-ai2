<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

final class PromptTemplate
{
    private string $category;
    private string $template;
    private string $version;

    public function __construct(string $category, string $template, string $version = 'v1')
    {
        $this->category = $category;
        $this->template = $template;
        $this->version = $version;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function version(): string
    {
        return $this->version;
    }
}
