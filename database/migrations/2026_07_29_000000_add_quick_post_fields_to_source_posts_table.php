<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_posts', function (Blueprint $table) {
            $table->string('origin_type', 30)->default('source_site')->after('source_site_id');
            $table->string('social_platform', 30)->nullable()->after('origin_type');
            $table->string('canonical_url', 2048)->nullable()->after('url');
            $table->timestamp('captured_at')->nullable()->after('scanned_at');

            $table->index(['origin_type', 'captured_at']);
            $table->index(['social_platform', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('source_posts', function (Blueprint $table) {
            $table->dropIndex(['origin_type', 'captured_at']);
            $table->dropIndex(['social_platform', 'captured_at']);
            $table->dropColumn(['origin_type', 'social_platform', 'canonical_url', 'captured_at']);
        });
    }
};
