<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

use ReaCms\Auth\Authorization;

final class PolicyEvaluator
{
    public function __construct(
        private readonly OriginAllowlist $origins,
        private readonly NetworkMatcher $networks,
        private readonly Authorization $authorization,
    ) {
    }

    /** @param list<string> $allowedNetworks */
    public function evaluate(
        PolicySet $policy,
        ?string $origin,
        string $clientIp,
        ApiIdentity $identity,
        array $allowedNetworks = [],
    ): PolicyDecision {
        if (in_array('disabled', $policy->policies, true) || in_array('server-only', $policy->policies, true)) {
            return new PolicyDecision(false, 'resource_unavailable');
        }

        if (in_array('same-origin', $policy->policies, true) && !$this->origins->allows($origin)) {
            return new PolicyDecision(false, 'origin_denied');
        }

        if (in_array('authenticated', $policy->policies, true) && $identity->userId === null) {
            return new PolicyDecision(false, 'authentication_required');
        }

        if (in_array('token', $policy->policies, true) && $identity->tokenId === null) {
            return new PolicyDecision(false, 'token_required');
        }

        if (
            in_array('ip-allowlist', $policy->policies, true)
            && !$this->networks->matchesAny($allowedNetworks, $clientIp)
        ) {
            return new PolicyDecision(false, 'ip_denied');
        }

        if ($identity->tokenCidr !== null && !$this->networks->contains($identity->tokenCidr, $clientIp)) {
            return new PolicyDecision(false, 'token_ip_denied');
        }

        foreach ($policy->scopes as $scope) {
            if (!in_array($scope, $identity->scopes, true)) {
                return new PolicyDecision(false, 'scope_denied');
            }
        }

        if (
            $policy->permission !== null
            && ($identity->userId === null || !$this->authorization->allows($identity->userId, $policy->permission))
        ) {
            return new PolicyDecision(false, 'permission_denied');
        }

        return new PolicyDecision(true);
    }
}
