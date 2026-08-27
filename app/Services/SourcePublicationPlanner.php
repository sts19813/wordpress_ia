<?php

namespace App\Services;

use App\Models\Scheduler;
use App\Models\SourceSite;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class SourcePublicationPlanner
{
    public function firstScanAt(SourceSite $sourceSite, ?CarbonInterface $at = null): CarbonInterface
    {
        $at = $at ? Carbon::instance($at) : now();
        $priorityTimes = collect($sourceSite->normalizedPublicationSchedules())->pluck('priority_time')->sort();

        if ($priorityTimes->isEmpty()) {
            return $at->copy()->addDay();
        }

        $priorityDates = $priorityTimes
            ->map(fn (string $time) => $at->copy()->startOfDay()->setTimeFromTimeString($time));

        if ($priorityDates->contains(fn (CarbonInterface $date) => $date->lte($at))) {
            return $at->copy();
        }

        return $priorityDates->first();
    }

    /**
     * @return array<int, int>
     */
    public function remainingByProfile(SourceSite $sourceSite, ?CarbonInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : now();
        $schedules = $sourceSite->normalizedPublicationSchedules();

        if ($sourceSite->company_id) {
            $used = Scheduler::query()
                ->where('source_site_id', $sourceSite->id)
                ->where('type', Scheduler::TYPE_SOURCE_ARTICLE)
                ->whereDate('created_at', $at->toDateString())
                ->count();
            $priorityAt = $at->copy()->startOfDay()->setTimeFromTimeString($sourceSite->publicationPriorityTime());
            $remaining = $at->lt($priorityAt)
                ? 0
                : max(0, $sourceSite->dailyPublicationTarget() - $used);

            return collect($schedules)
                ->mapWithKeys(fn (array $schedule, int $profileId) => [$profileId => $remaining])
                ->all();
        }

        $used = array_fill_keys(array_keys($schedules), 0);

        Scheduler::query()
            ->where('source_site_id', $sourceSite->id)
            ->where('type', Scheduler::TYPE_SOURCE_ARTICLE)
            ->whereDate('created_at', $at->toDateString())
            ->get(['payload'])
            ->each(function (Scheduler $task) use (&$used): void {
                foreach (array_unique(array_map('intval', (array) data_get($task->payload, 'publication_profile_ids', []))) as $profileId) {
                    if (array_key_exists($profileId, $used)) {
                        $used[$profileId]++;
                    }
                }
            });

        return collect($schedules)
            ->mapWithKeys(function (array $schedule, int $profileId) use ($at, $used): array {
                $priorityAt = $at->copy()->startOfDay()->setTimeFromTimeString($schedule['priority_time']);
                $remaining = $at->lt($priorityAt)
                    ? 0
                    : max(0, $schedule['daily_target'] - ($used[$profileId] ?? 0));

                return [$profileId => $remaining];
            })
            ->all();
    }

    public function generationCapacity(SourceSite $sourceSite, ?CarbonInterface $at = null): int
    {
        return max([0, ...array_values($this->remainingByProfile($sourceSite, $at))]);
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function allocate(SourceSite $sourceSite, int $articleCount, ?CarbonInterface $at = null): array
    {
        if ($articleCount < 1) {
            return [];
        }

        $profileIds = $sourceSite->selectedPublicationProfileIds();

        if ($profileIds === []) {
            return [];
        }

        return collect(range(0, $articleCount - 1))
            ->map(fn (): array => $profileIds)
            ->values()
            ->all();
    }

    public function nextScanAt(SourceSite $sourceSite, ?CarbonInterface $at = null): CarbonInterface
    {
        $at = $at ? Carbon::instance($at) : now();
        $schedules = $sourceSite->normalizedPublicationSchedules();

        if ($schedules === []) {
            return $at->copy()->addDay();
        }

        if ($this->generationCapacity($sourceSite, $at) > 0) {
            return $at->copy()->addHour();
        }

        $futureToday = collect($schedules)
            ->map(fn (array $schedule) => $at->copy()->startOfDay()->setTimeFromTimeString($schedule['priority_time']))
            ->filter(fn (CarbonInterface $date) => $date->gt($at))
            ->sort()
            ->first();

        if ($futureToday) {
            return $futureToday;
        }

        $earliest = collect($schedules)->min('priority_time') ?: '08:00';

        return $at->copy()->addDay()->startOfDay()->setTimeFromTimeString($earliest);
    }
}
