<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use ReaCms\Media\MediaDeletion;
use ReaCms\Media\MediaException;
use ReaCms\Media\MediaUsage;

final class MediaDeletionTest extends TestCase
{
    public function testDeletionIsBlockedWhileMediaIsInUse(): void
    {
        $usage = new class implements MediaUsage {
            public function count(int $mediaId): int
            {
                return 2;
            }
        };

        $this->expectException(MediaException::class);
        (new MediaDeletion($usage))->delete(5, static function (): void {
            self::fail('Referenced media must not be deleted.');
        });
    }
}
