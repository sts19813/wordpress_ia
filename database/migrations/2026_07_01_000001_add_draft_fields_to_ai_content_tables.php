<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_articles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('ai_prompt_profile_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('writing_style')->nullable()->after('temperature');
            $table->string('tone')->nullable()->after('writing_style');
            $table->string('content_length', 20)->nullable()->after('tone');
            $table->string('language', 10)->nullable()->after('content_length');
            $table->string('audience')->nullable()->after('language');
            $table->unsignedInteger('max_output_tokens')->nullable()->after('audience');
            $table->text('generation_error')->nullable()->after('status');
            $table->timestamp('generated_at')->nullable()->after('generation_error');

            $table->index(['user_id', 'status']);
        });

        Schema::table('ai_images', function (Blueprint $table) {
            $table->foreignId('ai_article_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('quality', 20)->nullable()->after('resolution');
            $table->string('file_path', 2048)->nullable()->after('image_url');
            $table->string('mime_type', 100)->nullable()->after('file_path');
            $table->text('generation_error')->nullable()->after('mime_type');

            $table->index(['ai_article_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_article_id');
            $table->dropColumn(['quality', 'file_path', 'mime_type', 'generation_error']);
        });

        Schema::table('ai_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_prompt_profile_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'writing_style',
                'tone',
                'content_length',
                'language',
                'audience',
                'max_output_tokens',
                'generation_error',
                'generated_at',
            ]);
        });
    }
};
