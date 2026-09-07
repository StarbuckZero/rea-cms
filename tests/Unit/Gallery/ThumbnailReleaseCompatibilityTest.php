<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Gallery;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;
use ReaCms\Cms\CmsController;
use ReflectionClass;

final class ThumbnailReleaseCompatibilityTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testThumbnailWorksWithTheOriginalAuthoritativeReleaseClassMap(): void
    {
        $loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';
        $reflection = new ReflectionClass(CmsController::class);
        $loader->setClassMapAuthoritative(true);
        self::assertFalse(class_exists('ReaCms\\Media\\ImageThumbnail', false));
        $controller = $reflection->newInstanceWithoutConstructor();
        $result = $reflection->getMethod('imageThumbnail')->invoke($controller, '/missing/image.png');
        self::assertNull($result);
        self::assertTrue(class_exists('ReaCms\\Media\\ImageThumbnail', false));
    }
}
