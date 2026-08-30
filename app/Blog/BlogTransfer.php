<?php

declare(strict_types=1);

namespace ReaCms\Blog;

use JsonException;

final class BlogTransfer
{
    /** @param list<BlogPost> $posts */
    public function export(array $posts, string $pluginVersion = '1.0.0'): string
    {
        return json_encode([
            'schemaVersion' => 1,
            'plugin' => ['id' => 'blog', 'version' => $pluginVersion],
            'exportedAt' => gmdate(DATE_ATOM),
            'posts' => array_map(static fn (BlogPost $post): array => $post->api(), $posts),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** @return list<array<string, mixed>> */
    public function validateImport(string $json): array
    {
        try {
            $document = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('The Blog import is not valid JSON.', previous: $exception);
        }
        if (
            !is_array($document) || ($document['schemaVersion'] ?? null) !== 1
            || ($document['plugin']['id'] ?? null) !== 'blog' || !is_array($document['posts'] ?? null)
        ) {
            throw new \InvalidArgumentException('The Blog import metadata is incompatible.');
        }
        $allowed = ['id', 'title', 'slug', 'excerpt', 'content', 'locale', 'publishedAt'];
        foreach ($document['posts'] as $post) {
            if (!is_array($post) || array_diff(array_keys($post), $allowed) !== []) {
                throw new \InvalidArgumentException('A Blog import post contains unknown fields.');
            }
        }
        return array_values($document['posts']);
    }

    /** @param list<BlogPost> $posts */
    public function sitemap(array $posts, string $baseUrl): string
    {
        $urls = array_map(static fn (BlogPost $post): string => sprintf(
            '<url><loc>%s/blog/%s</loc></url>',
            htmlspecialchars(rtrim($baseUrl, '/'), ENT_XML1, 'UTF-8'),
            htmlspecialchars($post->slug, ENT_XML1, 'UTF-8'),
        ), $posts);
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . implode('', $urls) . '</urlset>';
    }
}
