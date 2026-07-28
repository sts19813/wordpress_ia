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
        return SourceSite::query()->create($this->normalizePayload($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SourceSite $sourceSite, array $data): SourceSite
    {
        $sourceSite->update($this->normalizePayload($data, true));

        return $sourceSite;
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
            'active' => true,
        ], $data);
    }
}
