<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var list<array<string, mixed>> $media */
/** @var string $csrfToken */
?>
<section>
    <p class="eyebrow">Shared assets</p>
    <h1 class="mt-3 text-3xl font-bold">Media</h1>
    <form class="panel mt-8 space-y-4" method="post" action="/cms/media" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label class="form-label">Image or video
            <input class="form-input" type="file" name="media"
                   accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime" required>
        </label>
        <label class="form-label">Alt text / accessible label
            <input class="form-input" name="alt_text">
        </label>
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
            </article>
        <?php endforeach; ?>
    </div>
</section>
