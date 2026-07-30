<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->string('x_client_id')->nullable()->after('x_username');
            $table->text('x_client_secret')->nullable()->after('x_client_id');
            $table->text('x_refresh_token')->nullable()->after('x_access_token');
            $table->timestamp('x_token_expires_at')->nullable()->after('x_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->dropColumn([
                'x_client_id',
                'x_client_secret',
                'x_refresh_token',
                'x_token_expires_at',
            ]);
        });
    }
};
