<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var list<array<string, mixed>> $media */
/** @var string $csrfToken */
/** @var bool $uploadFailed */
/** @var bool $deleteFailed */
/** @var bool $deleted */
?>
<section>
    <p class="eyebrow">Shared assets</p>
    <h1 class="mt-3 text-3xl font-bold">Media</h1>
    <?php if ($uploadFailed) : ?>
        <p class="mt-4" role="alert">Some files could not be uploaded. Successfully uploaded files appear below.
            Check the remaining files and try uploading only those files again.</p>
    <?php endif; ?>
    <?php if ($deleteFailed) : ?>
        <p class="mt-4" role="alert">Media could not be deleted. Remove any content references first.
            If it is unused, check the server’s file permissions and try again.</p>
    <?php elseif ($deleted) : ?>
        <p class="mt-4" role="status">Media deleted.</p>
    <?php endif; ?>
    <form class="panel mt-8 space-y-4" method="post" action="/cms/media" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label class="form-label">Images or videos
            <input class="form-input" type="file" name="media[]" multiple
                   accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime" required>
        </label>
        <label class="form-label">Alt text / accessible label
            <input class="form-input" name="alt_text">
        </label>
        <p class="text-sm text-secondary">Select one or more files. The alt text applies to every selected file.</p>
        <button class="button-primary" type="submit">Upload media</button>
    </form>
    <div class="media-grid mt-8">
        <?php foreach ($media as $asset) : ?>
            <?php $isVideo = str_starts_with((string) $asset['mime_type'], 'video/'); ?>
            <article class="plugin-card">
                <?php if ($isVideo) : ?>
                    <video controls preload="metadata" src="/cms/media/<?= (int) $asset['id'] ?>"></video>
                <?php else : ?>
                    <img src="/cms/media/<?= (int) $asset['id'] ?>" alt="<?= $escape($asset['alt_text']) ?>">
                <?php endif; ?>
                <p class="mt-3 font-semibold"><?= $escape($asset['original_name']) ?></p>
                <p class="text-sm text-secondary"><?= $escape($asset['mime_type']) ?></p>
                <form class="mt-3" method="post" action="/cms/media/<?= (int) $asset['id'] ?>/delete"
                      data-confirm-delete="<?= $escape($asset['original_name']) ?>">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <button class="button-secondary" type="submit"
                            aria-label="Delete <?= $escape($asset['original_name']) ?>">Delete</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script src="/assets/text-blocks.js?v=1" defer></script>
