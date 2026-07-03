<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedulers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_article_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('type', 60)->default('ai_article')->after('ai_article_id');
            $table->string('name')->after('type');
            $table->string('status', 30)->default('queued')->after('name');
            $table->string('step')->nullable()->after('status');
            $table->unsignedTinyInteger('progress')->default(0)->after('step');
            $table->unsignedTinyInteger('attempts')->default(0)->after('progress');
            $table->unsignedTinyInteger('max_attempts')->default(3)->after('attempts');
            $table->json('payload')->nullable()->after('max_attempts');
            $table->json('events')->nullable()->after('payload');
            $table->text('last_error')->nullable()->after('events');
            $table->timestamp('started_at')->nullable()->after('last_error');
            $table->timestamp('finished_at')->nullable()->after('started_at');

            $table->index(['user_id', 'status']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('schedulers', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['type', 'created_at']);
            $table->dropConstrainedForeignId('ai_article_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'type',
                'name',
                'status',
                'step',
                'progress',
                'attempts',
                'max_attempts',
                'payload',
                'events',
                'last_error',
                'started_at',
                'finished_at',
            ]);
        });
    }
};
