<?php

declare(strict_types=1);

use ReaCms\Auth\User;
use ReaCms\Plugin\PluginRecord;

/** @var callable(mixed): string $escape */
/** @var User $user */
/** @var list<PluginRecord> $plugins */
?>
<section aria-labelledby="dashboard-heading">
    <p class="eyebrow">Dashboard</p>
    <h1 id="dashboard-heading" class="mt-3 text-3xl font-bold">Welcome, <?= $escape($user->displayName) ?></h1>
    <p class="mt-4 text-secondary">An overview of this Rea CMS installation.</p>

    <div class="dashboard-grid mt-10">
        <section class="panel dashboard-widget" aria-labelledby="plugins-heading">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Extensions</p>
                    <h2 id="plugins-heading" class="mt-2 text-xl font-semibold">Active plugins</h2>
                </div>
                <span class="plugin-count" aria-label="<?= count($plugins) ?> active plugins">
                    <?= count($plugins) ?> active
                </span>
            </div>

            <?php if ($plugins === []) : ?>
                <p class="empty-state mt-6">No plugins are currently active.</p>
            <?php else : ?>
                <ul class="plugin-list mt-6">
                    <?php foreach ($plugins as $plugin) : ?>
                        <li class="plugin-card">
                            <div class="plugin-card-heading">
                                <h3 class="font-semibold">
                                    <?= $escape($plugin->name !== '' ? $plugin->name : $plugin->id) ?>
                                </h3>
                                <span class="status-badge"><?= $escape(ucfirst($plugin->state)) ?></span>
                            </div>
                            <p class="mt-2 text-sm text-secondary">Version <?= $escape($plugin->version) ?></p>
                            <?php if ($plugin->description !== '') : ?>
                                <p class="mt-3 text-secondary"><?= $escape($plugin->description) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>
