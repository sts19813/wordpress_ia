<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->longText('system_prompt');
            $table->string('model')->default('gpt-4.1-mini');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->string('writing_style')->default('periodístico informativo');
            $table->string('tone')->default('claro, objetivo y profesional');
            $table->string('content_length', 20)->default('medium');
            $table->string('language', 10)->default('es');
            $table->string('audience')->default('público general');
            $table->unsignedInteger('max_output_tokens')->default(4000);
            $table->boolean('generate_image')->default(true);
            $table->string('image_model')->default('gpt-image-2');
            $table->string('image_size', 30)->default('1536x1024');
            $table->string('image_quality', 20)->default('medium');
            $table->string('image_style')->default('fotografía editorial realista, sin texto incrustado');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_profiles');
    }
};
