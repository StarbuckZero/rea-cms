<?php

declare(strict_types=1);

use ReaCms\Auth\User;

/** @var callable(mixed): string $escape */
/** @var string $title */
/** @var string $theme */
/** @var string $content */
/** @var User|null $authenticatedUser */
/** @var string|null $csrfToken */
/** @var bool $canAccessAdmin */
?>
<!doctype html>
<html lang="en" data-theme="<?= $escape($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $escape($title) ?></title>
    <script src="/assets/theme.js?v=1"></script>
    <link rel="stylesheet" href="/assets/app.css?v=3">
    <script src="/assets/htmx.min.js?v=4.0.0" defer></script>
</head>
<body class="min-h-screen bg-surface text-primary antialiased">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="border-b border-default bg-surface-raised">
        <div class="page-shell flex items-center justify-between gap-4 py-4">
            <a class="text-lg font-semibold" href="/dashboard">Rea CMS</a>
            <?php if ($authenticatedUser === null) : ?>
                <a class="button-primary" href="/login">Login</a>
            <?php else : ?>
                <details class="profile-menu">
                    <summary aria-label="Open user profile menu">
                        <span class="profile-avatar" aria-hidden="true">
                            <?= $escape(mb_strtoupper(mb_substr($authenticatedUser->displayName, 0, 1))) ?>
                        </span>
                        <span><?= $escape($authenticatedUser->displayName) ?></span>
                    </summary>
                    <div class="profile-menu-panel">
                        <p class="text-sm text-secondary">Signed in as</p>
                        <p class="font-semibold"><?= $escape($authenticatedUser->displayName) ?></p>
                        <nav class="profile-navigation" aria-label="User navigation">
                            <a href="/dashboard">Dashboard</a>
                            <?php if ($canAccessAdmin) : ?>
                                <a href="/admin">Admin</a>
                            <?php endif; ?>
                        </nav>
                        <details class="settings-menu">
                            <summary>Settings</summary>
                            <fieldset class="theme-picker" aria-label="Color theme">
                                <legend>Theme</legend>
                                <?php foreach (['system', 'light', 'dark', 'high-contrast'] as $choice) : ?>
                                    <button type="button" data-theme-choice="<?= $escape($choice) ?>">
                                        <?= $escape(ucwords(str_replace('-', ' ', $choice))) ?>
                                    </button>
                                <?php endforeach; ?>
                            </fieldset>
                        </details>
                        <form class="profile-logout" method="post" action="/logout">
                            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                            <button type="submit">Logout</button>
                        </form>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </header>
    <main id="main-content" class="page-shell py-12" tabindex="-1">
        <?= $content ?>
    </main>
    <div id="status-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
