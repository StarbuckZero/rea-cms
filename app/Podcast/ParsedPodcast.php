<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

final class ParsedPodcast
{
    /** @param list<ParsedPodcastEpisode> $episodes */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $link,
        public readonly string $language,
        public readonly string $author,
        public readonly string $imageUrl,
        public readonly bool $explicit,
        public readonly array $episodes,
        public readonly string $contentHash,
    ) {
    }
}
