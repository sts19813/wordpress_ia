<?php

namespace App\Services\Publications;

use App\Models\AiImage;
use DateTimeInterface;
use RuntimeException;

class InstagramMediaUrl
{
    public function temporary(AiImage $image, DateTimeInterface $expiration): string
    {
        $expires = $expiration->getTimestamp();
        $path = route('publication-media.show', [
            'aiImage' => $image->id,
            'expires' => $expires,
            'token' => $this->token($image->id, $expires),
        ], absolute: false);

        return rtrim((string) config('app.url'), '/').$path;
    }

    public function isValid(AiImage $image, string $expires, string $token): bool
    {
        if (! ctype_digit($expires) || (int) $expires < now()->timestamp) {
            return false;
        }

        return hash_equals($this->token($image->id, (int) $expires), $token);
    }

    private function token(int $imageId, int $expires): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY debe estar configurada para publicar imágenes en Instagram.');
        }

        return hash_hmac('sha256', "instagram-media|{$imageId}|{$expires}", $key);
    }
}
