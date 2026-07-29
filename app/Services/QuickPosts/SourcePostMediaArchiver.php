<?php

namespace App\Services\QuickPosts;

use App\Models\SourcePost;
use App\Models\SourcePostMedia;
use App\Support\SocialPostUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SourcePostMediaArchiver
{
    /**
     * @param  array<int, array<string, mixed>>  $images
     */
    public function archive(SourcePost $sourcePost, array $images): void
    {
        foreach (array_slice($images, 0, 20) as $position => $image) {
            $url = trim((string) ($image['url'] ?? ''));

            if (! SocialPostUrl::isAllowedMediaUrl($url)) {
                continue;
            }

            $urlHash = hash('sha256', $this->stableImageUrl($url));
            $media = SourcePostMedia::query()->firstOrNew([
                'source_post_id' => $sourcePost->id,
                'url_hash' => $urlHash,
            ]);
            $media->fill([
                'type' => 'image',
                'position' => $position,
                'original_url' => $url,
                'width' => $image['width'] ?? null,
                'height' => $image['height'] ?? null,
                'metadata' => array_filter(['alt' => $image['alt'] ?? null]),
            ]);

            if (! $media->file_path || ! Storage::disk('local')->exists($media->file_path)) {
                try {
                    $this->download($media, $sourcePost->id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $media->save();
        }
    }

    private function download(SourcePostMedia $media, int $sourcePostId): void
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; WordPressIA/1.0)',
            'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ])->connectTimeout(10)->timeout(30)->get($media->original_url);

        if ($response->failed()) {
            throw new RuntimeException("No se pudo descargar una imagen original ({$response->status()}).");
        }

        $contents = $response->body();

        if ($contents === '' || strlen($contents) > 20 * 1024 * 1024) {
            throw new RuntimeException('Una imagen original está vacía o supera 20 MB.');
        }

        $mimeType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if (! str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException('La URL de medios no devolvió una imagen.');
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'bin',
        };
        $path = "source-posts/{$sourcePostId}/original-{$media->position}-{$media->url_hash}.{$extension}";
        Storage::disk('local')->put($path, $contents);

        $media->file_path = $path;
        $media->mime_type = $mimeType;
    }

    private function stableImageUrl(string $url): string
    {
        $parts = parse_url($url);

        return is_array($parts)
            ? (($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').($parts['path'] ?? ''))
            : $url;
    }
}
