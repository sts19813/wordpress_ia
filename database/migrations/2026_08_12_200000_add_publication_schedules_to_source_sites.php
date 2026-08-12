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
        if (! Schema::hasColumn('source_sites', 'publication_schedules')) {
            Schema::table('source_sites', function (Blueprint $table) {
                $table->json('publication_schedules')->nullable()->after('publication_profile_ids');
            });
        }

        DB::table('source_sites')->orderBy('id')->each(function (object $site): void {
            $profileIds = json_decode((string) ($site->publication_profile_ids ?: '[]'), true) ?: [];

            if ($profileIds === [] && $site->wordpress_site_id) {
                $profileIds = [(int) $site->wordpress_site_id];
            }

            $dailyTarget = max(1, (int) ($site->max_generations_per_scan ?: 5));
            $schedules = collect($profileIds)
                ->mapWithKeys(fn (mixed $profileId) => [(string) ((int) $profileId) => [
                    'daily_target' => $dailyTarget,
                    'priority_time' => '08:00',
                ]])
                ->all();

            DB::table('source_sites')->where('id', $site->id)->update([
                'publication_schedules' => json_encode($schedules),
                'auto_generate' => $schedules !== [],
                'auto_publish' => $schedules !== [],
                'frequency_minutes' => 60,
                'daily_limit' => max(50, $dailyTarget * 10),
                'max_posts_per_scan' => min(100, max(20, $dailyTarget * 5)),
                'max_generations_per_scan' => $dailyTarget,
            ]);
        });

        DB::table('ai_prompt_profiles')->update([
            'generate_image' => true,
            'image_model' => AiPromptProfile::DEFAULT_IMAGE_MODEL,
            'image_size' => '1536x1024',
            'image_quality' => 'high',
            'image_style' => 'Fotoperiodismo realista, cámara profesional, luz natural, composición espontánea, texturas auténticas, colores sobrios y anatomía correcta; sin texto, logotipos, ilustración, render 3D ni apariencia artificial.',
        ]);
    }

    public function down(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->dropColumn('publication_schedules');
        });
    }
};
