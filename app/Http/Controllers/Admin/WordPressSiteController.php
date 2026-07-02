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
                'status' => WordPressSite::STATUS_ACTIVE,
                'active' => true,
            ]),
        ]);
    }

    public function store(WordPressSiteRequest $request): RedirectResponse
    {
        $site = $request->user()->wordpressSites()->create([
            ...$request->validated(),
            'status' => WordPressSite::STATUS_ACTIVE,
        ]);

        return $this->testAndRedirect($site, 'Sitio WordPress guardado y conectado correctamente.');
    }

    public function edit(WordPressSite $wordpressSite): View
    {
        Gate::authorize('update', $wordpressSite);

        return view('admin.wordpress-sites.edit', ['site' => $wordpressSite]);
    }

    public function update(WordPressSiteRequest $request, WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('update', $wordpressSite);
        $data = $request->validated();

        if (blank($data['application_password'] ?? null)) {
            unset($data['application_password']);
        }

        $wordpressSite->update($data);

        return $this->testAndRedirect($wordpressSite, 'Sitio WordPress actualizado y conectado correctamente.');
    }

    public function test(WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('update', $wordpressSite);

        return $this->testAndRedirect($wordpressSite, 'Conexión verificada correctamente. El sitio está listo para publicar.');
    }

    public function destroy(WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('delete', $wordpressSite);
        $wordpressSite->delete();

        return redirect()->route('admin.wordpress-sites.index')->with('status', 'Sitio WordPress eliminado. El historial de publicaciones se conservó.');
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
            $remoteMessage = $exception instanceof RequestException ? $exception->response?->json('message') : null;
            $message = is_string($remoteMessage) && $remoteMessage !== ''
                ? $remoteMessage
                : 'No pudimos autenticar el sitio. Verifica el dominio, el usuario y la contraseña de aplicación.';

            $site->update([
                'status' => WordPressSite::STATUS_ERROR,
                'last_tested_at' => now(),
                'connection_error' => $message,
            ]);

            return redirect()->route('admin.wordpress-sites.edit', $site)
                ->with('warning', 'El sitio quedó guardado, pero la prueba de conexión falló. '.$message);
        }
    }
}
