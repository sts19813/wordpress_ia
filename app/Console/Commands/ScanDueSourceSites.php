<?php

namespace App\Console\Commands;

use App\Models\SourceSite;
use App\Services\NewsSources\SourceImportService;
use Illuminate\Console\Command;

class ScanDueSourceSites extends Command
{
    protected $signature = 'sources:scan-due';

    protected $description = 'Escanea los sitios fuente cuya frecuencia configurada ya venció';

    public function handle(SourceImportService $imports): int
    {
        $sites = SourceSite::query()
            ->where('active', true)
            ->where('status', '!=', SourceSite::STATUS_PAUSED)
            ->get()
            ->filter(fn (SourceSite $site) => $site->last_synced_at === null
                || $site->last_synced_at->copy()->addMinutes($site->frequency_minutes)->lte(now()));

        $errors = 0;

        foreach ($sites as $site) {
            $result = $imports->importSource($site);

            if ($result['error']) {
                $errors++;
                $this->error($result['error']);
            } else {
                $this->line("{$site->name}: {$result['created']} nuevas, {$result['discarded']} descartadas.");
            }
        }

        if ($sites->isEmpty()) {
            $this->info('No hay sitios fuente pendientes de escaneo.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
