<?php

namespace Tests\Feature\News;

use App\Models\SourcePost;
use App\Models\SourceSite;
use App\Services\SourcePostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourcePostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_insert_duplicate_posts_with_the_same_hash(): void
    {
        $sourceSite = SourceSite::query()->create([
            'name' => 'Fuente RSS',
            'url' => 'https://example.com/feed',
            'type' => SourceSite::TYPE_RSS,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'active' => true,
        ]);

        $item = [
            'titulo' => 'Título duplicado',
            'contenido' => 'Contenido',
            'contenido_html' => '<p>Contenido</p>',
            'resumen' => 'Resumen',
            'autor' => 'Autor',
            'fecha' => '2026-06-29T12:00:00-06:00',
            'imagen' => 'https://example.com/image.jpg',
            'url' => 'https://example.com/post',
            'categorias' => ['Noticias'],
            'tags' => ['RSS'],
            'idioma' => 'es',
        ];

        $service = app(SourcePostService::class);

        $first = $service->storeNormalizedItem($sourceSite, $item);
        $second = $service->storeNormalizedItem($sourceSite, $item);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, SourcePost::query()->count());
        $this->assertSame(hash('sha256', 'https://example.com/post|Título duplicado|2026-06-29T12:00:00-06:00|<p>Contenido</p>'), $first->hash);
    }
}
