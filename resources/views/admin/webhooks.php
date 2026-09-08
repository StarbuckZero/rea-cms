<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var list<array<string, mixed>> $hooks */
/** @var list<array<string, mixed>> $deliveries */
/** @var list<string> $events */
/** @var string $csrfToken */
/** @var ?string $secret */
/** @var ?string $success */
/** @var ?string $error */
?>
<section aria-labelledby="webhooks-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="webhooks-heading" class="mt-3 text-3xl font-bold">Webhooks</h1>
    <p class="mt-4 text-secondary">Notify your website when blog posts, gallery content, or text blocks change.</p>
    <?php if ($error !== null) : ?><p class="panel mt-6" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <?php if ($success !== null) : ?><p class="panel mt-6" role="status"><?= $escape($success) ?></p><?php endif; ?>
    <?php if ($secret !== null) : ?>
        <div class="panel mt-6">
            <label for="signing-secret">Signing secret — copy now and store on your website’s server</label>
            <input id="signing-secret" type="text" readonly value="<?= $escape($secret) ?>" autocomplete="off">
        </div>
    <?php endif; ?>
    <section class="panel mt-6" aria-labelledby="new-hook-heading">
        <h2 id="new-hook-heading" class="text-xl font-semibold">Add a webhook</h2>
        <form method="post" action="/admin/webhooks" class="mt-5 space-y-4">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="create">
            <div><label for="hook-name">Name</label><input id="hook-name" name="name" required maxlength="191"></div>
            <div><label for="hook-url">Website receiver URL</label>
                <input id="hook-url" name="url" type="url" placeholder="https://example.com/api/rea-webhook" required maxlength="2048">
                <p class="text-secondary">Use a public HTTPS endpoint on port 443. A signing secret is generated for you.</p>
            </div>
            <fieldset><legend>Events</legend>
                <?php foreach ($events as $event) : ?>
                    <label class="block"><input type="checkbox" name="events[]" value="<?= $escape($event) ?>"> <?= $escape($event) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit" class="button-primary">Create webhook</button>
        </form>
    </section>
    <h2 class="mt-10 text-xl font-semibold">Destinations</h2>
    <?php if ($hooks === []) : ?><p class="mt-4 text-secondary">No webhooks configured.</p><?php endif; ?>
    <?php foreach ($hooks as $hook) : ?>
        <?php $selected = json_decode((string) $hook['events_json'], true) ?? []; ?>
        <section class="panel mt-6">
            <h3 class="text-xl font-semibold"><?= $escape($hook['name']) ?></h3>
            <p class="mt-3 break-all"><?= $escape($hook['url']) ?></p>
            <p class="text-secondary">To change the URL or signing secret, disable this destination and create a new one.</p>
            <form method="post" action="/admin/webhooks" class="mt-5 space-y-4">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="configure">
                <input type="hidden" name="id" value="<?= $escape($hook['id']) ?>">
                <label><input type="checkbox" name="active" value="1" <?= $hook['status'] === 'active' ? 'checked' : '' ?>> Enabled</label>
                <details><summary>Subscribed events</summary><fieldset><legend>Select events</legend>
                    <?php foreach ($events as $event) : ?>
                        <label class="block"><input type="checkbox" name="events[]" value="<?= $escape($event) ?>" <?= in_array($event, $selected, true) ? 'checked' : '' ?>> <?= $escape($event) ?></label>
                    <?php endforeach; ?>
                </fieldset></details>
                <button class="button-secondary" type="submit">Save settings</button>
            </form>
            <form method="post" action="/admin/webhooks" class="mt-4">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="test">
                <input type="hidden" name="id" value="<?= $escape($hook['id']) ?>">
                <button class="button-secondary" type="submit" <?= $hook['status'] !== 'active' ? 'disabled' : '' ?>>Send test</button>
            </form>
        </section>
    <?php endforeach; ?>
    <h2 class="mt-10 text-xl font-semibold">Recent deliveries</h2>
    <p class="mt-3 text-secondary">Latest 100 deliveries. Refresh to see progress. Disabled destinations cancel pending deliveries when processed.</p>
    <div class="mt-6 overflow-x-auto"><table>
        <thead><tr><th scope="col">Destination / event</th><th scope="col">Status</th><th scope="col">Attempts</th><th scope="col">HTTP</th><th scope="col">Created (UTC)</th><th scope="col">Action</th></tr></thead>
        <tbody>
        <?php foreach ($deliveries as $delivery) : ?>
            <tr><td><?= $escape($delivery['name']) ?><br><?= $escape($delivery['event_type']) ?><br><small><?= $escape($delivery['delivery_id']) ?></small></td>
                <td><?= $escape($delivery['delivery_status']) ?></td><td><?= $escape($delivery['attempts']) ?></td>
                <td><?= $escape($delivery['response_status'] ?? '—') ?></td><td><?= $escape($delivery['created_at']) ?></td>
                <td><?php if (in_array($delivery['delivery_status'], ['failed', 'cancelled'], true)) : ?>
                    <form method="post" action="/admin/webhooks">
                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="retry">
                        <input type="hidden" name="delivery_id" value="<?= $escape($delivery['delivery_id']) ?>">
                        <button class="button-secondary" type="submit">Retry</button>
                    </form>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
