<?php

declare(strict_types=1);

namespace ReaCms\Setup;

use DateTimeZone;
use InvalidArgumentException;

final readonly class InstallConfiguration
{
    public function __construct(
        public string $appUrl,
        public string $timezone,
        public string $mailFrom,
        public string $databaseHost,
        public int $databasePort,
        public string $databaseName,
        public string $databaseUsername,
        public string $databasePassword,
        public string $tablePrefix,
        public string $adminEmail,
        public string $adminName,
        public string $adminPassword,
    ) {
    }

    /**
     * @param array<string, string> $form
     */
    public static function fromForm(array $form): self
    {
        $url = rtrim(trim($form['app_url'] ?? ''), '/');
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $localHost = is_string($host) && in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
        if (
            !is_array($parts)
            || !is_string($host)
            || $host === ''
            || !is_string($scheme)
            || !in_array(strtolower($scheme), $localHost ? ['http', 'https'] : ['https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/')
        ) {
            throw new InvalidArgumentException('Enter an HTTPS site URL without a path, query, or fragment.');
        }

        $timezone = trim($form['timezone'] ?? '');
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Choose a valid timezone.');
        }

        $mailFrom = strtolower(trim($form['mail_from'] ?? ''));
        $adminEmail = strtolower(trim($form['admin_email'] ?? ''));
        if (filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid sender email address.');
        }
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid administrator email address.');
        }

        $port = filter_var($form['db_port'] ?? '', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if (!is_int($port)) {
            throw new InvalidArgumentException('Enter a valid database port.');
        }

        $databaseHost = self::requiredText($form, 'db_host', 'database host', 255);
        $databaseName = self::requiredText($form, 'db_database', 'database name', 191);
        $databaseUsername = self::requiredText($form, 'db_username', 'database username', 191);
        $databasePassword = self::requiredSecret($form, 'db_password', 'database password');
        $tablePrefix = trim($form['db_table_prefix'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $tablePrefix) !== 1) {
            throw new InvalidArgumentException(
                'The table prefix must start with a lowercase letter and contain only lowercase letters, '
                    . 'numbers, and underscores.',
            );
        }

        $adminName = self::requiredText($form, 'admin_name', 'administrator name', 191);
        $adminPassword = $form['admin_password'] ?? '';
        if (strlen($adminPassword) < 12 || strlen($adminPassword) > 1024) {
            throw new InvalidArgumentException(
                'The administrator password must contain between 12 and 1024 characters.',
            );
        }
        if (!hash_equals($adminPassword, $form['admin_password_confirmation'] ?? '')) {
            throw new InvalidArgumentException('The administrator passwords do not match.');
        }

        return new self(
            $url,
            $timezone,
            $mailFrom,
            $databaseHost,
            $port,
            $databaseName,
            $databaseUsername,
            $databasePassword,
            $tablePrefix,
            $adminEmail,
            $adminName,
            $adminPassword,
        );
    }

    /**
     * @return array<string, string>
     */
    public function environment(string $applicationKey): array
    {
        return [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $this->appUrl,
            'APP_TIMEZONE' => $this->timezone,
            'APP_KEY' => $applicationKey,
            'SESSION_SECURE_COOKIE' => str_starts_with($this->appUrl, 'https://') ? 'true' : 'false',
            'SESSION_LIFETIME_MINUTES' => '120',
            'MAIL_FROM' => $this->mailFrom,
            'DB_HOST' => $this->databaseHost,
            'DB_PORT' => (string) $this->databasePort,
            'DB_DATABASE' => $this->databaseName,
            'DB_USERNAME' => $this->databaseUsername,
            'DB_PASSWORD' => $this->databasePassword,
            'DB_TABLE_PREFIX' => $this->tablePrefix,
            'LOG_LEVEL' => 'warning',
            'TRUSTED_PROXIES' => '',
            'API_ALLOWED_ORIGINS' => $this->appUrl,
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", [
            $this->appUrl,
            $this->databaseHost,
            (string) $this->databasePort,
            $this->databaseName,
            $this->databaseUsername,
            $this->tablePrefix,
            $this->adminEmail,
        ]));
    }

    /**
     * @param array<string, string> $form
     */
    private static function requiredText(array $form, string $key, string $label, int $maximum): string
    {
        $value = trim($form[$key] ?? '');
        if ($value === '' || strlen($value) > $maximum || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException(sprintf('Enter a valid %s.', $label));
        }

        return $value;
    }

    /**
     * @param array<string, string> $form
     */
    private static function requiredSecret(array $form, string $key, string $label): string
    {
        $value = $form[$key] ?? '';
        if ($value === '' || strlen($value) > 1024 || preg_match('/[\x00\r\n]/', $value) === 1) {
            throw new InvalidArgumentException(sprintf('Enter a valid %s.', $label));
        }

        return $value;
    }
}
