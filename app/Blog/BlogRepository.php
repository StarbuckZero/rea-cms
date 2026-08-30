<?php

declare(strict_types=1);

namespace ReaCms\Blog;

use DateTimeImmutable;

interface BlogRepository
{
    /** @return list<BlogPost> */
    public function published(string $locale, DateTimeImmutable $now, int $limit, int $offset): array;

    public function countPublished(string $locale, DateTimeImmutable $now): int;

    public function findPublishedById(int $id, string $locale, DateTimeImmutable $now): ?BlogPost;

    public function findPublishedBySlug(string $slug, string $locale, DateTimeImmutable $now): ?BlogPost;
}
