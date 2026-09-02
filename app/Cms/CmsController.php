<?php

declare(strict_types=1);

namespace ReaCms\Cms;

use ReaCms\Auth\AuthServices;
use ReaCms\Auth\SessionContext;
use ReaCms\Auth\User;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Content\Slugger;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Gallery\GalleryApiPresenter;
use ReaCms\Media\MediaException;
use ReaCms\Media\MediaIngestor;
use ReaCms\Plugin\PluginNavigation;
use ReaCms\Plugin\SafeHtml;

final class CmsController
{
    public function __construct(
        private readonly AuthServices $auth,
        private readonly PdoCmsRepository $contents,
        private readonly ViewRenderer $views,
        private readonly string $uploadRoot,
        private readonly OriginAllowlist $origins,
        private readonly PluginApiRenderer $api,
    ) {
    }

    public function blogIndex(Request $request): Response
    {
        return $this->page($request, 'blog', 'Blogs', 'cms/blog/index', ['posts' => $this->contents->blogPosts()]);
    }

    public function blogForm(Request $request, ?int $id = null): Response
    {
        $post = $id === null ? null : $this->contents->blogPost($id);
        if ($id !== null && $post === null) {
            return Response::redirect('/cms/blog');
        }
        return $this->page($request, 'blog', $id === null ? 'New blog post' : 'Edit blog post', 'cms/blog/editor', [
            'post' => $post,
            'media' => $this->contents->images(),
        ]);
    }

