<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var array<string, mixed> $item */
/** @var list<string> $errors */
/** @var bool $saved */
/** @var string $csrfToken */
$id = (int) $item['id'];
?>
<section>
    <h1 class="text-3xl font-bold">Edit image details</h1>
    <?php if ($saved) : ?>
        <p role="status">Image details saved.</p>
    <?php endif; ?>
    <?php if ($errors !== []) : ?>
        <ul role="alert">
            <?php foreach ($errors as $error) : ?>
                <li><?= $escape($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <img class="mt-4" width="320" src="/cms/media/<?= (int) $item['media_id'] ?>?thumbnail=1"
         alt="<?= $escape($item['alt_text']) ?>">
    <form class="panel mt-4 space-y-4" method="post" action="/cms/gallery/<?= $id ?>/image">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label class="form-label">Image name
            <input class="form-input" name="original_name" required maxlength="255"
                   value="<?= $escape($item['original_name']) ?>">
        </label>
        <p class="form-help">This display name is shared wherever this uploaded image is used.
            The stored file and its URL stay the same.</p>
        <label class="form-label">Alt text
            <textarea class="form-input" name="alt_text" maxlength="500"
                      rows="3"><?= $escape($item['alt_text']) ?></textarea>
        </label>
        <p class="form-help">Describe the image, or leave blank for a decorative image. This updates this Gallery
            item and the Media library default; other Gallery items keep their own alt text.</p>
        <button class="button-primary" type="submit">Save image details</button>
    </form>
    <p class="mt-4"><a href="/cms/gallery/<?= $id ?>/edit">Back to Gallery item</a></p>
    <?php if ((int) $item['album_id'] > 0) : ?>
        <p><a href="/cms/gallery/albums/<?= (int) $item['album_id'] ?>/edit">Back to album</a></p>
    <?php endif; ?>
</section>
