<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var string $csrfToken */
/** @var string $email */
/** @var string $token */
/** @var string|null $error */
?>
<section class="auth-panel" aria-labelledby="reset-heading">
    <p class="eyebrow">Account recovery</p>
    <h1 id="reset-heading" class="mt-3 text-3xl font-bold">Choose a new password</h1>
    <p class="mt-4 text-secondary">Use at least 12 characters.</p>
    <?php if ($error !== null): ?>
        <div class="notice-danger mt-6" role="alert"><?= $escape($error) ?></div>
    <?php endif; ?>
    <form class="mt-8 space-y-6" method="post" action="/reset-password">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <input id="reset-email-value" type="hidden" name="email" value="<?= $escape($email) ?>">
        <input id="reset-token-value" type="hidden" name="token" value="<?= $escape($token) ?>">
        <div>
            <label class="form-label" for="new-password">New password</label>
            <input class="form-input" id="new-password" name="password" type="password" autocomplete="new-password" minlength="12" required>
        </div>
        <div>
            <label class="form-label" for="password-confirmation">Confirm new password</label>
            <input class="form-input" id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
        </div>
        <button id="reset-submit" class="button-primary w-full" type="submit" disabled>Reset password</button>
    </form>
    <script src="/assets/reset-password.js?v=2"></script>
</section>
