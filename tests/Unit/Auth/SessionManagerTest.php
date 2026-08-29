<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Auth\SessionManager;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemorySessionRepository;

final class SessionManagerTest extends TestCase
{
    public function testItCreatesAndHashesAnAnonymousSession(): void
    {
        $repository = new InMemorySessionRepository();
        $manager = $this->manager($repository);
        $context = $manager->start(new Request('GET', '/login'));

        self::assertTrue($context->isNew);
        self::assertFalse($context->isAuthenticated());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context->token);
        self::assertArrayHasKey(hash('sha256', $context->token), $repository->records);
        self::assertArrayNotHasKey($context->token, $repository->records);
    }

    public function testLoginRotationRevokesTheOldToken(): void
    {
        $repository = new InMemorySessionRepository();
        $manager = $this->manager($repository);
        $request = new Request('GET', '/login');
        $anonymous = $manager->start($request);
        $authenticated = $manager->rotate($request, $anonymous, 42);

        self::assertNotSame($anonymous->token, $authenticated->token);
        self::assertSame(42, $authenticated->record->userId);
        self::assertContains($anonymous->record->tokenHash, $repository->revoked);
    }

    public function testCookieUsesSecureBrowserControls(): void
    {
        $repository = new InMemorySessionRepository();
        $manager = $this->manager($repository, true);
        $context = $manager->start(new Request('GET', '/login'));
        $cookie = $manager->withCookie(Response::html(''), $context)->header('Set-Cookie') ?? '';

        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Lax', $cookie);
        self::assertStringContainsString('Secure', $cookie);
    }

    public function testAUserCannotRevokeAnotherUsersSession(): void
    {
        $repository = new InMemorySessionRepository();
        $manager = $this->manager($repository);
        $anonymous = $manager->start(new Request('GET', '/login'));
        $authenticated = $manager->rotate(new Request('POST', '/login'), $anonymous, 42);

        self::assertFalse($manager->revokeForUser(99, $authenticated->record->tokenHash));
        self::assertTrue($manager->revokeForUser(42, $authenticated->record->tokenHash));
    }

    private function manager(InMemorySessionRepository $repository, bool $secure = false): SessionManager
    {
        return new SessionManager(
            $repository,
            new FrozenClock(new DateTimeImmutable('2026-08-29T12:00:00+00:00')),
            120,
            $secure,
        );
    }
}
