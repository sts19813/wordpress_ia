<?php

namespace App\Services\OpenAI;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AudioTranscriptionService
{
    public function transcribe(UploadedFile $audio): string
    {
        $apiKey = trim((string) config('services.openai.api_key'));
        $path = $audio->getRealPath();

        if ($apiKey === '') {
            throw new RuntimeException('Falta configurar OPENAI_API_KEY en el archivo .env.');
        }

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new RuntimeException('No fue posible leer el archivo de audio.');
        }

        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException('No fue posible abrir el archivo de audio.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/'))
                ->withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.openai.connect_timeout', 15))
                ->timeout((int) config('services.openai.timeout', 180))
                ->attach('file', $stream, $audio->getClientOriginalName())
                ->post('/audio/transcriptions', [
                    'model' => config('services.openai.transcription_model', 'gpt-transcribe'),
                ]);
        } finally {
            fclose($stream);
        }

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'No fue posible transcribir el audio.');
        }

        $text = trim((string) $response->json('text'));

        if ($text === '') {
            throw new RuntimeException('La transcripción no contiene texto.');
        }

        return $text;
    }
}
