<?php

namespace Tests\Feature\Admin;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\Scheduler;
use App\Models\SourceScanLog;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_summarizes_today_and_displays_published_posts_with_destination_links(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');
        Storage::fake('local');

        $user = User::factory()->create();
        SourceScanLog::query()->create([
            'title' => 'Entrada aceptada',
            'url' => 'https://source.test/aceptada',
            'outcome' => SourceScanLog::OUTCOME_ACCEPTED,
            'applies' => true,
            'scanned_at' => now()->subHour(),
        ]);
        SourceScanLog::query()->create([
            'title' => 'Entrada descartada',
            'url' => 'https://source.test/descartada',
            'outcome' => SourceScanLog::OUTCOME_DISCARDED,
            'applies' => false,
            'scanned_at' => now()->subMinutes(30),
        ]);
        SourceScanLog::query()->create([
            'title' => 'Entrada anterior',
            'url' => 'https://source.test/anterior',
            'outcome' => SourceScanLog::OUTCOME_ACCEPTED,
            'applies' => true,
            'scanned_at' => now()->subDay(),
        ]);

        $article = AiArticle::query()->create([
            'user_id' => $user->id,
            'title' => 'Nuevo programa de apoyo comunitario',
            'content' => '<p>El programa abre una nueva etapa para la comunidad.</p>',
            'excerpt' => 'El programa abre una nueva etapa para la comunidad.',
            'slug' => 'programa-apoyo-comunitario',
            'tags' => ['Comunidad', 'Apoyo'],
            'model' => 'gpt-4.1-mini',
            'tokens' => ['input' => 1000, 'output' => 500, 'total' => 1500],
            'cost' => 0.0012,
            'status' => AiArticle::STATUS_DRAFT,
            'generated_at' => now()->subMinutes(20),
        ]);
        $image = $article->images()->create([
            'type' => AiImage::TYPE_MAIN,
            'title' => 'Imagen del programa',
            'prompt' => 'Comunidad reunida',
            'resolution' => '1536x1024',
            'quality' => 'medium',
            'model' => 'gpt-image-2',
            'tokens' => ['input' => 10, 'output' => 190, 'total' => 200],
            'cost' => 0.005,
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/dashboard.png',
            'mime_type' => 'image/png',
        ]);
        Storage::disk('local')->put($image->file_path, 'fake-image');

        $wordpress = WordPressSite::query()->create([
            'user_id' => $user->id,
            'type' => WordPressSite::TYPE_WORDPRESS,
            'name' => 'Portal principal',
            'rest_api_url' => 'https://portal.test',
            'username' => 'editor',
            'application_password' => 'secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        $facebook = WordPressSite::query()->create([
            'user_id' => $user->id,
            'type' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'name' => 'Facebook comunidad',
            'facebook_page_id' => '123456',
            'facebook_access_token' => 'token',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        Publication::query()->create([
            'user_id' => $user->id,
            'wordpress_site_id' => $wordpress->id,
            'ai_article_id' => $article->id,
            'ai_image_id' => $image->id,
            'remote_url' => 'https://portal.test/programa-apoyo',
            'status' => Publication::STATUS_PUBLISHED,
            'published_at' => now()->subMinutes(10),
            'last_action' => 'publish',
        ]);
        Publication::query()->create([
            'user_id' => $user->id,
            'wordpress_site_id' => $facebook->id,
            'ai_article_id' => $article->id,
            'ai_image_id' => $image->id,
            'remote_url' => 'https://facebook.com/123456/posts/789',
            'status' => Publication::STATUS_PUBLISHED,
            'published_at' => now()->subMinutes(5),
            'last_action' => 'publish',
        ]);
        Scheduler::query()->create([
            'user_id' => $user->id,
            'type' => Scheduler::TYPE_AI_ARTICLE,
            'name' => 'Generar artículo de prueba',
            'status' => Scheduler::STATUS_FAILED,
            'step' => 'generate_text',
            'last_error' => 'El proveedor de IA no respondió.',
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertViewHas('scansToday', 2)
            ->assertViewHas('acceptedToday', 1)
            ->assertViewHas('generatedToday', 1)
            ->assertViewHas('publishedToday', 2)
            ->assertViewHas('publishedArticlesToday', 1)
            ->assertViewHas('errorsToday', 1)
            ->assertViewHas('aiUsageToday', fn (array $usage) => $usage['total_tokens'] === 1700
                && $usage['average_tokens'] === 1700
                && abs($usage['total_cost'] - 0.0062) < 0.000001)
            ->assertSee('Centro de operaciones')
            ->assertSee('Consumo de inteligencia artificial')
            ->assertSee('1,700')
            ->assertSee('$0.0062')
            ->assertSee('Nuevo programa de apoyo comunitario')
            ->assertSee('Portal principal')
            ->assertSee('Facebook comunidad')
            ->assertSee('https://portal.test/programa-apoyo', false)
            ->assertSee('https://facebook.com/123456/posts/789', false)
            ->assertSee('El proveedor de IA no respondió.');
    }
}
