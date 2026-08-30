<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use ReaCms\Audit\PdoAuditLogger;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Database\ConnectionFactory;
use ReaCms\Security\Csrf;
use ReaCms\Plugin\PdoPluginRegistry;
use ReaCms\Support\SystemClock;
use RuntimeException;

final class AuthServicesFactory
{
    public static function create(Environment $environment): AuthServices
    {
        $pdo = ConnectionFactory::create($environment);
        $prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
        $clock = new SystemClock();
        $users = new PdoUserRepository($pdo, $prefix);
        $sessionRepository = new PdoSessionRepository($pdo, $prefix);
        $lifetime = self::positiveInteger($environment->get('SESSION_LIFETIME_MINUTES', '120'));
        $sessions = new SessionManager(
            $sessionRepository,
            $clock,
            $lifetime,
            $environment->bool('SESSION_SECURE_COOKIE', true),
        );
        $passwords = new PasswordHasher();
        $passwordReset = new PasswordResetService(
            $users,
            new PdoPasswordResetRepository($pdo, $prefix),
            $sessionRepository,
            $passwords,
            new NativeMailPasswordResetDelivery($environment->require('MAIL_FROM')),
            $clock,
            $environment->require('APP_URL'),
        );

        return new AuthServices(
            $users,
            $sessionRepository,
            $sessions,
            new LoginService($users, new PdoLoginThrottle($pdo, $prefix), $passwords, $clock),
            new PdoAuthorization($pdo, $prefix),
            new PdoAuditLogger($pdo, $prefix),
            new Csrf($environment->require('APP_KEY')),
            $passwordReset,
            new PdoPluginRegistry($pdo, $prefix),
        );
    }

    private static function positiveInteger(?string $value): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!is_int($parsed)) {
            throw new RuntimeException('SESSION_LIFETIME_MINUTES must be a positive integer.');
        }

        return $parsed;
    }
}
