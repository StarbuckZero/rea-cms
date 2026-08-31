<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var list<array<string, mixed>> $albums */
?>
<section>
    <p class="eyebrow">Gallery</p>
    <div class="widget-heading mt-3">
        <h1 class="text-3xl font-bold">Albums</h1>
        <div class="button-row">
            <a class="button-secondary" href="/cms/gallery">All media</a>
            <a class="button-primary" href="/cms/gallery/albums/new">Create album</a>
        </div>
    </div>
    <div class="media-grid mt-8">
        <?php foreach ($albums as $album) : ?>
            <?php $cover = $album['cover_media_id'] === null
                ? '/assets/gallery-default-album-cover.svg'
                : '/cms/media/' . (int) $album['cover_media_id']; ?>
            <article class="plugin-card">
                <img src="<?= $escape($cover) ?>" alt="">
                <h2 class="mt-3 font-semibold"><?= $escape($album['title']) ?></h2>
                <p class="text-secondary"><?= $escape($album['description']) ?></p>
                <p class="text-sm">
                    <?= (int) $album['item_count'] ?> item<?= (int) $album['item_count'] === 1 ? '' : 's' ?> ·
                    <?= $escape($album['status']) ?>
                </p>
                <a href="/cms/gallery/albums/<?= (int) $album['id'] ?>/edit">View and edit</a>
            </article>
        <?php endforeach; ?>
        <?php if ($albums === []) : ?>
            <p class="empty-state">No albums yet. Gallery media can still be used without an album.</p>
        <?php endif; ?>
    </div>
</section>
