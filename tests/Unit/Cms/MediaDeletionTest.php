<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Cms;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReaCms\Cms\PdoCmsRepository;
use ReaCms\Media\MediaException;

final class MediaDeletionTest extends TestCase
{
    public function testUnusedMediaAndVariantsAreRemoved(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::once())->method('commit');
        $pdo->expects(self::never())->method('rollBack');
        $pdo->expects(self::exactly(5))->method('prepare')->willReturnOnConsecutiveCalls(
            $this->statement(['stored_name' => 'original.png']),
            $this->statement(['total' => 0]),
            $this->statement(false, []),
            $this->statement(false, [['stored_name' => 'thumbnail.png']]),
            $this->statement(false),
        );
        $removed = [];
        (new PdoCmsRepository($pdo))->deleteMedia(5, static function (array $names) use (&$removed): void {
            $removed = $names;
        });
        self::assertSame(['original.png', 'thumbnail.png'], $removed);
    }

    public function testFileRemovalFailureRollsBackTheDatabaseDeletion(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::never())->method('commit');
        $pdo->expects(self::once())->method('rollBack');
        $pdo->method('prepare')->willReturnOnConsecutiveCalls(
            $this->statement(['stored_name' => 'original.png']),
            $this->statement(['total' => 0]),
            $this->statement(false, []),
            $this->statement(false, []),
            $this->statement(false),
        );
        $this->expectException(MediaException::class);
        (new PdoCmsRepository($pdo))->deleteMedia(5, static function (): void {
            throw new MediaException('File is not writable.');
        });
    }

    public function testMissingMediaDoesNotRemoveFiles(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::once())->method('commit');
        $pdo->expects(self::never())->method('rollBack');
        $pdo->expects(self::once())->method('prepare')->willReturn($this->statement(false));
        (new PdoCmsRepository($pdo))->deleteMedia(999, static function (): void {
            self::fail('There are no files to remove.');
        });
    }

    public function testGalleryReferencesPreventDeletion(): void
    {
        $this->assertBlocked(1, []);
    }

    public function testBlogFeaturedImagePreventsDeletion(): void
    {
        $this->assertBlocked(0, [['featured_media_id' => 5, 'content' => '']]);
    }

    public function testBlogInlineImagePreventsDeletion(): void
    {
        $this->assertBlocked(0, [['featured_media_id' => null, 'content' => '<img src="/media/5">']]);
    }

    public function testMediaIdPrefixDoesNotMatchAnotherImage(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls(
            $this->statement(['total' => 0]),
            $this->statement(false, [['featured_media_id' => null, 'content' => '<img src="/media/50">']]),
        );
        self::assertSame(0, (new PdoCmsRepository($pdo))->count(5));
    }

    /** @param list<array<string, mixed>> $posts */
    private function assertBlocked(int $usage, array $posts): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::never())->method('commit');
        $pdo->expects(self::once())->method('rollBack');
        $pdo->expects(self::exactly(3))->method('prepare')->willReturnOnConsecutiveCalls(
            $this->statement(['stored_name' => 'original.png']),
            $this->statement(['total' => $usage]),
            $this->statement(false, $posts),
        );
        $this->expectException(MediaException::class);
        (new PdoCmsRepository($pdo))->deleteMedia(5, static function (): void {
            self::fail('Referenced files must remain on disk.');
        });
    }

    /** @param array<string, mixed>|false $row
     * @param list<array<string, mixed>> $rows
     */
    private function statement(array|false $row, array $rows = []): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->willReturn(true);
        $statement->method('fetch')->willReturn($row);
        $statement->method('fetchAll')->willReturn($rows);
        return $statement;
    }
}
