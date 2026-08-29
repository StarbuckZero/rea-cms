<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateInterval;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Support\Clock;

final class SessionManager
{
    public const COOKIE = 'rea_session';

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly Clock $clock,
        private readonly int $lifetimeMinutes = 120,
        private readonly bool $secureCookie = true,
    ) {
    }

    public function start(Request $request): SessionContext
    {
        $token = $request->cookie(self::COOKIE);
        $now = $this->clock->now();

        if ($token !== null && preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
            $record = $this->sessions->findActive(hash('sha256', $token), $now);

            if ($record !== null) {
                $this->sessions->touch($record->tokenHash, $now);

                return new SessionContext($token, $record, false);
            }
        }

        return $this->create($request, null);
    }

    public function rotate(Request $request, SessionContext $current, int $userId): SessionContext
    {
        $this->sessions->revoke($current->record->tokenHash, $this->clock->now());

        return $this->create($request, $userId);
    }

    public function revoke(SessionContext $context): void
    {
        $this->sessions->revoke($context->record->tokenHash, $this->clock->now());
    }

    public function markReauthenticated(SessionContext $context): void
    {
        $this->sessions->markReauthenticated($context->record->tokenHash, $this->clock->now());
    }

    /**
     * @return list<UserSession>
     */
    public function listForUser(int $userId): array
    {
        return $this->sessions->listForUser($userId, $this->clock->now());
    }

    public function revokeForUser(int $userId, string $tokenHash): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1) {
            return false;
        }

        return $this->sessions->revokeForUser($userId, $tokenHash, $this->clock->now());
    }

    public function withCookie(Response $response, SessionContext $context): Response
    {
        $parts = [
            self::COOKIE . '=' . $context->token,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
            'Max-Age=' . ($this->lifetimeMinutes * 60),
        ];

        if ($this->secureCookie) {
            $parts[] = 'Secure';
        }

        return $response->withHeader('Set-Cookie', implode('; ', $parts));
    }

    public function clearCookie(Response $response): Response
    {
        $parts = [
            self::COOKIE . '=',
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
            'Max-Age=0',
        ];

        if ($this->secureCookie) {
            $parts[] = 'Secure';
        }

        return $response->withHeader('Set-Cookie', implode('; ', $parts));
    }

    private function create(Request $request, ?int $userId): SessionContext
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = $this->clock->now()->add(new DateInterval(sprintf('PT%dM', $this->lifetimeMinutes)));
        $this->sessions->create(
            $tokenHash,
            $userId,
            $request->clientIp(),
            hash('sha256', $request->userAgent()),
            $expiresAt,
        );

        return new SessionContext(
            $token,
            new SessionRecord($tokenHash, $userId, $expiresAt),
            true,
        );
    }
}
