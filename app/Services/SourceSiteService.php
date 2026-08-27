<?php

namespace App\Services;

use App\Models\SourceSite;

class SourceSiteService
{
    public function __construct(private readonly SourcePublicationPlanner $publicationPlanner) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SourceSite
    {
        $sourceSite = SourceSite::query()->create($this->normalizePayload($data));
        $sourceSite->forceFill([
            'next_scan_at' => $sourceSite->active ? $this->publicationPlanner->firstScanAt($sourceSite) : null,
        ])->save();

        return $sourceSite->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SourceSite $sourceSite, array $data): SourceSite
    {
        $wasInactive = ! $sourceSite->active;
        $sourceSite->update($this->normalizePayload($data, true));

        if (! $sourceSite->active) {
            $sourceSite->forceFill(['next_scan_at' => null])->save();
        } elseif ($wasInactive || array_intersect(array_keys($data), ['publication_schedules', 'daily_publication_target', 'publication_priority_time', 'company_id']) !== [] || ! $sourceSite->next_scan_at) {
            $sourceSite->forceFill(['next_scan_at' => $this->publicationPlanner->firstScanAt($sourceSite)])->save();
        }

        return $sourceSite->fresh();
    }

    public function delete(SourceSite $sourceSite): void
    {
        $sourceSite->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, bool $updating = false): array
    {
        unset($data['frequency_hours']);

        if (array_key_exists('publication_profile_ids', $data)) {
            $data['publication_profile_ids'] = array_values(array_unique(array_filter(array_map(
                'intval',
                (array) $data['publication_profile_ids'],
            ))));
            // Keep the original column synchronized for older integrations.
            $data['wordpress_site_id'] = $data['publication_profile_ids'][0] ?? null;
        }

        if (array_key_exists('publication_schedules', $data)) {
            $data['publication_schedules'] = collect((array) $data['publication_schedules'])
                ->mapWithKeys(fn (mixed $schedule, mixed $profileId) => [(int) $profileId => [
                    'daily_target' => min(100, max(1, (int) data_get($schedule, 'daily_target', 1))),
                    'priority_time' => (string) data_get($schedule, 'priority_time', '08:00'),
                ]])
                ->all();
        }

        foreach (['custom_headers', 'cookies'] as $jsonField) {
            if (isset($data[$jsonField]) && is_string($data[$jsonField])) {
                $data[$jsonField] = json_decode($data[$jsonField], true);
            }

            if (($data[$jsonField] ?? null) === '') {
                $data[$jsonField] = null;
            }
        }

        foreach (['api_key', 'password'] as $secretField) {
            if ($updating && blank($data[$secretField] ?? null)) {
                unset($data[$secretField]);
            }
        }

        if (blank($data['last_synced_at'] ?? null)) {
            $data['last_synced_at'] = null;
        }

        return array_merge([
            'status' => SourceSite::STATUS_PENDING,
            'frequency_minutes' => 60,
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'daily_limit' => 20,
            'max_posts_per_scan' => 20,
            'max_generations_per_scan' => 5,
            'daily_publication_target' => 5,
            'publication_priority_time' => '08:00',
            'active' => true,
            'auto_generate' => true,
            'auto_publish' => false,
        ], $data);
    }
}
