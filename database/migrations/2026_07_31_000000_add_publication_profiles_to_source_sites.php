<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->json('publication_profile_ids')->nullable()->after('wordpress_site_id');
        });

        DB::table('source_sites')
            ->whereNotNull('wordpress_site_id')
            ->orderBy('id')
            ->eachById(function (object $sourceSite): void {
                DB::table('source_sites')
                    ->where('id', $sourceSite->id)
                    ->update([
                        'publication_profile_ids' => json_encode([(int) $sourceSite->wordpress_site_id]),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('source_sites', function (Blueprint $table) {
            $table->dropColumn('publication_profile_ids');
        });
    }
};
