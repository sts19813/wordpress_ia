<?php

namespace Tests\Feature\Admin;

use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiProductionReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_report_summarizes_generated_and_published_articles_by_day_and_destination(): void
    {
        Carbon::setTestNow('2026-09-03 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $company = $admin->companies()->create(['name' => 'Portal de prueba', 'active' => true]);
        $wordpress = $this->site($admin, $company->id, 'Sitio principal', WordPressSite::TYPE_WORDPRESS);
        $facebook = $this->site($admin, $company->id, 'Facebook principal', WordPressSite::TYPE_FACEBOOK_PAGE);

        $published = $this->article($admin, $company->id, 'Nota publicada', '2026-08-01 09:30:00');
        $unpublished = $this->article($admin, $company->id, 'Nota pendiente', '2026-08-02 11:00:00');
        $this->publication($published, $wordpress, 'https://portal.test/nota-publicada', '2026-08-01 10:00:00');
        $this->publication($published, $facebook, 'https://facebook.com/test/posts/1', '2026-08-01 10:05:00');

        $failure = AiArticle::query()->create([
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'title' => 'Generación fallida',
            'status' => AiArticle::STATUS_FAILED,
            'generation_error' => 'No hay créditos disponibles.',
        ]);
        $failure->timestamps = false;
        $failure->forceFill([
            'created_at' => Carbon::parse('2026-08-02 12:00:00'),
            'updated_at' => Carbon::parse('2026-08-02 12:00:00'),
        ])->save();
        AiArticle::query()->create([
            'user_id' => $admin->id,
            'title' => 'Prueba de conexión WordPress IA',
            'status' => AiArticle::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ai-production-report.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-02',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['generated']);
        $this->assertSame(1, $summary['published']);
        $this->assertSame(1, $summary['unpublished']);
        $this->assertSame(2, $summary['publication_sends']);
        $this->assertSame(1, $summary['failed_generations']);
        $this->assertSame(50.0, $summary['publication_rate']);

        $response
            ->assertSee('Resumen de producción IA')
            ->assertSee('Exportar Excel')
            ->assertSee('Nota publicada')
            ->assertSee('Nota pendiente')
            ->assertSee('Sitio principal')
            ->assertSee('Facebook principal')
            ->assertSee('No hay créditos disponibles.');

        $this->assertNotSame($published->id, $unpublished->id);
    }

    public function test_report_filters_by_publication_state_and_exports_a_real_xlsx_file(): void
    {
        Carbon::setTestNow('2026-09-03 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $company = $admin->companies()->create(['name' => 'Empresa exportable', 'active' => true]);
        $site = $this->site($admin, $company->id, 'Destino exportable', WordPressSite::TYPE_WORDPRESS);
        $published = $this->article($admin, $company->id, '=Título protegido', '2026-08-10 08:00:00');
        $this->article($admin, $company->id, 'Nota no exportada', '2026-08-10 09:00:00');
        $this->publication($published, $site, 'https://export.test/nota', '2026-08-10 08:10:00');

        $parameters = [
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-10',
            'company_id' => $company->id,
            'publication_status' => 'published',
        ];

        $this->actingAs($admin)
            ->get(route('admin.ai-production-report.index', $parameters))
            ->assertOk()
            ->assertSee('=Título protegido')
            ->assertDontSee('Nota no exportada');

        $response = $this->actingAs($admin)->get(route('admin.ai-production-report.export', $parameters));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('reporte-produccion-ia-2026-08-10-2026-08-10.xlsx');

        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);
        $this->assertStringContainsString('[Content_Types].xml', $content);
    }

    private function article(User $user, int $companyId, string $title, string $generatedAt): AiArticle
    {
        return AiArticle::query()->create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'title' => $title,
            'content' => '<p>Contenido.</p>',
            'status' => AiArticle::STATUS_DRAFT,
            'generated_at' => Carbon::parse($generatedAt),
        ]);
    }

    private function site(User $user, int $companyId, string $name, string $type): WordPressSite
    {
        return WordPressSite::query()->create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'type' => $type,
            'name' => $name,
            'rest_api_url' => $type === WordPressSite::TYPE_WORDPRESS ? 'https://portal.test' : null,
            'username' => $type === WordPressSite::TYPE_WORDPRESS ? 'editor' : null,
            'application_password' => $type === WordPressSite::TYPE_WORDPRESS ? 'secret' : null,
            'facebook_page_id' => $type === WordPressSite::TYPE_FACEBOOK_PAGE ? '123' : null,
            'facebook_access_token' => $type === WordPressSite::TYPE_FACEBOOK_PAGE ? 'token' : null,
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
    }

    private function publication(AiArticle $article, WordPressSite $site, string $url, string $publishedAt): Publication
    {
        return Publication::query()->create([
            'user_id' => $article->user_id,
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'remote_url' => $url,
            'status' => Publication::STATUS_PUBLISHED,
            'published_at' => Carbon::parse($publishedAt),
            'last_action' => 'publish',
        ]);
    }
}
