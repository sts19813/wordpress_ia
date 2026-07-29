<?php

namespace App\Services\QuickPosts;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\SourcePost;
use App\Models\SourcePostMedia;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OriginalPostImageService
{
    public function attach(AiArticle $article, SourcePost $sourcePost): int
    {
        $mediaItems = $sourcePost->media
            ->filter(fn (SourcePostMedia $media): bool => filled($media->file_path)
                && Storage::disk('local')->exists($media->file_path))
            ->values();

        if ($mediaItems->isEmpty()) {
            throw new RuntimeException('No se encontraron imágenes originales disponibles para conservar.');
        }

        foreach ($mediaItems as $index => $media) {
            $path = "ai-images/{$article->id}/original-{$media->id}-".basename((string) $media->file_path);

            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->copy((string) $media->file_path, $path);
            }

            $image = AiImage::query()
                ->where('ai_article_id', $article->id)
                ->where('model', 'original')
                ->where('source_context->source_post_media_id', $media->id)
                ->firstOrNew();

            $image->fill([
                'ai_article_id' => $article->id,
                'type' => $index === 0 ? AiImage::TYPE_MAIN : AiImage::TYPE_VARIANT,
                'title' => $index === 0
                    ? 'Imagen principal original'
                    : 'Imagen original '.($index + 1),
                'prompt' => 'Imagen original conservada sin generación ni modificación por IA.',
                'model' => 'original',
                'cost' => 0,
                'duration_ms' => 0,
                'resolution' => $media->width && $media->height
                    ? "{$media->width}x{$media->height}"
                    : null,
                'quality' => 'original',
                'status' => AiImage::STATUS_GENERATED,
                'source_context' => [
                    'origin' => 'quick_post_original',
                    'source_post_id' => $sourcePost->id,
                    'source_post_media_id' => $media->id,
                    'position' => $media->position,
                ],
                'full_response' => null,
                'image_url' => $media->original_url,
                'file_path' => $path,
                'mime_type' => $media->mime_type,
                'generation_error' => null,
            ]);
            $image->save();
        }

        return $mediaItems->count();
    }
}