    public function saveBlog(Request $request, ?int $id = null): Response
    {
        $context = $this->authorized($request, 'blog');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->auth->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $title = trim($form['title'] ?? '');
        $content = SafeHtml::sanitize($form['content'] ?? '')->value;
        if ($title === '' || (trim(strip_tags($content)) === '' && !str_contains($content, '<img'))) {
            return $this->failure($request, $session, $user, 422, 'Blog post incomplete', 'cms/message', [
                'message' => 'A title and content are required.',
            ]);
        }
        $slug = (new Slugger())->slug($form['slug'] ?? $title);
        $status = ($form['status'] ?? '') === 'published' ? 'published' : 'draft';
        $mediaId = filter_var($form['featured_media_id'] ?? null, FILTER_VALIDATE_INT);
        $this->contents->saveBlog($id, $user->id, [
            'title' => $title, 'slug' => $slug, 'excerpt' => trim($form['excerpt'] ?? ''),
            'content' => $content, 'status' => $status, 'locale' => 'en', 'visibility' => 'public',
            'featured_media_id' => is_int($mediaId) ? $mediaId : null,
            'publish_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ]);
        return $this->auth->sessions->withCookie(Response::redirect('/cms/blog'), $session);
    }

    public function uploadBlogImage(Request $request): Response
    {
        $context = $this->authorized($request, 'blog');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->auth->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->auth->sessions->withCookie(Response::json(['error' => [
                'code' => 'invalid_csrf',
                'message' => 'The upload session expired. Refresh and try again.',
            ]], 419), $session);
        }
        $file = $request->file('image');
        if ($file === null || !$file->isValid()) {
            return $this->auth->sessions->withCookie(Response::json([
                'error' => ['code' => 'invalid_upload', 'message' => 'Choose a valid image to upload.'],
            ], 422), $session);
        }

        try {
            $stored = (new MediaIngestor(
                $this->uploadRoot,
                scanner: static fn (string $_path, string $mime): bool => str_starts_with($mime, 'image/'),
            ))->ingest($file->temporaryPath, $file->clientName);
            $altText = trim($form['alt_text'] ?? '');
            $id = $this->contents->addMedia($stored, $user->id, $altText);
        } catch (MediaException) {
            return $this->auth->sessions->withCookie(Response::json([
                'error' => [
                    'code' => 'invalid_image',
                    'message' => 'The image must be a valid JPEG, PNG, or WebP file no larger than 25 MB.',
                ],
            ], 422), $session);
        }

        return $this->auth->sessions->withCookie(Response::json(['media' => [
            'id' => $id,
            'url' => '/media/' . $id,
            'thumbnailUrl' => '/cms/media/' . $id,
            'alt' => $altText,
            'title' => $stored['originalName'],
        ]], 201), $session);
    }

    public function deleteBlog(Request $request, int $id): Response
    {
        return $this->delete($request, 'blog', '/cms/blog', fn () => $this->contents->deleteBlog($id));
    }

    public function galleryIndex(Request $request): Response
    {
        return $this->page($request, 'gallery', 'Gallery', 'cms/gallery/index', [
            'items' => $this->contents->galleryItems(),
            'albums' => $this->contents->galleryAlbums(),
        ]);
    }

    public function galleryFeed(Request $request, string $format): Response
    {
        if (!$this->galleryApiEnabled($request)) {
            return Response::json(['error' => ['code' => 'not_found', 'message' => 'Gallery is unavailable.']], 404);
        }
        $presenter = new GalleryApiPresenter();
        $items = array_values(array_map($presenter->item(...), $this->publicGalleryItems()));
        return $this->serializeGallery(
            $request,
            $format,
            'list',
            ['data' => $items, 'meta' => ['total' => count($items)]],
        );
    }

    public function galleryApiItem(Request $request, int $id, string $format): Response
    {
        if (!$this->galleryApiEnabled($request)) {
            return $this->galleryNotFound();
        }
        $item = $this->contents->galleryItem($id);
        if ($item === null || !$this->isPublicGalleryItem($item)) {
            return $this->galleryNotFound();
        }
        return $this->serializeGallery(
            $request,
            $format,
            'detail',
            ['data' => (new GalleryApiPresenter())->item($item)],
        );
    }

    public function galleryAlbumsFeed(Request $request, string $format): Response
    {
        if (!$this->galleryApiEnabled($request)) {
            return $this->galleryNotFound();
        }
        $presenter = new GalleryApiPresenter();
        $albums = array_values(array_map(
            $presenter->album(...),
            array_filter($this->contents->galleryAlbums(), static fn (array $album): bool => (
                ($album['status'] ?? '') === 'published'
            ))
        ));
        return $this->serializeGallery($request, $format, 'list', [
            'data' => $albums,
            'meta' => ['total' => count($albums)],
        ]);
    }

    public function galleryAlbumFeed(Request $request, int $id, string $format): Response
    {
        if (!$this->galleryApiEnabled($request)) {
            return $this->galleryNotFound();
        }
        $album = $this->contents->galleryAlbum($id);
        if ($album === null || ($album['status'] ?? '') !== 'published') {
            return $this->galleryNotFound();
        }
        return $this->serializeGallery(
            $request,
            $format,
            'detail',
            ['data' => (new GalleryApiPresenter())->album($album)],
        );
    }

    public function galleryAlbumItemsFeed(Request $request, int $id, string $format): Response
    {
        if (!$this->galleryApiEnabled($request)) {
            return $this->galleryNotFound();
        }
        $album = $this->contents->galleryAlbum($id);
        if ($album === null || ($album['status'] ?? '') !== 'published') {
            return $this->galleryNotFound();
        }
        $presenter = new GalleryApiPresenter();
        $items = array_map($presenter->item(...), $this->publicGalleryItems($id));
        return $this->serializeGallery($request, $format, 'list', [
            'data' => $items,
            'meta' => ['albumId' => $id, 'total' => count($items)],
        ]);
    }

    public function galleryForm(Request $request, ?int $id = null): Response
    {
        $item = $id === null ? null : $this->contents->galleryItem($id);
        if ($id !== null && $item === null) {
            return Response::redirect('/cms/gallery');
        }
        return $this->page(
            $request,
            'gallery',
            $id === null ? 'Add gallery item' : 'Edit gallery item',
            'cms/gallery/editor',
            ['item' => $item, 'media' => $this->contents->media(), 'albums' => $this->contents->galleryAlbums()]
        );
    }

    public function saveGallery(Request $request, ?int $id = null): Response
    {
        $context = $this->authorized($request, 'gallery');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->auth->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $mediaId = filter_var($form['media_id'] ?? null, FILTER_VALIDATE_INT);
        $medium = is_int($mediaId) ? $this->contents->medium($mediaId) : null;
        $mediaType = $medium === null ? null : GalleryApiPresenter::mediaType((string) $medium['mime_type']);
        if (!is_int($mediaId) || $mediaType === null) {
            return Response::redirect('/cms/gallery/new?error=media');
        }
        $albumId = filter_var($form['album_id'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($albumId) || $albumId < 1 || $this->contents->galleryAlbum($albumId) === null) {
            $albumId = 0;
        }
        $this->contents->saveGallery($id, ['album_id' => $albumId, 'media_id' => $mediaId,
            'media_type' => $mediaType, 'title' => trim($form['title'] ?? ''),
            'caption' => trim($form['caption'] ?? ''), 'alt_text' => trim($form['alt_text'] ?? ''),
            'position' => (int) ($form['position'] ?? 0),
            'status' => ($form['status'] ?? '') === 'active' ? 'active' : 'inactive']);
        return $this->auth->sessions->withCookie(Response::redirect('/cms/gallery'), $session);
    }

    public function deleteGallery(Request $request, int $id): Response
    {
        return $this->delete($request, 'gallery', '/cms/gallery', fn () => $this->contents->deleteGallery($id));
    }

    public function galleryAlbumIndex(Request $request): Response
    {
        return $this->page($request, 'gallery', 'Gallery albums', 'cms/gallery/albums', [
            'albums' => $this->contents->galleryAlbums(),
        ]);
    }

    public function galleryAlbumForm(Request $request, ?int $id = null): Response
    {
        $album = $id === null ? null : $this->contents->galleryAlbum($id);
        if ($id !== null && $album === null) {
            return Response::redirect('/cms/gallery/albums');
        }
        return $this->page(
            $request,
            'gallery',
            $id === null ? 'New album' : 'Edit album',
            'cms/gallery/album-editor',
            [
                'album' => $album,
                'items' => $id === null ? [] : $this->contents->galleryItems($id),
                'images' => $this->contents->images(),
            ]
        );
    }

    public function saveGalleryAlbum(Request $request, ?int $id = null): Response
    {
        $context = $this->authorized($request, 'gallery');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->auth->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $title = trim($form['title'] ?? '');
        if ($title === '') {
            return $this->failure($request, $session, $user, 422, 'Album incomplete', 'cms/message', [
                'message' => 'An album title is required.',
            ]);
        }
        $coverId = filter_var($form['cover_media_id'] ?? null, FILTER_VALIDATE_INT);
        $cover = is_int($coverId) ? $this->contents->medium($coverId) : null;
        if ($cover === null || GalleryApiPresenter::mediaType((string) $cover['mime_type']) !== 'image') {
            $coverId = null;
        }
        $this->contents->saveGalleryAlbum($id, [
            'title' => $title,
            'slug' => (new Slugger())->slug($form['slug'] ?? $title),
            'description' => trim($form['description'] ?? ''),
            'status' => ($form['status'] ?? '') === 'published' ? 'published' : 'draft',
            'cover_media_id' => $coverId,
            'position' => (int) ($form['position'] ?? 0),
        ]);
        return $this->auth->sessions->withCookie(Response::redirect('/cms/gallery/albums'), $session);
    }

    public function deleteGalleryAlbum(Request $request, int $id): Response
    {
        return $this->delete(
            $request,
            'gallery',
            '/cms/gallery/albums',
            fn () => $this->contents->deleteGalleryAlbum($id)
        );
    }

    public function reorderGalleryAlbum(Request $request, int $id): Response
    {
        $context = $this->authorized($request, 'gallery');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->auth->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $positions = [];
        foreach ($form as $key => $value) {
            if (preg_match('/^position_([1-9][0-9]*)$/D', $key, $matches) === 1) {
                $positions[(int) $matches[1]] = (int) $value;
            }
        }
        $this->contents->reorderGalleryAlbum($id, $positions);
        return $this->auth->sessions->withCookie(
            Response::redirect('/cms/gallery/albums/' . $id . '/edit'),
            $session
        );
    }

    public function mediaIndex(Request $request): Response
    {
        return $this->page($request, 'media', 'Media', 'cms/media/index', ['media' => $this->contents->media()]);
    }

    public function uploadMedia(Request $request): Response
    {
        $context = $this->authorized($request, 'media');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->auth->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $file = $_FILES['media'] ?? $_FILES['image'] ?? null;
        if (
            !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)
        ) {
            return Response::redirect('/cms/media?error=upload');
        }
        try {
            $stored = (new MediaIngestor($this->uploadRoot))->ingest($file['tmp_name'], $file['name']);
            $this->contents->addMedia($stored, $user->id, trim($form['alt_text'] ?? ''));
        } catch (MediaException) {
            return Response::redirect('/cms/media?error=validation');
        }
        return $this->auth->sessions->withCookie(Response::redirect('/cms/media'), $session);
    }

    public function medium(Request $request, int $id): Response
    {
        $context = $this->authorized($request, 'media');
        if ($context instanceof Response) {
            return $context;
        }
        $medium = $this->contents->medium($id);
        if ($medium === null) {
            return new Response('', 404);
        }
        $path = $this->uploadRoot . '/' . basename((string) $medium['stored_name']);
        $body = file_get_contents($path);
        return $body === false ? new Response('', 404) : new Response($body, 200, [
            'Content-Type' => (string) $medium['mime_type'], 'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function publicMedium(int $id): Response
    {
        $medium = $this->contents->medium($id);
        if ($medium === null || ($medium['visibility'] ?? '') !== 'public') {
            return new Response('', 404);
        }
        $body = file_get_contents($this->uploadRoot . '/' . basename((string) $medium['stored_name']));
        return $body === false ? new Response('', 404) : new Response($body, 200, [
            'Content-Type' => (string) $medium['mime_type'], 'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function publicGalleryItems(?int $albumId = null): array
    {
        return array_values(array_filter(
            $this->contents->galleryItems($albumId),
            fn (array $item): bool => $this->isPublicGalleryItem($item)
        ));
    }

    /** @param array<string, mixed>|null $item */
    private function isPublicGalleryItem(?array $item): bool
    {
        return $item !== null && ($item['status'] ?? '') === 'active'
            && ($item['visibility'] ?? '') === 'public'
            && GalleryApiPresenter::mediaType((string) $item['mime_type']) !== null;
    }

    /** @param array<string, mixed> $document */
    private function serializeGallery(Request $request, string $format, string $mode, array $document): Response
    {
        if ($this->auth->plugins->find('gallery')?->state !== 'enabled') {
            return $this->galleryNotFound();
        }
        $response = $this->api->render('gallery', 'gallery', $format, $mode, $document);
        return $response === null
            ? Response::json(['error' => ['code' => 'not_acceptable', 'message' => 'Unsupported format.']], 406)
            : $this->galleryCors($request, $response);
    }

    private function galleryNotFound(): Response
    {
        return Response::json(['error' => ['code' => 'not_found', 'message' => 'Gallery content was not found.']], 404);
    }

    private function galleryApiEnabled(Request $request): bool
    {
        return $this->auth->plugins->find('gallery')?->state === 'enabled'
            && $this->origins->allows($request->header('origin'));
    }

    private function galleryCors(Request $request, Response $response): Response
    {
        $origin = $request->header('origin');
        return $origin === null ? $response : $response->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')->withHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /** @param array<string, mixed> $data */
    private function page(Request $request, string $pluginId, string $title, string $view, array $data): Response
    {
        $context = $this->authorized($request, $pluginId);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $content = $this->views->render($view, [...$data, 'csrfToken' => $this->auth->csrf->token($session->token)]);
        return $this->auth->sessions->withCookie($this->render($request, $user, $title, $content), $session);
    }

    /** @return array{\ReaCms\Auth\SessionContext, User}|Response */
    private function authorized(Request $request, string $pluginId): array|Response
    {
        $session = $this->auth->sessions->start($request);
        $user = $session->record->userId === null ? null : $this->auth->users->findById($session->record->userId);
        if ($user === null || !$user->isActive()) {
            return $this->auth->sessions->withCookie(Response::redirect('/login'), $session);
        }
        $pluginEnabled = $pluginId === 'media' || $this->auth->plugins->find($pluginId)?->state === 'enabled';
        $allowed = $pluginId === 'media'
            ? $this->auth->authorization->allows($user->id, 'core.admin.access')
                || $this->auth->pluginAccess->allows($user->id, 'media')
            : $this->auth->pluginAccess->allows($user->id, $pluginId);
        if (!$pluginEnabled || !$allowed) {
            return $this->auth->sessions->withCookie($this->render(
                $request,
                $user,
                'Access denied',
                $this->views->render('errors/forbidden'),
                403,
            ), $session);
        }
        return [$session, $user];
    }

    private function render(Request $request, User $user, string $title, string $content, int $status = 200): Response
    {
        return Response::html($this->views->render('layouts/base', [
            'title' => $title,
            'theme' => ThemePreference::parse($user->theme),
            'content' => $content,
            'authenticatedUser' => $user,
            'csrfToken' => $this->auth->csrf->token($this->auth->sessions->start($request)->token),
            'canAccessAdmin' => $this->auth->authorization->allows($user->id, 'core.admin.access'),
            'canManagePlugins' => $this->auth->authorization->allows($user->id, 'core.admin.access')
                && $this->auth->authorization->allows($user->id, 'core.plugins.view'),
            'pluginNavigation' => (new PluginNavigation(
                $this->auth->plugins,
                $this->auth->pluginAccess,
            ))->forUser($user->id),
        ]), $status);
    }

    /** @param array<string, mixed> $data */
    private function failure(
        Request $request,
        SessionContext $session,
        User $user,
        int $status,
        string $title,
        string $view,
        array $data = [],
    ): Response {
        return $this->auth->sessions->withCookie($this->render(
            $request,
            $user,
            $title,
            $this->views->render($view, $data),
            $status,
        ), $session);
    }

    private function delete(Request $request, string $plugin, string $redirect, callable $delete): Response
    {
        $context = $this->authorized($request, $plugin);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->auth->csrf->validate($session->token, $request->form()['_csrf'] ?? null)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $delete();
        return $this->auth->sessions->withCookie(Response::redirect($redirect), $session);
    }
}
