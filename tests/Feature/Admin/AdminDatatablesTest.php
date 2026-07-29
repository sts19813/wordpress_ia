<?php

namespace Tests\Feature\Admin;

use App\Models\SourcePost;
use App\Models\SourceSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDatatablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_index_uses_datatables_instead_of_laravel_pagination(): void
    {
        $sourceSite = $this->createSourceSite();

        SourcePost::query()->create([
            'source_site_id' => $sourceSite->id,
            'title' => 'Nota para DataTables',
            'content' => 'Contenido de prueba',
            'summary' => 'Resumen',
            'author' => 'Redaccion',
            'published_at' => now(),
            'categories' => ['Noticias'],
            'tags' => ['IA'],
            'url' => 'https://example.com/nota-datatables',
            'hash' => hash('sha256', 'nota-datatables'),
            'status' => SourcePost::STATUS_FETCHED,
            'language' => 'es',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.news.index'));

        $response
            ->assertOk()
            ->assertSee('admin-datatable', false)
            ->assertSee('data-page-length="50"', false)
            ->assertSee('Nota para DataTables')
            ->assertSee('Generar nota con IA')
            ->assertSee('Ir al post original')
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="language"', false)
            ->assertDontSee('name="category"', false)
            ->assertDontSee('name="tag"', false)
            ->assertDontSee('role="navigation"', false)
            ->assertDontSee('Showing 1 to', false);
    }

    public function test_news_index_can_be_filtered_with_the_single_source_site_selector(): void
    {
        $selectedSite = $this->createSourceSite([
            'name' => 'Fuente seleccionada',
            'url' => 'https://selected.example.com/feed',
        ]);
        $otherSite = $this->createSourceSite([
            'name' => 'Fuente no seleccionada',
            'url' => 'https://other.example.com/feed',
        ]);

        SourcePost::query()->create([
            'source_site_id' => $selectedSite->id,
            'title' => 'Nota que debe mostrarse',
            'url' => 'https://selected.example.com/nota',
            'hash' => hash('sha256', 'selected-note'),
            'status' => SourcePost::STATUS_FETCHED,
            'published_at' => now(),
        ]);
        SourcePost::query()->create([
            'source_site_id' => $otherSite->id,
            'title' => 'Nota que debe ocultarse',
            'url' => 'https://other.example.com/nota',
            'hash' => hash('sha256', 'other-note'),
            'status' => SourcePost::STATUS_FETCHED,
            'published_at' => now(),
        ]);

        $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.news.index', ['source_site_id' => $selectedSite->id]))
            ->assertOk()
            ->assertSee('Nota que debe mostrarse')
            ->assertDontSee('Nota que debe ocultarse')
            ->assertSee('value="'.$selectedSite->id.'" selected', false);
    }

    public function test_source_sites_index_uses_datatables_instead_of_laravel_pagination(): void
    {
        $this->createSourceSite([
            'name' => 'Fuente para DataTables',
            'url' => 'https://example.com/feed-datatables',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.source-sites.index'));

        $response
            ->assertOk()
            ->assertSee('admin-datatable', false)
            ->assertSee('Fuente para DataTables')
            ->assertDontSee('role="navigation"', false)
            ->assertDontSee('Showing 1 to', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSourceSite(array $overrides = []): SourceSite
    {
        return SourceSite::query()->create(array_merge([
            'name' => 'Fuente RSS',
            'url' => 'https://example.com/feed',
            'type' => SourceSite::TYPE_RSS,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'category' => 'Noticias',
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'active' => true,
        ], $overrides));
    }
}
