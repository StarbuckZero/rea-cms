<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var string $title */
/** @var string $theme */
/** @var string $content */
?>
<!doctype html>
<html lang="en" data-theme="<?= $escape($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $escape($title) ?></title>
    <script src="/assets/theme.js"></script>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/htmx.min.js" defer></script>
</head>
<body class="min-h-screen bg-surface text-primary antialiased">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="border-b border-default bg-surface-raised">
        <div class="page-shell flex items-center justify-between gap-4 py-4">
            <span class="text-lg font-semibold">Rea CMS</span>
            <fieldset class="theme-picker" aria-label="Color theme">
                <legend class="sr-only">Color theme</legend>
                <?php foreach (['system', 'light', 'dark', 'high-contrast'] as $choice): ?>
                    <button type="button" data-theme-choice="<?= $escape($choice) ?>">
                        <?= $escape(ucwords(str_replace('-', ' ', $choice))) ?>
                    </button>
                <?php endforeach; ?>
            </fieldset>
        </div>
    </header>
    <main id="main-content" class="page-shell py-12" tabindex="-1">
        <?= $content ?>
    </main>
    <div id="status-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
