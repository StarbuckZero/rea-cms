<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var list<array<string, mixed>> $items */
/** @var list<array<string, mixed>> $albums */
?>
<section>
    <p class="eyebrow">Content</p>
    <div class="widget-heading mt-3">
        <h1 class="text-3xl font-bold">Gallery</h1>
        <div class="button-row">
            <a class="button-secondary" href="/cms/gallery/albums">Manage albums</a>
            <a class="button-primary" href="/cms/gallery/new">Add media</a>
        </div>
    </div>
    <p class="mt-3 text-secondary">
        <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> across
        <?= count($albums) ?> album<?= count($albums) === 1 ? '' : 's' ?>.
    </p>
    <div class="media-grid mt-8">
        <?php foreach ($items as $item) : ?>
            <?php $mediaType = str_starts_with((string) $item['mime_type'], 'video/') ? 'video' : 'image'; ?>
            <article class="plugin-card">
                <?php if ($mediaType === 'video') : ?>
                    <video controls preload="metadata" src="/cms/media/<?= (int) $item['media_id'] ?>"></video>
                <?php else : ?>
                    <img src="/cms/media/<?= (int) $item['media_id'] ?>"
                         alt="<?= $escape($item['alt_text']) ?>">
                <?php endif; ?>
                <p class="eyebrow mt-3"><?= $escape($mediaType) ?></p>
                <h2 class="mt-2 font-semibold">
                    <?= $escape($item['title'] ?: $item['original_name']) ?>
                </h2>
                <p class="text-secondary"><?= $escape($item['caption']) ?></p>
                <p class="text-sm">
                    Order <?= (int) $item['position'] ?> · <?= $escape($item['status'] ?? 'inactive') ?>
                </p>
                <a href="/cms/gallery/<?= (int) $item['id'] ?>/edit">Edit</a>
            </article>
        <?php endforeach; ?>
        <?php if ($items === []) : ?>
            <p class="empty-state">No Gallery media yet.</p>
        <?php endif; ?>
    </div>
</section>
