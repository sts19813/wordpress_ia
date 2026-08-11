<?php

namespace App\Services\NewsSources\Strategies\Concerns;

use App\Models\SourceSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

    protected function sourceDocument(SourceSite $sourceSite, ?string $url = null): string
    {
        $url ??= (string) $sourceSite->url;

        try {
            return $this->requestFor($sourceSite)->get($url)->throw()->body();
        } catch (RequestException $exception) {
            if (! $this->isCloudflareChallenge($exception)) {
                throw $exception;
            }
        }

        $markdown = Http::timeout(45)
            ->connectTimeout(10)
            ->accept('text/plain')
            ->withUserAgent('WordPressIA/1.0 (+source-reader)')
            ->get('https://r.jina.ai/'.$url)
            ->throw()
            ->body();

        return '<!doctype html><html><body><main>'
            .Str::markdown($markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])
            .'</main></body></html>';
    }

    private function isCloudflareChallenge(RequestException $exception): bool
    {
        $response = $exception->response;

        if (! $response || $response->status() !== 403) {
            return false;
        }

        $body = strtolower($response->body());

        return str_contains($body, 'just a moment')
            || str_contains($body, 'cf-chl-')
            || str_contains($body, 'cloudflare ray id');
    }
}
