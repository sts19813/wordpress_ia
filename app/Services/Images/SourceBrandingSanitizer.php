<?php

namespace App\Services\Images;

class SourceBrandingSanitizer
{
    /**
     * Obscures configured third-party source branding using only local GD
     * operations. It never sends an image to an external service.
     *
     * @return array{binary: string, mime_type: string|null, sanitized: bool, domains: array<int, string>}
     */
    public function sanitize(string $binary, ?string $sourceUrl): array
    {
        $domains = $this->matchingDomains($sourceUrl);

        if ($binary === '' || $domains === [] || ! function_exists('imagecreatefromstring')) {
            return $this->unchanged($binary);
        }

        $imageInfo = @getimagesizefromstring($binary);
        $mimeType = strtolower((string) ($imageInfo['mime'] ?? ''));

        if (! is_array($imageInfo) || ! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $this->unchanged($binary, $mimeType ?: null);
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return $this->unchanged($binary, $mimeType);
        }

        $appliedDomains = [];
        foreach ($domains as $domain => $regions) {
            foreach ($regions as $region) {
                $this->obscureRegion($image, $region);
            }

            $appliedDomains[] = $domain;
        }

        $encoded = $this->encode($image, $mimeType);
        imagedestroy($image);

        if ($encoded === null || $encoded === '') {
            return $this->unchanged($binary, $mimeType);
        }

        return [
            'binary' => $encoded,
            'mime_type' => $mimeType,
            'sanitized' => true,
            'domains' => $appliedDomains,
        ];
    }

    /** @return array<string, array<int, array<string, float|int>>> */
    private function matchingDomains(?string $sourceUrl): array
    {
        $host = strtolower(trim((string) parse_url((string) $sourceUrl, PHP_URL_HOST)));

        if ($host === '') {
            return [];
        }

        $matches = [];
        foreach ((array) config('source_image_sanitization.domains', []) as $domain => $regions) {
            $domain = strtolower(trim((string) $domain));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                $matches[$domain] = (array) $regions;
            }
        }

        return $matches;
    }

    /** @param array<string, float|int> $region */
    private function obscureRegion(\GdImage $image, array $region): void
    {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $x = max(0, min($imageWidth - 1, (int) floor($imageWidth * (float) ($region['x'] ?? 0))));
        $y = max(0, min($imageHeight - 1, (int) floor($imageHeight * (float) ($region['y'] ?? 0))));
        $width = max(2, min($imageWidth - $x, (int) ceil($imageWidth * (float) ($region['width'] ?? 0))));
        $height = max(2, min($imageHeight - $y, (int) ceil($imageHeight * (float) ($region['height'] ?? 0))));

        if ($width < 2 || $height < 2) {
            return;
        }

        $crop = imagecrop($image, compact('x', 'y', 'width', 'height'));
        if ($crop === false) {
            return;
        }

        // Pixelation removes text legibility first, then blur softens its edges.
        if (defined('IMG_FILTER_PIXELATE')) {
            imagefilter($crop, IMG_FILTER_PIXELATE, max(6, (int) floor(min($width, $height) / 12)), true);
        }

        $passes = max(2, min(10, (int) ($region['strength'] ?? 5)));
        for ($pass = 0; $pass < $passes; $pass++) {
            imagefilter($crop, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($image, $crop, $x, $y, 0, 0, $width, $height);
        imagedestroy($crop);
    }

    private function encode(\GdImage $image, string $mimeType): ?string
    {
        ob_start();
        $success = match ($mimeType) {
            'image/png' => imagepng($image, null, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 88) : false,
            default => imagejpeg($image, null, 90),
        };
        $binary = ob_get_clean();

        return $success && is_string($binary) ? $binary : null;
    }

    /** @return array{binary: string, mime_type: string|null, sanitized: bool, domains: array<int, string>} */
    private function unchanged(string $binary, ?string $mimeType = null): array
    {
        return [
            'binary' => $binary,
            'mime_type' => $mimeType,
            'sanitized' => false,
            'domains' => [],
        ];
    }
}
