<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Gallery;

use PHPUnit\Framework\TestCase;
use ReaCms\Media\ImageThumbnail;

final class ImageThumbnailTest extends TestCase
{
    public function testMissingAndInvalidImagesFallBack(): void
    {
        self::assertNull((new ImageThumbnail())->create('/missing/thumbnail.jpg'));
        self::assertNull((new ImageThumbnail())->create(__FILE__));
    }

    public function testThumbnailPreservesAspectRatioTransparencyAndOriginal(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is unavailable.');
        }
        $path = tempnam(sys_get_temp_dir(), 'thumbnail');
        self::assertIsString($path);
        try {
            $source = imagecreatetruecolor(800, 400);
            imagealphablending($source, false);
            imagesavealpha($source, true);
            imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
            imagepng($source, $path);
            imagedestroy($source);
            $original = file_get_contents($path);
            $thumbnail = (new ImageThumbnail())->create($path);
            self::assertIsString($thumbnail);
            $size = getimagesizefromstring($thumbnail);
            self::assertSame(320, $size[0]);
            self::assertSame(160, $size[1]);
            self::assertSame('image/png', $size['mime']);
            $image = imagecreatefromstring($thumbnail);
            self::assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 127);
            imagedestroy($image);
            self::assertSame($original, file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
