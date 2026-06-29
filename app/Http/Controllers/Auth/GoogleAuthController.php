<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'email' => 'No fue posible completar el acceso con Google. Inténtalo de nuevo.',
            ]);
        }

        if (! $googleUser->getEmail()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google no proporcionó un correo electrónico para esta cuenta.',
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first() ?? new User;

        $user->name = $user->name ?: ($googleUser->getName() ?: $googleUser->getEmail());
        $user->email = $googleUser->getEmail();
        $user->google_id = $googleUser->getId();
        $user->google_avatar_url = $googleUser->getAvatar();
        $user->email_verified_at ??= now();
        $user->password ??= Str::password(32);
        $user->save();

        Auth::login($user, true);

        return redirect()->intended(route('admin.dashboard'));
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function configurationError(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => 'Configura GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET y GOOGLE_REDIRECT_URI para activar este acceso.',
        ]);
    }
}
