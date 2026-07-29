<?php

namespace Tests\Feature\QuickPosts;

use App\Services\QuickPosts\BrowserSocialPostExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XPublicPostExtractorTest extends TestCase
{
    public function test_x_posts_use_the_lightweight_public_capture_with_all_original_images(): void
    {
        $url = 'https://x.com/Claudiashein/status/2081496337636372887?s=20';
        $canonicalUrl = 'https://x.com/Claudiashein/status/2081496337636372887';
        Http::fake([
            $canonicalUrl => Http::response(<<<'HTML'
                <!doctype html>
                <html lang="es">
                <head>
                    <meta property="og:title" content="Claudia Sheinbaum Pardo (@Claudiashein) on X">
                    <meta property="og:description" content="Texto público del post">
                    <meta property="og:image" content="https://pbs.twimg.com/media/IMAGE_ONE.jpg:large">
                    <meta property="og:image:width" content="2048">
                    <meta property="og:image:height" content="1366">
                </head>
                <body>
                    <article data-tweet-id="2081496337636372887">
                        <img src="https://pbs.twimg.com/media/IMAGE_TWO?format=webp&amp;name=small">
                    </article>
                    <article data-tweet-id="999">
                        <img src="https://pbs.twimg.com/media/REPLY_IMAGE?format=webp&amp;name=small">
                    </article>
                </body>
                </html>
                HTML),
            'https://publish.x.com/oembed*' => Http::response([
                'url' => $canonicalUrl,
                'author_name' => 'Claudia Sheinbaum Pardo',
                'author_url' => 'https://x.com/Claudiashein',
                'html' => '<blockquote><p>En Acapulco entregamos tarjetas de Pensión Mujeres Bienestar.</p></blockquote>',
            ]),
        ]);

        $capture = app(BrowserSocialPostExtractor::class)->extract($url);

        $this->assertSame($canonicalUrl, $capture['final_url']);
        $this->assertSame('En Acapulco entregamos tarjetas de Pensión Mujeres Bienestar.', $capture['text']);
        $this->assertSame('Claudia Sheinbaum Pardo', $capture['meta']['author_name']);
        $this->assertSame([
            'https://pbs.twimg.com/media/IMAGE_ONE?format=jpg&name=large',
            'https://pbs.twimg.com/media/IMAGE_TWO?format=jpg&name=large',
        ], array_column($capture['images'], 'url'));
        Http::assertSentCount(2);
    }
}
