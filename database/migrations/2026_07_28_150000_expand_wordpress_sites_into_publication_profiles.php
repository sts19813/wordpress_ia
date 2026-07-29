<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->string('type', 40)->default('wordpress')->after('user_id');
            $table->string('facebook_page_id')->nullable()->after('application_password');
            $table->text('facebook_access_token')->nullable()->after('facebook_page_id');
            $table->string('facebook_api_version', 20)->default('v24.0')->after('facebook_access_token');

            $table->string('rest_api_url', 2048)->nullable()->change();
            $table->string('username')->nullable()->change();
            $table->text('application_password')->nullable()->change();
            $table->index(['user_id', 'type', 'active']);
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->string('remote_post_key')->nullable()->after('remote_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('remote_post_key');
        });

        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type', 'active']);
            $table->dropColumn([
                'type',
                'facebook_page_id',
                'facebook_access_token',
                'facebook_api_version',
            ]);
        });
    }
};
