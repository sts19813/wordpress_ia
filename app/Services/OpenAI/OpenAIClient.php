<?php

namespace App\Services\OpenAI;

use App\Services\OpenAI\Data\OpenAIRequest;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIClient
{
    /**
     * @return array<string, mixed>
     */
    public function execute(OpenAIRequest $request): array
    {
        $path = match ($request->capability) {
            'responses' => '/responses',
            'images' => '/images/generations',
            default => throw new RuntimeException("Capacidad de OpenAI no ejecutable [{$request->capability}]."),
        };

        $response = $this->http()->post($path, $request->payload);

        if ($response->failed()) {
            $message = $response->json('error.message') ?: 'OpenAI devolvió una respuesta no válida.';

            throw new RuntimeException($message, $response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('OpenAI devolvió una respuesta vacía o no interpretable.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function outputText(array $response): string
    {
        $direct = data_get($response, 'output_text');

        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $text = collect($response['output'] ?? [])
            ->flatMap(fn (mixed $item) => is_array($item) ? ($item['content'] ?? []) : [])
            ->first(fn (mixed $content) => is_array($content) && ($content['type'] ?? null) === 'output_text');

        if (! is_array($text) || ! is_string($text['text'] ?? null)) {
            throw new RuntimeException('La respuesta de OpenAI no contiene texto generado.');
        }

        return $text['text'];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, int>
     */
    public function usage(array $response): array
    {
        return array_filter([
            'input' => (int) data_get($response, 'usage.input_tokens', 0),
            'output' => (int) data_get($response, 'usage.output_tokens', 0),
            'total' => (int) data_get($response, 'usage.total_tokens', 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function imageBase64(array $response): string
    {
        $encoded = data_get($response, 'data.0.b64_json');

        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('La respuesta de OpenAI no contiene la imagen generada.');
        }

        return $encoded;
    }

    private function http(): PendingRequest
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('Falta configurar OPENAI_API_KEY en el archivo .env.');
        }

        return Http::baseUrl(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.openai.connect_timeout', 15))
            ->timeout((int) config('services.openai.timeout', 180))
            ->retry(2, 500, throw: false);
    }
}
