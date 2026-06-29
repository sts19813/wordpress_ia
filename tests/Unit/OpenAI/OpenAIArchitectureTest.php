<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\OpenAIService;
use App\Services\OpenAI\PromptManager;
use App\Services\OpenAI\PromptRenderer;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class OpenAIArchitectureTest extends TestCase
{
    public function test_prompt_renderer_replaces_variables(): void
    {
        $renderer = new PromptRenderer;

        $result = $renderer->render('Título: {{title}} / Categorías: {{categories}}', [
            'title' => 'Nota prueba',
            'categories' => ['Noticias', 'IA'],
        ]);

        $this->assertSame('Título: Nota prueba / Categorías: Noticias, IA', $result);
    }

    public function test_prompt_manager_loads_markdown_prompt_files(): void
    {
        $manager = new PromptManager(new Filesystem);

        $this->assertTrue($manager->exists('news-summary'));
        $this->assertStringContainsString('{{title}}', $manager->get('news-summary'));
        $this->assertContains('news-summary', $manager->all());
    }

    public function test_openai_service_prepares_requests_without_calling_api(): void
    {
        $openAI = app(OpenAIService::class);

        $responses = $openAI->responses->createFromPrompt('news-summary', [
            'title' => 'Título',
            'content' => 'Contenido',
            'categories' => ['Tecnología'],
        ]);
        $chat = $openAI->chat->createFromPrompt('news-summary', ['title' => 'Título']);
        $embeddings = $openAI->embeddings->create('Texto para vector');
        $images = $openAI->images->generateFromPrompt('news-image', ['title' => 'Título']);
        $moderation = $openAI->moderation->createFromPrompt('news-moderation', ['content' => 'Contenido']);

        $this->assertSame('responses', $responses->capability);
        $this->assertSame('chat', $chat->capability);
        $this->assertSame('embeddings', $embeddings->capability);
        $this->assertSame('images', $images->capability);
        $this->assertSame('moderation', $moderation->capability);
        $this->assertStringContainsString('Título', $responses->payload['input']);
        $this->assertSame('Texto para vector', $embeddings->payload['input']);
    }
}
