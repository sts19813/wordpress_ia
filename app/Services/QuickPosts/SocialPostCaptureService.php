<?php

namespace App\Services\QuickPosts;

use App\Models\SourcePost;
use App\Support\SocialPostUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SocialPostCaptureService
{
    public function __construct(
        private readonly BrowserSocialPostExtractor $browser,
        private readonly SocialPostAnalyzer $analyzer,
        private readonly SourcePostMediaArchiver $media,
    ) {}

    public function capture(string $url): SourcePost
    {
        $platform = SocialPostUrl::validate($url);
        $capture = $this->browser->extract($url);
        $finalUrl = (string) ($capture['final_url'] ?? $capture['canonical_url'] ?? $url);
        SocialPostUrl::platform($finalUrl);
        $canonicalUrl = SocialPostUrl::canonicalize($finalUrl);
        $analysis = $this->analyzer->analyze($url, $platform, $capture);
        $hash = hash('sha256', 'quick-post|'.$canonicalUrl);
        $publishedAt = $this->publishedAt($analysis['published_at'] ?? null);
        $title = str(trim((string) ($analysis['title'] ?? 'Publicación social')))
            ->limit(250, '')
            ->toString();
        $content = trim((string) $analysis['content']);

        $sourcePost = DB::transaction(function () use (
            $url,
            $canonicalUrl,
            $platform,
            $capture,
            $analysis,
            $hash,
            $publishedAt,
            $title,
            $content,
        ): SourcePost {
            $sourcePost = SourcePost::query()
                ->where('hash', $hash)
                ->orWhere('canonical_url', $canonicalUrl)
                ->firstOrNew();

            $sourcePost->fill([
                'source_site_id' => null,
                'origin_type' => SourcePost::ORIGIN_QUICK_POST,
                'social_platform' => $platform,
                'title' => $title,
                'content' => $content,
                'content_html' => nl2br(e($content)),
                'summary' => str($content)->squish()->limit(500)->toString(),
                'author' => $analysis['author'] ?? null,
                'published_at' => $publishedAt,
                'image_url' => data_get($capture, 'images.0.url'),
                'categories' => [$platform],
                'tags' => collect($analysis['hashtags'] ?? [])
                    ->map(fn (mixed $tag) => ltrim(trim((string) $tag), '#'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'url' => $canonicalUrl,
                'canonical_url' => $canonicalUrl,
                'hash' => $hash,
                'status' => SourcePost::STATUS_FETCHED,
                'original_json' => [
                    'requested_url' => $url,
                    'capture' => $capture,
                    'analysis' => $analysis,
                ],
                'language' => str((string) ($analysis['language'] ?? $capture['html_language'] ?? 'es'))
                    ->lower()
                    ->limit(10, '')
                    ->toString(),
                'filter_applies' => true,
                'filter_reason' => 'Importación manual desde Post rápido.',
                'matched_topics' => [],
                'filter_method' => 'quick_post',
                'scanned_at' => now(),
                'captured_at' => now(),
            ]);
            $sourcePost->save();

            return $sourcePost;
        });

        $this->media->archive($sourcePost, $capture['images'] ?? []);

        return $sourcePost->fresh('media');
    }

    private function publishedAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
