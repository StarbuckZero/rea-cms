<?php

declare(strict_types=1);

namespace ReaCms\Api\Token;

use ReaCms\Api\Policy\ApiIdentity;
use ReaCms\Support\Clock;

final class ApiTokenAuthenticator
{
    public function __construct(private readonly ApiTokenRepository $tokens, private readonly Clock $clock)
    {
    }

    public function authenticate(?string $authorization): ApiIdentity
    {
        if (
            $authorization === null
            || preg_match('/^Bearer rea_([a-f0-9]{16})_([a-f0-9]{64})$/D', $authorization, $matches) !== 1
        ) {
            return new ApiIdentity();
        }

        $token = $this->tokens->findActive($matches[1], $this->clock->now());
        if ($token === null || !hash_equals($token->hash, hash('sha256', $matches[2]))) {
            return new ApiIdentity();
        }

        $this->tokens->touch($token->id, $this->clock->now());

        return new ApiIdentity($token->userId, $token->id, $token->scopes, $token->ipCidr);
    }

    /** @return array{tokenId: string, secret: string, plaintext: string, hash: string} */
    public static function generate(): array
    {
        $tokenId = bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(32));

        return [
            'tokenId' => $tokenId,
            'secret' => $secret,
            'plaintext' => sprintf('rea_%s_%s', $tokenId, $secret),
            'hash' => hash('sha256', $secret),
        ];
    }
}
