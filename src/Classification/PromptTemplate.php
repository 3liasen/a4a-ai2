<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

final class PromptTemplate
{
    private ?int $id;
    private string $category;
    private string $template;
    private string $version;
    private bool $active;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(
        ?int $id,
        string $category,
        string $template,
        string $version,
        bool $active,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->category = $category;
        $this->template = $template;
        $this->version = $version;
        $this->active = $active;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): ?int
    {
        return $this->id;
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'template' => $this->template,
            'version' => $this->version,
            'active' => $this->active,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
