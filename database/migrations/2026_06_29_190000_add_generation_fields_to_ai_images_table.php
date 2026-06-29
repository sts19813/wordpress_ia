<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->string('type', 40)->default('main')->after('id');
            $table->string('title')->nullable()->after('type');
            $table->longText('prompt')->after('title');
            $table->unsignedBigInteger('seed')->nullable()->after('prompt');
            $table->string('model')->nullable()->after('seed');
            $table->decimal('cost', 12, 6)->nullable()->after('model');
            $table->unsignedInteger('duration_ms')->nullable()->after('cost');
            $table->string('resolution', 40)->nullable()->after('duration_ms');
            $table->string('status', 40)->default('pending_generation')->after('resolution');
            $table->json('source_context')->nullable()->after('status');
            $table->longText('full_response')->nullable()->after('source_context');
            $table->string('image_url', 2048)->nullable()->after('full_response');
        });
    }

    public function down(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'title',
                'prompt',
                'seed',
                'model',
                'cost',
                'duration_ms',
                'resolution',
                'status',
                'source_context',
                'full_response',
                'image_url',
            ]);
        });
    }
};
