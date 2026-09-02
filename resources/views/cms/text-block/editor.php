<?php

declare(strict_types=1);

use ReaCms\TextBlock\TextBlock;

/** @var callable(mixed): string $escape */
/** @var TextBlock|null $block */
/** @var string $csrfToken */
$id = $block?->id ?? 0;
?>
<section>
    <p class="eyebrow">Text Blocks</p>
    <h1 class="mt-3 text-3xl font-bold"><?= $id > 0 ? 'Edit text block' : 'New text block' ?></h1>

    <form class="panel mt-8 space-y-5" method="post"
        action="<?= $id > 0 ? '/cms/text-block/' . $id : '/cms/text-block' ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">

        <label class="form-label" for="text-block-name">API name</label>
        <input class="form-input" id="text-block-name" name="name" maxlength="191" required
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= $escape($block?->name ?? '') ?>"
            placeholder="welcome-message" aria-describedby="text-block-name-help">
        <p class="form-help" id="text-block-name-help">
            Use a URL-safe name such as <code>welcome-message</code>. Spaces are converted to hyphens when saved.
        </p>

        <label class="form-label" for="text-block-content">Content</label>
        <textarea class="form-input" id="text-block-content" name="content" rows="16" maxlength="60000"
            required aria-describedby="text-block-content-help"><?= $escape($block?->content ?? '') ?></textarea>
        <p class="form-help" id="text-block-content-help">
            Plain text and safe formatting such as paragraphs, headings, lists, emphasis, and links are supported.
            HTML is sanitized before storage and converted to readable plain text by the TXT API.
        </p>

        <?php if ($block !== null) : ?>
            <div class="text-sm text-secondary">
                <p>Block ID: <code><?= (int) $block->id ?></code></p>
                <p>
                    Created:
                    <time datetime="<?= $escape($block->createdAt->format(DATE_ATOM)) ?>">
                        <?= $escape($block->createdAt->format(DATE_ATOM)) ?>
                    </time>
                </p>
                <p>
                    Last updated:
                    <time datetime="<?= $escape($block->updatedAt->format(DATE_ATOM)) ?>">
                        <?= $escape($block->updatedAt->format(DATE_ATOM)) ?>
                    </time>
                </p>
            </div>
        <?php endif; ?>

        <div class="button-row">
            <button class="button-primary" type="submit">Save text block</button>
            <a class="button-secondary" href="/cms/text-block">Cancel</a>
        </div>
    </form>

    <?php if ($block !== null) : ?>
        <form class="mt-4" method="post" action="/cms/text-block/<?= (int) $block->id ?>/delete"
            data-confirm-delete="<?= $escape($block->name) ?>">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <button class="button-secondary" type="submit">Delete text block</button>
        </form>
    <?php endif; ?>
</section>
<script src="/assets/text-blocks.js?v=1" defer></script>
