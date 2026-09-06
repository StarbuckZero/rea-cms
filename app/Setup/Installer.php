<?php

declare(strict_types=1);

namespace ReaCms\Setup;

use PDO;
use ReaCms\Audit\PdoAuditLogger;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PdoUserRepository;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Database\ConnectionFactory;
use ReaCms\Database\Migrations\CoreMigrationRunner;
use ReaCms\Database\Migrations\PdoMigrationDatabase;
use ReaCms\Release\ApplicationVersion;

final class Installer
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly EnvironmentFile $environmentFile,
        private readonly InstallState $state,
    ) {
    }

    /**
     * @return list<string> Applied migrations.
     */
    public function install(InstallConfiguration $configuration, string $requestId, string $ipAddress): array
    {
        $lockPath = $this->projectRoot . '/storage/install.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new InstallException('Another installation attempt is already running. Wait and try again.');
        }
        chmod($lockPath, 0600);

        try {
            return $this->performInstall($configuration, $requestId, $ipAddress);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return list<string> Applied migrations.
     */
    private function performInstall(
        InstallConfiguration $configuration,
        string $requestId,
        string $ipAddress,
    ): array {
        $this->environmentFile->assertCanCreate();
        $applicationKey = bin2hex(random_bytes(32));
        $values = $configuration->environment($applicationKey);
        $environment = Environment::fromArray($values);
        $pdo = ConnectionFactory::create($environment);
        $continuing = $this->state->matchesPending($configuration->fingerprint());

        if ($this->hasApplicationTables($pdo, $configuration->tablePrefix) && !$continuing) {
            throw new InstallException(
                'The selected database already contains Rea CMS tables for this prefix. '
                . 'Choose an empty database or use the upgrade page.',
            );
        }

        $this->state->begin($configuration->fingerprint());
        $runner = new CoreMigrationRunner(
            new PdoMigrationDatabase($pdo),
            $this->projectRoot . '/database/migrations',
            $configuration->tablePrefix,
        );
        $migrated = $runner->migrate();

        $users = new PdoUserRepository($pdo, $configuration->tablePrefix);
        $user = $users->findByEmail($configuration->adminEmail);
        if ($user === null) {
            $userId = $users->create(
                $configuration->adminEmail,
                (new PasswordHasher())->hash($configuration->adminPassword),
                $configuration->adminName,
            );
        } elseif ($continuing) {
            $userId = $user->id;
            $users->update(
                $userId,
                $configuration->adminEmail,
                $configuration->adminName,
                'active',
            );
            $users->updatePassword($userId, (new PasswordHasher())->hash($configuration->adminPassword));
        } else {
            throw new InstallException('An account with that administrator email already exists.');
        }
        $users->assignRole($userId, 'super-administrator');

        (new PdoAuditLogger($pdo, $configuration->tablePrefix))->record(
            'system.installation_completed',
            $userId,
            $ipAddress,
            $requestId,
            ['version' => ApplicationVersion::detect($this->projectRoot)],
            'user',
            (string) $userId,
        );

        $this->environmentFile->create($values);
        $this->state->complete(ApplicationVersion::detect($this->projectRoot));

        return $migrated;
    }

    private function hasApplicationTables(PDO $pdo, string $prefix): bool
    {
        $statement = $pdo->query('SHOW TABLES');
        if ($statement === false) {
            throw new InstallException('The database tables could not be inspected.');
        }

        while (($name = $statement->fetchColumn()) !== false) {
            if (is_string($name) && str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
