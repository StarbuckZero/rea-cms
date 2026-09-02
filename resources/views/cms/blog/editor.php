<?php

declare(strict_types=1);

use ReaCms\Plugin\SafeHtml;

/** @var callable(mixed): string $escape */
/** @var array<string, mixed>|null $post */
/** @var list<array<string, mixed>> $media */
/** @var string $csrfToken */
$id = (int) ($post['id'] ?? 0);
$content = SafeHtml::sanitize((string) ($post['content'] ?? ''))->value;
?>
<section>
    <p class="eyebrow">Blogs</p>
    <h1 class="mt-3 text-3xl font-bold"><?= $id ? 'Edit post' : 'New post' ?></h1>

    <form class="panel mt-8 space-y-5" method="post"
          action="<?= $id ? '/cms/blog/' . $id : '/cms/blog' ?>" data-blog-form>
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label class="form-label">Title
            <input class="form-input" name="title" value="<?= $escape($post['title'] ?? '') ?>" required>
        </label>
        <label class="form-label">Slug
            <input class="form-input" name="slug" value="<?= $escape($post['slug'] ?? '') ?>"
                   placeholder="Generated from title">
        </label>
        <label class="form-label">Excerpt
            <textarea class="form-input" name="excerpt" rows="3"><?= $escape($post['excerpt'] ?? '') ?></textarea>
        </label>

        <fieldset class="rich-editor-fieldset">
            <legend class="form-label">Content</legend>
            <div class="rich-editor-shell" data-rich-text-editor data-upload-url="/cms/blog/media"
                 data-csrf-token="<?= $escape($csrfToken) ?>">
                <div class="editor-mode-selector" role="tablist" aria-label="Editor mode">
                    <?php foreach (['edit' => 'Edit', 'html' => 'HTML', 'preview' => 'Preview'] as $mode => $label) : ?>
                        <button type="button" role="tab" data-editor-mode="<?= $mode ?>"
                                aria-selected="<?= $mode === 'edit' ? 'true' : 'false' ?>">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="editor-toolbar" role="toolbar" aria-label="Text formatting" data-editor-toolbar>
                    <span class="editor-toolbar-group">
                        <button type="button" data-editor-command="undo" aria-label="Undo" title="Undo (Ctrl/Cmd+Z)">↶</button>
                        <button type="button" data-editor-command="redo" aria-label="Redo" title="Redo (Ctrl/Cmd+Shift+Z)">↷</button>
                    </span>
                    <span class="editor-toolbar-group">
                        <label class="sr-only" for="blog-block-format">Block format</label>
                        <select id="blog-block-format" data-block-format aria-label="Block format" title="Block format">
                            <option value="p">Paragraph</option>
                            <?php foreach (range(1, 6) as $level) : ?>
                                <option value="h<?= $level ?>">Heading <?= $level ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                    <span class="editor-toolbar-group">
                        <?php foreach ([
                            ['bold', 'B', 'Bold (Ctrl/Cmd+B)'],
                            ['italic', 'I', 'Italic (Ctrl/Cmd+I)'],
                            ['underline', 'U', 'Underline (Ctrl/Cmd+U)'],
                            ['strikeThrough', 'S', 'Strikethrough'],
                        ] as [$command, $label, $title]) : ?>
                            <button type="button" data-editor-command="<?= $command ?>" aria-label="<?= $escape($title) ?>"
                                    title="<?= $escape($title) ?>"><?= $label ?></button>
                        <?php endforeach; ?>
                    </span>
                    <span class="editor-toolbar-group">
                        <?php foreach ([
                            ['left', 'Left', '⇤'], ['center', 'Center', '↔'], ['right', 'Right', '⇥'],
                        ] as [$alignment, $label, $icon]) : ?>
                            <button type="button" data-editor-align="<?= $alignment ?>" aria-label="Align <?= strtolower($label) ?>"
                                    title="Align <?= strtolower($label) ?>"><?= $icon ?></button>
                        <?php endforeach; ?>
                    </span>
                    <span class="editor-toolbar-group">
                        <button type="button" data-editor-command="insertUnorderedList" aria-label="Bullet list" title="Bullet list">• List</button>
                        <button type="button" data-editor-command="insertOrderedList" aria-label="Numbered list" title="Numbered list">1. List</button>
                        <button type="button" data-editor-block="blockquote" aria-label="Blockquote" title="Blockquote">❝</button>
                        <button type="button" data-editor-block="pre" aria-label="Code block" title="Code block">&lt;/&gt;</button>
                    </span>
                    <span class="editor-toolbar-group">
                        <button type="button" data-editor-link aria-label="Insert or edit link" title="Link (Ctrl/Cmd+K)">Link</button>
                        <button type="button" data-editor-command="insertHorizontalRule" aria-label="Horizontal rule" title="Horizontal rule">―</button>
                        <button type="button" data-editor-command="removeFormat" aria-label="Clear formatting" title="Clear formatting">Clear</button>
                        <button type="button" data-media-open aria-label="Insert image" title="Insert image">Image</button>
                    </span>
                    <span class="editor-toolbar-group editor-toolbar-end">
                        <button type="button" data-source-toggle aria-label="Edit HTML source" title="HTML source">&lt;HTML&gt;</button>
                        <button type="button" data-editor-fullscreen aria-label="Toggle fullscreen editor"
                                title="Toggle fullscreen" aria-pressed="false">⛶</button>
                    </span>
                </div>

                <div id="blog-editor" class="rich-editor prose-content" contenteditable="true" role="textbox"
                     aria-multiline="true" aria-label="Blog post content" data-editor-surface><?= $content ?></div>
                <textarea id="blog-content" class="rich-editor-source" name="content" rows="20"
                          aria-label="Blog post HTML" data-editor-source hidden><?= $escape($content) ?></textarea>
                <div class="rich-editor-preview prose-content" data-editor-preview hidden
                     aria-label="Blog post preview"></div>
                <p class="editor-help" data-editor-help>
                    Tip: type <code>/h1</code>, <code>/image</code>, <code>/quote</code>, <code>/code</code>,
                    <code>/ul</code>, or <code>/ol</code> at the start of a line.
                </p>

                <aside class="image-inspector" data-image-inspector hidden aria-label="Image settings">
                    <div class="widget-heading">
                        <h2 class="font-semibold">Image settings</h2>
                        <button type="button" data-image-close aria-label="Close image settings">Close</button>
                    </div>
                    <div class="image-inspector-grid mt-3">
                        <label class="form-label">Alt text
                            <input class="form-input" data-image-alt>
                        </label>
                        <label class="form-label">Title
                            <input class="form-input" data-image-title>
                        </label>
                        <label class="form-label image-inspector-wide">Caption
                            <input class="form-input" data-image-caption>
                        </label>
                        <label class="form-label">Alignment
                            <select class="form-input" data-image-alignment>
                                <option value="none">None</option>
                                <option value="left">Left</option>
                                <option value="center">Center</option>
                                <option value="right">Right</option>
                            </select>
                        </label>
                        <label class="form-label">Size
                            <select class="form-input" data-image-size>
                                <option value="small">Small</option>
                                <option value="medium">Medium</option>
                                <option value="large" selected>Large</option>
                                <option value="original">Original</option>
                            </select>
                        </label>
                        <label class="form-label image-inspector-wide">Link
                            <input class="form-input" type="url" placeholder="https://example.com" data-image-link>
                        </label>
                    </div>
                    <div class="button-row mt-3">
                        <button class="button-secondary" type="button" data-image-replace>Replace image</button>
                        <button class="button-secondary" type="button" data-image-remove>Remove image</button>
                    </div>
                </aside>

                <div class="editor-status" role="status" aria-live="polite" data-editor-status></div>
            </div>
        </fieldset>

        <label class="form-label">Featured image
            <select class="form-input" name="featured_media_id">
                <option value="">None</option>
                <?php foreach ($media as $image) : ?>
                    <option value="<?= (int) $image['id'] ?>"
                        <?= (int) ($post['featured_media_id'] ?? 0) === (int) $image['id'] ? 'selected' : '' ?>>
                        <?= $escape($image['original_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-label">Status
            <select class="form-input" name="status">
                <option value="draft">Draft</option>
                <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </label>
        <div class="flex gap-3">
            <button class="button-primary" type="submit">Save post</button>
            <a class="button-secondary" href="/cms/blog">Cancel</a>
        </div>
    </form>

    <?php if ($id) : ?>
        <form class="mt-4" method="post" action="/cms/blog/<?= $id ?>/delete">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <button class="button-secondary" type="submit">Delete post</button>
        </form>
    <?php endif; ?>

    <dialog id="media-picker" class="media-picker" data-media-picker>
        <div class="widget-heading">
            <h2 class="text-xl font-semibold">Choose an image</h2>
            <button type="button" data-media-close>Close</button>
        </div>
        <form class="media-upload-form mt-5" method="post" action="/cms/blog/media"
              enctype="multipart/form-data" data-media-upload-form>
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <label class="form-label">Upload a new image
                <input class="form-input" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
            </label>
            <label class="form-label">Alt text
                <input class="form-input" name="alt_text">
            </label>
            <button class="button-primary" type="submit">Upload and insert</button>
        </form>
        <div class="media-grid mt-5" data-media-grid>
            <?php foreach ($media as $image) : ?>
                <button type="button" class="media-choice" data-media-url="/media/<?= (int) $image['id'] ?>"
                        data-media-alt="<?= $escape($image['alt_text']) ?>"
                        data-media-title="<?= $escape($image['original_name']) ?>">
                    <img src="/cms/media/<?= (int) $image['id'] ?>" alt="<?= $escape($image['alt_text']) ?>">
                    <span><?= $escape($image['original_name']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </dialog>
</section>
<script type="module" src="/assets/editor.js?v=2"></script>
