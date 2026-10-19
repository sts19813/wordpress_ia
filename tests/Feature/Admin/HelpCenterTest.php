<?php

namespace Tests\Feature\Admin;

use App\Models\HelpArticleView;
use App\Models\HelpFeedback;
use App\Models\HelpSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_browse_and_search_the_help_center(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.help.index', ['q' => 'fuente']));

        $response
            ->assertOk()
            ->assertSee('¿Cómo podemos ayudarte?')
            ->assertSee('Agregar y probar un sitio fuente')
            ->assertDontSee('Crear un post desde texto, una URL o una nota de voz');
    }

    public function test_help_articles_are_available_and_primary_pages_link_to_their_contextual_guide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.help.index'))
            ->assertOk()
            ->assertSee('Rutas guiadas')
            ->assertSee('Automatizar una fuente');

        $this->actingAs($user)
            ->get(route('admin.help.show', 'crear-un-post'))
            ->assertOk()
            ->assertSee('Crear un post desde texto, una URL o una nota de voz')
            ->assertSee('Antes de comenzar')
            ->assertSee('Pantalla para generar un post')
            ->assertSee('assets/img/help/generar-post-demo.jpeg', false)
            ->assertSee('Paso a paso')
            ->assertSee('Si algo no sale como esperabas')
            ->assertSee('Comentario opcional')
            ->assertSee('Necesito más ayuda');

        $this->actingAs($user)
            ->get(route('admin.manual-posts.create'))
            ->assertOk()
            ->assertSee('Ayuda de esta página')
            ->assertSee(route('admin.help.show', 'crear-un-post'), false);
    }

    public function test_unknown_help_article_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.help.show', 'guia-inexistente'))
            ->assertNotFound();
    }

    public function test_help_center_records_searches_and_feedback(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.help.index', ['q' => 'termino inexistente']))
            ->assertOk()
            ->assertSee('¿Qué información esperabas encontrar?');

        $this->assertDatabaseHas('help_searches', [
            'user_id' => $user->id,
            'term' => 'termino inexistente',
            'results_count' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('admin.help.show', 'crear-un-post'))
            ->assertOk();

        $this->assertDatabaseHas('help_article_views', [
            'user_id' => $user->id,
            'article_slug' => 'crear-un-post',
        ]);

        $this->actingAs($user)
            ->post(route('admin.help.feedback'), [
                'article_slug' => 'crear-un-post',
                'rating' => 'helpful',
                'message' => 'La guía fue clara.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('help_feedback', [
            'user_id' => $user->id,
            'article_slug' => 'crear-un-post',
            'rating' => HelpFeedback::RATING_HELPFUL,
            'message' => 'La guía fue clara.',
        ]);
    }

    public function test_only_administrators_can_view_help_analytics(): void
    {
        $operator = User::factory()->create();
        $administrator = User::factory()->create(['is_admin' => true]);
        HelpSearch::query()->create(['user_id' => $administrator->id, 'term' => 'fuente', 'results_count' => 1]);
        HelpArticleView::query()->create(['user_id' => $administrator->id, 'article_slug' => 'crear-un-post']);

        $this->actingAs($operator)->get(route('admin.help.analytics'))->assertForbidden();
        $this->actingAs($administrator)
            ->get(route('admin.help.analytics'))
            ->assertOk()
            ->assertSee('Resumen de ayuda')
            ->assertSee('fuente')
            ->assertSee('Guías más consultadas')
            ->assertSee('Crear un post desde texto, una URL o una nota de voz');
    }
}
