<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var string $title */
/** @var string $content */
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $escape($title) ?></title>
    <script src="/assets/theme.js?v=1"></script>
    <link rel="stylesheet" href="/assets/app.css?v=7">
</head>
<body class="min-h-screen bg-surface text-primary antialiased">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="border-b border-default bg-surface-raised">
        <div class="page-shell py-4">
            <span class="text-lg font-semibold">REA CMS</span>
        </div>
    </header>
    <main id="main-content" class="page-shell py-12" tabindex="-1">
        <?= $content ?>
    </main>
</body>
</html>
