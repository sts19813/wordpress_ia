<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->foreignId('automation_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('ai_prompt_profile_id')->nullable()->after('automation_user_id')->constrained('ai_prompt_profiles')->nullOnDelete();
            $table->foreignId('wordpress_site_id')->nullable()->after('ai_prompt_profile_id')->constrained('wordpress_sites')->nullOnDelete();
            $table->boolean('auto_generate')->default(true)->after('wordpress_site_id');
            $table->boolean('auto_publish')->default(false)->after('auto_generate');
            $table->timestamp('next_scan_at')->nullable()->after('last_synced_at');
            $table->timestamp('last_queued_at')->nullable()->after('next_scan_at');

            $table->index(['active', 'next_scan_at']);
        });

        Schema::table('schedulers', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('schedulers')->nullOnDelete();
            $table->foreignId('source_site_id')->nullable()->after('ai_article_id')->constrained('source_sites')->nullOnDelete();
            $table->foreignId('source_post_id')->nullable()->after('source_site_id')->constrained('source_posts')->nullOnDelete();
            $table->foreignId('publication_id')->nullable()->after('source_post_id')->constrained('publications')->nullOnDelete();
            $table->timestamp('scheduled_for')->nullable()->after('finished_at');

            $table->index(['source_site_id', 'status']);
            $table->index(['parent_id', 'created_at']);
            $table->index(['type', 'scheduled_for']);
        });

        $defaultUserId = DB::table('users')->orderBy('id')->value('id');
        $defaultProfileId = $defaultUserId
            ? DB::table('ai_prompt_profiles')
                ->where('user_id', $defaultUserId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id')
            : null;
        $defaultWordPressSiteId = $defaultUserId
            ? DB::table('wordpress_sites')
                ->where('user_id', $defaultUserId)
                ->where('active', true)
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id')
            : null;

        DB::table('source_sites')->orderBy('id')->eachById(function (object $site) use ($defaultUserId, $defaultProfileId, $defaultWordPressSiteId): void {
            $nextScanAt = $site->last_synced_at
                ? Carbon::parse($site->last_synced_at)->addMinutes((int) ($site->frequency_minutes ?: 60))
                : now();

            DB::table('source_sites')->where('id', $site->id)->update([
                'automation_user_id' => $defaultUserId,
                'ai_prompt_profile_id' => $defaultProfileId,
                'wordpress_site_id' => $defaultWordPressSiteId,
                'auto_generate' => true,
                'auto_publish' => $defaultProfileId && $defaultWordPressSiteId,
                'next_scan_at' => $site->active ? $nextScanAt : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('schedulers', function (Blueprint $table) {
            $table->dropIndex(['source_site_id', 'status']);
            $table->dropIndex(['parent_id', 'created_at']);
            $table->dropIndex(['type', 'scheduled_for']);
            $table->dropConstrainedForeignId('publication_id');
            $table->dropConstrainedForeignId('source_post_id');
            $table->dropConstrainedForeignId('source_site_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('scheduled_for');
        });

        Schema::table('source_sites', function (Blueprint $table) {
            $table->dropIndex(['active', 'next_scan_at']);
            $table->dropConstrainedForeignId('wordpress_site_id');
            $table->dropConstrainedForeignId('ai_prompt_profile_id');
            $table->dropConstrainedForeignId('automation_user_id');
            $table->dropColumn([
                'auto_generate',
                'auto_publish',
                'next_scan_at',
                'last_queued_at',
            ]);
        });
    }
};
