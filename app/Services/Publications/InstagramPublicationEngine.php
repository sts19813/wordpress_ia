<?php

namespace App\Services\Publications;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class InstagramPublicationEngine
{
    public function __construct(
        private readonly InstagramClient $client,
        private readonly InstagramMediaUrl $mediaUrl,
    ) {}

    public function publishNow(WordPressSite $profile, AiArticle $article, ?AiImage $image = null): Publication
    {
        $publication = $this->publication($profile, $article, $image);

        try {
            if (! $image?->file_path || ! Storage::disk('local')->exists($image->file_path)) {
                throw new RuntimeException('Instagram requiere una imagen generada disponible para publicar.');
            }

            $imageUrl = $this->mediaUrl->temporary($image, now()->addHour());
            $caption = $this->caption($article);
            $container = $this->client->createImageContainer($profile, $imageUrl, $caption);
            $creationId = (string) $container->json('id');

            if ($creationId === '') {
                throw new RuntimeException('Instagram no devolvió el identificador del contenido.');
            }

            $published = $this->client->publishContainer($profile, $creationId);
            $mediaId = (string) $published->json('id');

            if ($mediaId === '') {
                throw new RuntimeException('Instagram no confirmó la publicación.');
            }

            $details = $this->client->mediaDetails($profile, $mediaId);
            $publication->update([
                'remote_post_key' => $mediaId,
                'remote_url' => $details->json('permalink'),
                'status' => Publication::STATUS_PUBLISHED,
                'last_action' => 'publish_instagram_image',
                'request_payload' => [
                    'platform' => WordPressSite::TYPE_INSTAGRAM,
                    'caption' => $caption,
                    'image_url' => $imageUrl,
                ],
                'full_response' => [
                    'container' => $container->json(),
                    'published' => $published->json(),
                    'media' => $details->json(),
                ],
                'published_at' => now(),
                'error_message' => null,
            ]);

            return $publication->fresh();
        } catch (Throwable $exception) {
            return $this->recordFailure($publication, $exception, 'Instagram rechazó la publicación.');
        }
    }

    private function publication(WordPressSite $profile, AiArticle $article, ?AiImage $image): Publication
    {
        return Publication::query()->firstOrCreate(
            [
                'wordpress_site_id' => $profile->id,
                'ai_article_id' => $article->id,
                'status' => Publication::STATUS_DRAFT,
            ],
            [
                'user_id' => $article->user_id ?: $profile->user_id,
                'ai_image_id' => $image?->id,
                'last_action' => 'prepared_instagram',
            ],
        );
    }

    private function caption(AiArticle $article): string
    {
        $content = html_entity_decode(strip_tags((string) $article->content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = trim((string) ($article->excerpt ?: $content));
        $tags = collect($article->tags ?: [])
            ->map(fn (mixed $tag) => '#'.preg_replace('/[^\pL\pN_]+/u', '', str_replace(' ', '', (string) $tag)))
            ->filter(fn (string $tag) => mb_strlen($tag) > 1)
            ->take(10)
            ->implode(' ');

        return str(collect([
            trim((string) $article->title),
            str($body)->limit(1800, '…')->toString(),
            $tags,
        ])->filter()->implode("\n\n"))->limit(2200, '…')->toString();
    }

    private function recordFailure(Publication $publication, Throwable $exception, string $fallback): Publication
    {
        $response = $exception instanceof RequestException ? ($exception->response?->json() ?: []) : [];
        $message = data_get($response, 'error.message') ?: $exception->getMessage() ?: $fallback;

        $publication->update([
            'status' => Publication::STATUS_FAILED,
            'last_action' => 'publish_instagram_failed',
            'full_response' => $response,
            'error_message' => $message,
        ]);

        return $publication->fresh();
    }
}
