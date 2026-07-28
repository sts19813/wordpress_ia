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
            $table->unsignedInteger('max_posts_per_scan')->default(20)->after('daily_limit');
            $table->unsignedInteger('max_generations_per_scan')->default(5)->after('max_posts_per_scan');
        });

        DB::table('source_sites')->orderBy('id')->eachById(function (object $site): void {
            $maxPosts = min(20, max(1, (int) $site->daily_limit));

            DB::table('source_sites')->where('id', $site->id)->update([
                'max_posts_per_scan' => $maxPosts,
                'max_generations_per_scan' => min(5, $maxPosts),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->dropColumn([
                'max_posts_per_scan',
                'max_generations_per_scan',
            ]);
        });
    }
};
