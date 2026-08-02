<?php

namespace App\Services;

use App\Jobs\GenerateSourceArticle;
use App\Jobs\ScanSourceSite;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Models\SourceSite;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SourcePipelineService
{
    /**
     * @return Collection<int, Scheduler>
     */
    public function enqueueDue(): Collection
    {
        return SourceSite::query()
            ->where('active', true)
            ->where('status', '!=', SourceSite::STATUS_PAUSED)
            ->where(fn ($query) => $query
                ->whereNull('next_scan_at')
                ->orWhere('next_scan_at', '<=', now()))
            ->orderBy('next_scan_at')
            ->get()
            ->map(fn (SourceSite $site) => $this->enqueueScan($site, 'scheduled'))
            ->filter()
            ->values();
    }

    public function enqueueScan(SourceSite $sourceSite, string $trigger = 'manual', ?User $actor = null): ?Scheduler
    {
        $created = false;

        $task = DB::transaction(function () use ($sourceSite, $trigger, $actor, &$created): ?Scheduler {
            $site = SourceSite::query()->lockForUpdate()->find($sourceSite->id);

            if (! $site || ! $site->active || $site->status === SourceSite::STATUS_PAUSED) {
                return null;
            }

            $activeTask = Scheduler::query()
                ->where('source_site_id', $site->id)
                ->where('type', Scheduler::TYPE_SOURCE_SCAN)
                ->whereIn('status', [Scheduler::STATUS_QUEUED, Scheduler::STATUS_RUNNING])
                ->latest('id')
                ->first();

            if ($activeTask) {
                return $activeTask;
            }

            $userId = $site->automation_user_id ?: $actor?->id;
            $scheduledFor = $site->next_scan_at ?: now();
            $task = Scheduler::query()->create([
                'user_id' => $userId,
                'source_site_id' => $site->id,
                'type' => Scheduler::TYPE_SOURCE_SCAN,
                'name' => 'Consultar '.$site->name,
                'status' => Scheduler::STATUS_QUEUED,
                'step' => 'Esperando un procesador de cola',
                'progress' => 0,
                'max_attempts' => 3,
                'scheduled_for' => $scheduledFor,
                'payload' => [
                    'trigger' => $trigger,
                    'profile_id' => $site->ai_prompt_profile_id,
                    'company_id' => $site->company_id,
                    'wordpress_site_id' => $site->wordpress_site_id,
                    'publication_profile_ids' => $site->selectedPublicationProfileIds(),
                    'auto_generate' => (bool) $site->auto_generate,
                    'auto_publish' => (bool) $site->auto_publish,
                ],
                'events' => [[
                    'at' => now()->toIso8601String(),
                    'level' => 'info',
                    'message' => $trigger === 'scheduled'
                        ? 'La frecuencia configurada venció y la consulta se añadió a la cola.'
                        : 'Se solicitó una consulta manual del sitio fuente.',
                ]],
            ]);

            $site->forceFill([
                'automation_user_id' => $userId,
                'last_queued_at' => now(),
                'next_scan_at' => now()->addMinutes(max(1, (int) $site->frequency_minutes)),
            ])->save();
            $created = true;

            return $task;
        });

        if ($task && $created) {
            ScanSourceSite::dispatch($task->id, $task->source_site_id)
                ->onQueue('source-pipeline');
        }

        return $task?->fresh();
    }

    /**
     * @param  array<int, int>  $sourcePostIds
     * @return Collection<int, Scheduler>
     */
    public function enqueueArticles(Scheduler $parent, array $sourcePostIds): Collection
    {
        $parent->loadMissing('sourceSite');
        $payload = $parent->payload ?: [];

        if (! ($payload['auto_generate'] ?? true)) {
            return collect();
        }

        return SourcePost::query()
            ->whereIn('id', array_values(array_unique(array_map('intval', $sourcePostIds))))
            ->where('source_site_id', $parent->source_site_id)
            ->where('status', SourcePost::STATUS_FETCHED)
            ->get()
            ->map(function (SourcePost $post) use ($parent, $payload): Scheduler {
                $task = Scheduler::query()->create([
                    'parent_id' => $parent->id,
                    'user_id' => $parent->user_id,
                    'source_site_id' => $parent->source_site_id,
                    'source_post_id' => $post->id,
                    'type' => Scheduler::TYPE_SOURCE_ARTICLE,
                    'name' => 'Crear y publicar: '.str($post->title)->limit(90),
                    'status' => Scheduler::STATUS_QUEUED,
                    'step' => 'Nota aceptada; esperando generación con IA',
                    'progress' => 40,
                    'max_attempts' => 3,
                    'payload' => [
                        'profile_id' => $payload['profile_id'] ?? null,
                        'company_id' => $payload['company_id'] ?? null,
                        'wordpress_site_id' => $payload['wordpress_site_id'] ?? null,
                        'publication_profile_ids' => $payload['publication_profile_ids']
                            ?? array_values(array_filter([$payload['wordpress_site_id'] ?? null])),
                        'auto_publish' => (bool) ($payload['auto_publish'] ?? false),
                        'filter_reason' => $post->filter_reason,
                        'matched_topics' => $post->matched_topics ?: [],
                    ],
                    'events' => [[
                        'at' => now()->toIso8601String(),
                        'level' => 'success',
                        'message' => 'La nota superó los filtros inteligentes y se añadió al flujo de generación.'
                            .($post->filter_reason ? ' '.$post->filter_reason : ''),
                    ]],
                ]);

                GenerateSourceArticle::dispatch($task->id, $parent->source_site_id)
                    ->onQueue('source-pipeline');

                return $task;
            })
            ->values();
    }
}
