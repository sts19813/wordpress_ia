<?php

namespace Tests\Feature\AiArticles;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\AiPromptProfile;
use App\Models\SourcePost;
use App\Models\User;
use App\Services\AiPromptProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiDraftWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_text_and_image_as_a_private_draft(): void
    {
        Storage::fake('local');
        config(['services.openai.api_key' => 'test-key']);
        $user = User::factory()->create();
        $profile = AiPromptProfile::query()->create([
            'user_id' => $user->id,
            'name' => 'Tecnología',
            'system_prompt' => AiPromptProfile::DEFAULT_SYSTEM_PROMPT,
            'model' => 'gpt-4.1-mini',
            'temperature' => 0.55,
            'writing_style' => 'periodístico',
            'tone' => 'objetivo',
            'content_length' => 'short',
            'language' => 'es',
            'audience' => 'lectores generales',
            'max_output_tokens' => 2500,
            'generate_image' => true,
            'image_model' => 'gpt-image-1.5',
            'image_size' => '1536x1024',
            'image_quality' => 'medium',
            'image_style' => 'fotografía editorial sin texto',
            'is_default' => true,
        ]);
        $sourcePost = SourcePost::query()->create([
            'title' => 'Fuente original',
            'content' => 'Hechos de la fuente.',
            'url' => 'https://example.com/fuente',
            'hash' => hash('sha256', 'draft-source'),
            'status' => SourcePost::STATUS_FETCHED,
            'language' => 'es',
        ]);
        $generated = json_encode([
            'title' => 'Una nota completamente nueva',
            'content' => '<h2>Contexto</h2><p onclick="alert(1)">Contenido <a href="https://example.com/fuente" onclick="alert(2)">original</a>.</p><script>alert(1)</script>',
            'excerpt' => 'Extracto nuevo',
            'meta_description' => 'Descripción para buscadores',
            'slug' => 'una-nota-completamente-nueva',
            'categories' => ['Tecnología'],
            'tags' => ['IA'],
            'seo_keywords' => ['innovación'],
            'faqs' => [['question' => '¿Qué ocurrió?', 'answer' => 'Esto ocurrió.']],
            'conclusion' => 'Conclusión.',
        ], JSON_THROW_ON_ERROR);

        Http::fake([
            '*/responses' => Http::response([
                'model' => 'gpt-4.1-mini-2025-04-14',
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => $generated]],
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 200, 'total_tokens' => 300],
            ]),
            '*/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
                'size' => '1536x1024',
                'quality' => 'medium',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('admin.ai-articles.store'), [
            'source_post_ids' => [$sourcePost->id],
            'ai_prompt_profile_id' => $profile->id,
        ]);

        $article = AiArticle::query()->sole();
        $image = AiImage::query()->sole();

        $response->assertRedirect(route('admin.ai-articles.show', $article));
        $this->assertSame(AiArticle::STATUS_DRAFT, $article->status);
        $this->assertSame($user->id, $article->user_id);
        $this->assertSame('Una nota completamente nueva', $article->title);
        $this->assertStringNotContainsString('onclick', $article->content);
        $this->assertStringNotContainsString('<script', $article->content);
        $this->assertStringContainsString('href="https://example.com/fuente"', $article->content);
        $this->assertSame(300, $article->tokens['total']);
        $this->assertSame(AiImage::STATUS_GENERATED, $image->status);
        $this->assertStringNotContainsString(base64_encode('fake-png'), $image->full_response);
        Storage::disk('local')->assertExists($image->file_path);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/responses')
            && $request['temperature'] === 0.55
            && data_get($request->data(), 'text.format.type') === 'json_schema'
            && str_contains($request['input'], 'Entre 400 y 600 palabras.'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/generations')
            && $request['model'] === 'gpt-image-1.5'
            && $request['size'] === '1536x1024');
    }

    public function test_generation_failure_is_saved_for_review_instead_of_losing_the_request(): void
    {
        config(['services.openai.api_key' => null]);
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $sourcePost = SourcePost::query()->create([
            'title' => 'Fuente',
            'url' => 'https://example.com/failure',
            'hash' => hash('sha256', 'failure-source'),
            'status' => SourcePost::STATUS_FETCHED,
        ]);

        $response = $this->actingAs($user)->post(route('admin.ai-articles.store'), [
            'source_post_ids' => [$sourcePost->id],
            'ai_prompt_profile_id' => $profile->id,
        ]);

        $article = AiArticle::query()->sole();
        $response->assertRedirect(route('admin.ai-articles.show', $article));
        $this->assertSame(AiArticle::STATUS_FAILED, $article->status);
        $this->assertStringContainsString('OPENAI_API_KEY', $article->generation_error);
    }
}
