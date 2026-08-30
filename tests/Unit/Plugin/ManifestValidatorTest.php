<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PluginException;

final class ManifestValidatorTest extends TestCase
{
    public function testValidManifestRecordsIdentityNamespacesAndHash(): void
    {
        $json = $this->manifest();
        $manifest = (new ManifestValidator())->validate($json);

        self::assertSame('notes', $manifest->id);
        self::assertSame(['plugin_notes_entries'], $manifest->tables);
        self::assertSame(['notes.entries.view'], $manifest->permissions);
        self::assertSame(hash('sha256', $json), $manifest->hash);
    }

    #[DataProvider('invalidManifests')]
    public function testInvalidOrPrivilegedManifestsAreRejected(array $changes): void
    {
        $data = json_decode($this->manifest(), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        foreach ($changes as $key => $value) {
            $data[$key] = $value;
        }

        $this->expectException(PluginException::class);
        (new ManifestValidator())->validate(json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function invalidManifests(): iterable
    {
        yield 'unknown executable capability' => [['phpEntrypoint' => 'run.php']];
        yield 'table namespace escape' => [['tables' => ['rea_users']]];
        yield 'permission namespace escape' => [['permissions' => ['core.users.manage']]];
        yield 'incompatible major version' => [['reaCmsVersion' => '^2.0']];
        yield 'invalid semantic version' => [['version' => 'latest']];
    }

    private function manifest(): string
    {
        return json_encode([
            'schemaVersion' => 1,
            'id' => 'notes',
            'name' => 'Notes',
            'version' => '1.0.0',
            'reaCmsVersion' => '^1.0',
            'description' => 'Declarative notes.',
            'tables' => ['plugin_notes_entries'],
            'permissions' => ['notes.entries.view'],
        ], JSON_THROW_ON_ERROR);
    }
}
