<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Gallery;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Cms\CmsController;
use ReaCms\Cms\PdoCmsRepository;
use ReflectionClass;

final class GalleryImageMetadataTest extends TestCase
{
    /** @return iterable<array{string, ?string, bool}> */
    public static function metadata(): iterable
    {
        yield ['Comic Con 2026.jpg', 'Cosplayer in a red costume', true];
        yield ['Été.png', '', true];
        yield ['<photo>.jpg', 'A "quoted" <description>', true];
        yield ['', '', false];
        yield ['../photo.jpg', '', false];
        yield ['folder\\photo.jpg', '', false];
        yield ["photo.jpg\n", '', false];
        yield [str_repeat('a', 256), '', false];
        yield ['photo.jpg', str_repeat('a', 501), false];
        yield ['photo.jpg', null, false];
        yield ['photo.jpg', "bad\0alt", false];
        yield ["bad\xFF.jpg", '', false];
    }

    #[DataProvider('metadata')]
    public function testMetadataValidation(string $name, ?string $alt, bool $valid): void
    {
        $reflection = new ReflectionClass(CmsController::class);
        $errors = $reflection->getMethod('galleryImageMetadataErrors')->invoke(
            $reflection->newInstanceWithoutConstructor(),
            $name,
            $alt
        );
        self::assertSame($valid, $errors === []);
    }

    public function testNamesAndAltTextAreBoundAsDataAndStoredFileIsUnchanged(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::once())->method('commit');
        $pdo->expects(self::never())->method('rollBack');
        $first = $this->createMock(PDOStatement::class);
        $first->expects(self::once())->method('execute')->with([
            'name' => '<photo>.jpg', 'alt' => 'A "photo"', 'id' => 37,
        ]);
        $second = $this->createMock(PDOStatement::class);
        $second->expects(self::once())->method('execute')->with([
            'alt' => 'A "photo"', 'id' => 2, 'media_id' => 37,
        ]);
        $calls = 0;
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$calls, $first, $second) {
            self::assertStringNotContainsString('stored_name', $sql);
            self::assertStringNotContainsString('<photo>', $sql);
            return ++$calls === 1 ? $first : $second;
        });
        (new PdoCmsRepository($pdo))->saveGalleryImageMetadata(2, 37, '<photo>.jpg', 'A "photo"');
    }

    public function testEditorEscapesImageNamesAndAltText(): void
    {
        $views = new \ReaCms\Core\View\ViewRenderer(dirname(__DIR__, 3) . '/resources/views');
        $html = $views->render('cms/gallery/image-editor', [
            'item' => ['id' => 2, 'media_id' => 37, 'album_id' => 1,
                'original_name' => '"><script>alert(1)</script>',
                'alt_text' => '</textarea><script>alert(2)</script>'],
            'errors' => [], 'saved' => false, 'csrfToken' => 'test',
        ]);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;/textarea&gt;', $html);
    }

    public function testMetadataFailureRollsBackBothUpdates(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::never())->method('commit');
        $pdo->expects(self::once())->method('rollBack');
        $first = $this->createMock(PDOStatement::class);
        $second = $this->createMock(PDOStatement::class);
        $second->method('execute')->willThrowException(new PDOException('Save failed'));
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($first, $second);
        $this->expectException(PDOException::class);
        (new PdoCmsRepository($pdo))->saveGalleryImageMetadata(2, 37, 'photo.jpg', 'Alt');
    }
}
