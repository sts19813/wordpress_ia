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
        $publication->update([
            'ai_image_id' => $image?->id,
            'request_payload' => [
                'platform' => WordPressSite::TYPE_X,
                'text' => $text,
            ],
            'error_message' => null,
        ]);

        try {
            $hasLocalImage = (bool) ($image?->file_path && Storage::disk('local')->exists($image->file_path));
            $mediaResponse = null;
            $mediaError = null;

            if ($hasLocalImage) {
                try {
                    $mediaResponse = $this->client->uploadImage(
                        $profile,
                        Storage::disk('local')->get($image->file_path),
                        basename($image->file_path),
                        $image->mime_type ?: Storage::disk('local')->mimeType($image->file_path) ?: 'image/png',
                    );
                } catch (Throwable $exception) {
                    $errorResponse = $exception instanceof RequestException ? ($exception->response?->json() ?: []) : [];
                    $mediaError = data_get($errorResponse, 'detail')
                        ?: data_get($errorResponse, 'errors.0.detail')
                        ?: $exception->getMessage()
                        ?: 'X rechazó la imagen; se publicó únicamente el texto.';
                }
            }

            $mediaId = $mediaResponse ? (string) $mediaResponse->json('data.id') : null;
            $response = $this->client->publishPost($profile, $text, $mediaId ?: null);
            $postId = (string) $response->json('data.id');

            $publication->update([
                'remote_post_key' => $postId,
                'remote_url' => $postId !== '' ? 'https://x.com/'.ltrim((string) $profile->x_username, '@').'/status/'.$postId : null,
                'status' => Publication::STATUS_PUBLISHED,
                'last_action' => 'publish_x_post',
                'full_response' => [
                    'media' => $mediaResponse?->json() ?: ($mediaError ? [
                        'omitted' => true,
                        'error' => $mediaError,
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
}
