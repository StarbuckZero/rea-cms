<?php

declare(strict_types=1);

use ReaCms\Plugin\Manifest;

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var Manifest $plugin */
/** @var string $packageHash */
/** @var string $pendingToken */
/** @var list<string> $migrations */
/** @var bool $recentlyReauthenticated */
?>
<section class="max-w-3xl" aria-labelledby="plugin-preview-heading">
    <p class="eyebrow">Plugin Management</p>
    <h1 id="plugin-preview-heading" class="mt-3 text-3xl font-bold">Confirm <?= $escape($plugin->name) ?></h1>
    <p class="mt-4 text-secondary">The ZIP passed package safety and compatibility validation. Review it before installation.</p>

    <section class="panel mt-6" aria-labelledby="package-details-heading">
        <h2 id="package-details-heading" class="text-xl font-semibold">Package details</h2>
        <dl class="plugin-metadata mt-5">
            <div><dt>Plugin ID</dt><dd><code><?= $escape($plugin->id) ?></code></dd></div>
            <div><dt>Version</dt><dd><?= $escape($plugin->version) ?></dd></div>
            <div><dt>Author</dt><dd><?= $escape($plugin->author ?: 'Not provided') ?></dd></div>
            <div><dt>Rea CMS compatibility</dt><dd><?= $escape($plugin->reaCmsVersion) ?></dd></div>
            <div><dt>Tables</dt><dd><?= $escape(implode(', ', $plugin->tables) ?: 'None') ?></dd></div>
            <div><dt>Permissions</dt><dd><?= $escape(implode(', ', $plugin->permissions) ?: 'None') ?></dd></div>
            <div>
                <dt>CMS route</dt>
                <dd><?= $escape(is_string($plugin->document['navigation']['path'] ?? null)
                    ? $plugin->document['navigation']['path']
                    : 'None') ?></dd>
            </div>
            <div><dt>Migrations</dt><dd><?= $escape(implode(', ', $migrations) ?: 'None') ?></dd></div>
            <div><dt>SHA-256</dt><dd class="break-value"><code><?= $escape($packageHash) ?></code></dd></div>
        </dl>
        <p class="mt-5"><?= $escape($plugin->description ?: 'No description provided.') ?></p>
    </section>

    <?php if (!$recentlyReauthenticated) : ?>
        <p class="notice-danger mt-6" role="alert">
            Reauthenticate from the Administration page, then validate the ZIP again to install it.
        </p>
        <a class="button-primary mt-5" href="/admin">Reauthenticate</a>
    <?php else : ?>
        <form class="panel mt-6 space-y-4" method="post" action="/admin/plugins/install">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="pending_token" value="<?= $escape($pendingToken) ?>">
            <label>
                <input type="checkbox" name="confirm_install" value="1" required>
                I reviewed this package and want to install it in a disabled state.
            </label>
            <div class="button-row">
                <button class="button-primary" type="submit">Install plugin</button>
                <a class="button-secondary" href="/admin/plugins">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>
