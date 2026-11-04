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

class FacebookPublicationEngine
{
    public function __construct(
        private readonly FacebookPageClient $client,
        private readonly CompanyArticleUrlResolver $articleUrls,
    ) {}

    public function publishNow(WordPressSite $profile, AiArticle $article, ?AiImage $image = null): Publication
    {
        $publication = Publication::query()
            ->where('wordpress_site_id', $profile->id)
            ->where('ai_article_id', $article->id)
            ->where('status', '!=', Publication::STATUS_DELETED)
            ->latest('id')
            ->first();
        $link = $this->articleUrls->resolve($profile, $article);
        $message = $this->message($article, $link);
        $images = $this->imagesForPublication($article, $image);
        $hasLocalImage = $images->isNotEmpty();
        $payload = [
            'platform' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'message' => $message,
            'article_url' => $link,
            'publication_type' => $hasLocalImage ? 'photo' : 'link',
        ];

        if (! $publication) {
            $publication = Publication::query()->create([
                'user_id' => $article->user_id ?: $profile->user_id,
                'wordpress_site_id' => $profile->id,
                'ai_article_id' => $article->id,
                'ai_image_id' => $images->first()?->id,
                'status' => Publication::STATUS_DRAFT,
                'last_action' => 'prepared_facebook',
                'request_payload' => $payload,
            ]);
        } else {
            $publication->update([
                'ai_image_id' => $images->first()?->id,
                'request_payload' => $payload,
                'error_message' => null,
            ]);
        }

        if (blank($link)) {
            $publication->update([
                'status' => Publication::STATUS_FAILED,
                'last_action' => 'publish_facebook_failed',
                'error_message' => 'Primero publica el artículo en WordPress. Facebook necesita la URL pública del artículo para crear un post con enlace.',
            ]);

            return $publication->fresh();
        }

        try {
            $photoResponses = [];
            $photoIds = [];

            foreach ($images as $uploadedImage) {
                $photoResponse = $this->client->uploadPhoto(
                    $profile,
                    Storage::disk('local')->get($uploadedImage->file_path),
                    basename($uploadedImage->file_path),
                    $uploadedImage->mime_type ?: Storage::disk('local')->mimeType($uploadedImage->file_path) ?: 'image/jpeg',
                );
                $photoId = (string) $photoResponse->json('id');

                if ($photoId === '') {
                    throw new RuntimeException('Facebook no devolvió el identificador de la imagen subida.');
                }

                $photoIds[] = $photoId;
                $photoResponses[] = $photoResponse->json();
            }

            // A photo post makes the image independent from Facebook's Open Graph
            // scraper. The article URL remains visible and clickable in the message.
            $response = $this->client->publishPost(
                $profile,
                $message,
                $hasLocalImage ? null : $link,
                $photoIds,
            );
            $remoteKey = (string) $response->json('id');
            $remoteUrl = $remoteKey !== '' ? 'https://www.facebook.com/'.$remoteKey : null;

            try {
                $remoteUrl = $this->client->publicationUrl($profile, $remoteKey, $photoIds[0] ?? null) ?: $remoteUrl;
            } catch (Throwable) {
                // La publicación ya existe; una consulta de permalink no debe marcarla como fallida.
            }

            $publication->update([
                'remote_post_key' => $remoteKey,
                'remote_url' => $remoteUrl,
                'status' => Publication::STATUS_PUBLISHED,
                'last_action' => $hasLocalImage ? 'publish_facebook_photo_post' : 'publish_facebook_link_post',
                'full_response' => [
                    'photos' => $photoResponses,
                    'post' => $response->json(),
                ],
                'published_at' => now(),
                'error_message' => null,
            ]);

            return $publication->fresh();
        } catch (Throwable $exception) {
            $response = $exception instanceof RequestException ? ($exception->response?->json() ?: []) : [];
            $message = data_get($response, 'error.message')
                ?: $exception->getMessage()
                ?: 'Facebook rechazó la publicación.';

            $publication->update([
                'status' => Publication::STATUS_FAILED,
                'last_action' => 'publish_facebook_failed',
                'full_response' => $response,
                'error_message' => $message,
            ]);

            return $publication->fresh();
        }
    }

    private function message(AiArticle $article, ?string $link): string
    {
        $content = $this->plainText((string) $article->content);
        $body = $link
            ? trim((string) ($article->excerpt ?: $content))
            : $content;
        $bodyLimit = $link ? 1800 : 5000;
        $tags = collect($article->tags ?: [])
            ->map(fn (mixed $tag) => '#'.preg_replace('/[^\pL\pN_]+/u', '', str_replace(' ', '', (string) $tag)))
            ->filter(fn (string $tag) => mb_strlen($tag) > 1)
            ->take(5)
            ->implode(' ');

        return collect([
            trim((string) $article->title),
            str($body)->limit($bodyLimit, '…')->toString(),
            $link,
            $tags,
        ])->filter()->implode("\n\n");
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
                && Storage::disk('local')->exists($candidate->file_path))
            ->sortBy(fn (AiImage $candidate): int => $candidate->type === AiImage::TYPE_MAIN ? 0 : 1)
            ->take(10)
            ->values();
    }

    private function plainText(string $html): string
    {
        $withBreaks = preg_replace('/<(br\s*\/?|\/p|\/h[1-6]|\/li)>/i', "\n", $html) ?: $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?: $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?: $text);
    }
}
