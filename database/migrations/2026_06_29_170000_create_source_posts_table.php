<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_site_id')->nullable()->constrained('source_sites')->nullOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->longText('content_html')->nullable();
            $table->text('summary')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->string('url', 2048);
            $table->char('hash', 64)->unique();
            $table->string('status', 40)->default('fetched');
            $table->json('original_json')->nullable();
            $table->string('language', 10)->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['source_site_id', 'published_at']);
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_posts');
    }
};
