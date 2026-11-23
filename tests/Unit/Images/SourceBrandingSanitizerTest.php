<?php

namespace Tests\Unit\Images;

use App\Services\Images\SourceBrandingSanitizer;
use Tests\TestCase;

class SourceBrandingSanitizerTest extends TestCase
{
    public function test_it_obscures_only_configured_source_branding_regions_locally(): void
    {
        $binary = $this->sourceImage();

        $result = app(SourceBrandingSanitizer::class)->sanitize(
            $binary,
            'https://www.quintanaroohoy.com/nota',
        );

        $this->assertTrue($result['sanitized']);
        $this->assertSame(['quintanaroohoy.com'], $result['domains']);
        $this->assertSame('image/jpeg', $result['mime_type']);
        $this->assertNotSame($binary, $result['binary']);
    }

    public function test_it_leaves_unconfigured_source_images_untouched(): void
    {
        $binary = $this->sourceImage();

        $result = app(SourceBrandingSanitizer::class)->sanitize(
            $binary,
            'https://example.com/nota',
        );

        $this->assertFalse($result['sanitized']);
        $this->assertSame([], $result['domains']);
        $this->assertSame($binary, $result['binary']);
    }

    private function sourceImage(): string
    {
        $image = imagecreatetruecolor(1000, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 35, 108, 180));

        // A high-contrast pattern represents a branded corner badge.
        for ($x = 760; $x < 990; $x += 12) {
            for ($y = 6; $y < 138; $y += 12) {
                $color = (($x + $y) / 12) % 2 === 0
                    ? imagecolorallocate($image, 255, 255, 255)
                    : imagecolorallocate($image, 226, 32, 116);
                imagefilledrectangle($image, $x, $y, $x + 11, $y + 11, $color);
            }
        }

        ob_start();
        imagejpeg($image, null, 92);
        $binary = ob_get_clean();
        imagedestroy($image);

        return (string) $binary;
    }
}
