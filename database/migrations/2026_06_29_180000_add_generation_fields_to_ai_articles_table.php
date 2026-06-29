<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_articles', function (Blueprint $table) {
            $table->json('source_post_ids')->nullable()->after('id');
            $table->string('title')->nullable()->after('source_post_ids');
            $table->longText('content')->nullable()->after('title');
            $table->text('excerpt')->nullable()->after('content');
            $table->string('meta_description')->nullable()->after('excerpt');
            $table->string('slug')->nullable()->after('meta_description');
            $table->json('categories')->nullable()->after('slug');
            $table->json('tags')->nullable()->after('categories');
            $table->json('seo_keywords')->nullable()->after('tags');
            $table->json('faqs')->nullable()->after('seo_keywords');
            $table->text('conclusion')->nullable()->after('faqs');
            $table->longText('prompt_used')->nullable()->after('conclusion');
            $table->longText('full_response')->nullable()->after('prompt_used');
            $table->json('tokens')->nullable()->after('full_response');
            $table->decimal('cost', 12, 6)->nullable()->after('tokens');
            $table->unsignedInteger('duration_ms')->nullable()->after('cost');
            $table->string('model')->nullable()->after('duration_ms');
            $table->decimal('temperature', 3, 2)->nullable()->after('model');
            $table->string('status', 40)->default('pending_generation')->after('temperature');
        });
    }

    public function down(): void
    {
        Schema::table('ai_articles', function (Blueprint $table) {
            $table->dropColumn([
                'source_post_ids',
                'title',
                'content',
                'excerpt',
                'meta_description',
                'slug',
                'categories',
                'tags',
                'seo_keywords',
                'faqs',
                'conclusion',
                'prompt_used',
                'full_response',
                'tokens',
                'cost',
                'duration_ms',
                'model',
                'temperature',
                'status',
            ]);
        });
    }
};
