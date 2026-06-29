<?php

namespace Tests\Feature\Publications;

use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\WordPressSite;
use App\Services\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
