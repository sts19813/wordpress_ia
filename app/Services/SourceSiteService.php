<?php

namespace App\Services;

use App\Models\SourceSite;

class SourceSiteService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SourceSite
    {
        $sourceSite = SourceSite::query()->create($this->normalizePayload($data));
        $sourceSite->forceFill([
            'next_scan_at' => $sourceSite->active ? now() : null,
        ])->save();

        return $sourceSite->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SourceSite $sourceSite, array $data): SourceSite
    {
        $frequencyChanged = isset($data['frequency_minutes'])
            && (int) $data['frequency_minutes'] !== (int) $sourceSite->frequency_minutes;
        $wasInactive = ! $sourceSite->active;
        $sourceSite->update($this->normalizePayload($data, true));

        if (! $sourceSite->active) {
            $sourceSite->forceFill(['next_scan_at' => null])->save();
        } elseif ($frequencyChanged || $wasInactive || ! $sourceSite->next_scan_at) {
            $nextScanAt = $sourceSite->last_synced_at
                ? $sourceSite->last_synced_at->copy()->addMinutes($sourceSite->frequency_minutes)
                : now();
            $sourceSite->forceFill(['next_scan_at' => $nextScanAt])->save();
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
            'active' => true,
            'auto_generate' => true,
            'auto_publish' => false,
        ], $data);
    }
}
