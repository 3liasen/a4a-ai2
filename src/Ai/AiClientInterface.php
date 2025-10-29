<?php

declare(strict_types=1);

namespace Axs4allAi\Ai;

use Axs4allAi\Ai\Dto\ClassificationResult;
use Axs4allAi\Ai\Dto\PromptContext;

interface AiClientInterface
{
    public function classify(PromptContext $context): ClassificationResult;
}
