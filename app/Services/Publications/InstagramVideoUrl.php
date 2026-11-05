<?php

namespace App\Services\Publications;

use App\Models\AiImage;
use DateTimeInterface;
use RuntimeException;

class InstagramVideoUrl
{
    public function temporary(AiImage $video, DateTimeInterface $expiration): string
    {
        $expires = $expiration->getTimestamp();
        $path = route('publication-video.show', [
            'aiImage' => $video->id,
            'expires' => $expires,
            'token' => $this->token($video->id, $expires),
        ], absolute: false);

        return rtrim((string) config('app.url'), '/').$path;
    }

    public function isValid(AiImage $video, string $expires, string $token): bool
    {
        if (! ctype_digit($expires) || (int) $expires < now()->timestamp) {
            return false;
        }

        return hash_equals($this->token($video->id, (int) $expires), $token);
    }

    private function token(int $videoId, int $expires): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY debe estar configurada para publicar videos en Instagram.');
        }

        return hash_hmac('sha256', "instagram-video|{$videoId}|{$expires}", $key);
    }
}
