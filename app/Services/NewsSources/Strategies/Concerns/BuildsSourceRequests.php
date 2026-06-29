<?php

namespace App\Services\NewsSources\Strategies\Concerns;

use App\Models\SourceSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait BuildsSourceRequests
{
    protected function requestFor(SourceSite $sourceSite): PendingRequest
    {
        $request = Http::timeout(25)
            ->connectTimeout(10)
            ->accept('*/*')
            ->withHeaders($sourceSite->custom_headers ?: []);

        if ($sourceSite->cookies) {
            $request = $request->withCookies($sourceSite->cookies, parse_url($sourceSite->url, PHP_URL_HOST) ?: '');
        }

        return match ($sourceSite->auth_method) {
            SourceSite::AUTH_BASIC => $request->withBasicAuth((string) $sourceSite->username, (string) $sourceSite->password),
            SourceSite::AUTH_BEARER => $request->withToken((string) $sourceSite->api_key),
            SourceSite::AUTH_API_KEY => $request->withHeader('X-API-Key', (string) $sourceSite->api_key),
            default => $request,
        };
    }
}
