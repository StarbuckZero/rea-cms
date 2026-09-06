<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use PDO;
use RuntimeException;

final class PdoRoleLookup implements RoleLookup
{
    private readonly string $roles;
    private readonly string $userRoles;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->roles = $prefix . 'roles';
        $this->userRoles = $prefix . 'user_roles';
    }

    public function hasRole(int $userId, string $role): bool
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT 1 FROM `%s` AS user_roles '
                . 'JOIN `%s` AS roles ON roles.id = user_roles.role_id '
                . 'WHERE user_roles.user_id = :user_id AND roles.role_key = :role LIMIT 1',
            $this->userRoles,
            $this->roles,
        ));
        $statement->execute(['user_id' => $userId, 'role' => $role]);

        return $statement->fetchColumn() !== false;
    }
}
