<?php

namespace Tests\Feature\Admin;

use App\Models\AiArticle;
use App\Models\SourceScanLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_articles_table_is_compact_without_horizontal_wrapper_and_uses_an_actions_menu(): void
    {
        $user = User::factory()->create();
        $article = $user->aiArticles()->create([
            'title' => 'Borrador compacto',
            'content' => '<p>Contenido.</p>',
            'excerpt' => 'Extracto del artículo.',
            'slug' => 'borrador-compacto',
            'model' => 'gpt-5-mini',
            'status' => AiArticle::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user)->get(route('admin.ai-articles.index'));

        $response
            ->assertOk()
            ->assertSee('Borrador compacto')
            ->assertSee('gpt-5-mini')
            ->assertSee('Ver borrador')
            ->assertSee('Editar')
            ->assertSee('Eliminar')
            ->assertSee(route('admin.ai-articles.destroy', $article), false)
            ->assertDontSee('class="table-responsive"', false)
            ->assertDontSee('<th>Modelo</th>', false);
    }

    public function test_source_scan_log_has_no_filter_form_and_does_not_apply_legacy_query_filters(): void
    {
        $user = User::factory()->create();
        SourceScanLog::query()->create([
            'title' => 'Nota aceptada',
            'outcome' => SourceScanLog::OUTCOME_ACCEPTED,
            'applies' => true,
            'reason' => 'Coincide con la cobertura.',
            'filter_method' => 'ai',
            'scanned_at' => now(),
        ]);
        SourceScanLog::query()->create([
            'title' => 'Nota descartada',
            'outcome' => SourceScanLog::OUTCOME_DISCARDED,
            'applies' => false,
            'reason' => 'No corresponde.',
            'filter_method' => 'validation',
            'scanned_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.source-scan-logs.index', [
            'outcome' => SourceScanLog::OUTCOME_ACCEPTED,
        ]));

        $response
            ->assertOk()
            ->assertSee('Nota aceptada')
            ->assertSee('Nota descartada')
            ->assertSee('Coincide con la cobertura.')
            ->assertDontSee('name="source_site_id"', false)
            ->assertDontSee('name="outcome"', false)
            ->assertDontSee('name="date_from"', false)
            ->assertDontSee('name="date_to"', false)
            ->assertDontSee('class="table-responsive"', false)
            ->assertDontSee('<th>Método</th>', false);
    }
}
