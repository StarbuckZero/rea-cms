<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

final class FeedFetchResult
{
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly ?string $etag = null,
        public readonly ?string $lastModified = null,
    ) {
        if ($status !== 200 && $status !== 304) {
            throw new PodcastException('A feed fetch result must be HTTP 200 or 304.');
        }
    }
}
