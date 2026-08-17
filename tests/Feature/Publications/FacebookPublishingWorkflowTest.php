<?php

namespace Tests\Feature\Publications;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacebookPublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_profile_form_offers_wordpress_and_facebook_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.wordpress-sites.create'))
            ->assertOk()
            ->assertSee('Agregar perfil de publicación del post generado')
            ->assertSee('Página de Facebook')
            ->assertSee('Page Access Token')
            ->assertSee('Facebook no permite publicar automáticamente en perfiles personales');
    }

    public function test_user_can_save_and_verify_an_encrypted_facebook_page_profile(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/me*' => Http::response([
                'id' => '123456789',
                'name' => 'Noticias Demo',
                'category' => 'News & media website',
            ]),
            'graph.facebook.com/v24.0/123456789*' => Http::response([
                'id' => '123456789',
                'name' => 'Noticias Demo',
                'link' => 'https://www.facebook.com/NoticiasDemo',
            ]),
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'type' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'name' => 'Facebook Noticias',
            'facebook_page_id' => '123456789',
            'facebook_access_token' => 'page-token-secret',
            'facebook_api_version' => 'v24.0',
            'active' => '1',
        ]);

        $profile = WordPressSite::query()->sole();

        $response->assertRedirect(route('admin.wordpress-sites.index'));
        $this->assertSame(WordPressSite::TYPE_FACEBOOK_PAGE, $profile->type);
        $this->assertSame('123456789', $profile->facebook_page_id);
        $this->assertSame('page-token-secret', $profile->facebook_access_token);
        $this->assertNotSame('page-token-secret', DB::table('wordpress_sites')->value('facebook_access_token'));
        $this->assertNotNull($profile->last_tested_at);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v24.0/123456789')
            && $request['fields'] === 'id,name,link'
            && $request['access_token'] === 'page-token-secret');
    }

    public function test_a_user_token_is_resolved_to_its_only_managed_page_and_page_token(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/me/accounts*' => Http::response([
                'data' => [[
                    'id' => '123456789',
                    'name' => 'Noticias Demo',
                    'tasks' => ['CREATE_CONTENT', 'MANAGE'],
                    'access_token' => 'resolved-page-token',
                ]],
            ]),
            'graph.facebook.com/v24.0/me*' => Http::response([
                'error' => [
                    'message' => '(#100) Tried accessing nonexisting field (category)',
                    'code' => 100,
                ],
            ], 400),
            'graph.facebook.com/v24.0/123456789*' => Http::response([
                'id' => '123456789',
                'name' => 'Noticias Demo',
                'link' => 'https://www.facebook.com/NoticiasDemo',
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'type' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'name' => 'Facebook Noticias',
            'facebook_page_id' => '999999999',
            'facebook_access_token' => 'user-token',
            'facebook_api_version' => 'v24.0',
            'active' => '1',
        ])->assertRedirect(route('admin.wordpress-sites.index'));

        $profile = WordPressSite::query()->sole();
        $this->assertSame('123456789', $profile->facebook_page_id);
        $this->assertSame('resolved-page-token', $profile->facebook_access_token);
        $this->assertSame(WordPressSite::STATUS_ACTIVE, $profile->status);
    }

    public function test_api_access_blocked_is_reported_as_an_app_restriction(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/me*' => Http::response([
                'error' => [
                    'message' => 'API access blocked.',
                    'type' => 'OAuthException',
                    'code' => 200,
                ],
            ], 403),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'type' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'name' => 'Facebook bloqueado',
            'facebook_page_id' => '123456789',
            'facebook_access_token' => 'blocked-token',
            'facebook_api_version' => 'v24.0',
            'active' => '1',
        ])->assertSessionHas('warning', fn (string $message): bool => str_contains($message, 'Meta bloqueó el acceso API'));

        $this->assertSame(WordPressSite::STATUS_ERROR, WordPressSite::query()->sole()->status);
        $this->assertStringContainsString('aplicación asociada al token', WordPressSite::query()->sole()->connection_error);
        Http::assertSentCount(1);
    }

    public function test_article_can_be_published_to_a_facebook_page(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/me*' => Http::response([
                'id' => '123456789',
                'name' => 'Noticias Demo',
                'category' => 'News & media website',
            ]),
            'graph.facebook.com/v24.0/123456789/feed' => Http::response([
                'id' => '123456789_987654321',
            ], 200),
            'graph.facebook.com/v24.0/123456789_987654321*' => Http::response([
                'id' => '123456789_987654321',
                'permalink_url' => 'https://www.facebook.com/NoticiasDemo/posts/987654321',
            ], 200),
        ]);

        $user = User::factory()->create();
        $profile = $this->facebookProfile($user);
        $article = $this->article($user);

        $response = $this->actingAs($user)->post(route('admin.publications.publish', $article));
        $publication = Publication::query()->sole();

        $response->assertRedirect(route('admin.ai-articles.show', $article));
        $this->assertSame($profile->id, $publication->wordpress_site_id);
        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertSame('123456789_987654321', $publication->remote_post_key);
        $this->assertSame('https://www.facebook.com/NoticiasDemo/posts/987654321', $publication->remote_url);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v24.0/123456789/feed'
            && str_contains((string) $request['message'], 'Título para Facebook')
            && str_contains((string) $request['message'], 'Contenido completo del artículo generado.')
            && str_contains((string) $request['message'], '#Política')
            && $request['access_token'] === 'page-token-secret');
    }

    public function test_switching_an_existing_wordpress_profile_to_facebook_requires_a_page_token(): void
    {
        $user = User::factory()->create();
        $profile = $user->wordpressSites()->create([
            'type' => WordPressSite::TYPE_WORDPRESS,
            'name' => 'WordPress',
            'rest_api_url' => 'https://wp.test',
            'username' => 'editor',
            'application_password' => 'app-password',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.wordpress-sites.update', $profile), [
                'type' => WordPressSite::TYPE_FACEBOOK_PAGE,
                'name' => 'Facebook Noticias',
                'facebook_page_id' => '123456789',
                'facebook_api_version' => 'v24.0',
                'active' => '1',
            ])
            ->assertSessionHasErrors('facebook_access_token');

        $this->assertSame(WordPressSite::TYPE_WORDPRESS, $profile->fresh()->type);
    }

    public function test_generated_image_is_attached_to_a_facebook_feed_post(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-images/principal.png', 'fake-image');
        Http::fake([
            'graph.facebook.com/v24.0/me*' => Http::response([
                'id' => '123456789',
                'name' => 'Noticias Demo',
                'category' => 'News & media website',
            ]),
            'graph.facebook.com/v24.0/123456789/photos' => Http::response([
                'id' => '555',
            ], 200),
            'graph.facebook.com/v24.0/123456789/feed' => Http::response([
                'id' => '123456789_987654321',
            ], 200),
            'graph.facebook.com/v24.0/123456789_987654321*' => Http::response([
                'id' => '123456789_987654321',
                'permalink_url' => 'https://www.facebook.com/NoticiasDemo/posts/987654321',
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->facebookProfile($user);
        $article = $this->article($user);
        AiImage::query()->create([
            'ai_article_id' => $article->id,
            'type' => AiImage::TYPE_MAIN,
            'prompt' => 'Imagen de prueba',
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/principal.png',
            'mime_type' => 'image/png',
        ]);

        $this->actingAs($user)
            ->post(route('admin.publications.publish', $article))
            ->assertRedirect(route('admin.ai-articles.show', $article));

        $this->assertDatabaseHas('publications', [
            'status' => Publication::STATUS_PUBLISHED,
            'remote_post_key' => '123456789_987654321',
            'remote_url' => 'https://www.facebook.com/NoticiasDemo/posts/987654321',
            'last_action' => 'publish_facebook_post_with_photo',
        ]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v24.0/123456789/photos');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v24.0/123456789/feed'
            && $request['attached_media[0]'] === '{"media_fbid":"555"}');
    }

    private function facebookProfile(User $user): WordPressSite
    {
        return $user->wordpressSites()->create([
            'type' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'name' => 'Facebook Noticias',
            'facebook_page_id' => '123456789',
            'facebook_access_token' => 'page-token-secret',
            'facebook_api_version' => 'v24.0',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
    }

    private function article(User $user): AiArticle
    {
        return $user->aiArticles()->create([
            'title' => 'Título para Facebook',
            'content' => '<p>Contenido completo del artículo generado.</p>',
            'excerpt' => 'Resumen del artículo para la publicación.',
            'tags' => ['Política'],
            'slug' => 'titulo-para-facebook',
            'status' => AiArticle::STATUS_DRAFT,
        ]);
    }
}
