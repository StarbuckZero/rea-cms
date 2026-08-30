<?php

declare(strict_types=1);

namespace ReaCms\Api;

use ReaCms\Api\Policy\ApiIdentity;
use ReaCms\Api\Token\ApiTokenAuthenticator;
use ReaCms\Auth\SessionManager;
use ReaCms\Auth\SessionRepository;
use ReaCms\Core\Http\Request;
use ReaCms\Support\Clock;

final class ApiIdentityResolver
{
    public function __construct(
        private readonly ApiTokenAuthenticator $tokens,
        private readonly SessionRepository $sessions,
        private readonly Clock $clock,
    ) {
    }

    public function resolve(Request $request): ApiIdentity
    {
        $authorization = $request->header('authorization');
        if ($authorization !== null) {
            return $this->tokens->authenticate($authorization);
        }

        $sessionToken = $request->cookie(SessionManager::COOKIE);
        if ($sessionToken === null || preg_match('/^[a-f0-9]{64}$/D', $sessionToken) !== 1) {
            return new ApiIdentity();
        }

        $session = $this->sessions->findActive(hash('sha256', $sessionToken), $this->clock->now());
        if ($session === null || $session->userId === null) {
            return new ApiIdentity();
        }

        $this->sessions->touch($session->tokenHash, $this->clock->now());

        return new ApiIdentity($session->userId);
    }
}
