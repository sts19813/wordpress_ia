<?php

namespace Tests\Feature\Admin;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GlobalContentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_every_article_image_and_publication(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $article = $owner->aiArticles()->create([
            'title' => 'Contenido global de prueba',
            'content' => '<p>Visible para todos.</p>',
            'excerpt' => 'Extracto global',
            'slug' => 'contenido-global-de-prueba',
            'status' => AiArticle::STATUS_DRAFT,
        ]);
        $image = $article->images()->create([
            'type' => AiImage::TYPE_MAIN,
            'title' => 'Imagen global de prueba',
            'prompt' => 'Una imagen de prueba',
            'resolution' => '1536x1024',
            'quality' => 'medium',
            'status' => AiImage::STATUS_GENERATED,
            'file_path' => 'ai-images/global.png',
            'mime_type' => 'image/png',
        ]);
        Storage::disk('local')->put($image->file_path, 'fake-image');
        $site = WordPressSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Sitio global de prueba',
            'rest_api_url' => 'https://global.test',
            'username' => 'editor',
            'application_password' => 'secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        Publication::query()->create([
            'user_id' => $owner->id,
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'ai_image_id' => $image->id,
            'remote_url' => 'https://global.test/contenido',
            'status' => Publication::STATUS_PUBLISHED,
            'published_at' => now(),
            'last_action' => 'publish',
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.ai-articles.index'))
            ->assertOk()
            ->assertSee('Contenido global de prueba');

        $this->actingAs($viewer)
            ->get(route('admin.ai-articles.show', $article))
            ->assertOk()
            ->assertSee('Visible para todos.')
            ->assertSee('Sitio global de prueba');

        $this->actingAs($viewer)
            ->get(route('admin.ai-images.index'))
            ->assertOk()
            ->assertSee('Imagen global de prueba');

        $this->actingAs($viewer)
            ->get(route('admin.ai-images.file', $image))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('admin.publications.index'))
            ->assertOk()
            ->assertSee('Contenido global de prueba')
            ->assertSee('Sitio global de prueba');
    }
}
