<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateInterval;
use ReaCms\Support\Clock;

final class PasswordResetService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetRepository $resets,
        private readonly SessionRepository $sessions,
        private readonly PasswordHasher $passwords,
        private readonly PasswordResetDelivery $delivery,
        private readonly Clock $clock,
        private readonly string $applicationUrl,
    ) {
    }

    public function request(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->isActive()) {
            return;
        }

        $now = $this->clock->now();
        $token = bin2hex(random_bytes(32));
        $this->resets->invalidateForUser($user->id, $now);
        $this->resets->create(
            hash('sha256', $token),
            $user->id,
            $now->add(new DateInterval('PT30M')),
        );
        $url = rtrim($this->applicationUrl, '/')
            . '/reset-password#token=' . rawurlencode($token)
            . '&email=' . rawurlencode($user->email);
        $this->delivery->send($user->email, $url);
    }

    public function reset(string $email, string $token, string $password): bool
    {
        if (strlen($password) < 12 || strlen($password) > 1024) {
            return false;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->isActive() || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return false;
        }

        $now = $this->clock->now();
        $tokenHash = hash('sha256', $token);
        $record = $this->resets->findActive($tokenHash, $now);

        if ($record === null || $record->userId !== $user->id) {
            return false;
        }

        $this->users->updatePassword($user->id, $this->passwords->hash($password));
        $this->resets->markUsed($tokenHash, $now);
        $this->sessions->revokeUserSessions($user->id, $now);

        return true;
    }
}
