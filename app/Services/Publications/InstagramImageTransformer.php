<?php

namespace App\Services\Publications;

use App\Models\AiImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InstagramImageTransformer
{
    private const MIN_ASPECT_RATIO = 0.8;

    private const MAX_ASPECT_RATIO = 1.91;

    private const MIN_WIDTH = 320;

    private const MAX_WIDTH = 1440;

    public function jpeg(AiImage $image): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw new RuntimeException('El servidor necesita la extensión GD de PHP para preparar imágenes de Instagram.');
        }

        $path = (string) $image->file_path;
        $binary = Storage::disk('local')->get($path);
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException('La imagen seleccionada no contiene un archivo gráfico válido.');
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);

            if ($sourceWidth < 1 || $sourceHeight < 1) {
                throw new RuntimeException('La imagen seleccionada no tiene dimensiones válidas.');
            }

            [$canvasWidth, $canvasHeight] = $this->canvasDimensions($sourceWidth, $sourceHeight);
            $targetWidth = max(self::MIN_WIDTH, min(self::MAX_WIDTH, $canvasWidth));
            $scale = $targetWidth / $canvasWidth;
            $targetHeight = max(1, (int) round($canvasHeight * $scale));
            $renderedWidth = max(1, (int) round($sourceWidth * $scale));
            $renderedHeight = max(1, (int) round($sourceHeight * $scale));
            $offsetX = (int) floor(($targetWidth - $renderedWidth) / 2);
            $offsetY = (int) floor(($targetHeight - $renderedHeight) / 2);
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($target === false) {
                throw new RuntimeException('No fue posible preparar la imagen para Instagram.');
            }

            try {
                $white = imagecolorallocate($target, 255, 255, 255);
                imagefill($target, 0, 0, $white);
                imagecopyresampled(
                    $target,
                    $source,
                    $offsetX,
                    $offsetY,
                    0,
                    0,
                    $renderedWidth,
                    $renderedHeight,
                    $sourceWidth,
                    $sourceHeight,
                );

                ob_start();
                $encoded = imagejpeg($target, null, 90);
                $jpeg = ob_get_clean();

                if (! $encoded || ! is_string($jpeg) || $jpeg === '') {
                    throw new RuntimeException('No fue posible convertir la imagen al formato JPEG requerido por Instagram.');
                }

                return $jpeg;
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * @return array{int, int}
     */
    private function canvasDimensions(int $width, int $height): array
    {
        $ratio = $width / $height;

        if ($ratio < self::MIN_ASPECT_RATIO) {
            return [(int) ceil($height * self::MIN_ASPECT_RATIO), $height];
        }

        if ($ratio > self::MAX_ASPECT_RATIO) {
            return [$width, (int) ceil($width / self::MAX_ASPECT_RATIO)];
        }

        return [$width, $height];
    }
}
