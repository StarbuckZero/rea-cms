<?php

declare(strict_types=1);

use ReaCms\Plugin\PluginListing;

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var list<PluginListing> $plugins */
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

    <section class="mt-8" aria-labelledby="plugins-heading">
        <div class="plugin-management-heading">
            <h2 id="plugins-heading" class="text-2xl font-semibold">Plugins</h2>
            <span class="plugin-count"><?= count($plugins) ?> found</span>
        </div>
        <?php if ($plugins === []) : ?>
            <p class="empty-state mt-5">No plugins were found.</p>
        <?php else : ?>
            <div class="plugin-list mt-5">
                <?php foreach ($plugins as $plugin) : ?>
                    <article class="plugin-card plugin-management-card">
                        <div class="plugin-card-heading">
                            <div>
                                <h3 class="text-xl font-semibold"><?= $escape($plugin->name ?: $plugin->id) ?></h3>
                                <p class="text-sm text-secondary mt-2">Plugin ID: <code><?= $escape($plugin->id) ?></code></p>
                            </div>
                            <span class="status-badge status-<?= $escape($plugin->status) ?>"><?= $escape($plugin->statusLabel) ?></span>
                        </div>
                        <p class="mt-4"><?= $escape($plugin->description ?: 'No description provided.') ?></p>
                        <dl class="plugin-metadata mt-4">
                            <div><dt>Version</dt><dd><?= $escape($plugin->version) ?></dd></div>
                            <?php if ($plugin->status === 'update_available') : ?>
                                <div><dt>Installed version</dt><dd><?= $escape($plugin->installedVersion) ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Author</dt><dd><?= $escape($plugin->author ?: 'Not provided') ?></dd></div>
                            <div><dt>Data tables</dt><dd><?= count($plugin->tables) ?></dd></div>
                        </dl>
                        <?php if ($plugin->error !== null) : ?>
                            <p class="notice-danger mt-4" role="alert">
                                <strong>Validation failed:</strong> <?= $escape($plugin->error) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($canManage) : ?>
                            <div class="button-row mt-5">
                                <?php $apiFormats = $plugin->manifest['api']['formats'] ?? []; ?>
                                <?php if ($plugin->status !== 'invalid' && $plugin->installedVersion !== null && is_array($apiFormats) && in_array('html', $apiFormats, true) && in_array('txt', $apiFormats, true)) : ?>
                                    <a class="button-secondary" href="/admin/plugins/<?= $escape($plugin->id) ?>/api-templates">
                                        API templates
                                    </a>
                                <?php endif; ?>
                                <?php if ($plugin->status === 'available' || $plugin->status === 'update_available') : ?>
                                    <form method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/install">
                                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                        <button class="button-primary" type="submit">
                                            <?= $plugin->status === 'available' ? 'Install' : 'Install update' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($plugin->installedState === 'enabled') : ?>
                                    <form method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/disable">
                                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                        <button class="button-secondary" type="submit">Disable</button>
                                    </form>
                                <?php elseif ($plugin->installedState === 'disabled' && $plugin->status !== 'invalid') : ?>
                                    <form method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/enable">
                                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                        <button class="button-primary" type="submit">Enable</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($plugin->installedVersion !== null) : ?>
                                    <a class="button-danger" href="/admin/plugins/<?= $escape($plugin->id) ?>/remove">
                                        <?= $plugin->installedState === 'uninstalled' ? 'Review preserved data' : 'Remove…' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
