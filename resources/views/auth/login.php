<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var string|null $error */
?>
<section class="auth-panel" aria-labelledby="login-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="login-heading" class="mt-3 text-3xl font-bold">Sign in</h1>
    <?php if ($error !== null): ?>
        <div class="notice-danger mt-6" role="alert"><?= $escape($error) ?></div>
    <?php endif; ?>
    <form class="mt-8 space-y-6" method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <div>
            <label class="form-label" for="email">Email</label>
            <input class="form-input" id="email" name="email" type="email" autocomplete="username" required>
        </div>
        <div>
            <label class="form-label" for="password">Password</label>
            <input class="form-input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="button-primary w-full" type="submit">Sign in</button>
    </form>
    <p class="mt-6 text-center"><a class="text-accent underline" href="/forgot-password">Forgot password?</a></p>
</section>
