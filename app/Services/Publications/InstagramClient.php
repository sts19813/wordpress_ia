<?php

namespace App\Services\Publications;

use App\Models\WordPressSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class InstagramClient
{
    public function testConnection(WordPressSite $profile): Response
    {
        return $this->request($profile)
            ->get($this->endpoint($profile), [
                'fields' => 'id,username,account_type',
            ])
            ->throw();
    }

    public function createImageContainer(WordPressSite $profile, string $imageUrl, string $caption): Response
    {
        return $this->request($profile)
            ->asForm()
            ->post($this->endpoint($profile, 'media'), [
                'image_url' => $imageUrl,
                'caption' => $caption,
            ])
            ->throw();
    }

    public function publishContainer(WordPressSite $profile, string $creationId): Response
    {
        return $this->request($profile)
            ->asForm()
            ->post($this->endpoint($profile, 'media_publish'), [
                'creation_id' => $creationId,
            ])
            ->throw();
    }

    public function mediaDetails(WordPressSite $profile, string $mediaId): Response
    {
        return $this->request($profile)
            ->get($this->graphEndpoint($profile, $mediaId), [
                'fields' => 'id,permalink',
            ])
            ->throw();
    }

    private function request(WordPressSite $profile): PendingRequest
    {
        return Http::timeout(90)
            ->connectTimeout(15)
            ->acceptJson()
            ->withToken((string) $profile->instagram_access_token);
    }

    private function endpoint(WordPressSite $profile, ?string $edge = null): string
    {
        $path = trim((string) $profile->instagram_account_id);

        if ($edge) {
            $path .= '/'.$edge;
        }

        return $this->graphEndpoint($profile, $path);
    }

    private function graphEndpoint(WordPressSite $profile, string $path): string
    {
        $version = $profile->instagram_api_version ?: 'v24.0';

        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
    }
}
