<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('article_slug', 120)->nullable()->index();
            $table->string('rating', 20)->nullable()->index();
            $table->string('search_term', 255)->nullable()->index();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('help_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('term', 255)->index();
            $table->unsignedInteger('results_count');
            $table->timestamps();
        });

        Schema::create('help_article_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('article_slug', 120)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_views');
        Schema::dropIfExists('help_searches');
        Schema::dropIfExists('help_feedback');
    }
};
