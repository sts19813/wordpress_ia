<?php

namespace App\Services;

use App\Models\AiPromptProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class AiPromptProfileService
{
    public function ensureDefaultFor(?User $user = null): AiPromptProfile
    {
        return $this->ensureSystemProfiles()
            ->firstWhere('name', AiPromptProfile::SYSTEM_EDITORIAL_NAME);
    }

    /** @return Collection<int, AiPromptProfile> */
    public function ensureSystemProfiles(): Collection
    {
        $editorial = AiPromptProfile::query()->firstOrCreate(
            ['name' => AiPromptProfile::SYSTEM_EDITORIAL_NAME],
            $this->defaults(isDefault: true),
        );
        $social = AiPromptProfile::query()->firstOrCreate(
            ['name' => AiPromptProfile::SYSTEM_SOCIAL_NAME],
            [
                ...$this->defaults(isDefault: false),
                'writing_style' => 'periodístico para redes sociales',
                'content_length' => 'very_short',
                'image_size' => '1536x1024',
                'image_quality' => 'high',
            ],
        );

        AiPromptProfile::query()
            ->whereKeyNot($editorial->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (! $editorial->is_default || $editorial->user_id !== null) {
            $editorial->forceFill(['user_id' => null, 'is_default' => true])->save();
        }

        if ($social->user_id !== null || $social->is_default) {
            $social->forceFill(['user_id' => null, 'is_default' => false])->save();
        }

        return collect([$editorial->fresh(), $social->fresh()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): AiPromptProfile
    {
        throw new LogicException('Los perfiles editoriales son globales y sólo existen Editorial general y Redes Sociales.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiPromptProfile $profile, array $data): AiPromptProfile
    {
        return DB::transaction(function () use ($profile, $data): AiPromptProfile {
            $profile->update([
                ...$data,
                'user_id' => null,
                'is_default' => $profile->name === AiPromptProfile::SYSTEM_EDITORIAL_NAME,
            ]);

            return $profile->fresh();
        });
    }

    public function delete(AiPromptProfile $profile): void
    {
        throw new LogicException('Los perfiles editoriales globales no se pueden eliminar.');
    }

    /** @return array<string, mixed> */
    private function defaults(bool $isDefault): array
    {
        return [
            'user_id' => null,
            'system_prompt' => AiPromptProfile::DEFAULT_SYSTEM_PROMPT,
            'model' => AiPromptProfile::normalizeTextModel(config('services.openai.text_model')),
            'temperature' => 0.7,
            'writing_style' => 'periodístico informativo',
            'tone' => 'claro, objetivo y profesional',
            'content_length' => 'medium',
            'language' => 'es',
            'audience' => 'público general',
            'max_output_tokens' => 4000,
            'generate_image' => true,
            'image_model' => AiPromptProfile::normalizeImageModel(config('services.openai.image_model')),
            'image_size' => '1536x1024',
            'image_quality' => 'high',
            'image_style' => 'Fotoperiodismo editorial realista, apariencia de cámara profesional, luz natural, composición espontánea y creíble, texturas auténticas, colores sobrios, anatomía correcta, sin texto, sin logotipos, sin estética de ilustración, render 3D ni apariencia artificial.',
            'is_default' => $isDefault,
        ];
    }
}
