<?php

use App\Services\OpenAI\OpenAIClient;
use App\Services\OpenAI\OpenAICostCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_prompt_profiles', function (Blueprint $table) {
            $table->string('image_format', 20)->default('jpeg')->after('image_quality');
            $table->unsignedTinyInteger('image_compression')->default(85)->after('image_format');
        });

        Schema::table('ai_images', function (Blueprint $table) {
            $table->string('output_format', 20)->nullable()->after('quality');
            $table->unsignedTinyInteger('output_compression')->nullable()->after('output_format');
            $table->json('tokens')->nullable()->after('full_response');
        });

        DB::table('ai_prompt_profiles')->update([
            'image_quality' => 'low',
            'image_format' => 'jpeg',
            'image_compression' => 85,
        ]);

        $client = new OpenAIClient;
        $calculator = new OpenAICostCalculator;

        DB::table('ai_articles')
            ->whereNull('cost')
            ->whereNotNull('tokens')
            ->orderBy('id')
            ->chunkById(100, function ($articles) use ($calculator): void {
                foreach ($articles as $article) {
                    $tokens = json_decode((string) $article->tokens, true) ?: [];
                    DB::table('ai_articles')->where('id', $article->id)->update([
                        'cost' => $calculator->text((string) $article->model, $tokens),
                    ]);
                }
            });

        DB::table('ai_images')
            ->whereNull('tokens')
            ->whereNotNull('full_response')
            ->orderBy('id')
            ->chunkById(100, function ($images) use ($calculator, $client): void {
                foreach ($images as $image) {
                    $response = json_decode((string) $image->full_response, true) ?: [];
                    $tokens = $client->usage($response);
                    $format = data_get($response, 'output_format');

                    DB::table('ai_images')->where('id', $image->id)->update([
                        'tokens' => $tokens === [] ? null : json_encode($tokens),
                        'cost' => $calculator->image(
                            (string) $image->model,
                            $tokens,
                            $image->resolution,
                            $image->quality,
                        ),
                        'output_format' => is_string($format) ? $format : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->dropColumn(['output_format', 'output_compression', 'tokens']);
        });

        Schema::table('ai_prompt_profiles', function (Blueprint $table) {
            $table->dropColumn(['image_format', 'image_compression']);
        });
    }
};
