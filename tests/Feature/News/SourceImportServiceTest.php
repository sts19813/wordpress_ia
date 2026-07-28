<?php

namespace Tests\Feature\News;

use App\Models\SourcePost;
use App\Models\SourceScanLog;
use App\Models\SourceSite;
use App\Services\NewsSources\SourceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

    public function test_it_skips_items_that_point_to_a_homepage_instead_of_a_post(): void
    {
        Http::fake([
            'example.com/wp-json/wp/v2/posts*' => Http::response([
                $this->wordpressPost(1),
                array_merge($this->wordpressPost(2), [
                    'link' => 'https://example.com/',
                    'title' => ['rendered' => 'Example Site'],
                ]),
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

        $result = app(SourceImportService::class)->import($sourceSite->id);

        $this->assertSame(2, $result['fetched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, SourcePost::query()->count());
        $this->assertSame('https://example.com/entrada-1', SourcePost::query()->value('url'));
    }

    public function test_it_uses_ai_filters_fetches_only_matching_full_posts_and_logs_every_scan(): void
    {
        config(['services.openai.api_key' => 'test-key']);

        $politics = $this->wordpressPost(1);
        $politics['title']['rendered'] = 'El Congreso aprueba el paquete económico';
        $politics['excerpt']['rendered'] = '<p>Diputados votaron la reforma.</p>';
        $politics['link'] = 'https://example.com/entrada-politica';

        $technology = $this->wordpressPost(2);
        $technology['title']['rendered'] = 'Nuevo teléfono incorpora inteligencia artificial';
        $technology['excerpt']['rendered'] = '<p>La empresa presentó su dispositivo.</p>';
        $technology['link'] = 'https://example.com/entrada-tecnologia';

        Http::fake(function (Request $request) use ($politics, $technology) {
            if (str_contains($request->url(), '/wp-json/wp/v2/posts')) {
                return Http::response([$politics, $technology]);
            }

            if (str_contains($request->url(), '/responses')) {
                $isTechnology = str_contains((string) $request['input'], 'Nuevo teléfono');
                $decision = [
                    'applies' => ! $isTechnology,
                    'reason' => $isTechnology
                        ? 'La nota es de tecnología y no corresponde a política ni economía.'
                        : 'La decisión del Congreso corresponde a política y economía.',
                    'matched_topics' => $isTechnology ? [] : ['Política', 'Economía'],
                ];

                return Http::response([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode($decision, JSON_UNESCAPED_UNICODE),
                        ]],
                    ]],
                ]);
            }

            if (str_contains($request->url(), '/entrada-politica')) {
                return Http::response('<html><body><article><h1>El Congreso aprueba el paquete económico</h1><p>Contenido político completo para la publicación aceptada.</p></article></body></html>');
            }

            return Http::response('', 404);
        });

        $sourceSite = SourceSite::query()->create([
            'name' => 'WordPress filtrado',
            'url' => 'https://example.com',
            'type' => SourceSite::TYPE_WORDPRESS_REST,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'filter_topics' => ['Política', 'Economía'],
            'excluded_topics' => ['Tecnología'],
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'active' => true,
        ]);

        $result = app(SourceImportService::class)->import($sourceSite->id);

        $this->assertSame(2, $result['fetched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['discarded']);
        $this->assertSame(1, SourcePost::query()->count());
        $this->assertSame('El Congreso aprueba el paquete económico', SourcePost::query()->value('title'));
        $this->assertSame(2, SourceScanLog::query()->count());
        $this->assertDatabaseHas('source_scan_logs', ['outcome' => SourceScanLog::OUTCOME_ACCEPTED, 'applies' => true]);
        $this->assertDatabaseHas('source_scan_logs', ['outcome' => SourceScanLog::OUTCOME_DISCARDED, 'applies' => false]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/entrada-tecnologia'));
    }

    public function test_it_respects_the_daily_scanned_post_limit(): void
    {
        Http::fake([
            'example.com/wp-json/wp/v2/posts*' => Http::response([
                $this->wordpressPost(1),
                $this->wordpressPost(2),
                $this->wordpressPost(3),
            ]),
            'example.com/entrada-*' => Http::response('<html><body><article><p>Contenido completo.</p></article></body></html>'),
        ]);

        $sourceSite = SourceSite::query()->create([
            'name' => 'WordPress limitado',
            'url' => 'https://example.com',
            'type' => SourceSite::TYPE_WORDPRESS_REST,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'daily_limit' => 1,
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'active' => true,
        ]);

        $firstImport = app(SourceImportService::class)->import($sourceSite->id);
        $secondImport = app(SourceImportService::class)->import($sourceSite->id);

        $this->assertSame(1, $firstImport['fetched']);
        $this->assertSame(1, $firstImport['created']);
        $this->assertSame([$sourceSite->name], $firstImport['limits_reached']);
        $this->assertSame(0, $secondImport['fetched']);
        $this->assertSame([$sourceSite->name], $secondImport['limits_reached']);
        $this->assertSame(1, SourcePost::query()->count());
        $this->assertSame(1, SourceScanLog::query()->count());
    }

    public function test_auto_import_uses_ai_to_discover_and_extract_posts_from_an_unknown_structure(): void
    {
        config(['services.openai.api_key' => 'test-key']);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/responses')) {
                $schema = data_get($request->data(), 'text.format.name');
                $payload = $schema === 'ai_source_post_discovery'
                    ? [
                        'site_kind' => 'Aplicación web de noticias',
                        'structure_summary' => 'Listado almacenado en JSON de hidratación.',
                        'posts' => [[
                            'title' => 'Congreso aprueba reforma económica',
                            'url' => '/noticias/reforma-economica',
                            'published_at' => '2026-07-28T12:00:00-06:00',
                            'image_url' => '/images/reforma.jpg',
                            'summary' => 'La votación concluyó este martes.',
                        ]],
                    ]
                    : [
                        'title' => 'Congreso aprueba reforma económica',
                        'content' => 'El Congreso aprobó la reforma económica después de una sesión pública.',
                        'content_html' => '<p>El Congreso aprobó la reforma económica después de una sesión pública.</p>',
                        'summary' => 'La votación concluyó este martes.',
                        'author' => 'Mesa de redacción',
                        'published_at' => '2026-07-28T12:00:00-06:00',
                        'image_url' => 'https://example.com/images/reforma.jpg',
                        'categories' => ['Política'],
                        'tags' => ['Economía'],
                    ];

                return Http::response([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                    ]],
                ]);
            }

            if (str_contains($request->url(), '/noticias/reforma-economica')) {
                return Http::response('<html><body><div data-view="story">Contenido en estructura propia.</div></body></html>');
            }

            return Http::response(<<<'HTML'
                <html><head><title>Noticias dinámicas</title></head><body>
                    <main><div id="root">Aplicación de noticias</div></main>
                    <script type="application/json">{"stories":[{"headline":"Congreso aprueba reforma económica","path":"/noticias/reforma-economica"}]}</script>
                </body></html>
                HTML, 200, ['Content-Type' => 'text/html']);
        });

        $sourceSite = SourceSite::query()->create([
            'name' => 'Medio sin conector',
            'url' => 'https://example.com',
            'type' => SourceSite::TYPE_AUTO,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'daily_limit' => 20,
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'active' => true,
        ]);

        $result = app(SourceImportService::class)->import($sourceSite->id);

        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame('Congreso aprueba reforma económica', SourcePost::query()->value('title'));
        $this->assertSame('Mesa de redacción', SourcePost::query()->value('author'));
        $this->assertTrue((bool) SourcePost::query()->value('filter_applies'));
        $this->assertSame(
            SourceSite::TYPE_AI_WEB,
            SourceScanLog::query()->firstOrFail()->metadata['connection_type'],
        );
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
