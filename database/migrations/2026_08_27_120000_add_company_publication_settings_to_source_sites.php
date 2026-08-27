<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            if (! Schema::hasColumn('source_sites', 'daily_publication_target')) {
                $table->unsignedSmallInteger('daily_publication_target')->nullable()->after('publication_schedules');
            }

            if (! Schema::hasColumn('source_sites', 'publication_priority_time')) {
                $table->string('publication_priority_time', 5)->nullable()->after('daily_publication_target');
            }
        });

        DB::table('source_sites')->orderBy('id')->each(function (object $site): void {
            $schedules = json_decode((string) ($site->publication_schedules ?: '[]'), true) ?: [];
            $targets = collect($schedules)->pluck('daily_target')->filter()->map(fn ($target) => (int) $target);
            $times = collect($schedules)->pluck('priority_time')->filter()->sort();

            DB::table('source_sites')->where('id', $site->id)->update([
                'daily_publication_target' => min(100, max(1, (int) ($targets->max() ?: $site->max_generations_per_scan ?: 5))),
                'publication_priority_time' => (string) ($times->first() ?: '08:00'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('source_sites', 'daily_publication_target') ? 'daily_publication_target' : null,
                Schema::hasColumn('source_sites', 'publication_priority_time') ? 'publication_priority_time' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
