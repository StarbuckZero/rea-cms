<?php

declare(strict_types=1);

use ReaCms\Auth\User;
use ReaCms\Auth\UserSession;

/** @var callable(mixed): string $escape */
/** @var User $user */
/** @var string $csrfToken */
/** @var string|null $reauthenticatedAt */
/** @var list<UserSession> $sessions */
/** @var string $currentSessionHash */
?>
<section aria-labelledby="admin-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="admin-heading" class="mt-3 text-3xl font-bold">Welcome, <?= $escape($user->displayName) ?></h1>
    <p class="mt-4 text-secondary">Signed in as <?= $escape($user->email) ?></p>

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
