<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_post_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('image');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('original_url', 2048);
            $table->char('url_hash', 64);
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_post_id', 'url_hash']);
            $table->index(['source_post_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_post_media');
    }
};
