<?php

declare(strict_types=1);

use ReaCms\Plugin\PluginRecord;

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var PluginRecord $plugin */
/** @var string $binding */
/** @var list<array{path:string,binding:string,label:string,description:string,type:string}> $fields */
/** @var array{html_list:string,html_detail:string,txt_list:string,txt_detail:string} $templates */
/** @var string|null $success */
/** @var string|null $error */
$editors = [
    'html_list' => ['HTML list template', 'Rendered once for each item in a collection.'],
    'html_detail' => ['HTML detail template', 'Rendered once for a single resource.'],
    'txt_list' => ['TXT list template', 'Rendered once per item and converted to ASCII plain text.'],
    'txt_detail' => ['TXT detail template', 'Rendered once and converted to ASCII plain text.'],
];
?>
<section aria-labelledby="api-template-heading">
    <p class="eyebrow">Plugin API theme</p>
    <div class="plugin-management-heading mt-3">
        <div>
            <h1 id="api-template-heading" class="text-3xl font-bold">
                <?= $escape($plugin->name ?: $plugin->id) ?> API templates
            </h1>
            <p class="mt-3 text-secondary">
                Customize the HTML and terminal-friendly TXT layouts without changing plugin source code.
            </p>
        </div>
        <a class="button-secondary" href="/admin/plugins">Back to plugins</a>
    </div>

    <?php if ($success !== null) : ?>
        <p class="notice-success mt-6" role="status"><?= $escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== null) : ?>
        <p class="notice-danger mt-6" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <form class="mt-6" method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/api-templates"
        data-template-editor
        data-preview-url="/admin/plugins/<?= $escape($plugin->id) ?>/api-templates/preview">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">

        <div class="api-template-layout">
            <div class="space-y-6">
                <?php foreach ($editors as $name => [$label, $help]) : ?>
                    <section class="panel api-template-card" data-template-card="<?= $escape($name) ?>">
                        <div class="plugin-management-heading">
                            <div>
                                <h2 class="text-xl font-semibold"><?= $escape($label) ?></h2>
                                <p class="form-help mt-2"><?= $escape($help) ?></p>
                            </div>
                            <button class="button-secondary" type="button" data-template-preview="<?= $escape($name) ?>">
                                Preview
                            </button>
                        </div>
                        <label class="sr-only" for="<?= $escape($name) ?>"><?= $escape($label) ?></label>
                        <textarea class="form-input api-template-textarea mt-4" id="<?= $escape($name) ?>"
                            name="<?= $escape($name) ?>" rows="12" required spellcheck="false"
                            data-template-textarea data-template-label="<?= $escape($label) ?>"><?= $escape($templates[$name]) ?></textarea>
                        <section class="api-template-preview mt-4" aria-label="<?= $escape($label) ?> preview"
                            data-template-preview-panel="<?= $escape($name) ?>" hidden>
                            <div class="plugin-management-heading">
                                <h3 class="font-semibold">Sample preview</h3>
                                <button type="button" data-template-preview-close="<?= $escape($name) ?>">Close</button>
                            </div>
                            <p class="form-help mt-2" data-template-preview-message></p>
                            <iframe class="api-template-preview-frame mt-3" title="<?= $escape($label) ?> HTML preview"
                                sandbox data-template-preview-html hidden></iframe>
                            <pre class="api-template-preview-text mt-3" data-template-preview-text hidden></pre>
                        </section>
                    </section>
                <?php endforeach; ?>
            </div>

            <aside class="panel api-field-panel" aria-labelledby="available-fields-heading">
                <h2 id="available-fields-heading" class="text-xl font-semibold">Available fields</h2>
                <p class="form-help mt-2">
                    Insert into <strong data-active-template-label>HTML list template</strong> at the current cursor.
                </p>
                <?php if ($fields === []) : ?>
                    <p class="empty-state mt-4">
                        This plugin does not provide API field metadata. Bindings can still be entered using
                        <code>{<?= $escape($binding) ?>.field}</code>.
                    </p>
                <?php else : ?>
                    <ul class="api-field-list mt-4">
                        <?php foreach ($fields as $field) : ?>
                            <li class="api-field-item">
                                <button type="button" data-template-binding="<?= $escape($field['binding']) ?>"
                                    aria-label="Insert <?= $escape($field['binding']) ?>">
                                    <code><?= $escape($field['binding']) ?></code>
                                    <span>Insert</span>
                                </button>
                                <p class="text-sm font-semibold mt-2"><?= $escape($field['label']) ?></p>
                                <?php if ($field['description'] !== '') : ?>
                                    <p class="form-help mt-1"><?= $escape($field['description']) ?></p>
                                <?php endif; ?>
                                <p class="api-field-type mt-2"><?= $escape($field['type']) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>
        </div>

        <div class="button-row mt-6">
            <button class="button-primary" type="submit">Save API templates</button>
            <button class="button-secondary" type="reset">Discard unsaved changes</button>
            <a class="button-secondary" href="/admin/plugins">Cancel</a>
        </div>
    </form>

    <form class="mt-4" method="post"
        action="/admin/plugins/<?= $escape($plugin->id) ?>/api-templates/reset" data-template-reset-defaults>
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <button class="button-secondary" type="submit">Restore packaged defaults</button>
        <p class="form-help mt-2">Removes saved overrides for all four templates.</p>
    </form>
</section>
<script src="/assets/api-template-editor.js?v=1" defer></script>
