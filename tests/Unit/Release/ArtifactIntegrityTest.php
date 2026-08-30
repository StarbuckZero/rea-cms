<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Release;

use PHPUnit\Framework\TestCase;
use ReaCms\Release\ArtifactIntegrity;

final class ArtifactIntegrityTest extends TestCase
{
    public function testWrittenChecksumVerifiesAndDetectsModification(): void
    {
        $directory = sys_get_temp_dir() . '/rea-release-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));

        $artifact = $directory . '/rea-cms-1.0.0-rc.1.zip';
        $checksum = $artifact . '.sha256';
        file_put_contents($artifact, 'original release');

        ArtifactIntegrity::write($artifact, $checksum);
        self::assertTrue(ArtifactIntegrity::verify($artifact, $checksum));

        file_put_contents($artifact, 'modified release');
        self::assertFalse(ArtifactIntegrity::verify($artifact, $checksum));

        unlink($artifact);
        unlink($checksum);
        rmdir($directory);
    }
}
