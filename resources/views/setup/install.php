<?php

declare(strict_types=1);

use ReaCms\Setup\PlatformRequirement;

/** @var callable(mixed): string $escape */
/** @var list<PlatformRequirement> $requirements */
/** @var string $csrfToken */
/** @var array<string, string> $values */
/** @var string|null $error */
$value = static fn (string $key, string $default = ''): string => $values[$key] ?? $default;
$platformReady = !in_array(false, array_map(
    static fn (PlatformRequirement $requirement): bool => !$requirement->required || $requirement->passed,
    $requirements,
), true);
?>
<section class="mx-auto max-w-3xl" aria-labelledby="install-heading">
    <p class="eyebrow">First-time setup</p>
    <h1 id="install-heading" class="mt-3 text-4xl font-bold">Install Rea CMS</h1>
    <p class="mt-4 text-secondary">
        Connect an empty MySQL database, create the first administrator, and write the production configuration.
        Database names and users must be created in your hosting control panel first.
    </p>

    <?php if ($error !== null) : ?>
        <p class="notice-danger mt-6" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <section class="panel mt-8" aria-labelledby="requirements-heading">
        <h2 id="requirements-heading" class="text-2xl font-semibold">Server checks</h2>
        <ul class="mt-5 space-y-3">
            <?php foreach ($requirements as $requirement) : ?>
                <li class="flex items-start justify-between gap-4 border-b border-default pb-3">
                    <span>
                        <strong><?= $escape($requirement->label) ?></strong>
                        <span class="mt-1 block text-sm text-secondary"><?= $escape($requirement->detail) ?></span>
                    </span>
                    <span class="status-badge <?= $requirement->passed ? 'status-enabled' : 'status-invalid' ?>">
                        <?= $requirement->passed ? 'Pass' : 'Action needed' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <form class="mt-8 space-y-8" method="post" action="/install">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">

        <fieldset class="panel space-y-5">
            <legend class="text-2xl font-semibold">Site</legend>
            <div>
                <label class="form-label" for="app-url">Site URL</label>
                <input class="form-input" id="app-url" name="app_url" type="url"
                       value="<?= $escape($value('app_url', 'https://')) ?>" placeholder="https://example.com" required>
                <p class="form-help mt-2">Use the final HTTPS origin without a trailing path.</p>
            </div>
            <div>
                <label class="form-label" for="timezone">Timezone</label>
                <select class="form-input" id="timezone" name="timezone" required>
                    <?php foreach (DateTimeZone::listIdentifiers() as $timezone) : ?>
                        <option value="<?= $escape($timezone) ?>" <?= $value('timezone', 'UTC') === $timezone ? 'selected' : '' ?>>
                            <?= $escape($timezone) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="mail-from">Sender email</label>
                <input class="form-input" id="mail-from" name="mail_from" type="email"
                       value="<?= $escape($value('mail_from')) ?>" placeholder="no-reply@example.com" required>
            </div>
        </fieldset>

        <fieldset class="panel space-y-5">
            <legend class="text-2xl font-semibold">Database</legend>
            <div class="grid gap-5 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="form-label" for="db-host">Host</label>
                    <input class="form-input" id="db-host" name="db_host"
                           value="<?= $escape($value('db_host', 'localhost')) ?>" required>
                </div>
                <div>
                    <label class="form-label" for="db-port">Port</label>
                    <input class="form-input" id="db-port" name="db_port" type="number" min="1" max="65535"
                           value="<?= $escape($value('db_port', '3306')) ?>" required>
                </div>
            </div>
            <div>
                <label class="form-label" for="db-database">Database name</label>
                <input class="form-input" id="db-database" name="db_database"
                       value="<?= $escape($value('db_database')) ?>" required>
            </div>
            <div>
                <label class="form-label" for="db-username">Database username</label>
                <input class="form-input" id="db-username" name="db_username" autocomplete="username"
                       value="<?= $escape($value('db_username')) ?>" required>
            </div>
            <div>
                <label class="form-label" for="db-password">Database password</label>
                <input class="form-input" id="db-password" name="db_password" type="password"
                       autocomplete="new-password" required>
                <p class="form-help mt-2">Passwords are never redisplayed after a failed submission.</p>
            </div>
            <div>
                <label class="form-label" for="db-table-prefix">Table prefix</label>
                <input class="form-input" id="db-table-prefix" name="db_table_prefix"
                       value="<?= $escape($value('db_table_prefix', 'rea_')) ?>" pattern="[a-z][a-z0-9_]{0,31}" required>
            </div>
        </fieldset>

        <fieldset class="panel space-y-5">
            <legend class="text-2xl font-semibold">Administrator</legend>
            <div>
                <label class="form-label" for="admin-name">Display name</label>
                <input class="form-input" id="admin-name" name="admin_name"
                       value="<?= $escape($value('admin_name')) ?>" maxlength="191" required>
            </div>
            <div>
                <label class="form-label" for="admin-email">Email</label>
                <input class="form-input" id="admin-email" name="admin_email" type="email" autocomplete="username"
                       value="<?= $escape($value('admin_email')) ?>" required>
            </div>
            <div>
                <label class="form-label" for="admin-password">Password</label>
                <input class="form-input" id="admin-password" name="admin_password" type="password"
                       minlength="12" maxlength="1024" autocomplete="new-password" required>
            </div>
            <div>
                <label class="form-label" for="admin-password-confirmation">Confirm password</label>
                <input class="form-input" id="admin-password-confirmation" name="admin_password_confirmation"
                       type="password" minlength="12" maxlength="1024" autocomplete="new-password" required>
            </div>
        </fieldset>

        <div class="panel">
            <p class="text-sm text-secondary">
                Installation refuses to overwrite an existing <code>.env</code> file or database tables using this prefix.
            </p>
            <button class="button-primary mt-5" type="submit" <?= $platformReady ? '' : 'disabled' ?>>
                Install Rea CMS
            </button>
        </div>
    </form>
</section>
