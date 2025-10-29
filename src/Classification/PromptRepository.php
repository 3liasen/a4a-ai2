<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

final class PromptRepository
{
    /**
     * @var array<string, array{template: string, version: string}>
     */
    private array $defaults = [
        'default' => [
            'template' => <<<EOT
You are an accessibility compliance assistant.
Review the following context extracted from a hospitality website and answer strictly "yes" or "no" to the question:
"Does this content confirm that the venue is accessible for wheelchair users?"

Context:
{{context}}

Answer (only "yes" or "no"):
EOT,
            'version' => 'v1',
        ],
    ];

    public function getActiveTemplate(string $category): PromptTemplate
    {
        $key = strtolower($category);
        if ($key === '') {
            $key = 'default';
        }

        if (! isset($this->defaults[$key])) {
            $key = 'default';
        }

        $data = $this->defaults[$key];

        return new PromptTemplate($key, $data['template'], $data['version']);
    }
}
