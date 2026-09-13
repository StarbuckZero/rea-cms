<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use ReaCms\Media\MediaException;
use ReaCms\Media\MediaIngestor;

final class MediaIngestorTest extends TestCase
{
    public function testDefaultLimitAccepts100MbAndRejectsOneByteMore(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'media-limit-');
        self::assertIsString($source);
        $storage = $source . '-uploads';
        try {
            $handle = fopen($source, 'wb');
            self::assertIsResource($handle);
            fwrite($handle, "%PDF-1.4\n");
            ftruncate($handle, 100_000_000);
            fclose($handle);
            clearstatcache(true, $source);

            $ingestor = new MediaIngestor($storage);
            $result = $ingestor->ingest($source, 'boundary.pdf');
            self::assertSame(100_000_000, $result['size']);
            self::assertFileExists($storage . '/' . $result['storedName']);

            $handle = fopen($source, 'ab');
            self::assertIsResource($handle);
            fwrite($handle, 'x');
            fclose($handle);
            clearstatcache(true, $source);
            $this->expectException(MediaException::class);
            $ingestor->ingest($source, 'oversize.pdf');
        } finally {
            unlink($source);
            foreach (glob($storage . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($storage)) {
                rmdir($storage);
            }
        }
    }
}
