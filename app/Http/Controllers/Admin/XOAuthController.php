<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WordPressSite;
use App\Services\Publications\XOAuthService;
use App\Services\PublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class XOAuthController extends Controller
{
    public function __construct(
        private readonly XOAuthService $oauth,
        private readonly PublicationService $publications,
    ) {}

    public function redirect(Request $request, WordPressSite $wordpressSite): RedirectResponse
    {
        Gate::authorize('update', $wordpressSite);
        abort_unless($wordpressSite->isX(), 404);
        abort_unless(filled($wordpressSite->x_client_id) && filled($wordpressSite->x_client_secret), 422, 'Guarda primero el Client ID y Client Secret.');

        $state = Str::random(64);
        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $request->session()->put('x_oauth', [
            'profile_id' => $wordpressSite->id,
            'state' => $state,
            'verifier' => $verifier,
            'return_company_id' => $this->returnCompany($request)?->id,
        ]);

        return redirect()->away($this->oauth->authorizationUrl($wordpressSite, $state, $challenge));
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('x_oauth');
        $returnCompany = is_array($pending) && filled($pending['return_company_id'] ?? null)
            ? $request->user()->companies()->find((int) $pending['return_company_id'])
            : null;

        if (! is_array($pending)
            || blank($pending['state'] ?? null)
            || ! hash_equals((string) $pending['state'], (string) $request->query('state'))) {
            return $this->profilesRedirect($returnCompany)
                ->with('warning', 'La autorización de X expiró o no es válida. Intenta conectarla nuevamente.');
        }

        $profile = $request->user()->wordpressSites()->findOrFail($pending['profile_id']);

        if ($request->filled('error')) {
            return redirect()->route('admin.wordpress-sites.edit', [
                'wordpressSite' => $profile,
                ...($returnCompany ? ['return_company' => $returnCompany->id] : []),
            ])
                ->with('warning', 'X no autorizó la conexión: '.($request->query('error_description') ?: $request->query('error')));
        }

        try {
            $tokens = $this->oauth->exchangeCode(
                $profile,
                (string) $request->query('code'),
                (string) $pending['verifier'],
            );
            $this->oauth->storeTokens($profile, $tokens);
            $connection = $this->publications->testConnection($profile->fresh());
            $profile->update([
                'x_user_id' => $connection['x_user_id'],
                'x_username' => $connection['x_username'],
                'status' => $profile->active ? WordPressSite::STATUS_ACTIVE : WordPressSite::STATUS_PAUSED,
                'last_tested_at' => now(),
                'connection_error' => null,
            ]);

            return $this->profilesRedirect($returnCompany)
                ->with('status', 'Cuenta de X conectada correctamente como @'.$connection['x_username'].'.');
        } catch (Throwable $exception) {
            $profile->update([
                'status' => WordPressSite::STATUS_ERROR,
                'last_tested_at' => now(),
                'connection_error' => $exception->getMessage(),
            ]);

            return redirect()->route('admin.wordpress-sites.edit', [
                'wordpressSite' => $profile,
                ...($returnCompany ? ['return_company' => $returnCompany->id] : []),
            ])
                ->with('warning', 'No se pudo completar la conexión con X. '.$exception->getMessage());
        }
    }

    private function returnCompany(Request $request): ?Company
    {
        $companyId = (int) $request->query('return_company');

        return $companyId > 0
            ? $request->user()->companies()->find($companyId)
            : null;
    }

    private function profilesRedirect(?Company $company): RedirectResponse
    {
        return $company
            ? redirect()->route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos'])
            : redirect()->route('admin.wordpress-sites.index');
    }
}
