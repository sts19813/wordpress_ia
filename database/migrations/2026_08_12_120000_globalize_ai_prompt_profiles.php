<?php

use App\Models\AiPromptProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_prompt_profiles', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'name']);
            $table->dropIndex(['user_id', 'is_default']);
        });

        Schema::table('ai_prompt_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        $now = now();
        $profiles = DB::table('ai_prompt_profiles')->orderBy('id')->get();
        $editorial = $profiles
            ->where('name', AiPromptProfile::SYSTEM_EDITORIAL_NAME)
            ->sortByDesc('is_default')
            ->first();

        if (! $editorial) {
            $editorialId = DB::table('ai_prompt_profiles')->insertGetId([
                ...$this->profileDefaults($now),
                'name' => AiPromptProfile::SYSTEM_EDITORIAL_NAME,
                'is_default' => true,
            ]);
        } else {
            $editorialId = (int) $editorial->id;
        }

        $social = $profiles->firstWhere('name', AiPromptProfile::SYSTEM_SOCIAL_NAME);

        if (! $social) {
            $socialId = DB::table('ai_prompt_profiles')->insertGetId([
                ...$this->profileDefaults($now),
                'name' => AiPromptProfile::SYSTEM_SOCIAL_NAME,
                'writing_style' => 'periodístico para redes sociales',
                'content_length' => 'very_short',
                'image_size' => '1024x1024',
                'image_quality' => 'low',
                'is_default' => false,
            ]);
        } else {
            $socialId = (int) $social->id;
        }

        $profileMap = $profiles->mapWithKeys(fn (object $profile): array => [
            (int) $profile->id => $profile->name === AiPromptProfile::SYSTEM_SOCIAL_NAME
                ? $socialId
                : $editorialId,
        ])->all();
        $profileMap[$editorialId] = $editorialId;
        $profileMap[$socialId] = $socialId;

        foreach ($profileMap as $oldId => $newId) {
            if ($oldId === $newId) {
                continue;
            }

            DB::table('source_sites')->where('ai_prompt_profile_id', $oldId)->update(['ai_prompt_profile_id' => $newId]);
            DB::table('ai_articles')->where('ai_prompt_profile_id', $oldId)->update(['ai_prompt_profile_id' => $newId]);
        }

        DB::table('schedulers')
            ->whereNotNull('payload')
            ->orderBy('id')
            ->chunkById(200, function ($tasks) use ($profileMap): void {
                foreach ($tasks as $task) {
                    $payload = json_decode((string) $task->payload, true);

                    if (! is_array($payload)) {
                        continue;
                    }

                    $oldId = (int) ($payload['profile_id'] ?? 0);

                    if (! isset($profileMap[$oldId]) || $profileMap[$oldId] === $oldId) {
                        continue;
                    }

                    $payload['profile_id'] = $profileMap[$oldId];
                    DB::table('schedulers')->where('id', $task->id)->update([
                        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });

        DB::table('source_sites')->whereNull('ai_prompt_profile_id')->update(['ai_prompt_profile_id' => $editorialId]);
        DB::table('ai_prompt_profiles')->whereNotIn('id', [$editorialId, $socialId])->delete();
        DB::table('ai_prompt_profiles')->where('id', $editorialId)->update([
            'user_id' => null,
            'name' => AiPromptProfile::SYSTEM_EDITORIAL_NAME,
            'is_default' => true,
            'updated_at' => $now,
        ]);
        DB::table('ai_prompt_profiles')->where('id', $socialId)->update([
            'user_id' => null,
            'name' => AiPromptProfile::SYSTEM_SOCIAL_NAME,
            'is_default' => false,
            'updated_at' => $now,
        ]);

        Schema::table('ai_prompt_profiles', function (Blueprint $table): void {
            $table->unique('name');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        $userId = DB::table('users')->oldest('id')->value('id');

        if (! $userId) {
            return;
        }

        Schema::table('ai_prompt_profiles', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['name']);
            $table->dropIndex(['is_default']);
        });

        DB::table('ai_prompt_profiles')->whereNull('user_id')->update(['user_id' => $userId]);

        Schema::table('ai_prompt_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /** @return array<string, mixed> */
    private function profileDefaults(mixed $now): array
    {
        return [
            'user_id' => null,
            'system_prompt' => AiPromptProfile::DEFAULT_SYSTEM_PROMPT,
            'model' => AiPromptProfile::DEFAULT_TEXT_MODEL,
            'temperature' => 0.70,
            'writing_style' => 'periodístico informativo',
            'tone' => 'claro, objetivo y profesional',
            'content_length' => 'medium',
            'language' => 'es',
            'audience' => 'público general',
            'max_output_tokens' => 4000,
            'generate_image' => true,
            'image_model' => AiPromptProfile::DEFAULT_IMAGE_MODEL,
            'image_size' => '1536x1024',
            'image_quality' => 'medium',
            'image_style' => 'fotografía editorial realista, composición horizontal, sin texto incrustado',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
};
