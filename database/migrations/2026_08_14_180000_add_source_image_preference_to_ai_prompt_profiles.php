<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_prompt_profiles', function (Blueprint $table) {
            $table->boolean('use_source_image')->default(true)->after('generate_image');
        });

        DB::table('ai_prompt_profiles')->update(['use_source_image' => true]);
    }

    public function down(): void
    {
        Schema::table('ai_prompt_profiles', function (Blueprint $table) {
            $table->dropColumn('use_source_image');
        });
    }
};
