<?php

namespace Tests\Feature\Scheduler;

use App\Jobs\GenerateSourceArticle;
use App\Jobs\ScanSourceSite;
use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Models\SourceSite;
use App\Models\User;
use App\Models\WordPressSite;
use App\Services\AiPromptProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SourcePipelineQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_sources_are_enqueued_once_and_receive_their_next_execution_date(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $site = $this->sourceSite($user, $profile->id, [
            'frequency_minutes' => 1440,
            'next_scan_at' => now()->subMinute(),
        ]);

        $this->artisan('sources:scan-due')->assertSuccessful();

        $task = Scheduler::query()->sole();
        $this->assertSame(Scheduler::TYPE_SOURCE_SCAN, $task->type);
        $this->assertSame($site->id, $task->source_site_id);
        $this->assertTrue($site->fresh()->next_scan_at->isAfter(now()->addHours(23)));
        Queue::assertPushedOn('source-pipeline', ScanSourceSite::class);

        $this->artisan('sources:scan-due')->assertSuccessful();
        $this->assertDatabaseCount('schedulers', 1);
    }

    public function test_programmer_lists_every_next_source_run_and_allows_manual_queueing(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $site = $this->sourceSite($user, $profile->id, [
            'name' => 'Forbes programado',
            'frequency_minutes' => 24 * 60,
            'next_scan_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee('Próximas consultas de sitios fuente')
            ->assertSee('Forbes programado')
            ->assertSee('24 h')
            ->assertSee('Consultar ahora');

        $this->actingAs($user)
            ->post(route('admin.scheduler.sources.run', $site))
            ->assertRedirect();

        $task = Scheduler::query()->sole();
        $this->assertSame('manual', $task->payload['trigger']);
        Queue::assertPushed(ScanSourceSite::class);
    }

    public function test_a_queued_source_scan_can_be_executed_manually_when_the_worker_is_stopped(): void
    {
        Queue::fake();
        Http::fake([
            'source.test/wp-json/wp/v2/posts*' => Http::response([]),
        ]);
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $site = $this->sourceSite($user, $profile->id);

        $this->actingAs($user)->post(route('admin.scheduler.sources.run', $site))->assertRedirect();
        $task = Scheduler::query()->sole();

        $this->actingAs($user)
            ->post(route('admin.scheduler.execute', $task))
            ->assertRedirect(route('admin.scheduler.index', ['task' => $task->id]));

        $this->assertSame(Scheduler::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertSame('Finalizado', $task->fresh()->step);
        $this->assertTrue(collect($task->fresh()->events)->contains(
            fn (array $event) => str_contains($event['message'], 'Ejecución manual iniciada'),
        ));
    }

    public function test_complete_pipeline_scans_generates_and_publishes_accepted_notes_as_tracked_jobs(): void
    {
        Queue::fake();
        config(['services.openai.api_key' => 'test-key']);
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $profile->update(['generate_image' => false]);
        $wordpressSite = $user->wordpressSites()->create([
            'name' => 'Destino editorial',
            'rest_api_url' => 'https://target.test',
            'username' => 'editor',
            'application_password' => 'app-pass',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        $sourceSite = $this->sourceSite($user, $profile->id, [
            'wordpress_site_id' => $wordpressSite->id,
            'auto_publish' => true,
        ]);

        Http::fake([
            'source.test/wp-json/wp/v2/posts*' => Http::response([[
                'title' => ['rendered' => 'Congreso aprueba paquete económico'],
                'content' => ['rendered' => $this->completeSourceHtml()],
                'excerpt' => ['rendered' => '<p>La medida fue aprobada por el Congreso.</p>'],
                'date' => '2026-07-28T12:00:00',
                'link' => 'https://source.test/politica/paquete-economico',
                'slug' => 'paquete-economico',
                '_embedded' => [
                    'author' => [['name' => 'Redacción']],
                    'wp:term' => [],
                ],
            ]]),
            '*/responses' => Http::response([
                'model' => 'gpt-4.1-mini',
                'output' => [['content' => [['type' => 'output_text', 'text' => json_encode([
                    'title' => 'Nuevo análisis del paquete económico',
                    'content' => '<p>Contenido generado con los hechos de la fuente.</p>',
                    'excerpt' => 'Resumen del paquete económico.',
                    'meta_description' => 'Descripción para buscadores.',
                    'slug' => 'analisis-paquete-economico',
                    'categories' => [],
                    'tags' => [],
                    'seo_keywords' => ['economía'],
                    'faqs' => [],
                    'conclusion' => 'La medida entrará en vigor.',
                ], JSON_THROW_ON_ERROR)]]]],
                'usage' => ['total_tokens' => 120],
            ]),
            'target.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 987,
                'link' => 'https://target.test/analisis-paquete-economico',
                'status' => 'publish',
            ], 201),
        ]);

        $this->actingAs($user)->post(route('admin.scheduler.sources.run', $sourceSite))->assertRedirect();
        $scanTask = Scheduler::query()->sole();

        app()->call([new ScanSourceSite($scanTask->id, $sourceSite->id), 'handle']);

        $scanTask->refresh();
        $articleTask = Scheduler::query()->where('type', Scheduler::TYPE_SOURCE_ARTICLE)->sole();
        $this->assertSame(Scheduler::STATUS_COMPLETED, $scanTask->status);
        $this->assertSame(1, SourcePost::query()->count());
        $this->assertTrue(SourcePost::query()->sole()->filter_applies);
        Queue::assertPushed(GenerateSourceArticle::class);

        app()->call([new GenerateSourceArticle($articleTask->id, $sourceSite->id), 'handle']);

        $articleTask->refresh();
        $this->assertSame(Scheduler::STATUS_COMPLETED, $articleTask->status);
        $this->assertSame(AiArticle::STATUS_DRAFT, AiArticle::query()->sole()->status);
        $this->assertSame(Publication::STATUS_PUBLISHED, Publication::query()->sole()->status);
        $this->assertSame('https://target.test/analisis-paquete-economico', $articleTask->publication->remote_url);

        $this->actingAs($user)
            ->get(route('admin.scheduler.index', ['task' => $articleTask->id]))
            ->assertOk()
            ->assertSee('Generación y publicación')
            ->assertSee('Artículo publicado correctamente');
    }

    public function test_scan_creates_only_the_configured_number_of_generation_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $sourceSite = $this->sourceSite($user, $profile->id, [
            'max_posts_per_scan' => 3,
            'max_generations_per_scan' => 1,
        ]);
        $posts = collect(range(1, 3))->map(fn (int $number) => [
            'title' => ['rendered' => "Nota económica {$number}"],
            'content' => ['rendered' => $this->completeSourceHtml()],
            'excerpt' => ['rendered' => '<p>Resumen económico.</p>'],
            'date' => "2026-07-28T1{$number}:00:00",
            'link' => "https://source.test/nota-{$number}",
            'slug' => "nota-{$number}",
            '_embedded' => ['author' => [['name' => 'Redacción']], 'wp:term' => []],
        ])->all();

        Http::fake([
            'source.test/wp-json/wp/v2/posts*' => Http::response($posts),
        ]);

        $this->actingAs($user)->post(route('admin.scheduler.sources.run', $sourceSite))->assertRedirect();
        $scanTask = Scheduler::query()->sole();
        app()->call([new ScanSourceSite($scanTask->id, $sourceSite->id), 'handle']);

        $this->assertSame(3, SourcePost::query()->count());
        $this->assertSame(1, Scheduler::query()->where('type', Scheduler::TYPE_SOURCE_ARTICLE)->count());
        $this->assertSame(2, $scanTask->fresh()->payload['generation_skipped']);
        $this->assertTrue(collect($scanTask->fresh()->events)->contains(
            fn (array $event) => str_contains($event['message'], 'excedieron el máximo de 1'),
        ));
    }

    private function sourceSite(User $user, int $profileId, array $overrides = []): SourceSite
    {
        return SourceSite::query()->create(array_merge([
            'automation_user_id' => $user->id,
            'ai_prompt_profile_id' => $profileId,
            'name' => 'Fuente programada',
            'url' => 'https://source.test',
            'type' => SourceSite::TYPE_WORDPRESS_REST,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'daily_limit' => 20,
            'max_posts_per_scan' => 20,
            'max_generations_per_scan' => 5,
            'auto_generate' => true,
            'auto_publish' => false,
            'active' => true,
        ], $overrides));
    }

    private function completeSourceHtml(): string
    {
        return <<<'HTML'
            <p>El Congreso aprobó un paquete económico después de una sesión pública en la que participaron representantes de distintos grupos parlamentarios.</p>
            <p>La iniciativa contiene medidas fiscales, reglas de aplicación y mecanismos de seguimiento para las autoridades responsables durante el próximo ejercicio.</p>
            <p>Los legisladores informaron que los resultados serán evaluados periódicamente y publicados para facilitar la rendición de cuentas.</p>
            HTML;
    }
}
