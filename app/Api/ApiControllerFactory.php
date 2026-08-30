<?php

declare(strict_types=1);

namespace ReaCms\Api;

use ReaCms\Api\Policy\NetworkMatcher;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Policy\PolicyEvaluator;
use ReaCms\Api\Policy\PdoPolicyRepository;
use ReaCms\Api\Query\ApiQuery;
use ReaCms\Api\RateLimit\PdoRateLimiter;
use ReaCms\Api\Token\ApiTokenAuthenticator;
use ReaCms\Api\Token\PdoApiTokenRepository;
use ReaCms\Auth\PdoAuthorization;
use ReaCms\Auth\PdoSessionRepository;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Database\ConnectionFactory;
use ReaCms\Support\SystemClock;

final class ApiControllerFactory
{
    public static function create(Environment $environment): ApiController
    {
        $pdo = ConnectionFactory::create($environment);
        $prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
        $clock = new SystemClock();
        $configured = array_filter(array_map('trim', explode(',', $environment->get('API_ALLOWED_ORIGINS', '') ?? '')));
        $origins = new OriginAllowlist(array_values(array_unique([
            $environment->require('APP_URL'),
            ...$configured,
        ])));

        return new ApiController(
            new PolicyEvaluator($origins, new NetworkMatcher(), new PdoAuthorization($pdo, $prefix)),
            $origins,
            new PdoRateLimiter($pdo, $prefix),
            static function (ApiQuery $query): array {
                $items = [['service' => 'rea-cms', 'status' => 'ok']];
                if (($query->filters['status'] ?? 'ok') !== 'ok') {
                    $items = [];
                }

                return [
                    'data' => $items,
                    'meta' => [
                        'page' => $query->page,
                        'perPage' => $query->perPage,
                        'total' => count($items),
                        'totalPages' => count($items) === 0 ? 0 : 1,
                    ],
                    'links' => [
                        'next' => null,
                        'previous' => null,
                    ],
                ];
            },
            (new PdoPolicyRepository($pdo, $prefix))->forResource('status', 'read'),
            (new ApiIdentityResolver(
                new ApiTokenAuthenticator(new PdoApiTokenRepository($pdo, $prefix), $clock),
                new PdoSessionRepository($pdo, $prefix),
                $clock,
            ))->resolve(...),
        );
    }
}
