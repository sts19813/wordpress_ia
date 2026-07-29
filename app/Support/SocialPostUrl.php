<?php

namespace App\Support;

use InvalidArgumentException;

class SocialPostUrl
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HOSTS = [
        'facebook' => ['facebook.com', 'fb.watch'],
        'x' => ['x.com', 'twitter.com', 't.co'],
        'instagram' => ['instagram.com'],
    ];

    public static function platform(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (self::HOSTS as $platform => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return $platform;
                }
            }
        }

        throw new InvalidArgumentException('Solo se admiten publicaciones públicas de Facebook, X e Instagram.');
    }

    public static function validate(string $url): string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException('Ingresa una URL https válida.');
        }

        $platform = self::platform($url);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        $valid = match ($platform) {
            'facebook' => str_contains($path, '/share/')
                || str_contains($path, '/posts/')
                || str_contains($path, '/reel/')
                || str_contains($path, '/videos/')
                || str_contains($path, '/photos/')
                || (str_ends_with($path, '/story.php') && str_contains($query, 'story_fbid=')),
            'x' => str_contains($path, '/status/') || in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['t.co', 'www.t.co'], true),
            'instagram' => preg_match('#/(p|reel|tv)/[^/]+#i', $path) === 1,
            default => false,
        };

        if (! $valid) {
            throw new InvalidArgumentException('La URL corresponde a la red social, pero no parece ser una publicación individual.');
        }

        return $platform;
    }

    public static function canonicalize(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $platform = self::platform($url);
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);

        $query = match ($platform) {
            'facebook' => array_intersect_key($query, array_flip(['story_fbid', 'id', 'fbid'])),
            default => [],
        };

        return 'https://'.$host.$path.($query ? '?'.http_build_query($query) : '');
    }

    public static function isAllowedMediaUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = [
            'fbcdn.net',
            'cdninstagram.com',
            'twimg.com',
            'facebook.com',
            'instagram.com',
            'x.com',
            'twitter.com',
        ];

        return collect($allowed)->contains(
            fn (string $domain) => $host === $domain || str_ends_with($host, '.'.$domain),
        );
    }
}
