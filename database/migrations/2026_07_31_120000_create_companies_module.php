<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'active']);
        });

        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'type', 'active']);
        });

        Schema::table('source_sites', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('automation_user_id')->constrained()->nullOnDelete();
        });

        Schema::table('ai_articles', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Preserve existing profiles by grouping each user's destinations in a
        // default company. New installations simply have no rows to migrate.
        DB::table('wordpress_sites')
            ->whereNotNull('user_id')
            ->select('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->get()
            ->each(function (object $row): void {
                $companyId = DB::table('companies')->insertGetId([
                    'user_id' => $row->user_id,
                    'name' => 'Empresa principal',
                    'description' => 'Creada automáticamente para conservar los perfiles existentes.',
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('wordpress_sites')
                    ->where('user_id', $row->user_id)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId]);

                DB::table('source_sites')
                    ->where('automation_user_id', $row->user_id)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            });
    }

    public function down(): void
    {
        Schema::table('ai_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('source_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'type', 'active']);
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('companies');
    }
};
