<?php

declare(strict_types=1);

namespace ReaCms\Blog;

use DateTimeImmutable;

final class BlogPost
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $excerpt,
        public readonly string $content,
        public readonly string $status,
        public readonly string $visibility,
        public readonly string $locale,
        public readonly ?DateTimeImmutable $publishAt,
    ) {
    }

    /** @return array<string, mixed> */
    public function api(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'locale' => $this->locale,
            'publishedAt' => $this->publishAt?->format(DATE_ATOM),
        ];
    }
}
