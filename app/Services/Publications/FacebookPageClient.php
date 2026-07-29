<?php

namespace App\Services\Publications;

use App\Models\WordPressSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FacebookPageClient
{
    public function testConnection(WordPressSite $profile): Response
    {
        return $this->request()
            ->get($this->endpoint($profile), [
                'fields' => 'id,name,link',
                'access_token' => $profile->facebook_access_token,
            ])
            ->throw();
    }

    public function publishPost(WordPressSite $profile, string $message, ?string $link = null): Response
    {
        return $this->request()
            ->asForm()
            ->post($this->endpoint($profile, 'feed'), array_filter([
                'message' => $message,
                'link' => $link,
                'access_token' => $profile->facebook_access_token,
            ]))
            ->throw();
    }

    public function publishPhoto(
        WordPressSite $profile,
        string $contents,
        string $filename,
        string $mimeType,
        string $message,
    ): Response {
        return $this->request()
            ->attach('source', $contents, $filename, ['Content-Type' => $mimeType])
            ->post($this->endpoint($profile, 'photos'), [
                'message' => $message,
                'published' => 'true',
                'access_token' => $profile->facebook_access_token,
            ])
            ->throw();
    }

    private function request(): PendingRequest
    {
        return Http::timeout(90)
            ->connectTimeout(15)
            ->acceptJson();
    }

    private function endpoint(WordPressSite $profile, ?string $edge = null): string
    {
        $version = $profile->facebook_api_version ?: 'v24.0';
        $pageId = trim((string) $profile->facebook_page_id);
        $path = $edge ? "{$pageId}/{$edge}" : $pageId;

        return "https://graph.facebook.com/{$version}/{$path}";
    }
}
