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
                'fields' => 'id,username',
            ])
            ->throw();
    }

    public function createImageContainer(
        WordPressSite $profile,
        string $imageUrl,
        string $caption = '',
        bool $isCarouselItem = false,
    ): Response
    {
        $payload = [
            'image_url' => $imageUrl,
            'caption' => $caption,
        ];

        if ($isCarouselItem) {
            $payload['is_carousel_item'] = 'true';
            unset($payload['caption']);
        }

        return $this->request($profile)
            ->asForm()
            ->post($this->endpoint($profile, 'media'), $payload)
            ->throw();
    }

    public function createVideoContainer(
        WordPressSite $profile,
        string $videoUrl,
        string $caption,
    ): Response {
        return $this->request($profile)
            ->asForm()
            ->post($this->endpoint($profile, 'media'), [
                'media_type' => 'REELS',
                'video_url' => $videoUrl,
                'caption' => $caption,
                'share_to_feed' => 'true',
            ])
            ->throw();
    }

    /** @param array<int, string> $children */
    public function createCarouselContainer(WordPressSite $profile, array $children, string $caption): Response
    {
        $payload = [
            'media_type' => 'CAROUSEL',
            'caption' => $caption,
        ];

        $payload['children'] = json_encode(array_values($children), JSON_THROW_ON_ERROR);

        return $this->request($profile)
            ->asForm()
            ->post($this->endpoint($profile, 'media'), $payload)
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

    public function containerStatus(WordPressSite $profile, string $creationId): Response
    {
        return $this->request($profile)
            ->get($this->graphEndpoint($profile, $creationId), [
                'fields' => 'status_code',
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
