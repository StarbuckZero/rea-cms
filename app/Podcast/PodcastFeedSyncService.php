<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateInterval;
use ReaCms\Support\Clock;
use Throwable;

final class PodcastFeedSyncService
{
    public function __construct(
        private readonly PodcastRepository $repository,
        private readonly FeedFetcher $fetcher,
        private readonly PodcastFeedParser $parser,
        private readonly Clock $clock,
    ) {
    }

    public function refreshIfDue(PodcastFeed $feed): bool
    {
        $settings = $this->repository->settings();
        if (!$settings->automaticRefresh || !$feed->automaticRefresh || !$feed->enabled) {
            return false;
        }
        $now = $this->clock->now();
        if ($feed->nextRefreshAt !== null && $feed->nextRefreshAt > $now) {
            return false;
        }
        return $this->refresh($feed, $settings, false);
    }

    public function refreshAllDue(): int
    {
        $refreshed = 0;
        foreach ($this->repository->feeds(true) as $feed) {
            if ($this->refreshIfDue($feed)) {
                $refreshed++;
            }
        }
        return $refreshed;
    }

    public function forceRefresh(PodcastFeed $feed): bool
    {
        return $this->refresh($feed, $this->repository->settings(), true);
    }

    private function refresh(PodcastFeed $feed, PodcastSettings $settings, bool $throwOnFailure): bool
    {
        $now = $this->clock->now();
        $next = $now->add(new DateInterval('PT' . $settings->intervalFor($feed) . 'M'));
        $token = $this->repository->acquireRefreshLock($feed->id, $now);
        if ($token === null) {
            return false;
        }
        try {
            $result = $this->fetcher->fetch($feed, $settings);
            if ($result->status === 304) {
                if ($feed->lastSuccessfulRefreshAt === null && $feed->contentHash === null) {
                    throw new PodcastException('The feed returned 304 before any valid podcast data was cached.');
                }
                $this->repository->storeUnchangedFeed($feed, $result, $now, $next, $token);
                return true;
            }
            $podcast = $this->parser->parse($result->body);
            if ($feed->contentHash !== null && hash_equals($feed->contentHash, $podcast->contentHash)) {
                $this->repository->storeUnchangedFeed($feed, $result, $now, $next, $token);
                return true;
            }
            $this->repository->storeUpdatedFeed($feed, $podcast, $result, $now, $next, $token);
            return true;
        } catch (Throwable $exception) {
            $status = $exception instanceof PodcastFetchException ? $exception->httpStatus : null;
            $this->repository->storeRefreshFailure(
                $feed->id,
                substr($exception->getMessage(), 0, 1000),
                $status,
                $now,
                $next,
                $token,
            );
            if ($throwOnFailure) {
                throw $exception;
            }
            return false;
        }
    }
}
