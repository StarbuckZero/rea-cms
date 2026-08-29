<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use ReaCms\Audit\AuditLogger;
use ReaCms\Security\Csrf;

final class AuthServices
{
    public function __construct(
        public readonly UserRepository $users,
        public readonly SessionRepository $sessionRepository,
        public readonly SessionManager $sessions,
        public readonly LoginService $login,
        public readonly Authorization $authorization,
        public readonly AuditLogger $audit,
        public readonly Csrf $csrf,
        public readonly PasswordResetService $passwordReset,
    ) {
    }
}
