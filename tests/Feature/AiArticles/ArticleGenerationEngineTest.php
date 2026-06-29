<?php

namespace Tests\Feature\AiArticles;

use App\Models\AiArticle;
use App\Models\SourcePost;
use App\Services\AiArticles\ArticleGenerationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleGenerationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prepares_article_generation_from_one_or_more_source_posts(): void
    {
        $sourcePost = SourcePost::query()->create([
            'title' => 'Noticia original',
            'content' => 'Contenido base de la noticia.',
            'content_html' => '<p>Contenido base de la noticia.</p>',
            'summary' => 'Resumen base',
            'author' => 'Autor',
            'published_at' => '2026-06-29 12:00:00',
            'url' => 'https://example.com/noticia',
            'hash' => hash('sha256', 'source-post'),
            'categories' => ['Tecnología'],
            'tags' => ['IA'],
            'status' => 'fetched',
            'original_json' => ['raw' => true],
            'language' => 'es',
        ]);

        $result = app(ArticleGenerationEngine::class)->prepare([$sourcePost], [
            'model' => 'test-model',
            'temperature' => 0.65,
        ]);

        $this->assertSame(AiArticle::STATUS_PENDING, $result->article->status);
        $this->assertSame([$sourcePost->id], $result->article->source_post_ids);
        $this->assertStringContainsString('No copies frases', $result->article->prompt_used);
        $this->assertStringContainsString('Noticia original', $result->article->prompt_used);
        $this->assertSame('responses', $result->request->capability);
        $this->assertSame('test-model', $result->request->payload['model']);
        $this->assertSame(0.65, $result->request->payload['temperature']);
    }

    public function test_it_completes_generated_article_with_response_and_metrics(): void
    {
        $article = AiArticle::query()->create([
            'prompt_used' => 'Prompt utilizado',
            'model' => 'test-model',
            'temperature' => 0.7,
            'status' => AiArticle::STATUS_PENDING,
        ]);

        app(ArticleGenerationEngine::class)->complete($article, [
            'title' => 'Título nuevo',
            'content' => 'Contenido completamente nuevo.',
            'excerpt' => 'Extracto',
            'meta_description' => 'Meta description',
            'slug' => 'titulo-nuevo',
            'categories' => ['Tecnología'],
            'tags' => ['IA'],
            'seo_keywords' => ['automatización editorial'],
            'faqs' => [
                ['question' => '¿Qué pasó?', 'answer' => 'Una respuesta.'],
            ],
            'conclusion' => 'Conclusión final.',
        ], [
            'tokens' => ['input' => 100, 'output' => 200, 'total' => 300],
            'cost' => 0.012345,
            'duration_ms' => 1530,
            'model' => 'test-model',
            'temperature' => 0.7,
        ]);

        $article->refresh();

        $this->assertSame(AiArticle::STATUS_GENERATED, $article->status);
        $this->assertSame('Título nuevo', $article->title);
        $this->assertSame('Contenido completamente nuevo.', $article->content);
        $this->assertSame(['Tecnología'], $article->categories);
        $this->assertSame(['automatización editorial'], $article->seo_keywords);
        $this->assertSame(1530, $article->duration_ms);
        $this->assertSame('0.012345', $article->cost);
        $this->assertStringContainsString('Título nuevo', $article->full_response);
    }
}
