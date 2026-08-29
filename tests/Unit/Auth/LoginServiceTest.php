<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Auth\LoginService;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\User;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryLoginThrottle;
use ReaCms\Tests\Support\InMemoryUserRepository;

final class LoginServiceTest extends TestCase
{
    private PasswordHasher $passwords;
    private InMemoryUserRepository $users;
    private InMemoryLoginThrottle $throttle;
    private LoginService $login;

    protected function setUp(): void
    {
        $this->passwords = new PasswordHasher();
        $this->users = new InMemoryUserRepository();
        $this->throttle = new InMemoryLoginThrottle();
        $this->login = new LoginService(
            $this->users,
            $this->throttle,
            $this->passwords,
            new FrozenClock(new DateTimeImmutable('2026-08-29T12:00:00+00:00')),
        );
    }

    public function testValidActiveUsersCanLogin(): void
    {
        $this->users->users[1] = new User(
            1,
            'admin@example.com',
            $this->passwords->hash('correct horse battery staple'),
            'active',
            'Admin',
        );

        $result = $this->login->authenticate('ADMIN@example.com', 'correct horse battery staple', '127.0.0.1');

        self::assertTrue($result->succeeded());
        self::assertSame(1, $result->user?->id);
        self::assertSame([1], $this->users->loginMarks);
        self::assertCount(1, $this->throttle->cleared);
    }

    public function testWrongPasswordsAndSuspendedUsersAreRejectedGenerically(): void
    {
        $this->users->users[1] = new User(
            1,
            'admin@example.com',
            $this->passwords->hash('correct horse battery staple'),
            'suspended',
            'Admin',
        );

        self::assertFalse($this->login->authenticate('admin@example.com', 'wrong', '127.0.0.1')->succeeded());
        self::assertFalse($this->login->authenticate(
            'admin@example.com',
            'correct horse battery staple',
            '127.0.0.1',
        )->succeeded());
        self::assertCount(2, $this->throttle->failures);
    }

    public function testLockedIdentitiesAreRejectedWithoutAnotherFailure(): void
    {
        $this->throttle->locked = true;

        $result = $this->login->authenticate('unknown@example.com', 'wrong', '127.0.0.1');

        self::assertTrue($result->locked);
        self::assertSame([], $this->throttle->failures);
    }
}
