<?php

declare(strict_types=1);

use ReaCms\Plugin\PluginRecord;

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var list<PluginRecord> $plugins */
/** @var string|null $success */
/** @var string|null $error */
/** @var bool $canManage */
?>
<section aria-labelledby="plugin-management-heading">
    <p class="eyebrow">Administration</p>
    <div class="plugin-management-heading mt-3">
        <div>
            <h1 id="plugin-management-heading" class="text-3xl font-bold">Plugin Management</h1>
            <p class="mt-3 text-secondary">Install and manage declarative Rea CMS plugins.</p>
        </div>
        <a class="button-secondary" href="/admin">Back to administration</a>
    </div>

    <?php if ($success !== null) : ?>
        <p class="notice-success mt-6" role="status"><?= $escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== null) : ?>
        <p class="notice-danger mt-6" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <?php if ($canManage) : ?>
        <section class="panel mt-6" aria-labelledby="install-plugin-heading">
            <h2 id="install-plugin-heading" class="text-xl font-semibold">Install a plugin</h2>
            <p class="mt-3 text-secondary">
                Upload a ZIP package for validation. You will review its identity and capabilities before installation.
            </p>
            <form class="mt-5 space-y-4" method="post" action="/admin/plugins/inspect" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <div>
                    <label class="form-label" for="plugin-zip">Plugin ZIP</label>
                    <input class="form-input" id="plugin-zip" name="plugin_zip" type="file" accept=".zip,application/zip" required>
                    <p class="form-help mt-2">Maximum compressed size: 10 MB. Existing plugin IDs are never overwritten.</p>
                </div>
                <button class="button-primary" type="submit">Validate plugin</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="mt-8" aria-labelledby="installed-plugins-heading">
        <div class="plugin-management-heading">
            <h2 id="installed-plugins-heading" class="text-2xl font-semibold">Installed plugins</h2>
            <span class="plugin-count"><?= count($plugins) ?> installed</span>
        </div>
        <?php if ($plugins === []) : ?>
            <p class="empty-state mt-5">No plugins are installed.</p>
        <?php else : ?>
            <div class="plugin-list mt-5">
                <?php foreach ($plugins as $plugin) : ?>
                    <article class="plugin-card plugin-management-card">
                        <div class="plugin-card-heading">
                            <div>
                                <h3 class="text-xl font-semibold"><?= $escape($plugin->name ?: $plugin->id) ?></h3>
                                <p class="text-sm text-secondary mt-2">Plugin ID: <code><?= $escape($plugin->id) ?></code></p>
                            </div>
                            <span class="status-badge status-<?= $escape($plugin->state) ?>"><?= $escape(ucfirst($plugin->state)) ?></span>
                        </div>
                        <p class="mt-4"><?= $escape($plugin->description ?: 'No description provided.') ?></p>
                        <dl class="plugin-metadata mt-4">
                            <div><dt>Version</dt><dd><?= $escape($plugin->version) ?></dd></div>
                            <div><dt>Author</dt><dd><?= $escape($plugin->author ?: 'Not provided') ?></dd></div>
                            <div><dt>Data tables</dt><dd><?= count($plugin->tables) ?></dd></div>
                        </dl>
                        <?php if ($canManage) : ?>
                            <div class="button-row mt-5">
                                <?php if ($plugin->state === 'enabled') : ?>
                                    <form method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/disable">
                                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                        <button class="button-secondary" type="submit">Disable</button>
                                    </form>
                                <?php elseif ($plugin->state === 'disabled') : ?>
                                    <form method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/enable">
                                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                        <button class="button-primary" type="submit">Enable</button>
                                    </form>
                                <?php endif; ?>
                                <a class="button-danger" href="/admin/plugins/<?= $escape($plugin->id) ?>/remove">
                                    <?= $plugin->state === 'uninstalled' ? 'Review preserved data' : 'Remove…' ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
