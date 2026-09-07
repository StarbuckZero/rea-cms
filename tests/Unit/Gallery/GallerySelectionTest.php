<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Gallery;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReaCms\Cms\PdoCmsRepository;

final class GallerySelectionTest extends TestCase
{
    public function testEditUpdatesFirstItemAndCreatesAdditionalItemsInOneTransaction(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::once())->method('commit');
        $pdo->expects(self::never())->method('rollBack');
        $queries = [];
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('execute')->willReturn(true);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$queries, $statement) {
            $queries[] = $sql;
            return $statement;
        });
        $pdo->method('lastInsertId')->willReturn('12');
        (new PdoCmsRepository($pdo))->saveGallerySelection(7, [['media_id' => 3], ['media_id' => 4]]);
        self::assertStringStartsWith('UPDATE `plugin_gallery_items`', $queries[0]);
        self::assertStringStartsWith('INSERT INTO `plugin_gallery_items`', $queries[4]);
    }

    public function testFailureInSecondImageRollsBackEntireSelection(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction');
        $pdo->expects(self::never())->method('commit');
        $pdo->expects(self::once())->method('rollBack');
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('execute')->willReturn(true);
        $calls = 0;
        $pdo->method('prepare')->willReturnCallback(function () use (&$calls, $statement) {
            if (++$calls === 5) {
                throw new PDOException('Database failure');
            }
            return $statement;
        });
        $pdo->method('lastInsertId')->willReturn('12');
        $this->expectException(PDOException::class);
        (new PdoCmsRepository($pdo))->saveGallerySelection(null, [['media_id' => 3], ['media_id' => 4]]);
    }
}
