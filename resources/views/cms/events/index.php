<?php

declare(strict_types=1);

/** @var callable(mixed): string $escape */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $types */
/** @var list<array<string,mixed>> $media */
/** @var \ReaCms\Api\Query\ApiQuery $query */
/** @var array<string,mixed> $filters */
/** @var int $total */
/** @var int|null $defaultImage */
/** @var string $csrfToken */
?>
<section>
    <p class="eyebrow">Events / Schedule</p><h1 class="mt-3 text-3xl font-bold">Events</h1>
    <div class="button-row mt-6">
        <a class="button-primary" href="/cms/events/new">New event</a>
        <a class="button-secondary" href="/admin/plugins/events/api-templates">Edit HTML / Text templates</a>
    </div>
    <form class="panel mt-6 space-y-4" method="get" action="/cms/events" role="search">
        <label class="form-label">Search event names<input class="form-input" type="search" name="search" value="<?= $escape($query->filters['search'] ?? '') ?>"></label>
        <label class="form-label">Event type<select class="form-input" name="type"><option value="">All types</option>
            <?php foreach ($types as $type) : ?><option value="<?= $escape($type['slug']) ?>" <?= ($query->filters['type'] ?? '') === $type['slug'] ? 'selected' : '' ?>><?= $escape($type['name']) ?></option><?php endforeach; ?>
        </select></label>
        <?php foreach (['start' => 'From date', 'end' => 'Through date'] as $key => $label) : ?>
            <label class="form-label"><?= $escape($label) ?><input class="form-input" type="date" name="<?= $escape($key) ?>" value="<?= $escape($query->filters[$key] ?? '') ?>"></label>
        <?php endforeach; ?>
        <label><input type="checkbox" name="upcoming" value="true" <?= ($query->filters['upcoming'] ?? '') === 'true' ? 'checked' : '' ?>> Upcoming / in progress</label>
        <label><input type="checkbox" name="past" value="true" <?= ($query->filters['past'] ?? '') === 'true' ? 'checked' : '' ?>> Past</label>
        <label class="form-label">Sort<select class="form-input" name="sort">
            <?php foreach (['date' => 'Date: earliest first', 'date-desc' => 'Date: latest first', 'name' => 'Name: A–Z', 'name-desc' => 'Name: Z–A'] as $value => $label) : ?><option value="<?= $escape($value) ?>" <?= ($query->sort ?? 'date') === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?>
        </select></label>
        <div class="button-row"><button class="button-primary">Apply filters</button><a href="/cms/events">Clear</a></div>
    </form>
    <div class="panel mt-6 overflow-x-auto">
        <p><?= $total ?> events · Page <?= $query->page ?></p>
        <table class="w-full mt-4 text-left"><thead><tr><th>Event</th><th>Type</th><th>Date / time zone</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($events as $event) : ?>
            <tr>
                <td><a href="/cms/events/<?= (int) $event['id'] ?>/edit"><?= $escape($event['title']) ?></a></td>
                <td><?= $escape($event['type_name'] ?? 'Uncategorized') ?></td>
                <td><?= $escape($event['start_date']) ?> <?= $escape($event['start_time']) ?><br><?= $escape($event['timezone']) ?></td>
                <td><?= $event['published'] ? 'Published' : 'Unpublished' ?></td>
                <td><a href="/cms/events/<?= (int) $event['id'] ?>/preview">Preview</a>
                    <form method="post" action="/cms/events/<?= (int) $event['id'] ?>/<?= $event['published'] ? 'unpublish' : 'publish' ?>">
                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button><?= $event['published'] ? 'Unpublish' : 'Publish' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($events === []) : ?><tr><td colspan="5">No events match these filters.</td></tr><?php endif; ?>
        </tbody></table>
        <nav class="button-row mt-4" aria-label="Event pages">
            <?php if ($query->page > 1) : ?><a href="?<?= $escape(http_build_query([...$filters, 'page' => $query->page - 1])) ?>">Previous</a><?php endif; ?>
            <?php if ($query->page * $query->perPage < $total) : ?><a href="?<?= $escape(http_build_query([...$filters, 'page' => $query->page + 1])) ?>">Next</a><?php endif; ?>
        </nav>
    </div>
    <details class="panel mt-6"><summary class="text-xl font-semibold">Manage event types</summary>
        <p class="form-help mt-4">Deleting a type keeps its events and makes them uncategorized.</p>
        <?php foreach ([...$types, ['id' => null, 'name' => '', 'slug' => '', 'description' => '']] as $type) : ?>
            <form class="space-y-3 mt-6" method="post" action="/cms/events/types<?= $type['id'] ? '/' . (int) $type['id'] : '' ?>">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <label class="form-label"><?= $type['id'] ? 'Type name' : 'New type name' ?><input class="form-input" name="name" required maxlength="191" value="<?= $escape($type['name']) ?>"></label>
                <label class="form-label">Slug (leave empty to generate)<input class="form-input" name="slug" maxlength="191" value="<?= $escape($type['slug']) ?>"></label>
                <label class="form-label">Description<textarea class="form-input" name="description" maxlength="60000"><?= $escape($type['description']) ?></textarea></label>
                <button class="button-primary"><?= $type['id'] ? 'Save type' : 'Create type' ?></button>
            </form>
            <?php if ($type['id']) : ?><form class="mt-2" method="post" action="/cms/events/types/<?= (int) $type['id'] ?>/delete" data-confirm-delete="<?= $escape($type['name']) ?>"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary">Delete type</button></form><?php endif; ?>
        <?php endforeach; ?>
    </details>
    <details class="panel mt-6"><summary class="text-xl font-semibold">Default event image</summary>
        <form class="mt-4 space-y-4" method="post" action="/cms/events/settings">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <label class="form-label">Choose an image from the media library<select class="form-input" name="default_image_id"><option value="">No default image</option>
                <?php foreach ($media as $image) : ?><option value="<?= (int) $image['id'] ?>" <?= $defaultImage === (int) $image['id'] ? 'selected' : '' ?>><?= $escape($image['original_name']) ?></option><?php endforeach; ?>
            </select></label>
            <button class="button-primary">Save default image</button><a href="/cms/media">Media library</a>
        </form>
    </details>
</section>
<script src="/assets/text-blocks.js?v=1" defer></script>
