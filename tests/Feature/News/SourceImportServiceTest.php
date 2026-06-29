<?php

namespace Tests\Feature\News;

use App\Models\SourcePost;
use App\Models\SourceSite;
use App\Services\NewsSources\SourceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_wordpress_posts_and_skips_duplicates(): void
    {
        Http::fake([
            'example.com/wp-json/wp/v2/posts*' => Http::response([
                $this->wordpressPost(1),
                $this->wordpressPost(2),
                $this->wordpressPost(3),
            ]),
        ]);

        $sourceSite = SourceSite::query()->create([
            'name' => 'WordPress demo',
            'url' => 'https://example.com',
            'type' => SourceSite::TYPE_WORDPRESS_REST,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'active' => true,
        ]);

        $firstImport = app(SourceImportService::class)->import($sourceSite->id);
        $secondImport = app(SourceImportService::class)->import($sourceSite->id);

        $this->assertSame(3, $firstImport['fetched']);
        $this->assertSame(3, $firstImport['created']);
        $this->assertSame(0, $firstImport['duplicates']);
        $this->assertSame(3, $secondImport['fetched']);
        $this->assertSame(0, $secondImport['created']);
        $this->assertSame(3, $secondImport['duplicates']);
        $this->assertSame(3, SourcePost::query()->count());
        $this->assertNotNull($sourceSite->fresh()->last_synced_at);
        $this->assertSame('entrada-1', SourcePost::query()->first()->original_json['slug']);
    }

    /**
     * @return array<string, mixed>
     */
    private function wordpressPost(int $number): array
    {
        return [
            'title' => ['rendered' => "Entrada {$number}"],
            'content' => ['rendered' => "<p>Contenido {$number}</p>"],
            'excerpt' => ['rendered' => "<p>Resumen {$number}</p>"],
            'date' => "2026-06-29T1{$number}:00:00",
            'link' => "https://example.com/entrada-{$number}",
            'slug' => "entrada-{$number}",
            '_embedded' => [
                'author' => [['name' => 'Admin']],
                'wp:featuredmedia' => [['source_url' => "https://example.com/entrada-{$number}.jpg"]],
                'wp:term' => [
                    [
                        ['taxonomy' => 'category', 'name' => 'Noticias'],
                        ['taxonomy' => 'post_tag', 'name' => 'WordPress'],
                    ],
                ],
            ],
        ];
    }
}
