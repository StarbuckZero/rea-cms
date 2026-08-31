<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use PDO;
use RuntimeException;

final class PdoUserRepository implements UserRepository
{
    private readonly string $users;
    private readonly string $profiles;
    private readonly string $roles;
    private readonly string $userRoles;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->users = $prefix . 'users';
        $this->profiles = $prefix . 'user_profiles';
        $this->roles = $prefix . 'roles';
        $this->userRoles = $prefix . 'user_roles';
    }

    public function findByEmail(string $email): ?User
    {
        return $this->find('users.email = :value', strtolower(trim($email)));
    }

    public function findById(int $id): ?User
    {
        return $this->find('users.id = :value', $id);
    }

    public function all(): array
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT users.id, users.email, users.password_hash, users.status, profiles.display_name '
                . 'FROM `%s` AS users JOIN `%s` AS profiles ON profiles.user_id = users.id '
                . 'WHERE users.deleted_at IS NULL ORDER BY users.email',
            $this->users,
            $this->profiles,
        ));
        $statement->execute();
        $users = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $users[] = $this->hydrate($row);
            }
        }
        return $users;
    }

    public function create(string $email, string $passwordHash, string $displayName, string $status = 'active'): int
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(sprintf(
                'INSERT INTO `%s` (`email`, `password_hash`, `status`) VALUES (:email, :password_hash, :status)',
                $this->users,
            ));
            $statement->execute([
                'email' => strtolower(trim($email)),
                'password_hash' => $passwordHash,
                'status' => $status,
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            $profile = $this->pdo->prepare(sprintf(
                'INSERT INTO `%s` (`user_id`, `display_name`) VALUES (:user_id, :display_name)',
                $this->profiles,
            ));
            $profile->execute([
                'user_id' => $userId,
                'display_name' => $displayName,
            ]);
            $this->pdo->commit();

            return $userId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET `password_hash` = :password_hash WHERE `id` = :user_id',
            $this->users,
        ));
        $statement->execute(['password_hash' => $passwordHash, 'user_id' => $userId]);
    }

    public function update(int $userId, string $email, string $displayName, string $status): void
    {
        $this->pdo->beginTransaction();
        try {
            $user = $this->pdo->prepare(sprintf(
                'UPDATE `%s` SET email = :email, status = :status WHERE id = :id AND deleted_at IS NULL',
                $this->users,
            ));
            $user->execute(['email' => strtolower(trim($email)), 'status' => $status, 'id' => $userId]);
            $profile = $this->pdo->prepare(sprintf(
                'UPDATE `%s` SET display_name = :display_name WHERE user_id = :id',
                $this->profiles,
            ));
            $profile->execute(['display_name' => trim($displayName), 'id' => $userId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(int $userId): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET status = :status, deleted_at = CURRENT_TIMESTAMP(6) WHERE id = :id',
            $this->users,
        ));
        $statement->execute(['status' => 'disabled', 'id' => $userId]);
    }

    public function markLogin(int $userId): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET `last_login_at` = CURRENT_TIMESTAMP(6) WHERE `id` = :user_id',
            $this->users,
        ));
        $statement->execute(['user_id' => $userId]);
    }

    public function assignRole(int $userId, string $roleKey): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT IGNORE INTO `%s` (`user_id`, `role_id`) '
                . 'SELECT :user_id, `id` FROM `%s` WHERE `role_key` = :role_key',
            $this->userRoles,
            $this->roles,
        ));
        $statement->execute(['user_id' => $userId, 'role_key' => $roleKey]);
    }

    private function find(string $condition, string|int $value): ?User
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT users.id, users.email, users.password_hash, users.status, profiles.display_name '
                . 'FROM `%s` AS users JOIN `%s` AS profiles ON profiles.user_id = users.id '
                . 'WHERE %s LIMIT 1',
            $this->users,
            $this->profiles,
            $condition,
        ));
        $statement->execute(['value' => $value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['status'],
            (string) $row['display_name'],
        );
    }
}
