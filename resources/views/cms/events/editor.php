<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var array<string,mixed>|null $event */
/** @var list<array<string,mixed>> $types */
/** @var list<array<string,mixed>> $media */
/** @var string $csrfToken */
$id = (int) ($event['id'] ?? 0);
$event ??= [];
?>
<section>
    <p class="eyebrow">Events / Schedule</p>
    <h1 class="mt-3 text-3xl font-bold"><?= $id ? 'Edit event' : 'New event' ?></h1>
    <?php if (isset($error)) : ?><p class="panel mt-4" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <form class="panel mt-8 space-y-5" method="post" action="/cms/events<?= $id ? '/' . $id : '' ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label class="form-label">Title
            <input class="form-input" name="title" required maxlength="255" value="<?= $escape($event['title'] ?? '') ?>">
        </label>
        <label class="form-label">Event type
            <select class="form-input" name="type_id"><option value="">Uncategorized</option>
                <?php foreach ($types as $type) : ?>
                    <option value="<?= (int) $type['id'] ?>" <?= (int) ($event['type_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>><?= $escape($type['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php foreach (['short_description' => 'Short description', 'description' => 'Full description'] as $key => $label) : ?>
            <label class="form-label"><?= $escape($label) ?>
                <textarea class="form-input" name="<?= $escape($key) ?>" rows="<?= $key === 'description' ? 8 : 3 ?>" maxlength="60000"><?= $escape($event[$key] ?? '') ?></textarea>
            </label>
        <?php endforeach; ?>
        <p class="form-help">Descriptions support safe HTML formatting. Text output removes formatting.</p>
        <label class="form-label">Event image
            <select class="form-input" name="image_id" data-event-image>
                <option value="">Use default event image</option>
                <?php foreach ($media as $image) : ?>
                    <option value="<?= (int) $image['id'] ?>" <?= (int) ($event['image_id'] ?? 0) === (int) $image['id'] ? 'selected' : '' ?>><?= $escape($image['original_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <img data-event-image-preview class="max-w-xs" src="<?= empty($event['image_id']) ? '' : '/media/' . (int) $event['image_id'] ?>" alt="Selected event image" <?= empty($event['image_id']) ? 'hidden' : '' ?>>
        <p><a href="/cms/media" target="_blank" rel="noopener">Open media library</a> to upload images, then reload this editor.</p>
        <fieldset class="space-y-4"><legend class="text-xl font-semibold">Location</legend>
            <?php foreach (['location' => 'Location name', 'address' => 'Street address', 'city' => 'City', 'state' => 'State / region', 'zip' => 'ZIP / postal code', 'country' => 'Country'] as $key => $label) : ?>
                <label class="form-label"><?= $escape($label) ?><input class="form-input" name="<?= $escape($key) ?>" maxlength="255" value="<?= $escape($event[$key] ?? '') ?>"></label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="space-y-4"><legend class="text-xl font-semibold">Date and time</legend>
            <input type="hidden" name="all_day" value="0">
            <label><input type="checkbox" name="all_day" value="1" data-event-all-day <?= !empty($event['all_day']) ? 'checked' : '' ?>> All-day event</label>
            <?php foreach (['start_date' => 'Start date', 'end_date' => 'End date (last day, inclusive)', 'start_time' => 'Start time', 'end_time' => 'End time'] as $key => $label) : ?>
                <label class="form-label"><?= $escape($label) ?><input class="form-input" type="<?= str_ends_with($key, 'date') ? 'date' : 'time' ?>" name="<?= $escape($key) ?>" value="<?= $escape($event[$key] ?? '') ?>" <?= str_ends_with($key, 'time') ? 'data-event-time step="1"' : 'required' ?>></label>
            <?php endforeach; ?>
            <label class="form-label">Time zone
                <input class="form-input" name="timezone" list="event-timezones" required maxlength="64" value="<?= $escape($event['timezone'] ?? 'UTC') ?>">
                <datalist id="event-timezones"><?php foreach (DateTimeZone::listIdentifiers() as $zone) : ?><option value="<?= $escape($zone) ?>"></option><?php endforeach; ?></datalist>
            </label>
            <p class="form-help">Times use the event's time zone. All-day end dates include the selected day.</p>
        </fieldset>
        <label class="form-label">Event website<input class="form-input" type="url" name="url" maxlength="1000" value="<?= $escape($event['url'] ?? '') ?>"></label>
        <input type="hidden" name="published" value="0">
        <label><input type="checkbox" name="published" value="1" <?= !empty($event['published']) ? 'checked' : '' ?>> Published (visible through the public API)</label>
        <?php if ($id) : ?>
            <p class="form-help">Event ID: <?= $id ?> · Created: <?= $escape($event['created_at'] ?? '') ?> · Updated: <?= $escape($event['updated_at'] ?? '') ?></p>
        <?php endif; ?>
        <div class="button-row"><button class="button-primary">Save event</button><a class="button-secondary" href="/cms/events">Back to events</a>
            <?php if ($id) : ?><a class="button-secondary" target="_blank" rel="noopener" href="/cms/events/<?= $id ?>/preview">Preview saved event</a><?php endif; ?>
        </div>
    </form>
    <?php if ($id) : ?>
        <div class="button-row mt-4">
            <?php foreach (['duplicate' => 'Duplicate as draft', 'delete' => 'Delete event'] as $action => $label) : ?>
                <form method="post" action="/cms/events/<?= $id ?>/<?= $escape($action) ?>" <?= $action === 'delete' ? 'data-confirm-delete="this event"' : '' ?>>
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary"><?= $escape($label) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script src="/assets/events.js?v=1" defer></script>
<script src="/assets/text-blocks.js?v=1" defer></script>
