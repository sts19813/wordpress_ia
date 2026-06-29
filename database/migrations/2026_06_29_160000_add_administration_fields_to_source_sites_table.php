<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('url', 2048)->after('name');
            $table->string('type', 40)->default('rss')->after('url');
            $table->string('status', 40)->default('pending')->after('type');
            $table->unsignedInteger('frequency_minutes')->default(60)->after('status');
            $table->string('category')->nullable()->after('frequency_minutes');
            $table->string('language', 10)->default('es')->after('category');
            $table->string('country', 100)->nullable()->after('language');
            $table->unsignedTinyInteger('priority')->default(5)->after('country');
            $table->text('api_key')->nullable()->after('priority');
            $table->string('username')->nullable()->after('api_key');
            $table->text('password')->nullable()->after('username');
            $table->json('custom_headers')->nullable()->after('password');
            $table->json('cookies')->nullable()->after('custom_headers');
            $table->string('auth_method', 40)->default('none')->after('cookies');
            $table->unsignedInteger('daily_limit')->nullable()->after('auth_method');
            $table->timestamp('last_synced_at')->nullable()->after('daily_limit');
            $table->boolean('active')->default(true)->after('last_synced_at');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'name',
                'url',
                'type',
                'status',
                'frequency_minutes',
                'category',
                'language',
                'country',
                'priority',
                'api_key',
                'username',
                'password',
                'custom_headers',
                'cookies',
                'auth_method',
                'daily_limit',
                'last_synced_at',
                'active',
            ]);
        });
    }
};
