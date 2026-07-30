<?php

namespace App\Services;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use App\Services\Publications\FacebookPageClient;
use App\Services\Publications\FacebookPublicationEngine;
use App\Services\Publications\InstagramClient;
use App\Services\Publications\InstagramPublicationEngine;
use App\Services\Publications\PublicationEngine;
use App\Services\Publications\WordPressRestClient;
use App\Services\Publications\XClient;
use App\Services\Publications\XPublicationEngine;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PublicationService
{
    public function __construct(
        private readonly PublicationEngine $engine,
        private readonly WordPressRestClient $client,
        private readonly FacebookPublicationEngine $facebook,
        private readonly FacebookPageClient $facebookClient,
        private readonly InstagramPublicationEngine $instagram,
        private readonly InstagramClient $instagramClient,
        private readonly XPublicationEngine $x,
        private readonly XClient $xClient,
    ) {}

    public function createPublication(WordPressSite $site, AiArticle $article, ?AiImage $image = null): Publication
    {
        return $this->engine->createPublication($site, $article, $image);
    }

    public function uploadImage(Publication $publication, string $contents, string $filename, string $mimeType = 'image/jpeg'): Publication
    {
        return $this->engine->uploadImage($publication, $contents, $filename, $mimeType);
    }

    public function createCategory(WordPressSite $site, string $name): array
    {
        return $this->engine->createCategory($site, $name);
    }

    public function createTag(WordPressSite $site, string $name): array
    {
        return $this->engine->createTag($site, $name);
    }

    public function createArticle(Publication $publication, string $status = 'draft'): Publication
    {
        return $this->engine->createArticle($publication, $status);
    }

    public function updateArticle(Publication $publication, array $overrides = []): Publication
    {
        return $this->engine->updateArticle($publication, $overrides);
    }

    public function schedulePublication(Publication $publication, string $scheduledAt): Publication
    {
        return $this->engine->schedulePublication($publication, $scheduledAt);
    }

    public function deletePublication(Publication $publication, bool $force = false): Publication
    {
        return $this->engine->deletePublication($publication, $force);
    }

    public function testConnection(WordPressSite $site): array
    {
        if ($site->isFacebookPage()) {
            $connection = $this->facebookClient->testConnection($site);
            $response = $connection['response'];

            return [
                'id' => $response->json('id'),
                'name' => $response->json('name'),
                'link' => $response->json('link'),
                'roles' => [],
                'facebook_page_id' => $connection['page_id'],
                'facebook_access_token' => $connection['access_token'],
            ];
        }

        if ($site->isInstagram()) {
            $response = $this->instagramClient->testConnection($site);
            $accountType = strtoupper((string) $response->json('account_type'));

            if (! in_array($accountType, ['BUSINESS', 'CREATOR', 'MEDIA_CREATOR'], true)) {
                throw new RuntimeException('La cuenta de Instagram debe ser profesional (Business o Creator).');
            }

            return [
                'id' => $response->json('id'),
                'name' => $response->json('username'),
                'roles' => [],
                'instagram_account_id' => (string) $response->json('id'),
            ];
        }

        if ($site->isX()) {
            $response = $this->xClient->testConnection($site);

            return [
                'id' => $response->json('data.id'),
                'name' => $response->json('data.name'),
                'roles' => [],
                'x_user_id' => (string) $response->json('data.id'),
                'x_username' => (string) $response->json('data.username'),
            ];
        }

        $response = $this->client->testConnection($site);

        return [
            'id' => $response->json('id'),
            'name' => $response->json('name'),
            'roles' => $response->json('roles', []),
        ];
    }

    public function publishNow(WordPressSite $site, AiArticle $article, ?AiImage $image = null): Publication
    {
        if ($site->isFacebookPage()) {
            return $this->facebook->publishNow($site, $article, $image);
        }

        if ($site->isInstagram()) {
            return $this->instagram->publishNow($site, $article, $image);
        }

        if ($site->isX()) {
            return $this->x->publishNow($site, $article, $image);
        }

        $publication = Publication::query()
            ->where('wordpress_site_id', $site->id)
            ->where('ai_article_id', $article->id)
            ->where('status', '!=', Publication::STATUS_DELETED)
            ->latest('id')
            ->first();

        if (! $publication) {
            $publication = $this->createPublication($site, $article, $image);
        } else {
            $publication->update(['ai_image_id' => $image?->id]);
        }

        $publication->load(['wordpressSite', 'aiArticle']);
        $this->engine->refreshPayload($publication);

        if ($image?->file_path && ! $publication->remote_featured_media_id && Storage::disk('local')->exists($image->file_path)) {
            try {
                $this->uploadImage(
                    $publication,
                    Storage::disk('local')->get($image->file_path),
                    basename($image->file_path),
                    $image->mime_type ?: Storage::disk('local')->mimeType($image->file_path) ?: 'image/jpeg',
                );
            } catch (Throwable) {
                // A missing media permission should not prevent publishing the text.
            }
        }

        try {
            return $publication->remote_post_id
                ? $this->updateArticle($publication, ['status' => 'publish'])
                : $this->createArticle($publication, 'publish');
        } catch (Throwable $exception) {
            $response = $exception instanceof RequestException ? ($exception->response?->json() ?: []) : [];
            $message = is_string($response['message'] ?? null)
                ? $response['message']
                : 'No se pudo conectar con WordPress. Revisa el dominio, el usuario y la contraseña de aplicación.';

            return $this->engine->recordFailure($publication, $message, $response);
        }
    }
}
