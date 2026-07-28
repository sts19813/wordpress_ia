<?php

namespace Tests\Feature\Admin;

use App\Models\SourceSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceSiteConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_basic_filter_and_advanced_tabs(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.source-sites.create'));

        $response->assertOk()
            ->assertSee('Datos básicos')
            ->assertSee('Filtros inteligentes')
            ->assertSee('Avanzado')
            ->assertSee('Probar y traer la nota más reciente')
            ->assertSee('Frecuencia de consulta')
            ->assertSee('horas')
            ->assertSee('Límite de posts escaneados al día')
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="language"', false)
            ->assertDontSee('name="priority"', false);
    }

    public function test_it_stores_hours_and_topic_filters(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('admin.source-sites.store'), [
                'name' => 'Medio demo',
                'url' => 'https://example.com',
                'type' => SourceSite::TYPE_AUTO,
                'frequency_hours' => 3,
                'filter_topics' => "Política\nEconomía",
                'excluded_topics' => "Tecnología\nDeportes",
                'filter_instructions' => 'Aceptar decisiones del Congreso.',
                'auth_method' => SourceSite::AUTH_NONE,
                'custom_headers' => '',
                'cookies' => '',
                'status' => SourceSite::STATUS_PENDING,
                'language' => 'es',
                'priority' => 5,
                'daily_limit' => 20,
                'active' => '1',
            ]);

        $response->assertRedirect(route('admin.source-sites.index'));
        $sourceSite = SourceSite::query()->firstOrFail();

        $this->assertSame(180, $sourceSite->frequency_minutes);
        $this->assertSame(['Política', 'Economía'], $sourceSite->filter_topics);
        $this->assertSame(['Tecnología', 'Deportes'], $sourceSite->excluded_topics);
    }

    public function test_it_tests_a_source_before_saving_and_returns_the_latest_full_post(): void
    {
        Http::fake([
            'example.com/feed' => Http::response($this->rss(), 200, ['Content-Type' => 'application/rss+xml']),
            'example.com/politica/nota-reciente' => Http::response(<<<'HTML'
                <html><head>
                    <meta property="og:title" content="Decisión económica del Congreso">
                    <meta property="og:image" content="https://example.com/congreso.jpg">
                    <meta property="article:published_time" content="2026-07-28T09:30:00-06:00">
                </head><body><article>
                    <h1>Decisión económica del Congreso</h1>
                    <p>El Congreso aprobó una reforma económica con impacto nacional y explicó sus alcances.</p>
                    <p>La medida entrará en vigor después de su publicación oficial.</p>
                </article></body></html>
                HTML),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.source-sites.test'), [
                'name' => 'Medio demo',
                'url' => 'https://example.com/feed',
                'type' => SourceSite::TYPE_AUTO,
                'auth_method' => SourceSite::AUTH_NONE,
            ]);

        $response->assertOk()
            ->assertJsonPath('recommendation.type', SourceSite::TYPE_RSS)
            ->assertJsonPath('tested_type', SourceSite::TYPE_RSS)
            ->assertJsonPath('post.title', 'Decisión económica del Congreso')
            ->assertJsonPath('post.image_url', 'https://example.com/congreso.jpg');

        $this->assertStringContainsString(
            'reforma económica',
            (string) $response->json('post.content'),
        );
        $this->assertStringContainsString(
            '<article>',
            (string) $response->json('post.raw_html'),
        );
    }

    private function rss(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0">
            <channel>
                <title>Medio demo</title>
                <item>
                    <title>Decisión económica del Congreso</title>
                    <link>https://example.com/politica/nota-reciente</link>
                    <pubDate>Tue, 28 Jul 2026 15:30:00 GMT</pubDate>
                    <description>Resumen de la decisión.</description>
                    <category>Política</category>
                </item>
            </channel>
        </rss>
        XML;
    }
}
