<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use PDO;
use RuntimeException;

final class PdoAuthorization implements Authorization
{
    private readonly string $userRoles;
    private readonly string $roles;
    private readonly string $rolePermissions;
    private readonly string $permissions;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->userRoles = $prefix . 'user_roles';
        $this->roles = $prefix . 'roles';
        $this->rolePermissions = $prefix . 'role_permissions';
        $this->permissions = $prefix . 'permissions';
    }

    public function allows(int $userId, string $permission): bool
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT 1 FROM `%s` AS user_roles '
                . 'JOIN `%s` AS roles ON roles.id = user_roles.role_id '
                . 'JOIN `%s` AS role_permissions ON role_permissions.role_id = roles.id '
                . 'JOIN `%s` AS permissions ON permissions.id = role_permissions.permission_id '
                . 'WHERE user_roles.user_id = :user_id AND permissions.permission_key = :permission LIMIT 1',
            $this->userRoles,
            $this->roles,
            $this->rolePermissions,
            $this->permissions,
        ));
        $statement->execute(['user_id' => $userId, 'permission' => $permission]);

        return $statement->fetchColumn() !== false;
    }
}
