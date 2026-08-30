<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Api\Policy;

use PHPUnit\Framework\TestCase;
use ReaCms\Api\Policy\ApiIdentity;
use ReaCms\Api\Policy\NetworkMatcher;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Policy\PolicyEvaluator;
use ReaCms\Api\Policy\PolicySet;
use ReaCms\Tests\Support\InMemoryAuthorization;

final class PolicyEvaluatorTest extends TestCase
{
    public function testSameOriginNeverAuthorizesARequestWithoutAnOrigin(): void
    {
        $evaluator = $this->evaluator();

        self::assertFalse($evaluator->evaluate(
            new PolicySet(['same-origin']),
            null,
            '127.0.0.1',
            new ApiIdentity(),
        )->allowed);
        self::assertTrue($evaluator->evaluate(
            new PolicySet(['same-origin']),
            'http://rea-cms.test',
            '127.0.0.1',
            new ApiIdentity(),
        )->allowed);
    }

    public function testOriginMatchingIsExactAndRejectsNullOrigin(): void
    {
        $origins = new OriginAllowlist(['https://example.test:8443']);

        self::assertTrue($origins->allows('https://example.test:8443'));
        self::assertFalse($origins->allows('https://example.test'));
        self::assertFalse($origins->allows('http://example.test:8443'));
        self::assertFalse($origins->allows('null'));
    }

    public function testCombinedTokenScopePermissionAndIpRequirementsAllApply(): void
    {
        $authorization = new InMemoryAuthorization();
        $authorization->permissions[7] = ['core.api.status.read'];
        $evaluator = new PolicyEvaluator(
            new OriginAllowlist(['http://rea-cms.test']),
            new NetworkMatcher(),
            $authorization,
        );
        $policy = new PolicySet(
            ['same-origin', 'authenticated', 'token', 'ip-allowlist'],
            ['status:read'],
            'core.api.status.read',
        );
        $identity = new ApiIdentity(7, 9, ['status:read'], '192.0.2.0/24');

        self::assertTrue($evaluator->evaluate(
            $policy,
            'http://rea-cms.test',
            '192.0.2.12',
            $identity,
            ['192.0.2.0/24'],
        )->allowed);
        self::assertFalse($evaluator->evaluate(
            $policy,
            'http://rea-cms.test',
            '198.51.100.12',
            $identity,
            ['192.0.2.0/24'],
        )->allowed);
    }

    public function testPluginPolicyCannotWeakenGlobalPolicy(): void
    {
        $combined = PolicySet::combine(new PolicySet(['token']), new PolicySet(['public']));

        self::assertSame(['token'], $combined->policies);
        self::assertFalse($this->evaluator()->evaluate(
            $combined,
            null,
            '127.0.0.1',
            new ApiIdentity(),
        )->allowed);
    }

    public function testNetworkMatcherSupportsIpv4AndIpv6Cidrs(): void
    {
        $matcher = new NetworkMatcher();

        self::assertTrue($matcher->contains('10.0.0.0/8', '10.4.5.6'));
        self::assertFalse($matcher->contains('10.0.0.0/8', '11.4.5.6'));
        self::assertTrue($matcher->contains('2001:db8::/32', '2001:db8::1234'));
        self::assertFalse($matcher->contains('2001:db8::/32', '2001:db9::1'));
    }

    private function evaluator(): PolicyEvaluator
    {
        return new PolicyEvaluator(
            new OriginAllowlist(['http://rea-cms.test']),
            new NetworkMatcher(),
            new InMemoryAuthorization(),
        );
    }
}
