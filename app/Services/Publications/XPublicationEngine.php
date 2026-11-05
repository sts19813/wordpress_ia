<?php

namespace App\Services\Publications;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Throwable;

class XPublicationEngine
{
    public function __construct(
        private readonly XClient $client,
    ) {}

    public function publishNow(WordPressSite $profile, AiArticle $article, ?AiImage $image = null): Publication
    {
        $publication = Publication::query()->firstOrCreate(
            [
                'wordpress_site_id' => $profile->id,
                'ai_article_id' => $article->id,
                'status' => Publication::STATUS_DRAFT,
            ],
            [
                'user_id' => $article->user_id ?: $profile->user_id,
                'ai_image_id' => $image?->id,
                'last_action' => 'prepared_x',
            ],
        );
        $text = $this->postText($article);
        $images = $this->imagesForPublication($article, $image);
        $publication->update([
            'ai_image_id' => $images->first()?->id,
            'request_payload' => [
                'platform' => WordPressSite::TYPE_X,
                'text' => $text,
            ],
            'error_message' => null,
        ]);

        try {
            $mediaResponses = [];
            $mediaErrors = [];
            $mediaIds = [];

            foreach ($images as $uploadedImage) {
                try {
                    $mediaResponse = $this->client->uploadImage(
                        $profile,
                        Storage::disk('local')->get($uploadedImage->file_path),
                        basename($uploadedImage->file_path),
                        $uploadedImage->mime_type ?: Storage::disk('local')->mimeType($uploadedImage->file_path) ?: 'image/png',
                    );
                    $mediaId = (string) $mediaResponse->json('data.id');
                    if ($mediaId !== '') {
                        $mediaIds[] = $mediaId;
                        $mediaResponses[] = $mediaResponse->json();
                    }
                } catch (Throwable $exception) {
                    $errorResponse = $exception instanceof RequestException ? ($exception->response?->json() ?: []) : [];
                    $mediaErrors[] = data_get($errorResponse, 'detail')
                        ?: data_get($errorResponse, 'errors.0.detail')
                        ?: $exception->getMessage()
                        ?: 'X rechazó la imagen; se publicó únicamente el texto.';
                }
            }

            $response = $this->client->publishPost($profile, $text, $mediaIds);
            $postId = (string) $response->json('data.id');

            $publication->update([
                'remote_post_key' => $postId,
                'remote_url' => $postId !== '' ? 'https://x.com/'.ltrim((string) $profile->x_username, '@').'/status/'.$postId : null,
                'status' => Publication::STATUS_PUBLISHED,
                'last_action' => 'publish_x_post',
                'full_response' => [
                    'media' => $mediaResponses !== [] ? $mediaResponses : ($mediaErrors !== [] ? [
                        'omitted' => true,
                        'error' => implode(' ', $mediaErrors),
                    ] : null),
                    'post' => $response->json(),
                ],
                'published_at' => now(),
                'error_message' => null,
            ]);

            return $publication->fresh();
        } catch (Throwable $exception) {
            $response = $exception instanceof RequestException ? ($exception->response?->json() ?: []) : [];
            $message = data_get($response, 'detail')
                ?: data_get($response, 'errors.0.detail')
                ?: $exception->getMessage()
                ?: 'X rechazó la publicación.';

            $publication->update([
                'status' => Publication::STATUS_FAILED,
                'last_action' => 'publish_x_failed',
                'full_response' => $response,
                'error_message' => $message,
            ]);

            return $publication->fresh();
        }
    }

    private function postText(AiArticle $article): string
    {
        $link = $article->publications()
            ->where('status', Publication::STATUS_PUBLISHED)
            ->whereNotNull('remote_url')
            ->whereHas('wordpressSite', fn ($query) => $query->where('type', WordPressSite::TYPE_WORDPRESS))
            ->latest('published_at')
            ->value('remote_url');
        $body = trim((string) ($article->excerpt ?: html_entity_decode(strip_tags((string) $article->content))));
        $suffix = $link ? "\n\n".$link : '';
        $limit = max(1, 280 - mb_strlen($suffix));
        $text = str(collect([
            trim((string) $article->title),
            $body,
        ])->filter()->implode("\n\n"))->limit($limit, '…')->toString();

        return $text.$suffix;
    }

    /** @return \Illuminate\Support\Collection<int, AiImage> */
    private function imagesForPublication(AiArticle $article, ?AiImage $fallback): \Illuminate\Support\Collection
    {
        $images = $article->relationLoaded('images')
            ? $article->images
            : $article->images()->where('status', AiImage::STATUS_GENERATED)->get();

        return collect($images)
            ->when($fallback && ! $images->contains('id', $fallback->id), fn ($collection) => $collection->prepend($fallback))
            ->filter(fn (AiImage $candidate): bool => $candidate->status === AiImage::STATUS_GENERATED
                && filled($candidate->file_path)
                && $candidate->type !== AiImage::TYPE_VIDEO
                && ! str_starts_with((string) $candidate->mime_type, 'video/')
                && Storage::disk('local')->exists($candidate->file_path))
            ->sortBy(fn (AiImage $candidate): int => $candidate->type === AiImage::TYPE_MAIN ? 0 : 1)
            ->take(4)
            ->values();
    }
}
