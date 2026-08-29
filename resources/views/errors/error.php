<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var int $status */
/** @var string $message */
/** @var string $requestId */
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($status) ?> · Rea CMS</title>
    <script src="/assets/theme.js?v=1"></script>
    <link rel="stylesheet" href="/assets/app.css?v=2">
</head>
<body class="min-h-screen bg-surface text-primary">
    <main class="page-shell py-16">
        <p class="eyebrow">Error <?= $escape($status) ?></p>
        <h1 class="mt-3 text-3xl font-bold"><?= $escape($message) ?></h1>
        <p class="mt-6 text-secondary">
            Request ID: <code><?= $escape($requestId) ?></code>
        </p>
        <a class="button-primary mt-8 inline-flex" href="/">Return home</a>
    </main>
</body>
</html>
