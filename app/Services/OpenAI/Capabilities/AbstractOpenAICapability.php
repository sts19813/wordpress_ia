<?php

namespace App\Services\OpenAI\Capabilities;

use App\Services\OpenAI\PromptManager;
use App\Services\OpenAI\PromptRenderer;

abstract class AbstractOpenAICapability
{
    public function __construct(
        protected readonly PromptManager $prompts,
        protected readonly PromptRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     */
    protected function renderPrompt(string $prompt, array $variables = []): string
    {
        return $this->renderer->render(
            $this->prompts->get($prompt),
            $variables,
        );
    }
}
