<?php

namespace App\Console\Commands;

use App\Services\SourcePipelineService;
use Illuminate\Console\Command;

class ScanDueSourceSites extends Command
{
    protected $signature = 'sources:scan-due';

    protected $description = 'Escanea los sitios fuente cuya frecuencia configurada ya venció';

    public function handle(SourcePipelineService $pipeline): int
    {
        $tasks = $pipeline->enqueueDue();

        foreach ($tasks as $task) {
            $this->line("{$task->sourceSite?->name}: trabajo #{$task->id} añadido a la cola.");
        }

        if ($tasks->isEmpty()) {
            $this->info('No hay sitios fuente pendientes de escaneo.');
        }

        return self::SUCCESS;
    }
}
