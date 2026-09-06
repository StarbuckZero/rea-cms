<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Setup;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReaCms\Setup\InstallConfiguration;

final class InstallConfigurationTest extends TestCase
{
    public function testItBuildsAProductionEnvironmentFromValidInput(): void
    {
        $configuration = InstallConfiguration::fromForm($this->validForm());
        $environment = $configuration->environment(str_repeat('a', 64));

        self::assertSame('https://cms.example.com', $configuration->appUrl);
        self::assertSame('production', $environment['APP_ENV']);
        self::assertSame('false', $environment['APP_DEBUG']);
        self::assertSame('true', $environment['SESSION_SECURE_COOKIE']);
        self::assertSame(' db $ecret "value" ', $environment['DB_PASSWORD']);
        self::assertSame('https://cms.example.com', $environment['API_ALLOWED_ORIGINS']);
    }

    public function testItAllowsHttpOnlyForLocalDevelopmentHosts(): void
    {
        $form = $this->validForm();
        $form['app_url'] = 'http://localhost';

        $configuration = InstallConfiguration::fromForm($form);

        self::assertSame('false', $configuration->environment(str_repeat('b', 64))['SESSION_SECURE_COOKIE']);
    }

    public function testItRejectsAnInsecureProductionUrl(): void
    {
        $form = $this->validForm();
        $form['app_url'] = 'http://cms.example.com';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTPS site URL');

        InstallConfiguration::fromForm($form);
    }

    public function testItsRecoveryFingerprintDoesNotContainOrDependOnPasswords(): void
    {
        $first = InstallConfiguration::fromForm($this->validForm());
        $form = $this->validForm();
        $form['db_password'] = 'different database password';
        $form['admin_password'] = 'different admin password';
        $form['admin_password_confirmation'] = 'different admin password';
        $second = InstallConfiguration::fromForm($form);

        self::assertSame($first->fingerprint(), $second->fingerprint());
        self::assertStringNotContainsString('secret', $first->fingerprint());
    }

    /**
     * @return array<string, string>
     */
    private function validForm(): array
    {
        return [
            'app_url' => 'https://cms.example.com/',
            'timezone' => 'America/New_York',
            'mail_from' => 'no-reply@example.com',
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_database' => 'account_cms',
            'db_username' => 'account_cms',
            'db_password' => ' db $ecret "value" ',
            'db_table_prefix' => 'rea_',
            'admin_email' => 'admin@example.com',
            'admin_name' => 'Site Administrator',
            'admin_password' => 'correct horse battery staple',
            'admin_password_confirmation' => 'correct horse battery staple',
        ];
    }
}
