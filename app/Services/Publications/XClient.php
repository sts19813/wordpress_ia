<?php

namespace App\Services\Publications;

use App\Models\WordPressSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class XClient
{
    public function __construct(
        private readonly XOAuthService $oauth,
    ) {}

    public function testConnection(WordPressSite $profile): Response
    {
        return $this->request($profile)
            ->get('https://api.x.com/2/users/me')
            ->throw();
    }

    public function uploadImage(
        WordPressSite $profile,
        string $contents,
        string $filename,
        string $mimeType,
    ): Response {
        return $this->request($profile)
            ->attach('media', $contents, $filename, ['Content-Type' => $mimeType])
            ->post('https://api.x.com/2/media/upload', [
                'media_category' => 'tweet_image',
                'media_type' => $mimeType,
                'shared' => 'false',
            ])
            ->throw();
    }

    public function publishPost(WordPressSite $profile, string $text, ?string $mediaId = null): Response
    {
        return $this->request($profile)
            ->asJson()
            ->post('https://api.x.com/2/tweets', [
                'text' => $text,
                ...($mediaId ? ['media' => ['media_ids' => [$mediaId]]] : []),
            ])
            ->throw();
    }

    private function request(WordPressSite $profile): PendingRequest
    {
        $accessToken = (string) $profile->x_access_token;

        if ($profile->x_refresh_token
            && $profile->x_token_expires_at
            && $profile->x_token_expires_at->lte(now()->addMinutes(5))) {
            $accessToken = $this->oauth->refresh($profile);
        }

        return Http::timeout(90)
            ->connectTimeout(15)
            ->acceptJson()
            ->withToken($accessToken);
    }
}
