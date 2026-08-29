<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use ReaCms\Support\Clock;
use RuntimeException;

final class LoginService
{
    private readonly string $dummyHash;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginThrottle $throttle,
        private readonly PasswordHasher $passwords,
        private readonly Clock $clock,
    ) {
        $this->dummyHash = $this->passwords->hash(bin2hex(random_bytes(32)));
    }

    public function authenticate(string $email, string $password, string $ipAddress): LoginResult
    {
        $normalizedEmail = strtolower(trim($email));
        $throttleKey = hash('sha256', $normalizedEmail . '|' . $ipAddress);
        $now = $this->clock->now();

        if ($this->throttle->isLocked($throttleKey, $now)) {
            return LoginResult::failure(true);
        }

        $user = $this->users->findByEmail($normalizedEmail);
        $hash = $user === null ? $this->dummyHash : $user->passwordHash;
        $valid = $this->passwords->verify($password, $hash);

        if ($user === null || !$valid || !$user->isActive()) {
            $this->throttle->recordFailure($throttleKey, $now);

            return LoginResult::failure();
        }

        $this->throttle->clear($throttleKey);

        if ($this->passwords->needsRehash($user->passwordHash)) {
            $this->users->updatePassword($user->id, $this->passwords->hash($password));
        }

        $this->users->markLogin($user->id);

        return LoginResult::success($user);
    }

    public function reauthenticate(User $user, string $password): void
    {
        if (!$user->isActive() || !$this->passwords->verify($password, $user->passwordHash)) {
            throw new RuntimeException('Reauthentication failed.');
        }
    }
}
