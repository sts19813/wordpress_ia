<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rest_api_url', 2048);
            $table->string('username');
            $table->text('application_password');
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 40)->default('active');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_sites');
    }
};
