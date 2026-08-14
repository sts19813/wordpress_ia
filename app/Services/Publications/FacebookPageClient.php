<?php

namespace App\Services\Publications;

use App\Models\WordPressSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacebookPageClient
{
    /**
     * @return array{response: Response, page_id: string, access_token: string}
     */
    public function testConnection(WordPressSite $profile): array
    {
        $credentials = $this->pageCredentials($profile);
        $response = $this->request()
            ->get($this->endpoint($profile, pageId: $credentials['page_id']), [
                'fields' => 'id,name,link',
                'access_token' => $credentials['access_token'],
            ])
            ->throw();

        return [
            'response' => $response,
            ...$credentials,
        ];
    }

    public function publishPost(WordPressSite $profile, string $message, ?string $link = null): Response
    {
        $credentials = $this->pageCredentials($profile);

        return $this->request()
            ->asForm()
            ->post($this->endpoint($profile, 'feed', $credentials['page_id']), array_filter([
                'message' => $message,
                'link' => $link,
                'access_token' => $credentials['access_token'],
            ]))
            ->throw();
    }

    public function publishPhoto(
        WordPressSite $profile,
        string $contents,
        string $filename,
        string $mimeType,
        string $message,
    ): Response {
        $credentials = $this->pageCredentials($profile);

        return $this->request()
            ->attach('source', $contents, $filename, ['Content-Type' => $mimeType])
            ->post($this->endpoint($profile, 'photos', $credentials['page_id']), [
                'message' => $message,
                'published' => 'true',
                'access_token' => $credentials['access_token'],
            ])
            ->throw();
    }

    public function publicationUrl(
        WordPressSite $profile,
        string $postId,
        ?string $photoId = null,
    ): ?string {
        $credentials = $this->pageCredentials($profile);

        if (filled($photoId)) {
            $photo = $this->request()
                ->get($this->graphEndpoint($profile, $photoId), [
                    'fields' => 'id,link',
                    'access_token' => $credentials['access_token'],
                ]);
            $this->guardApiAccess($photo);

            if ($photo->successful() && filled($photo->json('link'))) {
                return (string) $photo->json('link');
            }
        }

        if (blank($postId)) {
            return null;
        }

        $post = $this->request()
            ->get($this->graphEndpoint($profile, $postId), [
                'fields' => 'id,permalink_url',
                'access_token' => $credentials['access_token'],
            ]);
        $this->guardApiAccess($post);

        return $post->successful() && filled($post->json('permalink_url'))
            ? (string) $post->json('permalink_url')
            : null;
    }

    /**
     * Accepts a Page Access Token directly. When the user pasted a User Access
     * Token, resolves the managed page and its Page Access Token via /me/accounts.
     *
     * @return array{page_id: string, access_token: string}
     */
    public function pageCredentials(WordPressSite $profile): array
    {
        $providedToken = trim((string) $profile->facebook_access_token);
        $configuredPageId = trim((string) $profile->facebook_page_id);
        $identity = $this->request()
            ->get($this->graphEndpoint($profile, 'me'), [
                'fields' => 'id,name,category',
                'access_token' => $providedToken,
            ]);
        $this->guardApiAccess($identity);

        if ($identity->successful() && filled($identity->json('category'))) {
            $identityPageId = (string) $identity->json('id');

            if ($identityPageId !== $configuredPageId) {
                throw new RuntimeException(
                    "El Page Access Token pertenece a la página {$identityPageId}, no a {$configuredPageId}.",
                );
            }

            return [
                'page_id' => $identityPageId,
                'access_token' => $providedToken,
            ];
        }

        $accountsResponse = $this->request()
            ->get($this->graphEndpoint($profile, 'me/accounts'), [
                'fields' => 'id,name,tasks,access_token',
                'access_token' => $providedToken,
            ]);
        $this->guardApiAccess($accountsResponse);
        $accounts = $accountsResponse->throw()
            ->collect('data')
            ->filter(fn (mixed $page): bool => is_array($page)
                && filled($page['id'] ?? null)
                && filled($page['access_token'] ?? null))
            ->values();

        $page = $accounts->first(
            fn (array $candidate): bool => (string) $candidate['id'] === $configuredPageId,
        );

        if (! $page && $accounts->count() === 1) {
            $page = $accounts->first();
        }

        if (! $page) {
            $available = $accounts
                ->map(fn (array $candidate): string => ($candidate['name'] ?? 'Página').' ('.$candidate['id'].')')
                ->implode(', ');

            throw new RuntimeException($available !== ''
                ? "El ID configurado no coincide con las páginas administradas por el token. Disponibles: {$available}."
                : 'El token no devolvió ninguna página administrable. Autoriza pages_show_list y pages_manage_posts.');
        }

        $tasks = $page['tasks'] ?? [];

        if (! in_array('CREATE_CONTENT', $tasks, true) && ! in_array('MANAGE', $tasks, true)) {
            throw new RuntimeException('El token de la página no tiene la tarea CREATE_CONTENT necesaria para publicar.');
        }

        return [
            'page_id' => (string) $page['id'],
            'access_token' => (string) $page['access_token'],
        ];
    }

    private function request(): PendingRequest
    {
        return Http::timeout(90)
            ->connectTimeout(15)
            ->acceptJson();
    }

    private function guardApiAccess(Response $response): void
    {
        if (strtolower((string) $response->json('error.message')) !== 'api access blocked.') {
            return;
        }

        throw new RuntimeException(
            'Meta bloqueó el acceso API para la aplicación asociada al token. Revisa en Meta Developers que la app esté activa y sin restricciones, y que la cuenta o el negocio no tengan una verificación pendiente. Generar otro token de la misma app no elimina este bloqueo.',
        );
    }

    private function endpoint(WordPressSite $profile, ?string $edge = null, ?string $pageId = null): string
    {
        $pageId ??= trim((string) $profile->facebook_page_id);
        $path = $edge ? "{$pageId}/{$edge}" : $pageId;

        return $this->graphEndpoint($profile, $path);
    }

    private function graphEndpoint(WordPressSite $profile, string $path): string
    {
        $version = $profile->facebook_api_version ?: 'v24.0';

        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
    }
}
