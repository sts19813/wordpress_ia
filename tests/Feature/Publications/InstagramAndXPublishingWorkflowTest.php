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

class InstagramAndXPublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_form_offers_instagram_and_x(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.wordpress-sites.create'))
            ->assertOk()
            ->assertSee('Cuenta de Instagram')
            ->assertSee('Cuenta de X')
            ->assertSee('instagram_content_publish')
            ->assertSee('me/accounts?fields=id,name,access_token,tasks,instagram_business_account')
            ->assertSee('{ID_DE_PAGINA_FACEBOOK}?fields=id,name,access_token,instagram_business_account')
            ->assertSee('instagram_business_account.id')
            ->assertSee('No compartas ni muestres el token')
            ->assertSee('tweet.write');
    }

    public function test_user_can_save_and_verify_encrypted_instagram_and_x_profiles(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/17841400000000000*' => Http::response([
                'id' => '17841400000000000',
                'username' => 'noticias_demo',
            ]),
            'api.x.com/2/users/me*' => Http::response([
                'data' => [
                    'id' => '2244994945',
                    'name' => 'Noticias X',
                    'username' => 'noticias_x',
                ],
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'name' => 'Instagram Noticias',
            'type' => WordPressSite::TYPE_INSTAGRAM,
            'instagram_account_id' => '17841400000000000',
            'instagram_access_token' => 'instagram-secret',
            'instagram_api_version' => 'v24.0',
            'active' => '1',
        ])->assertRedirect(route('admin.wordpress-sites.index'));

        $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'name' => 'X Noticias',
            'type' => WordPressSite::TYPE_X,
            'x_username' => 'nombre_anterior',
            'x_access_token' => 'x-user-secret',
            'active' => '1',
        ])->assertRedirect(route('admin.wordpress-sites.index'));

        $instagram = WordPressSite::query()->where('type', WordPressSite::TYPE_INSTAGRAM)->sole();
        $x = WordPressSite::query()->where('type', WordPressSite::TYPE_X)->sole();
        $this->assertSame('instagram-secret', $instagram->instagram_access_token);
        $this->assertSame('2244994945', $x->x_user_id);
        $this->assertSame('noticias_x', $x->x_username);
        $this->assertSame('x-user-secret', $x->x_access_token);
        $this->assertNotSame('instagram-secret', DB::table('wordpress_sites')->whereKey($instagram->id)->value('instagram_access_token'));
        $this->assertNotSame('x-user-secret', DB::table('wordpress_sites')->whereKey($x->id)->value('x_access_token'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/17841400000000000')
            && $request['fields'] === 'id,username');
    }

    public function test_article_with_generated_image_can_be_published_to_instagram(): void
    {
        Storage::fake('local');
        $imageUrl = null;
        Http::fake(function (Request $request) use (&$imageUrl) {
            if (str_ends_with($request->url(), '/media_publish')) {
                return Http::response(['id' => '180000000000001']);
            }

            if (str_ends_with($request->url(), '/media')) {
                $imageUrl = $request['image_url'];

                return Http::response(['id' => 'container-123']);
            }

            if (str_contains($request->url(), '/180000000000001')) {
                return Http::response([
                    'id' => '180000000000001',
                    'permalink' => 'https://www.instagram.com/p/ABC123/',
                ]);
            }

            return Http::response([], 404);
        });
        $user = User::factory()->create();
        $profile = $this->profile($user, [
            'type' => WordPressSite::TYPE_INSTAGRAM,
            'name' => 'Instagram principal',
            'instagram_account_id' => '17841400000000000',
            'instagram_access_token' => 'instagram-secret',
            'instagram_api_version' => 'v24.0',
        ]);
        [$article, $image] = $this->articleWithImage($user);

        $this->actingAs($user)
            ->post(route('admin.publications.publish', $article), ['site_ids' => [$profile->id]])
            ->assertRedirect(route('admin.ai-articles.show', $article));

        $publication = Publication::query()->sole();
        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertSame('https://www.instagram.com/p/ABC123/', $publication->remote_url);
        $this->assertSame('publish_instagram_image', $publication->last_action);
        $this->assertNotNull($imageUrl);
        $mediaUrlBehindProxy = preg_replace('#^https?://[^/]+#', 'https://media-proxy.example', $imageUrl);
        $mediaResponse = $this->get($mediaUrlBehindProxy)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringEndsWith('/imagen.jpg', parse_url($imageUrl, PHP_URL_PATH));
        $this->assertStringStartsWith("\xFF\xD8", $mediaResponse->getContent());
        $imageSize = getimagesizefromstring($mediaResponse->getContent());
        $this->assertIsArray($imageSize);
        $this->assertGreaterThanOrEqual(0.8, $imageSize[0] / $imageSize[1]);
        $this->assertLessThanOrEqual(1.91, $imageSize[0] / $imageSize[1]);
        $this->get(preg_replace('/token=[^&]+/', 'token=invalid', $mediaUrlBehindProxy))->assertForbidden();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/media')
            && str_contains((string) $request['caption'], 'Artículo para redes'));
        $this->assertSame($image->id, $publication->ai_image_id);
    }

    public function test_article_can_be_published_to_x_with_a_user_access_token(): void
    {
        Storage::fake('local');
        Http::fake([
            'api.x.com/2/media/upload' => Http::response([
                'data' => [
                    'id' => 'media-190000000000001',
                    'media_key' => '3_media-190000000000001',
                ],
            ]),
            'api.x.com/2/tweets' => Http::response([
                'data' => [
                    'id' => '190000000000001',
                    'text' => 'Artículo para redes',
                ],
            ], 201),
        ]);
        $user = User::factory()->create();
        $profile = $this->profile($user, [
            'type' => WordPressSite::TYPE_X,
            'name' => 'X principal',
            'x_user_id' => '2244994945',
            'x_username' => 'noticias_x',
            'x_access_token' => 'x-user-secret',
        ]);
        [$article] = $this->articleWithImage($user);

        $this->actingAs($user)
            ->post(route('admin.publications.publish', $article), ['site_ids' => [$profile->id]])
            ->assertRedirect(route('admin.ai-articles.show', $article));

        $publication = Publication::query()->sole();
        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertSame('https://x.com/noticias_x/status/190000000000001', $publication->remote_url);
        $this->assertSame('publish_x_post', $publication->last_action);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/tweets'
            && mb_strlen((string) $request['text']) <= 280
            && data_get($request->data(), 'media.media_ids.0') === 'media-190000000000001'
            && $request->hasHeader('Authorization', 'Bearer x-user-secret'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/media/upload'
            && $request->hasHeader('Authorization', 'Bearer x-user-secret'));
    }

    public function test_x_publication_falls_back_to_text_when_media_upload_is_not_authorized(): void
    {
        Storage::fake('local');
        Http::fake([
            'api.x.com/2/media/upload' => Http::response([
                'detail' => 'The access token is missing media.write.',
            ], 403),
            'api.x.com/2/tweets' => Http::response([
                'data' => [
                    'id' => '190000000000002',
                    'text' => 'Artículo para redes',
                ],
            ], 201),
        ]);
        $user = User::factory()->create();
        $profile = $this->profile($user, [
            'type' => WordPressSite::TYPE_X,
            'name' => 'X sin media.write',
            'x_username' => 'noticias_x',
            'x_access_token' => 'x-user-secret',
        ]);
        [$article] = $this->articleWithImage($user);

        $this->actingAs($user)
            ->post(route('admin.publications.publish', $article), ['site_ids' => [$profile->id]])
            ->assertRedirect(route('admin.ai-articles.show', $article));

        $publication = Publication::query()->sole();
        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertTrue((bool) data_get($publication->full_response, 'media.omitted'));
        $this->assertStringContainsString('media.write', (string) data_get($publication->full_response, 'media.error'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/tweets'
            && data_get($request->data(), 'media') === null);
    }

    private function profile(User $user, array $attributes): WordPressSite
    {
        return WordPressSite::query()->create([
            'user_id' => $user->id,
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @return array{AiArticle, AiImage}
     */
    private function articleWithImage(User $user): array
    {
        $article = $user->aiArticles()->create([
            'title' => 'Artículo para redes',
            'content' => '<p>Contenido generado para publicar en redes sociales.</p>',
            'excerpt' => 'Resumen del artículo generado.',
            'slug' => 'articulo-para-redes',
            'tags' => ['Noticias', 'Tecnología'],
            'status' => AiArticle::STATUS_DRAFT,
        ]);
        $image = $article->images()->create([
            'type' => AiImage::TYPE_MAIN,
            'title' => 'Imagen principal',
            'prompt' => 'Imagen editorial',
            'resolution' => '1080x1080',
            'quality' => 'medium',
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/social.png',
            'mime_type' => 'image/png',
        ]);
        Storage::disk('local')->put(
            $image->file_path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );

        return [$article, $image];
    }
}
