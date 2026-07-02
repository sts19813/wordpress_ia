<?php

namespace App\Services\Publications;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use Illuminate\Http\Client\Response;
use Throwable;

class PublicationEngine
{
    public function __construct(
        private readonly WordPressRestClient $client,
    ) {}

    public function createPublication(WordPressSite $site, AiArticle $article, ?AiImage $image = null): Publication
    {
        return Publication::query()->create([
            'user_id' => $article->user_id ?: $site->user_id,
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

        $payload = [...$payload, ...$this->taxonomyPayload($publication->wordpressSite, $publication->aiArticle)];

        if ($publication->remote_featured_media_id) {
            $payload['featured_media'] = $publication->remote_featured_media_id;
        }

        $response = $this->client->post($publication->wordpressSite, '/wp-json/wp/v2/posts', $payload);

        return $this->recordResponse($publication, 'create_article', $response, [
            'remote_post_id' => $response->json('id'),
            'remote_url' => $response->json('link'),
            'status' => $this->localStatusFromRemote($response->json('status')),
            'published_at' => $response->json('status') === 'publish' ? now() : null,
            'error_message' => null,
            'request_payload' => $payload,
        ]);
    }

    public function updateArticle(Publication $publication, array $overrides = []): Publication
    {
        $payload = [
            ...($publication->request_payload ?: $this->postPayload($publication->aiArticle)),
            ...$overrides,
        ];

        $payload = [...$payload, ...$this->taxonomyPayload($publication->wordpressSite, $publication->aiArticle)];

        $response = $this->client->put($publication->wordpressSite, "/wp-json/wp/v2/posts/{$publication->remote_post_id}", $payload);

        return $this->recordResponse($publication, 'update_article', $response, [
            'remote_url' => $response->json('link') ?: $publication->remote_url,
            'status' => $this->localStatusFromRemote($response->json('status')),
            'published_at' => $response->json('status') === 'publish' ? now() : $publication->published_at,
            'error_message' => null,
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

    public function refreshPayload(Publication $publication): Publication
    {
        $publication->update([
            'request_payload' => $this->postPayload($publication->aiArticle),
            'error_message' => null,
        ]);

        return $publication;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function recordFailure(Publication $publication, string $message, array $response = []): Publication
    {
        $publication->update([
            'status' => Publication::STATUS_FAILED,
            'last_action' => 'publish_failed',
            'error_message' => $message,
            'full_response' => $response,
        ]);

        return $publication;
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(AiArticle $article): array
    {
        $content = $article->content;

        if (filled($article->conclusion)) {
            $content .= '<h2>Conclusión</h2><p>'.htmlspecialchars($article->conclusion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        }

        return [
            'title' => $article->title,
            'content' => $content,
            'excerpt' => $article->excerpt,
            'slug' => $article->slug,
            'status' => 'draft',
        ];
    }

    /**
     * WordPress needs numeric term IDs. Term synchronization is best-effort so a
     * taxonomy permission never prevents the article itself from being published.
     *
     * @return array<string, array<int, int>>
     */
    private function taxonomyPayload(WordPressSite $site, AiArticle $article): array
    {
        $payload = [];

        foreach (['categories' => $article->categories ?: [], 'tags' => $article->tags ?: []] as $taxonomy => $names) {
            try {
                $ids = $this->termIds($site, $taxonomy, $names);

                if ($ids !== []) {
                    $payload[$taxonomy] = $ids;
                }
            } catch (Throwable) {
                // The post can still be published when this user cannot manage terms.
            }
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function termIds(WordPressSite $site, string $taxonomy, array $names): array
    {
        $ids = [];

        foreach (collect($names)->map(fn ($name) => trim((string) $name))->filter()->unique() as $name) {
            $found = collect($this->client->get($site, "/wp-json/wp/v2/{$taxonomy}", [
                'search' => $name,
                'per_page' => 100,
            ])->json())->first(fn ($term) => mb_strtolower((string) ($term['name'] ?? '')) === mb_strtolower($name));

            $id = $found['id'] ?? $this->client->post($site, "/wp-json/wp/v2/{$taxonomy}", ['name' => $name])->json('id');

            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
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
