<?php

namespace App\Services\Publications;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use Illuminate\Http\Client\RequestException;
use Throwable;

class FacebookPublicationEngine
{
    public function __construct(
        private readonly FacebookPageClient $client,
    ) {}

    public function publishNow(WordPressSite $profile, AiArticle $article, ?AiImage $image = null): Publication
    {
        $publication = Publication::query()
            ->where('wordpress_site_id', $profile->id)
            ->where('ai_article_id', $article->id)
            ->where('status', '!=', Publication::STATUS_DELETED)
            ->latest('id')
            ->first();
        $link = $this->publishedArticleUrl($article);
        $message = $this->message($article, $link);
        $payload = [
            'platform' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'message' => $message,
            'link' => $link,
            'native_link_post' => true,
        ];

        if (! $publication) {
            $publication = Publication::query()->create([
                'user_id' => $article->user_id ?: $profile->user_id,
                'wordpress_site_id' => $profile->id,
                'ai_article_id' => $article->id,
                'ai_image_id' => $image?->id,
                'status' => Publication::STATUS_DRAFT,
                'last_action' => 'prepared_facebook',
                'request_payload' => $payload,
            ]);
        } else {
            $publication->update([
                'ai_image_id' => $image?->id,
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
            // Do not attach a local image: Facebook would turn it into a photo post.
            // It obtains the preview image from the Open Graph metadata at $link.
            $response = $this->client->publishPost($profile, $message, $link);
            $remoteKey = (string) $response->json('id');
            $remoteUrl = $remoteKey !== '' ? 'https://www.facebook.com/'.$remoteKey : null;

            try {
                $remoteUrl = $this->client->publicationUrl($profile, $remoteKey) ?: $remoteUrl;
            } catch (Throwable) {
                // La publicación ya existe; una consulta de permalink no debe marcarla como fallida.
            }

            $publication->update([
                'remote_post_key' => $remoteKey,
                'remote_url' => $remoteUrl,
                'status' => Publication::STATUS_PUBLISHED,
                'last_action' => 'publish_facebook_link_post',
                'full_response' => ['post' => $response->json()],
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

    private function publishedArticleUrl(AiArticle $article): ?string
    {
        return $article->publications()
            ->where('status', Publication::STATUS_PUBLISHED)
            ->whereNotNull('remote_url')
            ->whereHas('wordpressSite', fn ($query) => $query->where('type', WordPressSite::TYPE_WORDPRESS))
            ->latest('published_at')
            ->value('remote_url');
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

    private function plainText(string $html): string
    {
        $withBreaks = preg_replace('/<(br\s*\/?|\/p|\/h[1-6]|\/li)>/i', "\n", $html) ?: $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?: $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?: $text);
    }
}
