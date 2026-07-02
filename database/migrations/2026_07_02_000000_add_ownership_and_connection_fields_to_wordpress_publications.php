<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('last_tested_at')->nullable()->after('active');
            $table->text('connection_error')->nullable()->after('last_tested_at');
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->text('error_message')->nullable()->after('full_response');
            $table->timestamp('published_at')->nullable()->after('scheduled_at');
        });

        DB::table('publications')->orderBy('id')->eachById(function (object $publication): void {
            $userId = DB::table('ai_articles')->where('id', $publication->ai_article_id)->value('user_id')
                ?: DB::table('wordpress_sites')->where('id', $publication->wordpress_site_id)->value('user_id');

            if ($userId) {
                DB::table('publications')->where('id', $publication->id)->update(['user_id' => $userId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['error_message', 'published_at']);
        });

        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['last_tested_at', 'connection_error']);
        });
    }
};
