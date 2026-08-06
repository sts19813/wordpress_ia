<?php

namespace Tests\Feature\AiArticles;

use App\Jobs\GenerateAiArticle;
use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Models\User;
use App\Services\AiPromptProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiGenerationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_request_returns_immediately_and_adds_a_tracked_job_to_the_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $source = SourcePost::query()->create([
            'title' => 'Noticia para la cola',
            'url' => 'https://example.com/queued',
            'hash' => hash('sha256', 'queued-source'),
            'status' => SourcePost::STATUS_FETCHED,
        ]);

        $response = $this->actingAs($user)->post(route('admin.ai-articles.store'), [
            'source_post_ids' => [$source->id],
            'ai_prompt_profile_id' => $profile->id,
        ]);

        $task = Scheduler::query()->sole();
        $response->assertRedirect(route('admin.scheduler.index', ['task' => $task->id]));
        $this->assertSame(Scheduler::STATUS_QUEUED, $task->status);
        $this->assertDatabaseCount('ai_articles', 0);
        Queue::assertPushedOn('ai-text', GenerateAiArticle::class);

        $this->actingAs($user)
            ->get(route('admin.scheduler.index', ['task' => $task->id]))
            ->assertOk()
            ->assertSee('Programador')
            ->assertSee('En cola')
            ->assertSee('task-progress-wrap', false)
            ->assertDontSee('Solicitud recibida y añadida a la cola.');

        $this->actingAs($user)
            ->getJson(route('admin.scheduler.status', $task))
            ->assertOk()
            ->assertJsonPath('status', Scheduler::STATUS_QUEUED)
            ->assertJsonPath('progress', 0);
    }

    public function test_every_authenticated_user_can_request_a_queued_task_without_running_it_in_the_http_request(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['services.openai.api_key' => 'test-key']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($owner);
        $source = SourcePost::query()->create([
            'title' => 'Noticia visible para todos',
            'content' => 'Contenido base.',
            'url' => 'https://example.com/global-task',
            'hash' => hash('sha256', 'global-task'),
            'status' => SourcePost::STATUS_FETCHED,
        ]);
        $generated = json_encode([
            'title' => 'Borrador ejecutado manualmente',
            'content' => '<p>Contenido generado.</p>',
            'excerpt' => 'Extracto',
            'meta_description' => 'Descripción',
            'slug' => 'borrador-ejecutado-manualmente',
            'categories' => ['Noticias'],
            'tags' => ['Demo'],
            'seo_keywords' => ['manual'],
            'faqs' => [],
            'conclusion' => 'Fin.',
        ], JSON_THROW_ON_ERROR);

        Http::fake([
            '*/responses' => Http::response([
                'model' => 'gpt-4.1-mini',
                'output' => [['content' => [['type' => 'output_text', 'text' => $generated]]]],
                'usage' => ['total_tokens' => 100],
            ]),
            '*/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-image')]],
                'size' => '1536x1024',
                'quality' => 'medium',
            ]),
        ]);

        $this->actingAs($owner)->post(route('admin.ai-articles.store'), [
            'source_post_ids' => [$source->id],
            'ai_prompt_profile_id' => $profile->id,
        ])->assertRedirect();

        $task = Scheduler::query()->sole();

        $this->actingAs($other)
            ->get(route('admin.scheduler.status', $task))
            ->assertOk()
            ->assertJsonPath('status', Scheduler::STATUS_QUEUED);

        $this->actingAs($other)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee('Ejecutar');

        $this->actingAs($other)
            ->post(route('admin.scheduler.execute', $task))
            ->assertRedirect(route('admin.scheduler.index', ['task' => $task->id]))
            ->assertSessionHas('status', 'El proceso continuará en segundo plano. Puedes seguir su avance en esta pantalla.');

        $this->assertSame(Scheduler::STATUS_QUEUED, $task->fresh()->status);
        $this->assertDatabaseCount('ai_articles', 0);
        $this->assertDatabaseCount('ai_images', 0);
        $this->assertTrue(collect($task->fresh()->events)->contains(
            fn (array $event) => str_contains($event['message'], 'continuará en segundo plano'),
        ));
        Queue::assertPushed(GenerateAiArticle::class);
    }

    public function test_database_worker_processes_text_and_image_as_separate_stages(): void
    {
        Storage::fake('local');
        config([
            'queue.default' => 'database',
            'services.openai.api_key' => 'test-key',
        ]);
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $source = SourcePost::query()->create([
            'title' => 'Noticia asíncrona',
            'content' => 'Contenido base.',
            'url' => 'https://example.com/async',
            'hash' => hash('sha256', 'async-source'),
            'status' => SourcePost::STATUS_FETCHED,
        ]);
        $generated = json_encode([
            'title' => 'Borrador desde la cola',
            'content' => '<p>Contenido generado.</p>',
            'excerpt' => 'Extracto',
            'meta_description' => 'Descripción',
            'slug' => 'borrador-desde-la-cola',
            'categories' => ['Noticias'],
            'tags' => ['Demo'],
            'seo_keywords' => ['cola'],
            'faqs' => [],
            'conclusion' => 'Fin.',
        ], JSON_THROW_ON_ERROR);

        Http::fake([
            '*/responses' => Http::response([
                'model' => 'gpt-4.1-mini',
                'output' => [['content' => [['type' => 'output_text', 'text' => $generated]]]],
                'usage' => ['total_tokens' => 100],
            ]),
            '*/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-image')]],
                'size' => '1536x1024',
                'quality' => 'medium',
            ]),
        ]);

        $this->actingAs($user)->post(route('admin.ai-articles.store'), [
            'source_post_ids' => [$source->id],
            'ai_prompt_profile_id' => $profile->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', [
            'connection' => 'database',
            '--queue' => 'ai-text,ai-image',
            '--stop-when-empty' => true,
            '--tries' => 3,
            '--timeout' => 300,
        ])->assertSuccessful();

        $task = Scheduler::query()->sole();
        $article = AiArticle::query()->sole();
        $image = AiImage::query()->sole();
        $this->assertSame(Scheduler::STATUS_COMPLETED, $task->status);
        $this->assertSame(AiArticle::STATUS_DRAFT, $article->status);
        $this->assertSame(AiImage::STATUS_GENERATED, $image->status);
        $this->assertDatabaseCount('jobs', 0);
        Storage::disk('local')->assertExists($image->file_path);
    }
}
