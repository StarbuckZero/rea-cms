<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateTimeImmutable;

final class ParsedPodcastEpisode
{
    public function __construct(
        public readonly string $guid,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly string $content,
        public readonly string $link,
        public readonly string $audioUrl,
        public readonly ?int $audioLength,
        public readonly string $audioType,
        public readonly ?int $durationSeconds,
        public readonly string $imageUrl,
        public readonly bool $explicit,
        public readonly string $episodeType,
        public readonly ?DateTimeImmutable $publishedAt,
    ) {
    }
}
