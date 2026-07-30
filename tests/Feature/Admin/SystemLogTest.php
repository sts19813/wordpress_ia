<?php

namespace Tests\Feature\Admin;

use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_module_shows_errors_and_confirmed_publications_in_a_compact_table(): void
    {
        SystemLog::query()->create([
            'level' => SystemLog::LEVEL_ERROR,
            'event' => SystemLog::EVENT_SYSTEM_ERROR,
            'source' => 'Sistema',
            'message' => 'Error visible para soporte.',
            'occurred_at' => now(),
        ]);
        SystemLog::query()->create([
            'level' => SystemLog::LEVEL_SUCCESS,
            'event' => SystemLog::EVENT_PUBLICATION_PUBLISHED,
            'source' => 'Publicaciones',
            'message' => 'Post confirmado en el sitio real.',
            'context' => ['remote_url' => 'https://site.test/post-confirmado'],
            'occurred_at' => now()->subMinute(),
        ]);
        SystemLog::query()->create([
            'level' => SystemLog::LEVEL_SUCCESS,
            'event' => 'draft_generated',
            'source' => 'Artículos IA',
            'message' => 'Este mensaje interno no debe mostrarse.',
            'occurred_at' => now()->subMinutes(2),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.system-logs.index'))
            ->assertOk()
            ->assertSee('Error visible para soporte.')
            ->assertSee('Post confirmado en el sitio real.')
            ->assertSee('https://site.test/post-confirmado')
            ->assertDontSee('Este mensaje interno no debe mostrarse.')
            ->assertSee('system-logs-table', false)
            ->assertSee('data-page-length="50"', false)
            ->assertDontSee('class="table-responsive"', false);
    }

    public function test_publication_activity_is_logged_only_after_remote_confirmation(): void
    {
        $user = User::factory()->create();
        $site = $user->wordpressSites()->create([
            'name' => 'Sitio real',
            'rest_api_url' => 'https://site.test',
            'username' => 'editor',
            'application_password' => 'secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        $article = $user->aiArticles()->create([
            'title' => 'Artículo confirmado',
            'content' => '<p>Contenido.</p>',
            'status' => AiArticle::STATUS_DRAFT,
        ]);
        $publication = Publication::query()->create([
            'user_id' => $user->id,
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->assertDatabaseCount('system_logs', 0);

        $publication->update([
            'status' => Publication::STATUS_PUBLISHED,
            'remote_post_id' => 321,
            'remote_url' => 'https://site.test/articulo-confirmado',
            'published_at' => now(),
        ]);

        $this->assertDatabaseHas('system_logs', [
            'level' => SystemLog::LEVEL_SUCCESS,
            'event' => SystemLog::EVENT_PUBLICATION_PUBLISHED,
            'message' => '“Artículo confirmado” se publicó en Sitio real.',
            'subject_type' => Publication::class,
            'subject_id' => $publication->id,
        ]);

        $publication->update(['last_action' => 'audit_only']);
        $this->assertDatabaseCount('system_logs', 1);

        Publication::query()->create([
            'user_id' => $user->id,
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'status' => Publication::STATUS_FAILED,
            'error_message' => 'El sitio rechazó el contenido.',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'level' => SystemLog::LEVEL_ERROR,
            'event' => SystemLog::EVENT_PUBLICATION_FAILED,
            'message' => 'El sitio rechazó el contenido.',
        ]);
    }
}
