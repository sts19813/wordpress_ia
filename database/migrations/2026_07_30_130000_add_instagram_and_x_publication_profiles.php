<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->string('instagram_account_id')->nullable()->after('facebook_api_version');
            $table->text('instagram_access_token')->nullable()->after('instagram_account_id');
            $table->string('instagram_api_version', 20)->default('v24.0')->after('instagram_access_token');
            $table->string('x_user_id')->nullable()->after('instagram_api_version');
            $table->string('x_username')->nullable()->after('x_user_id');
            $table->text('x_access_token')->nullable()->after('x_username');
        });
    }

    public function down(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->dropColumn([
                'instagram_account_id',
                'instagram_access_token',
                'instagram_api_version',
                'x_user_id',
                'x_username',
                'x_access_token',
            ]);
        });
    }
};
