<?php

namespace Tests\Feature\Scheduler;

use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\Scheduler;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_programmer_separates_active_tasks_from_confirmed_publications(): void
    {
        $user = User::factory()->create();
        $article = $this->article($user, 'Borrador publicado visible');
        $publication = $this->publication($user, $article);
        $queued = $this->task($user, [
            'name' => 'Proceso activo visible',
            'status' => Scheduler::STATUS_QUEUED,
            'progress' => 25,
        ]);
        $failed = $this->task($user, [
            'name' => 'Proceso fallido visible',
            'status' => Scheduler::STATUS_FAILED,
            'progress' => 100,
            'last_error' => 'Error compacto visible.',
            'finished_at' => now(),
        ]);
        $this->task($user, [
            'name' => 'Publicación confirmada',
            'status' => Scheduler::STATUS_COMPLETED,
            'progress' => 100,
            'ai_article_id' => $article->id,
            'publication_id' => $publication->id,
            'finished_at' => now(),
        ]);
        $this->task($user, [
            'name' => 'Finalizado sin publicación oculto',
            'status' => Scheduler::STATUS_COMPLETED,
            'progress' => 100,
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee($queued->name)
            ->assertSee($failed->name)
            ->assertSee('Error compacto visible.')
            ->assertDontSee('Borrador publicado visible')
            ->assertDontSee('Finalizado sin publicación oculto')
            ->assertSee('style="width: 25%"', false)
            ->assertDontSee('style="width: 100%"', false);

        $this->actingAs($user)
            ->get(route('admin.scheduler.index', ['tab' => 'completed']))
            ->assertOk()
            ->assertSee('Borrador publicado visible')
            ->assertSee('Ver borrador')
            ->assertSee('Ver publicado')
            ->assertSee('https://published.test/articulo')
            ->assertDontSee('Proceso activo visible')
            ->assertDontSee('Finalizado sin publicación oculto')
            ->assertDontSee('class="task-progress-wrap', false);
    }

    public function test_finished_or_failed_tasks_can_be_deleted_without_deleting_content(): void
    {
        $user = User::factory()->create();
        $article = $this->article($user, 'Contenido que se conserva');
        $failed = $this->task($user, [
            'name' => 'Ejecución para eliminar',
            'status' => Scheduler::STATUS_FAILED,
            'last_error' => 'Falló.',
            'ai_article_id' => $article->id,
            'finished_at' => now(),
        ]);
        $queued = $this->task($user, [
            'name' => 'Ejecución activa protegida',
            'status' => Scheduler::STATUS_QUEUED,
        ]);

        $this->actingAs($user)
            ->delete(route('admin.scheduler.destroy', $failed), ['return_tab' => 'active'])
            ->assertRedirect(route('admin.scheduler.index', ['tab' => 'active']));

        $this->assertDatabaseMissing('schedulers', ['id' => $failed->id]);
        $this->assertDatabaseHas('ai_articles', ['id' => $article->id]);

        $this->actingAs($user)
            ->delete(route('admin.scheduler.destroy', $queued))
            ->assertStatus(422);

        $this->assertDatabaseHas('schedulers', ['id' => $queued->id]);
    }

    private function article(User $user, string $title): AiArticle
    {
        return $user->aiArticles()->create([
            'title' => $title,
            'content' => '<p>Contenido.</p>',
            'status' => AiArticle::STATUS_DRAFT,
        ]);
    }

    private function publication(User $user, AiArticle $article): Publication
    {
        $site = $user->wordpressSites()->create([
            'name' => 'Sitio publicado',
            'rest_api_url' => 'https://published.test',
            'username' => 'editor',
            'application_password' => 'secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);

        return Publication::query()->create([
            'user_id' => $user->id,
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'status' => Publication::STATUS_PUBLISHED,
            'remote_post_id' => 10,
            'remote_url' => 'https://published.test/articulo',
            'published_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function task(User $user, array $overrides): Scheduler
    {
        return Scheduler::query()->create([
            'user_id' => $user->id,
            'type' => Scheduler::TYPE_AI_ARTICLE,
            'name' => 'Ejecución',
            'status' => Scheduler::STATUS_QUEUED,
            'step' => 'Procesando',
            'progress' => 0,
            'attempts' => 1,
            'max_attempts' => 3,
            'events' => [],
            ...$overrides,
        ]);
    }
}
