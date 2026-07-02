<?php

namespace Tests\Feature\Publications;

use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressPublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_verify_an_encrypted_wordpress_connection(): void
    {
        Http::fake([
            'wp.test/wp-json/wp/v2/users/me*' => Http::response([
                'id' => 10,
                'name' => 'Editor',
                'roles' => ['editor'],
            ]),
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'name' => 'Blog principal',
            'rest_api_url' => 'https://wp.test/wp-json',
            'username' => 'editor',
            'application_password' => 'abcd EFGH 1234',
            'active' => '1',
        ]);

        $site = WordPressSite::query()->sole();

        $response->assertRedirect(route('admin.wordpress-sites.index'));
        $this->assertSame($user->id, $site->user_id);
        $this->assertSame('https://wp.test', $site->rest_api_url);
        $this->assertSame('abcd EFGH 1234', $site->application_password);
        $this->assertNotSame('abcd EFGH 1234', DB::table('wordpress_sites')->value('application_password'));
        $this->assertNotNull($site->last_tested_at);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/wp-json/wp/v2/users/me')
            && $request->hasHeader('Authorization'));
    }

    public function test_wordpress_sites_index_counts_publications_using_the_real_foreign_key(): void
    {
        $user = User::factory()->create();
        $site = $this->site($user);
        $article = $this->article($user);

        Publication::query()->create([
            'user_id' => $user->id,
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'status' => Publication::STATUS_PUBLISHED,
        ]);

        $this->actingAs($user)
            ->get(route('admin.wordpress-sites.index'))
            ->assertOk()
            ->assertSee('WordPress')
            ->assertSee('Publicaciones');

        $this->assertSame($site->id, Publication::query()->firstOrFail()->wordpressSite->id);
    }

    public function test_one_configured_site_publishes_directly_without_a_site_selector(): void
    {
        Http::fake([
            'wp.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 321,
                'link' => 'https://wp.test/entrada',
                'status' => 'publish',
            ], 201),
        ]);

        $user = User::factory()->create();
        $site = $this->site($user);
        $article = $this->article($user);

        $this->actingAs($user)
            ->get(route('admin.ai-articles.show', $article))
            ->assertOk()
            ->assertSee('>Publicar</button>', false)
            ->assertDontSee('publish-sites-modal');

        $response = $this->actingAs($user)->post(route('admin.publications.publish', $article));
        $publication = Publication::query()->sole();

        $response->assertRedirect(route('admin.ai-articles.show', $article));
        $this->assertSame($site->id, $publication->wordpress_site_id);
        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertSame(321, $publication->remote_post_id);
    }

    public function test_multiple_sites_show_a_selector_and_publish_only_selected_destinations(): void
    {
        Http::fake([
            'one.test/wp-json/wp/v2/posts' => Http::response(['id' => 1, 'link' => 'https://one.test/post', 'status' => 'publish'], 201),
            'two.test/wp-json/wp/v2/posts' => Http::response(['id' => 2, 'link' => 'https://two.test/post', 'status' => 'publish'], 201),
        ]);

        $user = User::factory()->create();
        $first = $this->site($user, ['name' => 'Uno', 'rest_api_url' => 'https://one.test']);
        $second = $this->site($user, ['name' => 'Dos', 'rest_api_url' => 'https://two.test']);
        $article = $this->article($user);

        $this->actingAs($user)
            ->get(route('admin.ai-articles.show', $article))
            ->assertOk()
            ->assertSee('publish-sites-modal')
            ->assertSee('Uno')
            ->assertSee('Dos');

        $this->actingAs($user)->post(route('admin.publications.publish', $article), [
            'site_ids' => [$second->id],
        ])->assertRedirect(route('admin.ai-articles.show', $article));

        $this->assertDatabaseMissing('publications', ['wordpress_site_id' => $first->id]);
        $this->assertDatabaseHas('publications', [
            'wordpress_site_id' => $second->id,
            'status' => Publication::STATUS_PUBLISHED,
        ]);
    }

    public function test_a_failed_destination_does_not_stop_the_other_publications(): void
    {
        Http::fake([
            'good.test/wp-json/wp/v2/posts' => Http::response(['id' => 50, 'link' => 'https://good.test/post', 'status' => 'publish'], 201),
            'bad.test/wp-json/wp/v2/posts' => Http::response(['code' => 'rest_cannot_create', 'message' => 'El usuario no puede crear entradas.'], 403),
        ]);

        $user = User::factory()->create();
        $good = $this->site($user, ['rest_api_url' => 'https://good.test']);
        $bad = $this->site($user, ['name' => 'Sin permisos', 'rest_api_url' => 'https://bad.test']);
        $article = $this->article($user);

        $response = $this->actingAs($user)->post(route('admin.publications.publish', $article), [
            'site_ids' => [$good->id, $bad->id],
        ]);

        $response->assertRedirect(route('admin.ai-articles.show', $article));
        $this->assertDatabaseHas('publications', ['wordpress_site_id' => $good->id, 'status' => Publication::STATUS_PUBLISHED]);
        $this->assertDatabaseHas('publications', [
            'wordpress_site_id' => $bad->id,
            'status' => Publication::STATUS_FAILED,
            'error_message' => 'El usuario no puede crear entradas.',
        ]);

        $this->actingAs($user)
            ->get(route('admin.publications.index'))
            ->assertOk()
            ->assertSee('El usuario no puede crear entradas.')
            ->assertSee('https://good.test/post');
    }

    private function site(User $user, array $overrides = []): WordPressSite
    {
        return $user->wordpressSites()->create(array_merge([
            'name' => 'WordPress',
            'rest_api_url' => 'https://wp.test',
            'username' => 'editor',
            'application_password' => 'app-pass',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ], $overrides));
    }

    private function article(User $user): AiArticle
    {
        return $user->aiArticles()->create([
            'title' => 'Artículo listo para publicar',
            'content' => '<p>Contenido del artículo.</p>',
            'excerpt' => 'Extracto',
            'slug' => 'articulo-listo',
            'status' => AiArticle::STATUS_DRAFT,
        ]);
    }
}
