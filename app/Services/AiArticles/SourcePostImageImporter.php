<?php

namespace App\Services\AiArticles;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\AiPromptProfile;
use App\Models\SourcePost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SourcePostImageImporter
{
    private const MAX_DOWNLOAD_BYTES = 20 * 1024 * 1024;

    private const MAX_SOURCE_PIXELS = 50_000_000;

    /**
     * Descarga la primera imagen utilizable de las notas fuente, la recorta al
     * formato del perfil y la registra como imagen principal con costo de IA 0.
     *
     * @param  iterable<int, SourcePost>  $sourcePosts
     */
    public function attach(AiArticle $article, iterable $sourcePosts, AiPromptProfile $profile): ?AiImage
    {
        foreach ($sourcePosts as $sourcePost) {
            if (! $sourcePost instanceof SourcePost || ! $this->isValidUrl($sourcePost->image_url)) {
                continue;
            }

            try {
                $image = $this->downloadAndTransform((string) $sourcePost->image_url, $profile);

                if ($image === null) {
                    continue;
                }

                $path = 'ai-images/'.$article->id.'/source-'.$sourcePost->id.'-'.Str::uuid().'.'.$image['extension'];
                Storage::disk('local')->put($path, $image['binary']);

                return AiImage::query()->create([
                    'ai_article_id' => $article->id,
                    'type' => AiImage::TYPE_MAIN,
                    'title' => 'Imagen principal del post original',
                    'prompt' => 'Imagen original reutilizada y ajustada sin generación por IA.',
                    'model' => 'original',
                    'cost' => 0,
                    'duration_ms' => $image['duration_ms'],
                    'resolution' => $image['resolution'],
                    'quality' => 'original',
                    'output_format' => $image['format'],
                    'output_compression' => $image['format'] === 'png' ? null : $image['compression'],
                    'tokens' => ['input' => 0, 'output' => 0, 'total' => 0],
                    'status' => AiImage::STATUS_GENERATED,
                    'source_context' => [
                        'origin' => 'scanned_post_original',
                        'source_post_id' => $sourcePost->id,
                        'source_image_url' => $sourcePost->image_url,
                        'original_resolution' => $image['original_resolution'],
                        'transformation' => 'center_crop',
                    ],
                    'full_response' => json_encode([
                        'source' => 'original_post',
                        'transformation' => 'center_crop',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'image_url' => $sourcePost->image_url,
                    'file_path' => $path,
                    'mime_type' => $image['mime_type'],
                ]);
            } catch (Throwable) {
                // Una imagen externa inaccesible no debe detener la nota: se
                // intenta la siguiente y luego se usa el respaldo de IA.
                continue;
            }
        }

        return null;
    }

    private function isValidUrl(?string $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * @return array{binary: string, extension: string, mime_type: string, format: string, compression: int, resolution: string, original_resolution: string, duration_ms: int}|null
     */
    private function downloadAndTransform(string $url, AiPromptProfile $profile): ?array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecopyresampled')) {
            return null;
        }

        $startedAt = hrtime(true);
        $response = Http::withHeaders([
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'User-Agent' => 'Mozilla/5.0 (compatible; WordPressIA/1.0)',
        ])->connectTimeout(10)->timeout(30)->get($url);

        if (! $response->successful()) {
            return null;
        }

        $declaredLength = (int) ($response->header('Content-Length') ?: 0);
        $binary = $response->body();

        if ($binary === '' || $declaredLength > self::MAX_DOWNLOAD_BYTES || strlen($binary) > self::MAX_DOWNLOAD_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($binary);

        if (! is_array($info) || ($info[0] * $info[1]) > self::MAX_SOURCE_PIXELS || $info[0] < 200 || $info[1] < 120) {
            return null;
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        [$targetWidth, $targetHeight] = $this->targetDimensions($profile->image_size);
        $format = $this->supportedFormat((string) ($profile->image_format ?: 'jpeg'));
        $compression = max(40, min(100, (int) ($profile->image_compression ?: 85)));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        if ($format === 'jpeg') {
            $background = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $background);
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
            imagealphablending($canvas, true);
        }

        [$sourceX, $sourceY, $sourceWidth, $sourceHeight] = $this->centerCrop(
            (int) $info[0],
            (int) $info[1],
            $targetWidth,
            $targetHeight,
        );

        $copied = imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        if (! $copied) {
            imagedestroy($source);
            imagedestroy($canvas);

            return null;
        }

        ob_start();
        $encoded = match ($format) {
            'png' => imagepng($canvas, null, $this->pngCompression($compression)),
            'webp' => imagewebp($canvas, null, $compression),
            default => imagejpeg($canvas, null, $compression),
        };
        $transformed = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if (! $encoded || ! is_string($transformed) || $transformed === '') {
            return null;
        }

        [$extension, $mimeType] = match ($format) {
            'png' => ['png', 'image/png'],
            'webp' => ['webp', 'image/webp'],
            default => ['jpg', 'image/jpeg'],
        };

        return [
            'binary' => $transformed,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'format' => $format,
            'compression' => $compression,
            'resolution' => $targetWidth.'x'.$targetHeight,
            'original_resolution' => $info[0].'x'.$info[1],
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }

    /** @return array{int, int} */
    private function targetDimensions(?string $size): array
    {
        if (preg_match('/^(\d+)x(\d+)$/', (string) $size, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [1536, 1024];
    }

    /** @return array{int, int, int, int} */
    private function centerCrop(int $width, int $height, int $targetWidth, int $targetHeight): array
    {
        $sourceRatio = $width / $height;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropWidth = (int) round($height * $targetRatio);

            return [(int) floor(($width - $cropWidth) / 2), 0, $cropWidth, $height];
        }

        $cropHeight = (int) round($width / $targetRatio);

        return [0, (int) floor(($height - $cropHeight) / 2), $width, $cropHeight];
    }

    private function supportedFormat(string $format): string
    {
        $format = strtolower($format);

        if ($format === 'webp' && function_exists('imagewebp')) {
            return 'webp';
        }

        return $format === 'png' ? 'png' : 'jpeg';
    }

    private function pngCompression(int $compression): int
    {
        return max(0, min(9, (int) round((100 - $compression) * 9 / 100)));
    }
}
