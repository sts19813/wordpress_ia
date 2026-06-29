<?php

namespace App\Services\Publications;

use App\Models\WordPressSite;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WordPressRestClient
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(WordPressSite $site, string $path, array $payload): Response
    {
        return $this->request($site)->post($site->endpoint($path), $payload)->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(WordPressSite $site, string $path, array $payload): Response
    {
        return $this->request($site)->put($site->endpoint($path), $payload)->throw();
    }

    public function delete(WordPressSite $site, string $path): Response
    {
        return $this->request($site)->delete($site->endpoint($path))->throw();
    }

    public function uploadMedia(WordPressSite $site, string $contents, string $filename, string $mimeType): Response
    {
        return $this->request($site)
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Type' => $mimeType,
            ])
            ->withBody($contents, $mimeType)
            ->post($site->endpoint('/wp-json/wp/v2/media'))
            ->throw();
    }

    private function request(WordPressSite $site): PendingRequest
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->acceptJson()
            ->withBasicAuth($site->username, $site->application_password);
    }
}
