<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PasswordResetService;
use ReaCms\Auth\User;
use ReaCms\Tests\Support\CapturingPasswordResetDelivery;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryPasswordResetRepository;
use ReaCms\Tests\Support\InMemorySessionRepository;
use ReaCms\Tests\Support\InMemoryUserRepository;

final class PasswordResetServiceTest extends TestCase
{
    public function testResetTokensAreHashedSingleUseAndRevokeSessions(): void
    {
        $passwords = new PasswordHasher();
        $users = new InMemoryUserRepository();
        $users->users[1] = new User(1, 'user@example.com', $passwords->hash('old password value'), 'active', 'User');
        $resets = new InMemoryPasswordResetRepository();
        $sessions = new InMemorySessionRepository();
        $delivery = new CapturingPasswordResetDelivery();
        $service = new PasswordResetService(
            $users,
            $resets,
            $sessions,
            $passwords,
            $delivery,
            new FrozenClock(new DateTimeImmutable('2026-08-29T12:00:00+00:00')),
            'https://cms.example.com',
        );

        $service->request('user@example.com');

        self::assertCount(1, $delivery->messages);
        parse_str((string) parse_url($delivery->messages[0]['url'], PHP_URL_FRAGMENT), $fragment);
        $token = is_string($fragment['token'] ?? null) ? $fragment['token'] : '';
        self::assertNotSame('', $token);
        self::assertNull(parse_url($delivery->messages[0]['url'], PHP_URL_QUERY));
        self::assertArrayHasKey(hash('sha256', $token), $resets->records);
        self::assertArrayNotHasKey($token, $resets->records);

        self::assertTrue($service->reset('user@example.com', $token, 'a new secure password'));
        self::assertFalse($service->reset('user@example.com', $token, 'another secure password'));
        self::assertTrue($passwords->verify('a new secure password', $users->users[1]->passwordHash));
        self::assertSame([1], $sessions->revokedUsers);
    }

    public function testUnknownAccountsDoNotTriggerDelivery(): void
    {
        $delivery = new CapturingPasswordResetDelivery();
        $service = new PasswordResetService(
            new InMemoryUserRepository(),
            new InMemoryPasswordResetRepository(),
            new InMemorySessionRepository(),
            new PasswordHasher(),
            $delivery,
            new FrozenClock(new DateTimeImmutable('2026-08-29T12:00:00+00:00')),
            'https://cms.example.com',
        );

        $service->request('unknown@example.com');

        self::assertSame([], $delivery->messages);
    }
}
