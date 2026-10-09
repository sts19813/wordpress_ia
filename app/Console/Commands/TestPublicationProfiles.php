<?php

namespace App\Console\Commands;

use App\Models\WordPressSite;
use App\Services\PublicationService;
use Illuminate\Console\Command;
use Throwable;

class TestPublicationProfiles extends Command
{
    protected $signature = 'publication-profiles:test
        {--all : Incluye perfiles pausados o con error}
        {--profile=* : ID de perfil especifico a probar}';

    protected $description = 'Prueba las conexiones de WordPress, Facebook e Instagram sin mostrar credenciales.';

    public function handle(PublicationService $publications): int
    {
        $profiles = WordPressSite::query()
            ->when(! $this->option('all'), fn ($query) => $query
                ->where('active', true)
                ->where('status', WordPressSite::STATUS_ACTIVE))
            ->when($this->option('profile') !== [], fn ($query) => $query
                ->whereIn('id', array_map('intval', (array) $this->option('profile'))))
            ->orderBy('id')
            ->get();

        if ($profiles->isEmpty()) {
            $this->warn('No hay perfiles de publicacion para probar.');

            return self::SUCCESS;
        }

        $failed = 0;
        $rows = [];

        foreach ($profiles as $profile) {
            try {
                $result = $publications->testConnection($profile);
                $rows[] = [
                    $profile->id,
                    $profile->type,
                    $profile->name,
                    'OK',
                    collect([
                        $result['name'] ?? null,
                        $result['id'] ?? null,
                        $result['link'] ?? null,
                    ])->filter()->implode(' | '),
                ];
            } catch (Throwable $exception) {
                $failed++;
                $rows[] = [
                    $profile->id,
                    $profile->type,
                    $profile->name,
                    'FAIL',
                    $exception->getMessage(),
                ];
            }
        }

        $this->table(['ID', 'Tipo', 'Perfil', 'Estado', 'Detalle'], $rows);

        if ($failed > 0) {
            $this->error("{$failed} perfil(es) fallaron.");

            return self::FAILURE;
        }

        $this->info('Todos los perfiles probados respondieron correctamente.');

        return self::SUCCESS;
    }
}
