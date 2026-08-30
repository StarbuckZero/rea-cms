<?php

declare(strict_types=1);

namespace ReaCms\Api;

use InvalidArgumentException;
use ReaCms\Api\Policy\ApiIdentity;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Policy\PolicyEvaluator;
use ReaCms\Api\Policy\PolicySet;
use ReaCms\Api\Query\ApiQuery;
use ReaCms\Api\RateLimit\RateLimiter;
use ReaCms\Api\Serialization\SerializerRegistry;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;

final class ApiController
{
    /** @var callable(ApiQuery): array<string, mixed> */
    private $query;

    /** @var callable(Request): ApiIdentity */
    private $identityResolver;

    /** @param callable(ApiQuery): array<string, mixed> $query */
    public function __construct(
        private readonly PolicyEvaluator $policies,
        private readonly OriginAllowlist $origins,
        private readonly RateLimiter $rateLimiter,
        callable $query,
        private readonly PolicySet $statusPolicy = new PolicySet(['same-origin']),
        ?callable $identityResolver = null,
    ) {
        $this->query = $query;
        $this->identityResolver = $identityResolver ?? static fn (Request $request): ApiIdentity => new ApiIdentity();
    }

    public function status(Request $request, string $format): Response
    {
        $serializer = (new SerializerRegistry())->get($format);
        if ($serializer === null) {
            return $this->error($request, 'not_acceptable', 'The requested representation is not available.', 406);
        }

        $origin = $request->header('origin');
        $identity = ($this->identityResolver)($request);
        $decision = $this->policies->evaluate(
            $this->statusPolicy,
            $origin,
            $request->clientIp(),
            $identity,
        );
        if (!$decision->allowed) {
            return $this->error($request, 'access_denied', 'Access to this API resource was denied.', 403);
        }

        $rateIdentity = $identity->tokenId === null ? $request->clientIp() : 'token:' . $identity->tokenId;
        $rate = $this->rateLimiter->consume('public-api', $rateIdentity, 60, 60);
        if (!$rate->allowed) {
            return $this->cors($request, $this->error(
                $request,
                'rate_limit_exceeded',
                'Too many requests.',
                429,
            )->withHeader('Retry-After', (string) $rate->retryAfter));
        }

        try {
            $query = ApiQuery::fromArray($request->query(), ['status'], ['service', 'status']);
        } catch (InvalidArgumentException) {
            return $this->cors($request, $this->error(
                $request,
                'validation_failed',
                'The request could not be processed.',
                422,
            ));
        }

        $document = ($this->query)($query);
        $links = is_array($document['links'] ?? null) ? $document['links'] : [];
        $links['self'] = sprintf('/api/v1/status.%s?page=%d', $format, $query->page);
        $document['links'] = $links;

        $response = $serializer->serialize($document)
            ->withHeader('X-RateLimit-Remaining', (string) $rate->remaining);

        return $this->cors($request, $response);
    }

    private function cors(Request $request, Response $response): Response
    {
        $origin = $request->header('origin');
        if (!$this->origins->allows($origin) || $origin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    private function error(Request $request, string $code, string $message, int $status): Response
    {
        return Response::json([
            'error' => ['code' => $code, 'message' => $message, 'fields' => []],
            'requestId' => $request->requestId(),
        ], $status);
    }
}
