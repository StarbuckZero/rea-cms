<?php

declare(strict_types=1);

namespace ReaCms\Auth;

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /** @return list<User> */
    public function all(): array;

    public function create(string $email, string $passwordHash, string $displayName, string $status = 'active'): int;

    public function updatePassword(int $userId, string $passwordHash): void;

    public function updateProfile(int $userId, string $displayName, string $theme): void;

    public function update(int $userId, string $email, string $displayName, string $status): void;

    public function delete(int $userId): void;

    public function markLogin(int $userId): void;

    public function assignRole(int $userId, string $roleKey): void;
}
