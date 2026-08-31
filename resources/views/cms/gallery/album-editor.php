<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var array<string, mixed>|null $album */
/** @var list<array<string, mixed>> $items */
/** @var list<array<string, mixed>> $images */
/** @var string $csrfToken */
$id = (int) ($album['id'] ?? 0);
?>
<section>
    <p class="eyebrow">Gallery album</p>
    <h1 class="mt-3 text-3xl font-bold"><?= $id ? 'Edit album' : 'Create album' ?></h1>
    <form class="panel mt-8 space-y-5" method="post"
          action="<?= $id ? '/cms/gallery/albums/' . $id : '/cms/gallery/albums' ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label class="form-label">Title
            <input class="form-input" name="title" value="<?= $escape($album['title'] ?? '') ?>" required>
        </label>
        <label class="form-label">Slug
            <input class="form-input" name="slug" value="<?= $escape($album['slug'] ?? '') ?>"
                   placeholder="Generated from the title">
        </label>
        <label class="form-label">Description
            <textarea class="form-input" name="description"
                      rows="4"><?= $escape($album['description'] ?? '') ?></textarea>
        </label>
        <label class="form-label">Cover image
            <select class="form-input" name="cover_media_id">
                <option value="">Use the default Gallery cover</option>
                <?php foreach ($images as $image) : ?>
                    <option value="<?= (int) $image['id'] ?>"
                        <?= (int) ($album['cover_media_id'] ?? 0) === (int) $image['id'] ? 'selected' : '' ?>>
                        <?= $escape($image['original_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="text-sm text-secondary">
            Covers can use any image in Media, including an image already assigned to this album.
        </p>
        <label class="form-label">Album order
            <input class="form-input" type="number" name="position" value="<?= (int) ($album['position'] ?? 0) ?>">
        </label>
        <label>
            <input type="checkbox" name="status" value="published"
                <?= ($album['status'] ?? '') === 'published' ? 'checked' : '' ?>>
            Published / active
        </label>
        <button class="button-primary" type="submit">Save album</button>
    </form>

    <?php if ($id) : ?>
        <div class="panel mt-8">
            <div class="widget-heading">
                <h2 class="text-xl font-bold">Album items</h2>
                <a href="/cms/gallery/new">Add Gallery media</a>
            </div>
            <?php if ($items !== []) : ?>
                <form class="space-y-4 mt-4" method="post"
                      action="/cms/gallery/albums/<?= $id ?>/reorder">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <?php foreach ($items as $item) : ?>
                        <label class="form-label">
                            <?= $escape($item['title'] ?: $item['original_name']) ?>
                            <input class="form-input" type="number" name="position_<?= (int) $item['id'] ?>"
                                   value="<?= (int) $item['position'] ?>">
                            <a href="/cms/gallery/<?= (int) $item['id'] ?>/edit">Edit item</a>
                        </label>
                    <?php endforeach; ?>
                    <button class="button-secondary" type="submit">Save item order</button>
                </form>
            <?php else : ?>
                <p class="empty-state mt-4">This album does not contain any items yet.</p>
            <?php endif; ?>
        </div>
        <form class="mt-4" method="post" action="/cms/gallery/albums/<?= $id ?>/delete">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <button class="button-secondary" type="submit">Delete album</button>
        </form>
        <p class="text-sm text-secondary">Deleting an album leaves its Gallery items unassigned.</p>
    <?php endif; ?>
</section>
