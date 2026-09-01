<?php

declare(strict_types=1);

use ReaCms\Auth\User;
use ReaCms\Auth\UserSession;
use ReaCms\Plugin\PluginAccess;
use ReaCms\Plugin\PluginRecord;

/** @var callable(mixed): string $escape */
/** @var User $user */
/** @var string $csrfToken */
/** @var string|null $reauthenticatedAt */
/** @var list<UserSession> $sessions */
/** @var string $currentSessionHash */
/** @var list<User> $users */
/** @var list<PluginRecord> $plugins */
/** @var PluginAccess $pluginAccess */
/** @var bool $canManagePlugins */
?>
<section aria-labelledby="admin-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="admin-heading" class="mt-3 text-3xl font-bold">Welcome, <?= $escape($user->displayName) ?></h1>
    <p class="mt-4 text-secondary">Signed in as <?= $escape($user->email) ?></p>
    <?php if ($canManagePlugins) : ?>
        <div class="button-row mt-6">
            <a class="button-secondary" href="/admin/plugins">Plugin Management</a>
        </div>
    <?php endif; ?>

    <div class="mt-10 grid gap-6 md:grid-cols-2">
        <section class="panel" aria-labelledby="session-heading">
            <h2 id="session-heading" class="text-xl font-semibold">Session security</h2>
            <p class="mt-3 text-secondary">
                <?= $reauthenticatedAt === null
                    ? 'This session has not been recently reauthenticated.'
                    : 'Reauthenticated at ' . $escape($reauthenticatedAt) ?>
            </p>
            <form class="mt-5 space-y-4" method="post" action="/admin/reauthenticate">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <div>
                    <label class="form-label" for="reauth-password">Confirm password</label>
                    <input class="form-input" id="reauth-password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="button-secondary" type="submit">Reauthenticate</button>
            </form>
        </section>

        <section class="panel" aria-labelledby="account-heading">
            <h2 id="account-heading" class="text-xl font-semibold">Account</h2>
            <p class="mt-3 text-secondary">End this session securely on this device.</p>
            <form class="mt-5" method="post" action="/logout">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <button class="button-secondary" type="submit">Sign out</button>
            </form>
        </section>
    </div>

    <section class="panel mt-6" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading" class="text-xl font-semibold">Active sessions</h2>
        <ul class="mt-5 divide-y divide-default">
            <?php foreach ($sessions as $activeSession): ?>
                <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div>
                        <p class="font-semibold">
                            <?= $escape($activeSession->ipAddress) ?>
                            <?= hash_equals($currentSessionHash, $activeSession->tokenHash) ? '(current)' : '' ?>
                        </p>
                        <p class="text-sm text-secondary">
                            Last active <?= $escape($activeSession->lastSeenAt->format(DATE_ATOM)) ?>
                        </p>
                    </div>
                    <form method="post" action="/admin/sessions/revoke">
                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="session" value="<?= $escape($activeSession->tokenHash) ?>">
                        <button class="button-secondary" type="submit">Revoke</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</section>

<section class="panel mt-6" aria-labelledby="users-heading">
    <h2 id="users-heading" class="text-xl font-semibold">CMS users</h2>
    <p class="mt-3 text-secondary">Plugin access controls both navigation and protected CMS routes.</p>
    <details class="mt-5">
        <summary class="button-secondary">Create user</summary>
        <form class="mt-5 space-y-4" method="post" action="/admin/users">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <label class="form-label">Display name <input class="form-input" name="display_name" required></label>
            <label class="form-label">Email <input class="form-input" name="email" type="email" required></label>
            <label class="form-label">Temporary password <input class="form-input" name="password" type="password" minlength="12" required></label>
            <fieldset><legend class="form-label">Plugin access</legend>
                <?php foreach ($plugins as $plugin) : ?>
                    <label><input type="checkbox" name="plugin_<?= $escape($plugin->id) ?>" value="1"> <?= $escape($plugin->name) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <button class="button-primary" type="submit">Create user</button>
        </form>
    </details>
    <div class="mt-6 space-y-4">
        <?php foreach ($users as $managedUser) : $assigned = $pluginAccess->assignedTo($managedUser->id); ?>
            <details class="plugin-card">
                <summary><strong><?= $escape($managedUser->displayName) ?></strong> — <?= $escape($managedUser->email) ?> (<?= $escape($managedUser->status) ?>)</summary>
                <form class="mt-5 space-y-4" method="post" action="/admin/users/<?= $managedUser->id ?>">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <label class="form-label">Display name <input class="form-input" name="display_name" value="<?= $escape($managedUser->displayName) ?>" required></label>
                    <label class="form-label">Email <input class="form-input" name="email" type="email" value="<?= $escape($managedUser->email) ?>" required></label>
                    <label class="form-label">New password (optional) <input class="form-input" name="password" type="password" minlength="12"></label>
                    <label><input type="checkbox" name="status" value="active" <?= $managedUser->isActive() ? 'checked' : '' ?>> Enabled</label>
                    <fieldset><legend class="form-label">Plugin access</legend>
                        <?php foreach ($plugins as $plugin) : ?>
                            <label><input type="checkbox" name="plugin_<?= $escape($plugin->id) ?>" value="1" <?= in_array($plugin->id, $assigned, true) ? 'checked' : '' ?>> <?= $escape($plugin->name) ?></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <button class="button-primary" type="submit">Save user</button>
                </form>
                <?php if ($managedUser->id !== $user->id) : ?>
                    <form class="mt-3" method="post" action="/admin/users/<?= $managedUser->id ?>/delete">
                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                        <button class="button-secondary" type="submit">Remove user</button>
                    </form>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>
    </div>
</section>
