<?php

declare(strict_types=1);

namespace ReaCms\Blog;

use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Query\ApiQuery;
use ReaCms\Api\Serialization\SerializerRegistry;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Routing\RouteNotFound;
use ReaCms\Plugin\PluginRouteGate;
use ReaCms\Support\Clock;

final class BlogController
{
    public function __construct(
        private readonly BlogRepository $posts,
        private readonly PluginRouteGate $plugins,
        private readonly OriginAllowlist $origins,
        private readonly Clock $clock,
    ) {
    }

    public function collection(Request $request, string $format): Response
    {
        $this->requireEnabled();
        $this->requireApiOrigin($request);
        $serializer = (new SerializerRegistry())->get($format);
        if ($serializer === null) {
            return Response::json(['error' => ['code' => 'not_acceptable', 'message' => 'Unsupported format.']], 406);
        }
        $query = ApiQuery::fromArray($request->query(), [], ['publishedAt'], 100);
        $locale = $this->locale($request);
        $total = $this->posts->countPublished($locale, $this->clock->now());
        $posts = $this->posts->published(
            $locale,
            $this->clock->now(),
            $query->perPage,
            ($query->page - 1) * $query->perPage,
        );
        return $this->cors($request, $serializer->serialize([
            'data' => array_map(static fn (BlogPost $post): array => $post->api(), $posts),
            'meta' => ['page' => $query->page, 'perPage' => $query->perPage, 'total' => $total,
                'totalPages' => (int) ceil($total / $query->perPage)],
            'links' => ['self' => sprintf('/api/v1/blog.%s?page=%d', $format, $query->page)],
        ]));
    }

    public function item(Request $request, int $id, string $format): Response
    {
        $this->requireEnabled();
        $this->requireApiOrigin($request);
        $serializer = (new SerializerRegistry())->get($format);
        if ($serializer === null) {
            return Response::json(['error' => ['code' => 'not_acceptable', 'message' => 'Unsupported format.']], 406);
        }
        $post = $this->posts->findPublishedById($id, $this->locale($request), $this->clock->now());
        if ($post === null) {
            throw new RouteNotFound();
        }
        return $this->cors($request, $serializer->serialize(['data' => $post->api()]));
    }

    public function publicIndex(Request $request): Response
    {
        $this->requireEnabled();
        $posts = $this->posts->published($this->locale($request), $this->clock->now(), 20, 0);
        $items = array_map(static fn (BlogPost $post): string => sprintf(
            '<article><h2><a href="/blog/%s">%s</a></h2><p>%s</p></article>',
            rawurlencode($post->slug),
            htmlspecialchars($post->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($post->excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ), $posts);
        return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Blog</title></head>'
            . '<body><main><h1>Blog</h1>' . implode('', $items) . '</main></body></html>');
    }

    public function publicDetail(Request $request, string $slug): Response
    {
        $this->requireEnabled();
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            throw new RouteNotFound();
        }
        $post = $this->posts->findPublishedBySlug($slug, $this->locale($request), $this->clock->now());
        if ($post === null) {
            throw new RouteNotFound();
        }
        return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'
            . htmlspecialchars($post->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</title></head><body><main><article><h1>'
            . htmlspecialchars($post->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</h1>' . \ReaCms\Plugin\SafeHtml::sanitize($post->content)->value
            . '</article></main></body></html>');
    }

    private function requireEnabled(): void
    {
        if (!$this->plugins->exposes('blog')) {
            throw new RouteNotFound();
        }
    }

    private function requireApiOrigin(Request $request): void
    {
        if (!$this->origins->allows($request->header('origin'))) {
            throw new RouteNotFound();
        }
    }

    private function cors(Request $request, Response $response): Response
    {
        $origin = $request->header('origin');
        return $origin === null ? $response : $response->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')->withHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    private function locale(Request $request): string
    {
        $locale = $request->query()['locale'] ?? 'en';
        return is_string($locale) && preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D', $locale) === 1 ? $locale : 'en';
    }
}
