<?php

declare(strict_types=1);

use ReaCms\Auth\User;

/** @var callable(mixed): string $escape */
/** @var User $user */
/** @var string $csrfToken */
/** @var list<string> $themes */
/** @var string|null $success */
/** @var string|null $profileError */
/** @var string|null $passwordError */
?>
<section aria-labelledby="profile-heading">
    <p class="eyebrow">Account</p>
    <h1 id="profile-heading" class="mt-3 text-3xl font-bold">User Profile</h1>
    <p class="mt-4 text-secondary">Manage your account information, password, and CMS appearance.</p>

    <?php if ($success !== null) : ?>
        <div class="notice-success mt-6" role="status"><?= $escape($success) ?></div>
    <?php endif; ?>

    <div class="profile-settings-grid mt-8">
        <section class="panel" aria-labelledby="account-settings-heading">
            <h2 id="account-settings-heading" class="text-xl font-bold">Account settings</h2>
            <p class="mt-3 text-secondary">Update the name shown throughout REA CMS and choose your theme.</p>
            <?php if ($profileError !== null) : ?>
                <div class="notice-danger mt-6" role="alert"><?= $escape($profileError) ?></div>
            <?php endif; ?>
            <form class="mt-6 space-y-5" method="post" action="/profile">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <div>
                    <label class="form-label" for="profile-email">Email</label>
                    <input class="form-input" id="profile-email" type="email"
                           value="<?= $escape($user->email) ?>" autocomplete="username" readonly>
                    <p class="form-help mt-2">Contact an administrator to change your sign-in email.</p>
                </div>
                <div>
                    <label class="form-label" for="display-name">Display name</label>
                    <input class="form-input" id="display-name" name="display_name"
                           value="<?= $escape($user->displayName) ?>" maxlength="191" autocomplete="name" required>
                </div>
                <div>
                    <label class="form-label" for="profile-theme">Theme</label>
                    <select class="form-input" id="profile-theme" name="theme" data-theme-select required>
                        <?php foreach ($themes as $theme) : ?>
                            <option value="<?= $escape($theme) ?>" <?= $user->theme === $theme ? 'selected' : '' ?>>
                                <?= $escape(ucwords(str_replace('-', ' ', $theme))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-help mt-2">Theme changes preview immediately and are saved to your account.</p>
                </div>
                <button class="button-primary" type="submit">Save profile</button>
            </form>
        </section>

        <section class="panel" aria-labelledby="password-settings-heading">
            <h2 id="password-settings-heading" class="text-xl font-bold">Change password</h2>
            <p class="mt-3 text-secondary">Confirm your current password, then use at least 12 characters.</p>
            <?php if ($passwordError !== null) : ?>
                <div class="notice-danger mt-6" role="alert"><?= $escape($passwordError) ?></div>
            <?php endif; ?>
            <form class="mt-6 space-y-5" method="post" action="/profile/password">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <div>
                    <label class="form-label" for="current-password">Current password</label>
                    <input class="form-input" id="current-password" name="current_password" type="password"
                           autocomplete="current-password" required>
                </div>
                <div>
                    <label class="form-label" for="profile-new-password">New password</label>
                    <input class="form-input" id="profile-new-password" name="new_password" type="password"
                           autocomplete="new-password" minlength="12" required>
                </div>
                <div>
                    <label class="form-label" for="profile-password-confirmation">Confirm new password</label>
                    <input class="form-input" id="profile-password-confirmation" name="password_confirmation"
                           type="password" autocomplete="new-password" minlength="12" required>
                </div>
                <button class="button-primary" type="submit">Change password</button>
            </form>
        </section>
    </div>
</section>
