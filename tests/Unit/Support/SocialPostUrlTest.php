<?php

namespace Tests\Unit\Support;

use App\Support\SocialPostUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SocialPostUrlTest extends TestCase
{
    #[DataProvider('supportedUrls')]
    public function test_it_recognizes_supported_social_post_urls(string $url, string $platform): void
    {
        $this->assertSame($platform, SocialPostUrl::validate($url));
    }

    public static function supportedUrls(): array
    {
        return [
            ['https://www.facebook.com/share/p/1Zvt11XRZJ/?mibextid=wwXIfr', 'facebook'],
            ['https://www.facebook.com/story.php?story_fbid=123&id=456', 'facebook'],
            ['https://x.com/openai/status/123456', 'x'],
            ['https://twitter.com/openai/status/123456?s=20', 'x'],
            ['https://www.instagram.com/p/ABC123/', 'instagram'],
            ['https://www.instagram.com/reel/ABC123/', 'instagram'],
        ];
    }

    public function test_it_rejects_non_social_and_non_post_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SocialPostUrl::validate('https://example.com/private');
    }

    public function test_it_removes_tracking_parameters_from_facebook_canonical_urls(): void
    {
        $canonical = SocialPostUrl::canonicalize(
            'https://www.facebook.com/story.php?story_fbid=123&id=456&mibextid=abc&rdid=def',
        );

        $this->assertSame(
            'https://www.facebook.com/story.php?story_fbid=123&id=456',
            $canonical,
        );
    }
}
