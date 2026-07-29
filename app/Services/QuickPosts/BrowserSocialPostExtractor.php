<?php

namespace App\Services\QuickPosts;

use RuntimeException;
use Symfony\Component\Process\Process;

class BrowserSocialPostExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $url): array
    {
        $node = (string) config('services.social_capture.node_binary', 'node');
        $timeout = max(15, (int) config('services.social_capture.browser_timeout', 60));
        $environment = [];

        if (filled(config('services.social_capture.browser_executable'))) {
            $environment['SOCIAL_BROWSER_EXECUTABLE'] = (string) config('services.social_capture.browser_executable');
        }

        if (filled(config('services.social_capture.browser_ws_endpoint'))) {
            $environment['SOCIAL_BROWSER_WS_ENDPOINT'] = (string) config('services.social_capture.browser_ws_endpoint');
        }

        $environment['SOCIAL_BROWSER_TIMEOUT_MS'] = (string) (($timeout - 5) * 1000);

        $process = new Process(
            [$node, base_path('scripts/social-post-scraper.mjs'), $url],
            base_path(),
            $environment,
            null,
            $timeout,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException($message ?: 'El navegador no pudo abrir la publicación.');
        }

        $result = json_decode($process->getOutput(), true);

        if (! is_array($result)) {
            throw new RuntimeException('El navegador devolvió una respuesta que no se pudo interpretar.');
        }

        $text = trim((string) ($result['text'] ?? ''));

        if (mb_strlen($text) < 40) {
            throw new RuntimeException('La publicación no expuso contenido visible. Confirma que sea pública.');
        }

        return $result;
    }
}
