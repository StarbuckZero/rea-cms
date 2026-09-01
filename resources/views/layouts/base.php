<?php

declare(strict_types=1);

use ReaCms\Auth\User;
use ReaCms\Plugin\PluginNavigationItem;

/** @var callable(mixed): string $escape */
/** @var string $title */
/** @var string $theme */
/** @var string $content */
/** @var User|null $authenticatedUser */
/** @var string|null $csrfToken */
/** @var bool $canAccessAdmin */
/** @var bool $canManagePlugins */
/** @var list<PluginNavigationItem> $pluginNavigation */
$canManagePlugins = $canManagePlugins ?? false;
?>
<!doctype html>
<html lang="en" data-theme="<?= $escape($theme) ?>"<?= $authenticatedUser === null
    ? ''
    : ' data-theme-account="true"' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $escape($title) ?></title>
    <script src="/assets/theme.js?v=1"></script>
    <link rel="stylesheet" href="/assets/app.css?v=5">
    <script src="/assets/htmx.min.js?v=4.0.0" defer></script>
    <script src="/assets/navigation.js?v=1" defer></script>
</head>
<body class="min-h-screen bg-surface text-primary antialiased">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="border-b border-default bg-surface-raised">
        <div class="page-shell flex items-center justify-between gap-4 py-4">
            <a class="text-lg font-semibold" href="/dashboard">REA CMS</a>
            <?php if ($authenticatedUser === null) : ?>
                <a class="button-primary" href="/login">Login</a>
            <?php else : ?>
                <nav class="main-navigation" aria-label="Main navigation">
                    <details class="navigation-menu plugins-menu" name="main-navigation" data-navigation-menu>
                        <summary>Plugins</summary>
                        <div class="navigation-menu-panel plugins-menu-panel">
                            <nav class="menu-navigation" aria-label="Plugins">
                                <?php if ($pluginNavigation === []) : ?>
                                    <p class="menu-empty-state">No plugins available</p>
                                <?php else : ?>
                                    <?php foreach ($pluginNavigation as $item) : ?>
                                        <a href="<?= $escape($item->path) ?>">
                                            <?= $escape($item->label) ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </details>
                    <details class="navigation-menu profile-menu" name="main-navigation" data-navigation-menu>
                        <summary aria-label="Open user profile menu">
                            <span class="profile-avatar" aria-hidden="true">
                                <?= $escape(mb_strtoupper(mb_substr($authenticatedUser->displayName, 0, 1))) ?>
                            </span>
                            <span class="profile-name"><?= $escape($authenticatedUser->displayName) ?></span>
                        </summary>
                        <div class="navigation-menu-panel profile-menu-panel">
                            <p class="text-sm text-secondary">Signed in as</p>
                            <p class="font-semibold"><?= $escape($authenticatedUser->displayName) ?></p>
                            <nav class="menu-navigation profile-navigation" aria-label="User navigation">
                                <a href="/profile">User Profile</a>
                                <a href="/dashboard">Dashboard</a>
                                <?php if ($canAccessAdmin) : ?>
                                    <a href="/admin">Admin</a>
                                <?php endif; ?>
                                <?php if ($canManagePlugins) : ?>
                                    <a href="/admin/plugins">Plugin Management</a>
                                <?php endif; ?>
                            </nav>
                            <form class="profile-logout" method="post" action="/logout">
                                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                <button type="submit">Logout</button>
                            </form>
                        </div>
                    </details>
                </nav>
            <?php endif; ?>
        </div>
    </header>
    <main id="main-content" class="page-shell py-12" tabindex="-1">
        <?= $content ?>
    </main>
    <div id="status-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
