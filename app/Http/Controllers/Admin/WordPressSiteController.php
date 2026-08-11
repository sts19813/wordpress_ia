<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WordPressSiteRequest;
use App\Models\Company;
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
            'sites' => $request->user()->accessibleWordPressSites()
                ->with(['company:id,name', 'user:id,name,email'])
                ->withCount('publications')
                ->latest()
                ->get(),
            'companies' => $request->user()->accessibleCompanies()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', WordPressSite::class);
        $defaultCompany = $this->ensureCompany($request);

        return view('admin.wordpress-sites.create', [
            'site' => new WordPressSite([
                'company_id' => (int) $request->query('company', $defaultCompany->id),
                'type' => WordPressSite::TYPE_WORDPRESS,
                'facebook_api_version' => 'v24.0',
                'instagram_api_version' => 'v24.0',
                'status' => WordPressSite::STATUS_ACTIVE,
                'active' => true,
            ]),
            'companies' => $request->user()->companies()->where('active', true)->orderBy('name')->get(),
            'returnCompany' => $this->returnCompany($request),
        ]);
    }

    public function store(WordPressSiteRequest $request): RedirectResponse
    {
        $data = $this->normalizedData($request->validated());
        $data['company_id'] ??= $this->ensureCompany($request)->id;
        if (($data['type'] ?? null) === WordPressSite::TYPE_X && filled($data['x_access_token'] ?? null)) {
            $data['x_token_expires_at'] = now()->addHours(2);
        }
        $requiresXAuthorization = ($data['type'] ?? null) === WordPressSite::TYPE_X
            && blank($data['x_access_token'] ?? null);
        $site = $request->user()->wordpressSites()->create([
            ...$data,
            'status' => $requiresXAuthorization ? WordPressSite::STATUS_PAUSED : WordPressSite::STATUS_ACTIVE,
        ]);

        if ($requiresXAuthorization) {
            $returnCompany = $this->returnCompany($request);

            return redirect()->route('admin.x-oauth.redirect', [
                'wordpressSite' => $site,
                ...($returnCompany ? ['return_company' => $returnCompany->id] : []),
            ]);
        }

        return $this->testAndRedirect(
            $site,
            'Perfil de publicación guardado y conectado correctamente.',
            $this->returnCompany($request),
        );
    }

    public function edit(Request $request, WordPressSite $wordpressSite): View
    {
        Gate::authorize('update', $wordpressSite);

        return view('admin.wordpress-sites.edit', [
            'site' => $wordpressSite,
            'companies' => $wordpressSite->user->companies()
                ->where(fn ($query) => $query->where('active', true)->orWhere('id', $wordpressSite->company_id))
                ->orderBy('name')
                ->get(),
            'returnCompany' => $this->returnCompany($request),
        ]);
    }

    public function update(WordPressSiteRequest $request, WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('update', $wordpressSite);
        $data = $this->normalizedData($request->validated());
        $submittedXAccessToken = filled($data['x_access_token'] ?? null);

        if (($data['type'] ?? null) === WordPressSite::TYPE_X && $submittedXAccessToken) {
            $data['x_token_expires_at'] = now()->addHours(2);
        }

        if (($data['type'] ?? null) === WordPressSite::TYPE_WORDPRESS
            && blank($data['application_password'] ?? null)) {
            unset($data['application_password']);
        }

        if (($data['type'] ?? null) === WordPressSite::TYPE_FACEBOOK_PAGE
            && blank($data['facebook_access_token'] ?? null)) {
            unset($data['facebook_access_token']);
        }

        if (($data['type'] ?? null) === WordPressSite::TYPE_INSTAGRAM
            && blank($data['instagram_access_token'] ?? null)) {
            unset($data['instagram_access_token']);
        }

        if (($data['type'] ?? null) === WordPressSite::TYPE_X
            && blank($data['x_access_token'] ?? null)) {
            unset($data['x_access_token']);
        }

        if (($data['type'] ?? null) === WordPressSite::TYPE_X
            && blank($data['x_refresh_token'] ?? null)) {
            unset($data['x_refresh_token']);
        }

        if (($data['type'] ?? null) === WordPressSite::TYPE_X
            && blank($data['x_client_secret'] ?? null)) {
            unset($data['x_client_secret']);
        }

        $wordpressSite->update($data);

        if ($wordpressSite->isX()
            && (($wordpressSite->wasChanged('x_client_id') && ! $submittedXAccessToken)
                || blank($wordpressSite->x_access_token))) {
            $wordpressSite->update([
                'x_access_token' => null,
                'x_refresh_token' => null,
                'x_token_expires_at' => null,
                'status' => WordPressSite::STATUS_PAUSED,
            ]);

            $returnCompany = $this->returnCompany($request);

            return redirect()->route('admin.x-oauth.redirect', [
                'wordpressSite' => $wordpressSite,
                ...($returnCompany ? ['return_company' => $returnCompany->id] : []),
            ]);
        }

        return $this->testAndRedirect(
            $wordpressSite,
            'Perfil de publicación actualizado y conectado correctamente.',
            $this->returnCompany($request),
        );
    }

    public function test(Request $request, WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('update', $wordpressSite);

        if ($wordpressSite->isX() && blank($wordpressSite->x_access_token)) {
            $returnCompany = $this->returnCompany($request);

            return redirect()->route('admin.x-oauth.redirect', [
                'wordpressSite' => $wordpressSite,
                ...($returnCompany ? ['return_company' => $returnCompany->id] : []),
            ]);
        }

        return $this->testAndRedirect(
            $wordpressSite,
            'Conexión verificada correctamente. El perfil está listo para publicar.',
            $this->returnCompany($request),
            true,
        );
    }

    public function destroy(WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('delete', $wordpressSite);
        $wordpressSite->delete();

        return redirect()->route('admin.wordpress-sites.index')->with('status', 'Perfil de publicación eliminado. El historial de publicaciones se conservó.');
    }

    private function testAndRedirect(
        WordPressSite $site,
        string $successMessage,
        ?Company $returnCompany = null,
        bool $returnToCompanyOnFailure = false,
    ): RedirectResponse {
        try {
            $connection = $this->publications->testConnection($site);
            $updates = [
                'status' => $site->active ? WordPressSite::STATUS_ACTIVE : WordPressSite::STATUS_PAUSED,
                'last_tested_at' => now(),
                'connection_error' => null,
            ];

            if ($site->isFacebookPage()) {
                $updates['facebook_page_id'] = $connection['facebook_page_id'];
                $updates['facebook_access_token'] = $connection['facebook_access_token'];
            }

            if ($site->isInstagram()) {
                $updates['instagram_account_id'] = $connection['instagram_account_id'];
            }

            if ($site->isX()) {
                $updates['x_user_id'] = $connection['x_user_id'];
                $updates['x_username'] = $connection['x_username'];
            }

            $site->update($updates);

            return $this->profilesRedirect($returnCompany)->with('status', $successMessage);
        } catch (Throwable $exception) {
            $remoteMessage = $exception instanceof RequestException
                ? ($exception->response?->json('error.message')
                    ?: $exception->response?->json('message')
                    ?: $exception->response?->json('detail')
                    ?: $exception->response?->json('errors.0.detail'))
                : null;
            $exceptionMessage = trim($exception->getMessage());
            $message = is_string($remoteMessage) && $remoteMessage !== ''
                ? $remoteMessage
                : ($site->isSocial() && $exceptionMessage !== ''
                    ? $exceptionMessage
                    : ($site->isSocial()
                        ? 'No pudimos autenticar el perfil social. Verifica la cuenta, el token y sus permisos.'
                        : 'No pudimos autenticar el sitio. Verifica el dominio, el usuario y la contraseña de aplicación.'));

            $site->update([
                'status' => WordPressSite::STATUS_ERROR,
                'last_tested_at' => now(),
                'connection_error' => $message,
            ]);

            $redirect = $returnCompany && $returnToCompanyOnFailure
                ? $this->profilesRedirect($returnCompany)
                : redirect()->route('admin.wordpress-sites.edit', [
                    'wordpressSite' => $site,
                    ...($returnCompany ? ['return_company' => $returnCompany->id] : []),
                ]);

            return $redirect->with('warning', 'El perfil quedó guardado, pero la prueba de conexión falló. '.$message);
        }
    }

    private function returnCompany(Request $request): ?Company
    {
        $companyId = (int) $request->input('return_company_id', $request->query('return_company'));

        return $companyId > 0
            ? $request->user()->accessibleCompanies()->find($companyId)
            : null;
    }

    private function profilesRedirect(?Company $company): RedirectResponse
    {
        return $company
            ? redirect()->route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos'])
            : redirect()->route('admin.wordpress-sites.index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedData(array $data): array
    {
        $type = $data['type'] ?? WordPressSite::TYPE_WORDPRESS;
        $normalized = [
            ...$data,
            'rest_api_url' => $type === WordPressSite::TYPE_WORDPRESS ? ($data['rest_api_url'] ?? null) : null,
            'username' => $type === WordPressSite::TYPE_WORDPRESS ? ($data['username'] ?? null) : null,
            'application_password' => $type === WordPressSite::TYPE_WORDPRESS ? ($data['application_password'] ?? null) : null,
            'facebook_page_id' => null,
            'facebook_access_token' => null,
            'facebook_api_version' => 'v24.0',
            'instagram_account_id' => null,
            'instagram_access_token' => null,
            'instagram_api_version' => 'v24.0',
            'x_user_id' => null,
            'x_username' => null,
            'x_client_id' => null,
            'x_client_secret' => null,
            'x_access_token' => null,
            'x_refresh_token' => null,
        ];

        foreach (match ($type) {
            WordPressSite::TYPE_FACEBOOK_PAGE => ['facebook_page_id', 'facebook_access_token', 'facebook_api_version'],
            WordPressSite::TYPE_INSTAGRAM => ['instagram_account_id', 'instagram_access_token', 'instagram_api_version'],
            WordPressSite::TYPE_X => ['x_username', 'x_client_id', 'x_client_secret', 'x_access_token', 'x_refresh_token'],
            default => [],
        } as $field) {
            $normalized[$field] = $data[$field] ?? null;
        }

        return $normalized;
    }

    private function ensureCompany(Request $request): Company
    {
        $companies = $request->user()->companies();
        $requestedCompanyId = (int) $request->input('company_id', $request->query('company'));

        if ($requestedCompanyId && ($requested = (clone $companies)->find($requestedCompanyId))) {
            return $requested;
        }

        return (clone $companies)->where('active', true)->orderBy('name')->first()
            ?: (clone $companies)->orderBy('name')->first()
            ?: $companies->create([
                'name' => 'Empresa principal',
                'description' => 'Empresa predeterminada para tus perfiles de publicación.',
                'active' => true,
            ]);
    }
}
