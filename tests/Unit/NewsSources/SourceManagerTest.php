<?php

namespace Tests\Unit\NewsSources;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\SourceManager;
use App\Services\NewsSources\Strategies\RSSSourceStrategy;
use App\Services\NewsSources\Strategies\ScrapingSourceStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceManagerTest extends TestCase
{
    public function test_wordpress_strategy_returns_normalized_collection(): void
    {
        Http::fake([
            'example.com/wp-json/wp/v2/posts*' => Http::response([
                [
                    'title' => ['rendered' => 'Nota WP'],
                    'content' => ['rendered' => '<p>Contenido completo</p>'],
                    'excerpt' => ['rendered' => '<p>Resumen WP</p>'],
                    'date' => '2026-06-29T10:00:00',
                    'link' => 'https://example.com/nota-wp',
                    'slug' => 'nota-wp',
                    '_embedded' => [
                        'author' => [['name' => 'Editora']],
                        'wp:featuredmedia' => [['source_url' => 'https://example.com/image.jpg']],
                        'wp:term' => [
                            [
                                ['taxonomy' => 'category', 'name' => 'Mercado'],
                                ['taxonomy' => 'post_tag', 'name' => 'IA'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $items = app(SourceManager::class)->fetch(new SourceSite([
            'name' => 'WP',
            'url' => 'https://example.com',
            'type' => SourceSite::TYPE_WORDPRESS_REST,
            'language' => 'es',
            'auth_method' => SourceSite::AUTH_NONE,
        ]));

        $this->assertCount(1, $items);
        $this->assertSame([
            'titulo',
            'contenido',
            'autor',
            'fecha',
            'imagen',
            'url',
            'categorias',
            'tags',
            'contenido_html',
            'resumen',
            'slug',
            'idioma',
        ], array_keys($items->first()));
        $this->assertSame('Nota WP', $items->first()['titulo']);
        $this->assertSame(['Mercado'], $items->first()['categorias']);
        $this->assertSame(['IA'], $items->first()['tags']);
    }

    public function test_rss_strategy_parses_feed_items(): void
    {
        $xml = <<<'XML'
        <rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
            <channel>
                <item>
                    <title>Nota RSS</title>
                    <link>https://example.com/rss</link>
                    <dc:creator>Reportera</dc:creator>
                    <pubDate>Mon, 29 Jun 2026 12:00:00 GMT</pubDate>
                    <category>Noticias</category>
                    <content:encoded><![CDATA[<p>Contenido RSS</p>]]></content:encoded>
                </item>
            </channel>
        </rss>
        XML;

        $items = app(RSSSourceStrategy::class)->parse($xml, new SourceSite([
            'name' => 'RSS',
            'url' => 'https://example.com/feed',
            'type' => SourceSite::TYPE_RSS,
            'language' => 'es',
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('Nota RSS', $items->first()['titulo']);
        $this->assertSame('Reportera', $items->first()['autor']);
        $this->assertSame(['Noticias'], $items->first()['categorias']);
        $this->assertSame('es', $items->first()['idioma']);
    }

    public function test_scraping_strategy_parses_html_articles(): void
    {
        $html = <<<'HTML'
        <html>
            <head>
                <meta property="article:section" content="Tecnología">
                <meta name="keywords" content="wordpress, ia">
            </head>
            <body>
                <article>
                    <h2>Nota HTML</h2>
                    <a href="/nota-html">Leer</a>
                    <span class="author">Redacción</span>
                    <time datetime="2026-06-29T09:00:00-06:00"></time>
                    <img src="/nota.jpg">
                    <p>Primer párrafo.</p>
                    <p>Segundo párrafo.</p>
                </article>
            </body>
        </html>
        HTML;

        $items = app(ScrapingSourceStrategy::class)->parse($html, new SourceSite([
            'name' => 'HTML',
            'url' => 'https://example.com/noticias',
            'type' => SourceSite::TYPE_HTML,
            'language' => 'es',
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('Nota HTML', $items->first()['titulo']);
        $this->assertSame('https://example.com/nota-html', $items->first()['url']);
        $this->assertSame('https://example.com/nota.jpg', $items->first()['imagen']);
        $this->assertSame(['Tecnología'], $items->first()['categorias']);
        $this->assertSame(['wordpress', 'ia'], $items->first()['tags']);
    }

    public function test_source_manager_allows_registering_new_types(): void
    {
        $manager = new SourceManager;
        $manager->register('custom', new class implements SourceStrategyInterface
        {
            public function validate(SourceSite $sourceSite): void {}

            public function fetch(SourceSite $sourceSite): mixed
            {
                return [['titulo' => 'Custom']];
            }

            public function parse(mixed $payload, SourceSite $sourceSite): Collection
            {
                return collect($payload);
            }
        });

        $items = $manager->fetch(new SourceSite([
            'type' => 'custom',
            'url' => 'https://example.com',
        ]));

        $this->assertSame('Custom', $items->first()['titulo']);
    }
}
