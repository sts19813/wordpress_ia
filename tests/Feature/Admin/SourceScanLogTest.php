<?php

namespace Tests\Feature\Admin;

use App\Models\SourceScanLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceScanLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_button_to_clear_the_log(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.source-scan-logs.index'));

        $response
            ->assertOk()
            ->assertSee('Borrar bitácora')
            ->assertSee(route('admin.source-scan-logs.destroy'), false)
            ->assertSee('disabled', false);
    }

    public function test_index_only_loads_the_most_recent_two_hundred_logs(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 205) as $position) {
            SourceScanLog::query()->create([
                'title' => sprintf('Registro-%03d', $position),
                'outcome' => SourceScanLog::OUTCOME_ACCEPTED,
                'applies' => true,
                'scanned_at' => now()->subSeconds(205 - $position),
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.source-scan-logs.index'));
        $logs = $response->viewData('logs');

        $response
            ->assertOk()
            ->assertSee('Registro-205')
            ->assertDontSee('Registro-001')
            ->assertSee('últimos 200 registros');
        $this->assertCount(200, $logs);
        $this->assertSame(205, $response->viewData('logCount'));
    }

    public function test_authenticated_user_can_clear_all_source_scan_logs(): void
    {
        $user = User::factory()->create();

        SourceScanLog::query()->create([
            'title' => 'Primera nota',
            'outcome' => SourceScanLog::OUTCOME_ACCEPTED,
            'applies' => true,
            'scanned_at' => now(),
        ]);
        SourceScanLog::query()->create([
            'title' => 'Segunda nota',
            'outcome' => SourceScanLog::OUTCOME_DISCARDED,
            'applies' => false,
            'scanned_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('admin.source-scan-logs.destroy'));

        $response
            ->assertRedirect(route('admin.source-scan-logs.index'))
            ->assertSessionHas('status', 'Se eliminaron 2 registros de la bitácora.');
        $this->assertDatabaseCount('source_scan_logs', 0);
    }

    public function test_guest_cannot_clear_source_scan_logs(): void
    {
        SourceScanLog::query()->create([
            'title' => 'Nota protegida',
            'outcome' => SourceScanLog::OUTCOME_ACCEPTED,
            'applies' => true,
            'scanned_at' => now(),
        ]);

        $response = $this->delete(route('admin.source-scan-logs.destroy'));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('source_scan_logs', 1);
    }
}
