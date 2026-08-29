<?php

declare(strict_types=1);

namespace ReaCms\Auth;

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function create(string $email, string $passwordHash, string $displayName, string $status = 'active'): int;

    public function updatePassword(int $userId, string $passwordHash): void;

    public function markLogin(int $userId): void;

    public function assignRole(int $userId, string $roleKey): void;
}
