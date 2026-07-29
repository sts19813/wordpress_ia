<?php

namespace App\Services\QuickPosts;

use App\Support\SocialPostUrl;
use RuntimeException;
use Symfony\Component\Process\Process;

class BrowserSocialPostExtractor
{
    public function __construct(
        private readonly XPublicPostExtractor $x,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(string $url): array
    {
        if (SocialPostUrl::platform($url) === 'x') {
            return $this->x->extract($url);
        }

        $result = $this->runBrowser($url);

        if ($this->isFacebookLogin($result)) {
            $result = $this->extractFromFacebookEmbed($result);
        }

        $text = trim((string) ($result['text'] ?? ''));

        if (mb_strlen($text) < 40) {
            throw new RuntimeException('La publicación no expuso contenido visible. Confirma que sea pública.');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function runBrowser(string $url): array
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

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isFacebookLogin(array $result): bool
    {
        $finalUrl = (string) ($result['final_url'] ?? '');

        return SocialPostUrl::platform($finalUrl) === 'facebook'
            && str_starts_with((string) parse_url($finalUrl, PHP_URL_PATH), '/login/');
    }

    /**
     * @param  array<string, mixed>  $loginResult
     * @return array<string, mixed>
     */
    private function extractFromFacebookEmbed(array $loginResult): array
    {
        $loginUrl = (string) ($loginResult['final_url'] ?? '');
        parse_str((string) parse_url($loginUrl, PHP_URL_QUERY), $query);
        $nextUrl = (string) ($query['next'] ?? '');

        if ($nextUrl === '' || SocialPostUrl::platform($nextUrl) !== 'facebook') {
            throw new RuntimeException('Facebook redirigió al inicio de sesión y no expuso la URL pública del post.');
        }

        $canonicalUrl = SocialPostUrl::canonicalize($nextUrl);
        $embedUrl = 'https://www.facebook.com/plugins/post.php?'.http_build_query([
            'href' => $canonicalUrl,
            'show_text' => 'true',
            'width' => 500,
        ], '', '&', PHP_QUERY_RFC3986);
        $result = $this->runBrowser($embedUrl);
        $text = trim((string) ($result['text'] ?? ''));

        if (str_contains(mb_strtolower($text), 'ya no está disponible')) {
            throw new RuntimeException('Facebook no permitió abrir la versión pública de esta publicación.');
        }

        $result['capture_url'] = $embedUrl;
        $result['final_url'] = $canonicalUrl;
        $result['canonical_url'] = $canonicalUrl;

        return $result;
    }
}
