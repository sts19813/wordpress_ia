<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->foreignId('wordpress_site_id')->nullable()->after('id')->constrained('wordpress_sites')->nullOnDelete();
            $table->foreignId('ai_article_id')->nullable()->after('wordpress_site_id')->constrained('ai_articles')->nullOnDelete();
            $table->foreignId('ai_image_id')->nullable()->after('ai_article_id')->constrained('ai_images')->nullOnDelete();
            $table->unsignedBigInteger('remote_post_id')->nullable()->after('ai_image_id');
            $table->unsignedBigInteger('remote_featured_media_id')->nullable()->after('remote_post_id');
            $table->string('remote_url', 2048)->nullable()->after('remote_featured_media_id');
            $table->string('status', 40)->default('draft')->after('remote_url');
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->string('last_action', 80)->nullable()->after('scheduled_at');
            $table->json('request_payload')->nullable()->after('last_action');
            $table->json('full_response')->nullable()->after('request_payload');
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wordpress_site_id');
            $table->dropConstrainedForeignId('ai_article_id');
            $table->dropConstrainedForeignId('ai_image_id');
            $table->dropColumn([
                'remote_post_id',
                'remote_featured_media_id',
                'remote_url',
                'status',
                'scheduled_at',
                'last_action',
                'request_payload',
                'full_response',
            ]);
        });
    }
};
