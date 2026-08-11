<?php

namespace Tests\Feature\Admin;

use App\Models\SourceSite;
use App\Models\User;
use App\Models\WordPressSite;
use App\Services\AiPromptProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceSiteConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_basic_filter_and_advanced_tabs(): void
    {
        $user = User::factory()->create();
        $user->wordpressSites()->create([
            'name' => 'Destino disponible',
            'rest_api_url' => 'https://destination.test',
            'username' => 'editor',
            'application_password' => 'app-pass',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.source-sites.create'));

        $response->assertOk()
            ->assertSee('Datos básicos')
            ->assertSee('Filtros inteligentes')
            ->assertSee('Avanzado')
            ->assertSee('Probar y traer la nota más reciente')
            ->assertSee('Frecuencia de consulta')
            ->assertSee('horas')
            ->assertSee('Navegación y extracción con IA')
            ->assertSee('Límite de posts escaneados al día')
            ->assertSee('Máximo de posts por consulta')
            ->assertSee('Máximo de artículos generados por consulta')
            ->assertSee('name="publication_profile_ids[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('Seleccionar todos')
            ->assertSee('id="save-source-button"', false)
            ->assertDontSee('id="save-source-button" disabled', false)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="language"', false)
            ->assertDontSee('name="priority"', false);
    }

    public function test_it_stores_multiple_automatic_publication_profiles(): void
    {
        $user = User::factory()->create();
        $promptProfile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $publicationProfiles = collect(['Sitio principal', 'Sitio secundario'])->map(
            fn (string $name) => $user->wordpressSites()->create([
                'name' => $name,
                'rest_api_url' => 'https://'.str($name)->slug().'.test',
                'username' => 'editor',
                'application_password' => 'app-pass',
                'status' => WordPressSite::STATUS_ACTIVE,
                'active' => true,
            ]),
        );

        $response = $this->actingAs($user)
            ->post(route('admin.source-sites.store'), [
                'name' => 'Medio con multidestino',
                'url' => 'https://example.com',
                'type' => SourceSite::TYPE_AUTO,
                'frequency_hours' => 3,
                'auth_method' => SourceSite::AUTH_NONE,
                'daily_limit' => 20,
                'max_posts_per_scan' => 12,
                'max_generations_per_scan' => 4,
                'ai_prompt_profile_id' => $promptProfile->id,
                'publication_profile_ids' => $publicationProfiles->pluck('id')->all(),
                'auto_generate' => '1',
                'auto_publish' => '1',
                'active' => '1',
            ]);

        $response->assertRedirect(route('admin.source-sites.index'));
        $sourceSite = SourceSite::query()->sole();

        $this->assertSame($publicationProfiles->pluck('id')->all(), $sourceSite->publication_profile_ids);
        $this->assertSame($publicationProfiles->first()->id, $sourceSite->wordpress_site_id);
        $this->assertTrue($sourceSite->auto_publish);
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
                'max_posts_per_scan' => 12,
                'max_generations_per_scan' => 4,
                'active' => '1',
            ]);

        $response->assertRedirect(route('admin.source-sites.index'));
        $sourceSite = SourceSite::query()->firstOrFail();

        $this->assertSame(180, $sourceSite->frequency_minutes);
        $this->assertSame(['Política', 'Economía'], $sourceSite->filter_topics);
        $this->assertSame(['Tecnología', 'Deportes'], $sourceSite->excluded_topics);
        $this->assertSame(12, $sourceSite->max_posts_per_scan);
        $this->assertSame(4, $sourceSite->max_generations_per_scan);
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

    public function test_automatic_connection_uses_ai_when_conventional_extractors_find_no_posts(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/responses')) {
                $schema = data_get($request->data(), 'text.format.name');

                if ($schema === 'ai_source_post_discovery') {
                    return $this->openAiResponse([
                        'site_kind' => 'Medio construido como aplicación web',
                        'structure_summary' => 'Las publicaciones están declaradas dentro del estado JSON de la portada.',
                        'posts' => [[
                            'title' => 'Reforma económica entra en vigor',
                            'url' => '/politica/reforma-economica',
                            'published_at' => '2026-07-28T11:00:00-06:00',
                            'image_url' => '/media/reforma.jpg',
                            'summary' => 'La reforma fue publicada este lunes.',
                        ]],
                    ]);
                }

                return $this->openAiResponse([
                    'title' => 'Reforma económica entra en vigor',
                    'content' => str_repeat('Contenido completo de la reforma económica. ', 12),
                    'content_html' => '<p>Contenido completo de la reforma económica.</p>',
                    'summary' => 'La reforma fue publicada este lunes.',
                    'author' => 'Redacción',
                    'published_at' => '2026-07-28T11:00:00-06:00',
                    'image_url' => 'https://example.com/media/reforma.jpg',
                    'categories' => ['Política', 'Economía'],
                    'tags' => ['Congreso'],
                ]);
            }

            if (str_contains($request->url(), '/politica/reforma-economica')) {
                return Http::response('<html><body><div id="app">Contenido cargado por una estructura no convencional.</div></body></html>');
            }

            return Http::response(<<<'HTML'
                <html>
                    <head><title>Portada dinámica</title></head>
                    <body>
                        <main><div id="app">Cargando publicaciones…</div></main>
                        <script type="application/json">{"posts":[{"title":"Reforma económica entra en vigor","url":"/politica/reforma-economica"}]}</script>
                    </body>
                </html>
                HTML, 200, ['Content-Type' => 'text/html']);
        });

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.source-sites.test'), [
                'name' => 'Medio dinámico',
                'url' => 'https://example.com',
                'type' => SourceSite::TYPE_AUTO,
                'auth_method' => SourceSite::AUTH_NONE,
            ]);

        $response->assertOk()
            ->assertJsonPath('tested_type', SourceSite::TYPE_AI_WEB)
            ->assertJsonPath('recommendation.type', SourceSite::TYPE_AI_WEB)
            ->assertJsonPath('post.title', 'Reforma económica entra en vigor')
            ->assertJsonPath('post.author', 'Redacción');

        $this->assertStringContainsString('Contenido completo', (string) $response->json('post.content'));
    }

    public function test_automatic_connection_uses_reader_fallback_for_a_cloudflare_challenge(): void
    {
        $articleUrl = 'https://blocked-news.test/novedades/nota-reciente-123.html';
        $paragraph = str_repeat('La información confirmada forma parte del contenido completo de la publicación. ', 5);

        Http::fake(function (Request $request) use ($articleUrl, $paragraph) {
            return match ($request->url()) {
                'https://blocked-news.test/novedades/' => Http::response(
                    '<!doctype html><html><title>Just a moment...</title><body><div class="cf-chl-token">Cloudflare</div></body></html>',
                    403,
                    ['Content-Type' => 'text/html'],
                ),
                'https://r.jina.ai/https://blocked-news.test/novedades/' => Http::response(
                    "Title: Noticias recientes\n\n## [Nota reciente obtenida correctamente]({$articleUrl})",
                    200,
                    ['Content-Type' => 'text/plain'],
                ),
                $articleUrl => Http::response(
                    '<!doctype html><html><title>Just a moment...</title><body>Cloudflare Ray ID</body></html>',
                    403,
                    ['Content-Type' => 'text/html'],
                ),
                'https://r.jina.ai/'.$articleUrl => Http::response(
                    "Title: Nota reciente obtenida correctamente\n\n{$paragraph}\n\n{$paragraph}\n\n{$paragraph}",
                    200,
                    ['Content-Type' => 'text/plain'],
                ),
                default => Http::response('Not found', 404),
            };
        });

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.source-sites.test'), [
                'name' => 'Medio protegido',
                'url' => 'https://blocked-news.test/novedades/',
                'type' => SourceSite::TYPE_AUTO,
                'auth_method' => SourceSite::AUTH_NONE,
            ]);

        $response->assertOk()
            ->assertJsonPath('tested_type', SourceSite::TYPE_HTML)
            ->assertJsonPath('post.title', 'Nota reciente obtenida correctamente')
            ->assertJsonPath('post.url', $articleUrl)
            ->assertJsonPath('post.has_full_content', true);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://r.jina.ai/'.$articleUrl);
    }

    public function test_edit_form_can_select_companies_and_destinations_from_different_owners(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $ownerCompany = $owner->companies()->create(['name' => 'Empresa propietaria', 'active' => true]);
        $otherCompany = $otherOwner->companies()->create(['name' => 'Empresa destino global', 'active' => true]);
        $promptProfile = app(AiPromptProfileService::class)->ensureDefaultFor($owner);
        $otherDestination = $otherOwner->wordpressSites()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Destino global visible',
            'rest_api_url' => 'https://global-destination.test',
            'username' => 'editor',
            'application_password' => 'secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        $sourceSite = SourceSite::query()->create([
            'automation_user_id' => $owner->id,
            'company_id' => $ownerCompany->id,
            'ai_prompt_profile_id' => $promptProfile->id,
            'name' => 'Fuente editable',
            'url' => 'https://source.test',
            'type' => SourceSite::TYPE_AUTO,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'auth_method' => SourceSite::AUTH_NONE,
            'daily_limit' => 20,
            'max_posts_per_scan' => 20,
            'max_generations_per_scan' => 5,
            'active' => true,
            'auto_generate' => true,
            'auto_publish' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.source-sites.edit', $sourceSite))
            ->assertOk()
            ->assertSee($ownerCompany->name)
            ->assertSee($otherCompany->name)
            ->assertSee($otherDestination->name);

        $this->actingAs($admin)
            ->put(route('admin.source-sites.update', $sourceSite), [
                'name' => $sourceSite->name,
                'url' => $sourceSite->url,
                'type' => SourceSite::TYPE_AUTO,
                'frequency_hours' => 1,
                'auth_method' => SourceSite::AUTH_NONE,
                'daily_limit' => 20,
                'max_posts_per_scan' => 20,
                'max_generations_per_scan' => 5,
                'ai_prompt_profile_id' => $promptProfile->id,
                'company_id' => $otherCompany->id,
                'publication_profile_ids' => [$otherDestination->id],
                'auto_generate' => '1',
                'auto_publish' => '1',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.source-sites.index'));

        $this->assertSame($otherCompany->id, $sourceSite->fresh()->company_id);
        $this->assertSame([$otherDestination->id], $sourceSite->fresh()->publication_profile_ids);
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

    private function openAiResponse(array $payload): mixed
    {
        return Http::response([
            'output' => [[
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
            ]],
        ]);
    }
}
