<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PackageInspector;
use ReaCms\Plugin\PluginException;
use ZipArchive;

final class PackageInspectorTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->paths) as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }
    }

    public function testValidPackageIsInspectedThenPrivatelyStaged(): void
    {
        $archive = $this->archive([
            'notes/plugin.json' => $this->manifest(),
            'notes/templates/card.html' => '{{ note.title }}',
        ]);
        $staging = $this->temporaryDirectory();

        $package = (new PackageInspector(new ManifestValidator()))->inspect($archive, $staging);

        self::assertSame('notes', $package->manifest->id);
        self::assertFileExists($package->directory . '/plugin.json');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $package->packageHash);
    }

    #[DataProvider('maliciousEntries')]
    public function testUnsafePathsAndExecutableFilesAreRejected(string $name, string $contents): void
    {
        $archive = $this->archive(['notes/plugin.json' => $this->manifest(), $name => $contents]);

        $this->expectException(PluginException::class);
        (new PackageInspector(new ManifestValidator()))->inspect($archive, $this->temporaryDirectory());
    }

    public static function maliciousEntries(): iterable
    {
        yield 'traversal' => ['notes/../outside.json', '{}'];
        yield 'php extension' => ['notes/run.php', '<?php echo 1;'];
        yield 'server control' => ['notes/.htaccess', 'Require all granted'];
        yield 'shell polyglot' => ['notes/assets/readme.txt', "#!/bin/sh\nid\n"];
        yield 'late php marker' => ['notes/assets/readme.txt', str_repeat('A', 300) . '<?php echo 1;'];
        yield 'multiple roots' => ['other/file.json', '{}'];
    }

    public function testInvalidDeclarativeMigrationIsRejectedBeforeInstallation(): void
    {
        $archive = $this->archive([
            'notes/plugin.json' => $this->manifest(),
            'notes/migrations/001_install.json' => json_encode([
                'operations' => [[
                    'action' => 'create_table',
                    'table' => 'plugin_other_entries',
                    'columns' => [['name' => 'id', 'type' => 'bigint']],
                ]],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->expectException(PluginException::class);
        (new PackageInspector(new ManifestValidator()))->inspect($archive, $this->temporaryDirectory());
    }

    public function testUnsupportedTemplateSyntaxIsRejectedBeforeInstallation(): void
    {
        $archive = $this->archive([
            'notes/plugin.json' => $this->manifest(),
            'notes/templates/public/card.html' => '{{ arbitrary_function(note.title) }}',
        ]);

        $this->expectException(PluginException::class);
        (new PackageInspector(new ManifestValidator()))->inspect($archive, $this->temporaryDirectory());
    }

    public function testHtmlAndTextApisRequireListAndDetailTemplates(): void
    {
        $manifest = json_decode($this->manifest(), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        $manifest['api'] = [
            'resource' => 'notes',
            'formats' => ['json', 'html', 'txt'],
            'defaultPolicy' => 'same-origin',
        ];
        $archive = $this->archive([
            'notes/plugin.json' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'notes/templates/api/list.html' => '<h2>{notes.title}</h2>',
            'notes/templates/api/detail.html' => '<h1>{notes.title}</h1>',
            'notes/templates/api/list.txt' => '{notes.title}',
        ]);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessage('templates/api/detail.txt');
        (new PackageInspector(new ManifestValidator()))->inspect($archive, $this->temporaryDirectory());
    }

    public function testExpansionLimitRejectsBombLikeEntry(): void
    {
        $archive = $this->archive([
            'notes/plugin.json' => $this->manifest(),
            'notes/assets/data.txt' => str_repeat('A', 10_000),
        ]);

        $this->expectException(PluginException::class);
        (new PackageInspector(new ManifestValidator(), maximumFileBytes: 100))->inspect(
            $archive,
            $this->temporaryDirectory(),
        );
    }

    /** @param array<string, string> $entries */
    private function archive(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rea-plugin-');
        self::assertIsString($path);
        $this->paths[] = $path;
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            self::assertTrue($zip->addFromString($name, $contents));
        }
        self::assertTrue($zip->close());
        return $path;
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . '/rea-plugin-stage-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($path, 0700));
        $this->paths[] = $path;
        return $path;
    }

    private function manifest(): string
    {
        return json_encode([
            'schemaVersion' => 1, 'id' => 'notes', 'name' => 'Notes', 'version' => '1.0.0',
            'reaCmsVersion' => '^1.0', 'description' => '', 'tables' => ['plugin_notes_entries'],
            'permissions' => [],
        ], JSON_THROW_ON_ERROR);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
