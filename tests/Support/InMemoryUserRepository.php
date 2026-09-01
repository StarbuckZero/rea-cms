<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Auth\User;
use ReaCms\Auth\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    public array $users = [];

    /** @var array<int, list<string>> */
    public array $roles = [];

    /** @var list<int> */
    public array $loginMarks = [];

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === strtolower(trim($email))) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function create(string $email, string $passwordHash, string $displayName, string $status = 'active'): int
    {
        $id = count($this->users) + 1;
        $this->users[$id] = new User($id, strtolower(trim($email)), $passwordHash, $status, $displayName);

        return $id;
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $user = $this->users[$userId];
        $this->users[$userId] = new User(
            $user->id,
            $user->email,
            $passwordHash,
            $user->status,
            $user->displayName,
            $user->theme,
        );
    }

    public function updateProfile(int $userId, string $displayName, string $theme): void
    {
        $user = $this->users[$userId];
        $this->users[$userId] = new User(
            $user->id,
            $user->email,
            $user->passwordHash,
            $user->status,
            trim($displayName),
            $theme,
        );
    }

    public function update(int $userId, string $email, string $displayName, string $status): void
    {
        $existing = $this->users[$userId] ?? null;
        if ($existing !== null) {
            $this->users[$userId] = new User(
                $userId,
                $email,
                $existing->passwordHash,
                $status,
                $displayName,
                $existing->theme,
            );
        }
    }

    public function delete(int $userId): void
    {
        unset($this->users[$userId]);
    }

    public function markLogin(int $userId): void
    {
        $this->loginMarks[] = $userId;
    }

    public function assignRole(int $userId, string $roleKey): void
    {
        $this->roles[$userId][] = $roleKey;
    }
}
