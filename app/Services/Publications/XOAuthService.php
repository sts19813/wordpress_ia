<?php

namespace App\Services\Publications;

use App\Models\WordPressSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class XOAuthService
{
    public const SCOPES = 'tweet.read users.read tweet.write media.write offline.access';

    public function authorizationUrl(WordPressSite $profile, string $state, string $codeChallenge): string
    {
        return 'https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $profile->x_client_id,
            'redirect_uri' => route('x-oauth.callback'),
            'scope' => self::SCOPES,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    public function exchangeCode(WordPressSite $profile, string $code, string $codeVerifier): Response
    {
        return $this->tokenRequest($profile)
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('x-oauth.callback'),
                'code_verifier' => $codeVerifier,
                'client_id' => $profile->x_client_id,
            ])
            ->throw();
    }

    public function refresh(WordPressSite $profile): string
    {
        $response = $this->tokenRequest($profile)
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $profile->x_refresh_token,
                'client_id' => $profile->x_client_id,
            ])
            ->throw();

        $this->storeTokens($profile, $response);

        return (string) $profile->fresh()->x_access_token;
    }

    public function storeTokens(WordPressSite $profile, Response $response): void
    {
        $profile->update([
            'x_access_token' => $response->json('access_token'),
            'x_refresh_token' => $response->json('refresh_token') ?: $profile->x_refresh_token,
            'x_token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 7200))),
        ]);
    }

    private function tokenRequest(WordPressSite $profile): PendingRequest
    {
        return Http::timeout(90)
            ->connectTimeout(15)
            ->acceptJson()
            ->asForm()
            ->withBasicAuth((string) $profile->x_client_id, (string) $profile->x_client_secret);
    }
}
