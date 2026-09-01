<?php

declare(strict_types=1);

use ReaCms\Plugin\PluginDataSummary;
use ReaCms\Plugin\PluginRecord;

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var PluginRecord $plugin */
/** @var PluginDataSummary $summary */
/** @var bool $canPurge */
/** @var bool $recentlyReauthenticated */
?>
<section class="max-w-3xl" aria-labelledby="remove-plugin-heading">
    <p class="eyebrow">Plugin Management</p>
    <h1 id="remove-plugin-heading" class="mt-3 text-3xl font-bold">Remove <?= $escape($plugin->name ?: $plugin->id) ?></h1>
    <p class="notice-danger mt-6" role="alert">
        This is a destructive action. It removes the plugin from service and may permanently delete plugin files and data.
    </p>

    <section class="panel mt-6" aria-labelledby="plugin-data-heading">
        <h2 id="plugin-data-heading" class="text-xl font-semibold">Stored plugin data</h2>
        <p class="mt-3">
            <?= $summary->hasData()
                ? $escape($summary->totalRows()) . ' stored record(s) were found across plugin-owned tables '
                    . 'and core plugin-scoped data.'
                : 'No stored records were found in plugin-owned tables or core plugin-scoped data.' ?>
        </p>
        <?php if ($summary->tableRows !== []) : ?>
            <dl class="plugin-metadata mt-4">
                <?php foreach ($summary->tableRows as $table => $rows) : ?>
                    <div><dt><code><?= $escape($table) ?></code></dt><dd><?= $rows ?> row(s)</dd></div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
        <?php if ($canPurge) : ?>
            <form class="mt-5" method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/backup">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <button class="button-secondary" type="submit">Download data backup</button>
            </form>
        <?php endif; ?>
    </section>

    <?php if (!$recentlyReauthenticated) : ?>
        <p class="notice-danger mt-6">Reauthenticate from the Administration page before removing this plugin.</p>
        <a class="button-primary mt-5" href="/admin">Reauthenticate</a>
    <?php else : ?>
        <?php if ($plugin->state !== 'uninstalled') : ?>
            <form class="panel mt-6 space-y-4" method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/uninstall">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="mode" value="keep_data">
                <h2 class="text-xl font-semibold">Remove Plugin, Keep Data</h2>
                <p>The active plugin files will be moved into private backup storage. Its tables and stored data remain intact.</p>
                <label class="form-label" for="remove-confirmation">
                    Type <code>REMOVE <?= $escape($plugin->id) ?></code> to confirm
                </label>
                <input class="form-input" id="remove-confirmation" name="confirmation" autocomplete="off" required>
                <button class="button-secondary" type="submit">Remove Plugin, Keep Data</button>
            </form>
        <?php endif; ?>

        <?php if ($canPurge) : ?>
            <form class="panel danger-panel mt-6 space-y-4" method="post" action="/admin/plugins/<?= $escape($plugin->id) ?>/uninstall">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="mode" value="delete_data">
                <h2 class="text-xl font-semibold">Remove Plugin and Delete Data</h2>
                <p>
                    This permanently drops all declared plugin tables. A final integrity-checksummed backup is created automatically,
                    but you should download your own copy first<?= $summary->hasData() ? ' because stored data was detected' : '' ?>.
                </p>
                <label class="form-label" for="purge-confirmation">
                    Type <code>PURGE <?= $escape($plugin->id) ?></code> to permanently delete the data
                </label>
                <input class="form-input" id="purge-confirmation" name="confirmation" autocomplete="off" required>
                <button class="button-danger" type="submit">Remove Plugin and Delete Data</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <a class="button-secondary mt-6" href="/admin/plugins">Cancel and return</a>
</section>
