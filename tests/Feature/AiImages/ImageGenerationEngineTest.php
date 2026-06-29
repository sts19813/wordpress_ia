<?php

namespace Tests\Feature\AiImages;

use App\Models\AiImage;
use App\Services\AiImages\ImageGenerationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageGenerationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prepares_all_supported_image_types_without_calling_api(): void
    {
        $engine = app(ImageGenerationEngine::class);
        $context = [
            'title' => 'Artículo de prueba',
            'summary' => 'Resumen del artículo',
            'categories' => ['Tecnología'],
        ];

        $results = [
            $engine->prepareMain($context, ['seed' => 123]),
            $engine->prepareVariant([...$context, 'variant' => 'más contraste']),
            $engine->prepareThumbnail($context),
            $engine->prepareBanner($context),
            $engine->prepareOg($context),
        ];

        $this->assertSame(5, AiImage::query()->count());

        foreach ($results as $result) {
            $this->assertSame('images', $result->request->capability);
            $this->assertSame('generate', $result->request->operation);
            $this->assertSame(AiImage::STATUS_PENDING, $result->image->status);
            $this->assertStringContainsString('Artículo de prueba', $result->image->prompt);
            $this->assertNotEmpty($result->image->resolution);
        }

        $this->assertSame(123, $results[0]->image->seed);
        $this->assertSame(AiImage::TYPE_MAIN, $results[0]->image->type);
        $this->assertSame(AiImage::TYPE_OG, $results[4]->image->type);
    }

    public function test_it_completes_image_generation_with_metrics(): void
    {
        $image = AiImage::query()->create([
            'type' => AiImage::TYPE_MAIN,
            'title' => 'Imagen',
            'prompt' => 'Prompt usado',
            'model' => 'gpt-image-1',
            'resolution' => '1024x1024',
            'status' => AiImage::STATUS_PENDING,
        ]);

        app(ImageGenerationEngine::class)->complete($image, [
            'data' => [
                ['url' => 'https://example.com/image.png'],
            ],
        ], metrics: [
            'cost' => 0.045,
            'duration_ms' => 2400,
            'model' => 'gpt-image-1',
            'resolution' => '1024x1024',
        ]);

        $image->refresh();

        $this->assertSame(AiImage::STATUS_GENERATED, $image->status);
        $this->assertSame('https://example.com/image.png', $image->image_url);
        $this->assertSame('0.045000', $image->cost);
        $this->assertSame(2400, $image->duration_ms);
        $this->assertStringContainsString('image.png', $image->full_response);
    }
}
