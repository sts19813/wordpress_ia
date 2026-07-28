<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->json('filter_topics')->nullable()->after('category');
            $table->json('excluded_topics')->nullable()->after('filter_topics');
            $table->text('filter_instructions')->nullable()->after('excluded_topics');
        });

        Schema::table('source_posts', function (Blueprint $table) {
            $table->boolean('filter_applies')->nullable()->after('status');
            $table->text('filter_reason')->nullable()->after('filter_applies');
            $table->json('matched_topics')->nullable()->after('filter_reason');
            $table->string('filter_method', 40)->nullable()->after('matched_topics');
            $table->timestamp('scanned_at')->nullable()->after('filter_method');
            $table->index(['source_site_id', 'filter_applies', 'scanned_at'], 'source_posts_filter_audit_index');
        });

        Schema::create('source_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_site_id')->nullable()->constrained('source_sites')->nullOnDelete();
            $table->foreignId('source_post_id')->nullable()->constrained('source_posts')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('outcome', 40);
            $table->boolean('applies')->nullable();
            $table->text('reason')->nullable();
            $table->json('matched_topics')->nullable();
            $table->string('filter_method', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['source_site_id', 'scanned_at']);
            $table->index(['outcome', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_scan_logs');

        Schema::table('source_posts', function (Blueprint $table) {
            $table->dropIndex('source_posts_filter_audit_index');
            $table->dropColumn([
                'filter_applies',
                'filter_reason',
                'matched_topics',
                'filter_method',
                'scanned_at',
            ]);
        });

        Schema::table('source_sites', function (Blueprint $table) {
            $table->dropColumn([
                'filter_topics',
                'excluded_topics',
                'filter_instructions',
            ]);
        });
    }
};
