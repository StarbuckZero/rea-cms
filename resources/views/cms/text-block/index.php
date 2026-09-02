<?php

declare(strict_types=1);

use ReaCms\Support\PlainText;
use ReaCms\TextBlock\TextBlock;

/** @var callable(mixed): string $escape */
/** @var list<TextBlock> $blocks */
/** @var string $csrfToken */
/** @var string $search */
/** @var string $message */
?>
<section>
    <p class="eyebrow">Content</p>
    <div class="widget-heading mt-3">
        <div>
            <h1 class="text-3xl font-bold">Text Blocks</h1>
            <p class="mt-2 text-secondary">Manage reusable text served through the JSON, HTML, and TXT APIs.</p>
        </div>
        <a class="button-primary" href="/cms/text-block/new">New text block</a>
    </div>

    <?php if ($message !== '') : ?>
        <p class="notice-success mt-6" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <form class="panel mt-6" method="get" action="/cms/text-block" role="search">
        <label class="form-label" for="text-block-search">Search text blocks</label>
        <div class="flex flex-wrap gap-3 mt-2">
            <input class="form-input" id="text-block-search" type="search" name="q"
                value="<?= $escape($search) ?>" placeholder="Search by name or content">
            <button class="button-primary" type="submit">Search</button>
            <?php if ($search !== '') : ?>
                <a class="button-secondary" href="/cms/text-block">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="panel mt-8">
        <table class="cms-table">
            <thead>
                <tr>
                    <th>Text block</th>
                    <th>Content</th>
                    <th>Last updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blocks as $block) : ?>
                    <?php $preview = PlainText::fromHtml($block->content); ?>
                    <tr>
                        <td>
                            <strong><?= $escape($block->name) ?></strong><br>
                            <span class="text-sm text-secondary">ID <?= (int) $block->id ?></span>
                        </td>
                        <td>
                            <?= $escape(mb_strimwidth($preview, 0, 120, '…')) ?>
                        </td>
                        <td>
                            <time datetime="<?= $escape($block->updatedAt->format(DATE_ATOM)) ?>">
                                <?= $escape($block->updatedAt->format('M j, Y, g:i a')) ?>
                            </time>
                        </td>
                        <td>
                            <a href="/cms/text-block/<?= (int) $block->id ?>/edit">Edit</a>
                            <div class="button-row mt-2">
                                <button type="button" data-copy-value="<?= (int) $block->id ?>"
                                    data-copy-label="block ID">Copy ID</button>
                                <button type="button" data-copy-value="<?= $escape($block->name) ?>"
                                    data-copy-label="block name">Copy name</button>
                            </div>
                            <form class="mt-2" method="post"
                                action="/cms/text-block/<?= (int) $block->id ?>/delete"
                                data-confirm-delete="<?= $escape($block->name) ?>">
                                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($blocks === []) : ?>
                    <tr>
                        <td colspan="4">
                            <?= $search === ''
                                ? 'No text blocks have been created.'
                                : 'No text blocks match this search.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script src="/assets/text-blocks.js?v=1" defer></script>
