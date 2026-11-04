<?php

namespace Tests\Feature\Publications;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use App\Services\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_wordpress_publications(): void
    {
        Http::fake([
            'wp.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 123,
                'link' => 'https://wp.test/post',
                'status' => 'draft',
            ]),
            'wp.test/wp-json/wp/v2/posts/123' => Http::response([
                'id' => 123,
                'link' => 'https://wp.test/post-updated',
                'status' => 'publish',
            ]),
        ]);

        $site = $this->site();
        $article = $this->article();
        $service = app(PublicationService::class);

        $publication = $service->createPublication($site, $article);

        $this->assertSame(Publication::STATUS_DRAFT, $publication->status);
        $this->assertNull($publication->remote_post_id);

        $publication = $service->createArticle($publication);

        $this->assertSame(123, $publication->remote_post_id);
        $this->assertSame('https://wp.test/post', $publication->remote_url);
        $this->assertSame('create_article', $publication->last_action);
        $this->assertSame(123, $publication->full_response['id']);

        $publication = $service->updateArticle($publication, ['title' => 'Actualizado']);

        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertSame('https://wp.test/post-updated', $publication->remote_url);
        $this->assertSame('update_article', $publication->last_action);
    }

    public function test_it_uploads_media_creates_terms_schedules_and_deletes(): void
    {
        Http::fake([
            'wp.test/wp-json/wp/v2/media' => Http::response(['id' => 55, 'source_url' => 'https://wp.test/image.jpg']),
            'wp.test/wp-json/wp/v2/categories' => Http::response(['id' => 7, 'name' => 'Noticias']),
            'wp.test/wp-json/wp/v2/tags' => Http::response(['id' => 8, 'name' => 'IA']),
            'wp.test/wp-json/wp/v2/posts' => Http::response(['id' => 124, 'link' => 'https://wp.test/future', 'status' => 'future']),
            'wp.test/wp-json/wp/v2/posts/124?force=false' => Http::response(['deleted' => true]),
        ]);

        $service = app(PublicationService::class);
        $publication = $service->createPublication($this->site(), $this->article());

        $publication = $service->uploadImage($publication, 'image-bytes', 'image.jpg');
        $category = $service->createCategory($publication->wordpressSite, 'Noticias');
        $tag = $service->createTag($publication->wordpressSite, 'IA');
        $publication = $service->schedulePublication($publication, '2026-07-01T10:00:00');
        $publication = $service->deletePublication($publication);

        $this->assertSame(55, $publication->remote_featured_media_id);
        $this->assertSame(7, $category['id']);
        $this->assertSame(8, $tag['id']);
        $this->assertSame(Publication::STATUS_DELETED, $publication->status);
        $this->assertSame('delete_publication', $publication->last_action);
    }

    public function test_wordpress_uses_the_first_image_as_featured_and_includes_the_rest_in_the_post(): void
    {
        Storage::fake('local');
        $mediaCount = 0;
        Http::fake(function (Request $request) use (&$mediaCount) {
            if (str_ends_with($request->url(), '/media')) {
                $mediaCount++;

                return Http::response([
                    'id' => $mediaCount,
                    'source_url' => "https://wp.test/media/{$mediaCount}.jpg",
                ]);
            }
            if (str_ends_with($request->url(), '/posts')) {
                return Http::response(['id' => 124, 'link' => 'https://wp.test/post', 'status' => 'publish'], 201);
            }

            return Http::response([], 404);
        });
        $site = $this->site();
        $article = $this->article();
        $main = $article->images()->create([
            'type' => AiImage::TYPE_MAIN,
            'prompt' => 'Imagen principal',
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/main.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $second = $article->images()->create([
            'type' => AiImage::TYPE_VARIANT,
            'prompt' => 'Imagen secundaria',
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/second.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        Storage::disk('local')->put($main->file_path, 'main-image');
        Storage::disk('local')->put($second->file_path, 'second-image');

        $publication = app(PublicationService::class)->publishNow($site, $article, $main);

        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertSame(1, $publication->remote_featured_media_id);
        $this->assertSame(2, $mediaCount);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/posts')
            && $request['featured_media'] === 1
            && str_contains((string) $request['content'], 'https://wp.test/media/2.jpg'));
    }

    public function test_republishing_an_existing_wordpress_post_adds_a_missing_gallery_without_replacing_its_featured_image(): void
    {
        Storage::fake('local');
        Http::fake([
            'wp.test/wp-json/wp/v2/media' => Http::response(['id' => 99, 'source_url' => 'https://wp.test/media/second.jpg']),
            'wp.test/wp-json/wp/v2/posts/124' => Http::response(['id' => 124, 'link' => 'https://wp.test/post', 'status' => 'publish']),
        ]);
        $site = $this->site();
        $article = $this->article();
        $main = $article->images()->create([
            'type' => AiImage::TYPE_MAIN,
            'prompt' => 'Principal',
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/main.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $second = $article->images()->create([
            'type' => AiImage::TYPE_VARIANT,
            'prompt' => 'Secundaria',
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/second.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        Storage::disk('local')->put($main->file_path, 'main-image');
        Storage::disk('local')->put($second->file_path, 'second-image');
        $publication = Publication::query()->create([
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'ai_image_id' => $main->id,
            'remote_post_id' => 124,
            'remote_featured_media_id' => 55,
            'status' => Publication::STATUS_PUBLISHED,
            'request_payload' => ['content' => '<p>Contenido</p>'],
        ]);

        $updated = app(PublicationService::class)->publishNow($site, $article, $main);

        $this->assertSame(55, $updated->remote_featured_media_id);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/media'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/posts/124')
            && $request['featured_media'] === 55
            && str_contains((string) $request['content'], 'https://wp.test/media/second.jpg'));
    }

    private function site(): WordPressSite
    {
        return WordPressSite::query()->create([
            'name' => 'WP Test',
            'rest_api_url' => 'https://wp.test',
            'username' => 'editor',
            'application_password' => 'app-pass',
            'categories' => ['Noticias'],
            'tags' => ['IA'],
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
    }

    private function article(): AiArticle
    {
        return AiArticle::query()->create([
            'title' => 'Artículo generado',
            'content' => '<p>Contenido</p>',
            'excerpt' => 'Extracto',
            'meta_description' => 'Meta',
            'slug' => 'articulo-generado',
            'seo_keywords' => ['wordpress'],
            'status' => AiArticle::STATUS_GENERATED,
        ]);
    }
}
