<?php

namespace App\Services\OpenAI;

use App\Services\OpenAI\Capabilities\ChatService;
use App\Services\OpenAI\Capabilities\EmbeddingsService;
use App\Services\OpenAI\Capabilities\ImagesService;
use App\Services\OpenAI\Capabilities\ModerationService;
use App\Services\OpenAI\Capabilities\ResponsesService;

class OpenAIService
{
    public function __construct(
        public readonly ResponsesService $responses,
        public readonly ChatService $chat,
        public readonly EmbeddingsService $embeddings,
        public readonly ImagesService $images,
        public readonly ModerationService $moderation,
        public readonly PromptManager $prompts,
        public readonly PromptRenderer $renderer,
    ) {}
}
