<?php

declare(strict_types=1);

use ReaCms\Database\Migrations\CoreMigrationPlan;

/** @var callable(mixed): string $escape */
/** @var string $version */
/** @var CoreMigrationPlan $plan */
/** @var string $csrfToken */
/** @var string|null $success */
/** @var string|null $error */
/** @var bool $canRun */
?>
<section aria-labelledby="upgrade-heading">
    <p class="eyebrow">System maintenance</p>
    <div class="plugin-management-heading mt-3">
        <div>
            <h1 id="upgrade-heading" class="text-3xl font-bold">Upgrade Rea CMS</h1>
            <p class="mt-3 text-secondary">Apply database migrations after uploading and activating a verified release.</p>
        </div>
        <a class="button-secondary" href="/admin">Back to administration</a>
    </div>

    <?php if ($success !== null) : ?>
        <p class="notice-success mt-6" role="status"><?= $escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== null) : ?>
        <p class="notice-danger mt-6" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <div class="mt-8 grid gap-6 md:grid-cols-2">
        <section class="panel" aria-labelledby="release-heading">
            <h2 id="release-heading" class="text-xl font-semibold">Release</h2>
            <dl class="plugin-metadata mt-5">
                <div><dt>Application version</dt><dd><?= $escape($version) ?></dd></div>
                <div><dt>Applied migrations</dt><dd><?= count($plan->applied) ?></dd></div>
                <div><dt>Pending migrations</dt><dd><?= count($plan->pending) ?></dd></div>
            </dl>
        </section>

        <section class="panel" aria-labelledby="status-heading">
            <h2 id="status-heading" class="text-xl font-semibold">Database status</h2>
            <?php if (!$canRun) : ?>
                <p class="notice-danger mt-5">Migration status could not be verified.</p>
            <?php elseif ($plan->isCurrent()) : ?>
                <p class="notice-success mt-5">The database is current for this release.</p>
            <?php else : ?>
                <p class="notice-warning mt-5"><?= count($plan->pending) ?> migration(s) are ready to apply.</p>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($plan->pending !== []) : ?>
        <section class="panel mt-6" aria-labelledby="pending-heading">
            <h2 id="pending-heading" class="text-xl font-semibold">Pending migrations</h2>
            <ol class="mt-5 space-y-2">
                <?php foreach ($plan->pending as $migration) : ?>
                    <li><code><?= $escape($migration) ?></code></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($canRun && !$plan->isCurrent()) : ?>
        <section class="panel mt-6" aria-labelledby="run-upgrade-heading">
            <h2 id="run-upgrade-heading" class="text-xl font-semibold">Run upgrade</h2>
            <p class="mt-3 text-secondary">
                Put the site into a maintenance window first. Database migrations cannot be automatically rolled back.
            </p>
            <form class="mt-6 space-y-5" method="post" action="/admin/upgrade">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <label class="flex items-start gap-3">
                    <input class="mt-1" name="backup_confirmed" type="checkbox" value="1" required>
                    <span>I created and verified a current database and application backup.</span>
                </label>
                <div>
                    <label class="form-label" for="upgrade-password">Confirm your current password</label>
                    <input class="form-input" id="upgrade-password" name="password" type="password"
                           autocomplete="current-password" required>
                </div>
                <button class="button-primary" type="submit">Apply database upgrade</button>
            </form>
        </section>
    <?php endif; ?>
</section>
