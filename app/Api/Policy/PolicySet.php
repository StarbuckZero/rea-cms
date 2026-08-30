<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

use InvalidArgumentException;

final class PolicySet
{
    private const SUPPORTED = [
        'public', 'same-origin', 'authenticated', 'token', 'ip-allowlist', 'server-only', 'disabled',
    ];

    /** @var list<string> */
    public readonly array $policies;

    /**
     * @param list<string> $policies
     * @param list<string> $scopes
     */
    public function __construct(
        array $policies,
        public readonly array $scopes = [],
        public readonly ?string $permission = null,
    ) {
        foreach ($policies as $policy) {
            if (!in_array($policy, self::SUPPORTED, true)) {
                throw new InvalidArgumentException('Unsupported API policy: ' . $policy);
            }
        }

        $unique = array_values(array_unique($policies));
        $this->policies = count($unique) > 1 ? array_values(array_diff($unique, ['public'])) : $unique;
    }

    public static function combine(self ...$sets): self
    {
        $policies = [];
        $scopes = [];
        $permission = null;

        foreach ($sets as $set) {
            $policies = [...$policies, ...$set->policies];
            $scopes = [...$scopes, ...$set->scopes];
            $permission = $set->permission ?? $permission;
        }

        return new self(array_values(array_unique($policies)), array_values(array_unique($scopes)), $permission);
    }
}
