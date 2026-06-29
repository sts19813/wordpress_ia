<?php

namespace App\Services\Publications;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use Illuminate\Http\Client\Response;

class PublicationEngine
{
    public function __construct(
        private readonly WordPressRestClient $client,
    ) {}

    public function createPublication(WordPressSite $site, AiArticle $article, ?AiImage $image = null): Publication
    {
        return Publication::query()->create([
            'wordpress_site_id' => $site->id,
            'ai_article_id' => $article->id,
            'ai_image_id' => $image?->id,
            'status' => Publication::STATUS_DRAFT,
            'last_action' => 'prepared',
            'request_payload' => $this->postPayload($article),
        ]);
    }

    public function uploadImage(Publication $publication, string $contents, string $filename, string $mimeType = 'image/jpeg'): Publication
    {
        $response = $this->client->uploadMedia($publication->wordpressSite, $contents, $filename, $mimeType);

        return $this->recordResponse($publication, 'upload_image', $response, [
            'remote_featured_media_id' => $response->json('id'),
        ]);
    }

    public function createCategory(WordPressSite $site, string $name): array
    {
        return $this->client->post($site, '/wp-json/wp/v2/categories', [
            'name' => $name,
        ])->json();
    }

    public function createTag(WordPressSite $site, string $name): array
    {
        return $this->client->post($site, '/wp-json/wp/v2/tags', [
            'name' => $name,
        ])->json();
    }

    public function createArticle(Publication $publication, string $status = 'draft'): Publication
    {
        $payload = [
            ...($publication->request_payload ?: $this->postPayload($publication->aiArticle)),
            'status' => $status,
        ];

        if ($publication->remote_featured_media_id) {
            $payload['featured_media'] = $publication->remote_featured_media_id;
        }

        $response = $this->client->post($publication->wordpressSite, '/wp-json/wp/v2/posts', $payload);

        return $this->recordResponse($publication, 'create_article', $response, [
            'remote_post_id' => $response->json('id'),
            'remote_url' => $response->json('link'),
            'status' => $this->localStatusFromRemote($response->json('status')),
            'request_payload' => $payload,
        ]);
    }

    public function updateArticle(Publication $publication, array $overrides = []): Publication
    {
        $payload = [
            ...($publication->request_payload ?: $this->postPayload($publication->aiArticle)),
            ...$overrides,
        ];

        $response = $this->client->put($publication->wordpressSite, "/wp-json/wp/v2/posts/{$publication->remote_post_id}", $payload);

        return $this->recordResponse($publication, 'update_article', $response, [
            'remote_url' => $response->json('link') ?: $publication->remote_url,
            'status' => $this->localStatusFromRemote($response->json('status')),
            'request_payload' => $payload,
        ]);
    }

    public function schedulePublication(Publication $publication, string $scheduledAt): Publication
    {
        $payload = [
            ...($publication->request_payload ?: $this->postPayload($publication->aiArticle)),
            'status' => 'future',
            'date' => $scheduledAt,
        ];

        $response = $publication->remote_post_id
            ? $this->client->put($publication->wordpressSite, "/wp-json/wp/v2/posts/{$publication->remote_post_id}", $payload)
            : $this->client->post($publication->wordpressSite, '/wp-json/wp/v2/posts', $payload);

        return $this->recordResponse($publication, 'schedule_publication', $response, [
            'remote_post_id' => $response->json('id') ?: $publication->remote_post_id,
            'remote_url' => $response->json('link') ?: $publication->remote_url,
            'status' => Publication::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'request_payload' => $payload,
        ]);
    }

    public function deletePublication(Publication $publication, bool $force = false): Publication
    {
        $response = $this->client->delete(
            $publication->wordpressSite,
            "/wp-json/wp/v2/posts/{$publication->remote_post_id}?force=".($force ? 'true' : 'false'),
        );

        return $this->recordResponse($publication, 'delete_publication', $response, [
            'status' => Publication::STATUS_DELETED,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(AiArticle $article): array
    {
        return [
            'title' => $article->title,
            'content' => $article->content,
            'excerpt' => $article->excerpt,
            'slug' => $article->slug,
            'status' => 'draft',
            'meta' => [
                'description' => $article->meta_description,
                'seo_keywords' => $article->seo_keywords,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function recordResponse(Publication $publication, string $action, Response $response, array $updates = []): Publication
    {
        $publication->update([
            'last_action' => $action,
            'full_response' => $response->json(),
            ...$updates,
        ]);

        return $publication;
    }

    private function localStatusFromRemote(?string $status): string
    {
        return match ($status) {
            'publish' => Publication::STATUS_PUBLISHED,
            'future' => Publication::STATUS_SCHEDULED,
            'pending' => Publication::STATUS_PENDING,
            default => Publication::STATUS_DRAFT,
        };
    }
}
