<?php

namespace App\Services;

use App\Models\AiPromptProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AiPromptProfileService
{
    public function ensureDefaultFor(User $user): AiPromptProfile
    {
        $existing = $user->aiPromptProfiles()->orderByDesc('is_default')->first();

        if ($existing) {
            return $existing;
        }

        return $user->aiPromptProfiles()->create([
            'name' => 'Editorial general',
            'system_prompt' => AiPromptProfile::DEFAULT_SYSTEM_PROMPT,
            'model' => config('services.openai.text_model', 'gpt-4.1-mini'),
            'image_model' => AiPromptProfile::normalizeImageModel(config('services.openai.image_model')),
            'is_default' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): AiPromptProfile
    {
        return DB::transaction(function () use ($user, $data): AiPromptProfile {
            $makeDefault = (bool) ($data['is_default'] ?? false) || ! $user->aiPromptProfiles()->exists();

            if ($makeDefault) {
                $user->aiPromptProfiles()->update(['is_default' => false]);
            }

            return $user->aiPromptProfiles()->create([...$data, 'is_default' => $makeDefault]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiPromptProfile $profile, array $data): AiPromptProfile
    {
        return DB::transaction(function () use ($profile, $data): AiPromptProfile {
            if ($data['is_default'] ?? false) {
                AiPromptProfile::query()
                    ->where('user_id', $profile->user_id)
                    ->whereKeyNot($profile->id)
                    ->update(['is_default' => false]);
            }

            $profile->update($data);

            if (! $profile->is_default && ! AiPromptProfile::query()->where('user_id', $profile->user_id)->where('is_default', true)->exists()) {
                $profile->update(['is_default' => true]);
            }

            return $profile->fresh();
        });
    }

    public function delete(AiPromptProfile $profile): void
    {
        DB::transaction(function () use ($profile): void {
            $userId = $profile->user_id;
            $wasDefault = $profile->is_default;
            $profile->delete();

            if ($wasDefault) {
                AiPromptProfile::query()->where('user_id', $userId)->oldest()->first()?->update(['is_default' => true]);
            }
        });
    }
}
