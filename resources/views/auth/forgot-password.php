<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var bool $sent */
?>
<section class="auth-panel" aria-labelledby="forgot-heading">
    <p class="eyebrow">Account recovery</p>
    <h1 id="forgot-heading" class="mt-3 text-3xl font-bold">Reset your password</h1>
    <?php if ($sent): ?>
        <div class="notice-success mt-6" role="status">
            If an active account matches that address, a reset link has been sent.
        </div>
    <?php endif; ?>
    <form class="mt-8 space-y-6" method="post" action="/forgot-password">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <div>
            <label class="form-label" for="reset-email">Email</label>
            <input class="form-input" id="reset-email" name="email" type="email" autocomplete="email" required>
        </div>
        <button class="button-primary w-full" type="submit">Send reset link</button>
    </form>
</section>
