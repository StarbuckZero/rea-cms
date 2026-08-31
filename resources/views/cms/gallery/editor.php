<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var array<string, mixed>|null $item */
/** @var list<array<string, mixed>> $media */
/** @var list<array<string, mixed>> $albums */
/** @var string $csrfToken */
$id = (int) ($item['id'] ?? 0);
?>
<section>
    <p class="eyebrow">Gallery</p>
    <h1 class="mt-3 text-3xl font-bold"><?= $id ? 'Edit media' : 'Add media' ?></h1>
    <form class="panel mt-8 space-y-5" method="post"
          action="<?= $id ? '/cms/gallery/' . $id : '/cms/gallery' ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label class="form-label">Image or video
            <select class="form-input" name="media_id" required>
                <option value="">Choose from Media</option>
                <?php foreach ($media as $asset) : ?>
                    <?php $type = str_starts_with((string) $asset['mime_type'], 'video/') ? 'video' : 'image'; ?>
                    <option value="<?= (int) $asset['id'] ?>"
                        <?= (int) ($item['media_id'] ?? 0) === (int) $asset['id'] ? 'selected' : '' ?>>
                        <?= $escape($asset['original_name']) ?> (<?= $escape($type) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p><a href="/cms/media">Upload a new image or video in Media</a></p>
        <label class="form-label">Album
            <select class="form-input" name="album_id">
                <option value="">No album</option>
                <?php foreach ($albums as $album) : ?>
                    <option value="<?= (int) $album['id'] ?>"
                        <?= (int) ($item['album_id'] ?? 0) === (int) $album['id'] ? 'selected' : '' ?>>
                        <?= $escape($album['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-label">Title
            <input class="form-input" name="title" value="<?= $escape($item['title'] ?? '') ?>">
        </label>
        <label class="form-label">Description / caption
            <textarea class="form-input" name="caption" rows="4"><?= $escape($item['caption'] ?? '') ?></textarea>
        </label>
        <label class="form-label">Alt text / accessible label
            <input class="form-input" name="alt_text" value="<?= $escape($item['alt_text'] ?? '') ?>">
        </label>
        <label class="form-label">Display order
            <input class="form-input" type="number" name="position" value="<?= (int) ($item['position'] ?? 0) ?>">
        </label>
        <label>
            <input type="checkbox" name="status" value="active"
                <?= ($item['status'] ?? '') === 'active' ? 'checked' : '' ?>>
            Published / active
        </label>
        <button class="button-primary" type="submit">Save media</button>
    </form>
    <?php if ($id) : ?>
        <form class="mt-4" method="post" action="/cms/gallery/<?= $id ?>/delete">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <button class="button-secondary" type="submit">Remove from Gallery</button>
        </form>
    <?php endif; ?>
</section>
