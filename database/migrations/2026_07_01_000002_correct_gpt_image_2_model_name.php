<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_prompt_profiles')
            ->where('image_model', 'gpt-image-2.0')
            ->update(['image_model' => 'gpt-image-2']);
    }

    public function down(): void
    {
        // El alias inválido no debe restaurarse.
    }
};
