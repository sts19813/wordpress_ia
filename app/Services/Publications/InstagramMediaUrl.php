<?php

namespace App\Services\Publications;

use App\Models\AiImage;
use DateTimeInterface;
use Illuminate\Http\Request;
use RuntimeException;

class InstagramMediaUrl
{
    public function temporary(AiImage $image, DateTimeInterface $expiration): string
    {
        $expires = $expiration->getTimestamp();
        $path = route('publication-media.show', ['aiImage' => $image->id], absolute: false);
        $query = http_build_query([
            'expires' => $expires,
            'token' => $this->token($image->id, $expires),
        ], encoding_type: PHP_QUERY_RFC3986);

        return rtrim((string) config('app.url'), '/').$path.'?'.$query;
    }

    public function isValid(Request $request, AiImage $image): bool
    {
        $expires = $request->query('expires');
        $token = $request->query('token');

        if (! is_string($expires) || ! ctype_digit($expires) || (int) $expires < now()->timestamp) {
            return false;
        }

        if (! is_string($token) || $token === '') {
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
