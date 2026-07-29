<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WordPressSiteRequest;
use App\Models\WordPressSite;
use App\Services\PublicationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class WordPressSiteController extends Controller
{
    public function __construct(
        private readonly PublicationService $publications,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WordPressSite::class);

        return view('admin.wordpress-sites.index', [
            'sites' => $request->user()->wordpressSites()
                ->withCount('publications')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', WordPressSite::class);

        return view('admin.wordpress-sites.create', [
            'site' => new WordPressSite([
                'type' => WordPressSite::TYPE_WORDPRESS,
                'facebook_api_version' => 'v24.0',
                'status' => WordPressSite::STATUS_ACTIVE,
                'active' => true,
            ]),
        ]);
    }

    public function store(WordPressSiteRequest $request): RedirectResponse
    {
        $site = $request->user()->wordpressSites()->create([
            ...$this->normalizedData($request->validated()),
            'status' => WordPressSite::STATUS_ACTIVE,
        ]);

        return $this->testAndRedirect($site, 'Perfil de publicación guardado y conectado correctamente.');
    }

    public function edit(WordPressSite $wordpressSite): View
    {
        Gate::authorize('update', $wordpressSite);

        return view('admin.wordpress-sites.edit', ['site' => $wordpressSite]);
    }

    public function update(WordPressSiteRequest $request, WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('update', $wordpressSite);
        $data = $this->normalizedData($request->validated());

        if (($data['type'] ?? null) === WordPressSite::TYPE_WORDPRESS
            && blank($data['application_password'] ?? null)) {
            unset($data['application_password']);
        }

        if (($data['type'] ?? null) === WordPressSite::TYPE_FACEBOOK_PAGE
            && blank($data['facebook_access_token'] ?? null)) {
            unset($data['facebook_access_token']);
        }

        $wordpressSite->update($data);

        return $this->testAndRedirect($wordpressSite, 'Perfil de publicación actualizado y conectado correctamente.');
    }

    public function test(WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('update', $wordpressSite);

        return $this->testAndRedirect($wordpressSite, 'Conexión verificada correctamente. El perfil está listo para publicar.');
    }

    public function destroy(WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('delete', $wordpressSite);
        $wordpressSite->delete();

        return redirect()->route('admin.wordpress-sites.index')->with('status', 'Perfil de publicación eliminado. El historial de publicaciones se conservó.');
    }

    private function testAndRedirect(WordPressSite $site, string $successMessage): RedirectResponse
    {
        try {
            $this->publications->testConnection($site);
            $site->update([
                'status' => $site->active ? WordPressSite::STATUS_ACTIVE : WordPressSite::STATUS_PAUSED,
                'last_tested_at' => now(),
                'connection_error' => null,
            ]);

            return redirect()->route('admin.wordpress-sites.index')->with('status', $successMessage);
        } catch (Throwable $exception) {
            $remoteMessage = $exception instanceof RequestException
                ? ($exception->response?->json('error.message') ?: $exception->response?->json('message'))
                : null;
            $message = is_string($remoteMessage) && $remoteMessage !== ''
                ? $remoteMessage
                : ($site->isFacebookPage()
                    ? 'No pudimos autenticar la página. Verifica el ID, el Page Access Token y sus permisos.'
                    : 'No pudimos autenticar el sitio. Verifica el dominio, el usuario y la contraseña de aplicación.');

            $site->update([
                'status' => WordPressSite::STATUS_ERROR,
                'last_tested_at' => now(),
                'connection_error' => $message,
            ]);

            return redirect()->route('admin.wordpress-sites.edit', $site)
                ->with('warning', 'El perfil quedó guardado, pero la prueba de conexión falló. '.$message);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedData(array $data): array
    {
        if (($data['type'] ?? null) === WordPressSite::TYPE_FACEBOOK_PAGE) {
            return [
                ...$data,
                'rest_api_url' => null,
                'username' => null,
                'application_password' => null,
            ];
        }

        return [
            ...$data,
            'facebook_page_id' => null,
            'facebook_access_token' => null,
            'facebook_api_version' => 'v24.0',
        ];
    }
}
