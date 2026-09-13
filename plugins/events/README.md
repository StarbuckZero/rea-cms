# Events / Schedule

The `events` plugin follows the bundled RSS/Podcast, Gallery, and Text Block architecture: declarative plugin files under `plugins/events`, trusted PHP controllers under `app/Events`, and CMS views under `resources/views/cms/events`. No new Composer or JavaScript dependencies are required.

## Installation and updates

Deploy the CMS code and built assets with this plugin, then open **Admin → Plugins**, discover the bundled **Events / Schedule** plugin, install it, and enable it. Alternatively run `php bin/install-reference-events.php --enable`. Assign Events access to editors using the existing plugin access settings. The plugin ZIP contains declarative resources; it does not replace the trusted CMS controller code. Existing installations need the corresponding CMS update. The Events route loads its new trusted classes explicitly, so ZIP updates also work with older authoritative Composer class maps without running Composer on the host.

The existing migration runner applies `migrations/001_install.json` once. Plugin tables use the established unprefixed `plugin_events_*` namespace; core media/settings tables respect `DB_TABLE_PREFIX`. Disabling the plugin hides its API and CMS routes. Uninstall preserves data; the existing export/purge workflow handles its tables and media references. Do not edit applied migrations; add numbered migrations for updates.

## Administration

Open `/cms/events` to search, filter, paginate, publish/unpublish, and create events. Editing an event supports duplication as an unpublished draft, deletion, and an authenticated preview of the saved event. Manage types and the default event image in the sections below the event list. Deleting a type leaves its events uncategorized.

Image selection uses the existing Media library, as in Gallery. Upload in Media first, then select an image in Events. Only existing public images can be selected. The event's own image takes precedence over the current plugin default. References are registered in the CMS media usage table to protect selected images from deletion while they are in use.

The **Edit HTML / Text templates** link opens the existing administrator template editor at `/admin/plugins/events/api-templates`, including its field catalog, Insert buttons, preview, save, and reset actions. There are four independently editable slots: HTML list/detail and Text list/detail. List templates repeat once per event. Templates use the shared safe renderer; `{event.description | sanitized_html}` allows sanitized rich text in HTML, and Text output strips markup and uses the CMS's readable ASCII text conversion. ICS preserves UTF-8.

`{event.type}` and `{event.location}` are display aliases in templates. JSON exposes these as structured objects. Additional type aliases are `{event.typeId}`, `{event.typeName}`, and `{event.typeSlug}`. Address components use `{event.address}`, `{event.city}`, `{event.state}`, `{event.zip}`, and `{event.country}`. The template editor lists all supported fields.

## Public API

- `GET /api/v1/events.json`, `.html`, `.txt`
- `GET /api/v1/events/{id}.json`, `.html`, `.txt`
- `GET /api/v1/events/{id}.ics`
- `GET /api/v1/events/{id}/calendar` (same calendar response)
- `GET /api/v1/event-types.json`

Only published events appear on public list, detail, and calendar endpoints. Missing, unpublished, and disabled-plugin event resources return 404. Origin validation and CORS follow the existing RSS/Podcast plugin convention and `API_ALLOWED_ORIGINS`; scripts must send an allowlisted `Origin` header, for example `curl -H "Origin: https://your-cms.example" https://your-cms.example/api/v1/events.txt`. Same-origin browser navigation is also accepted when browser fetch metadata and an allowlisted Referer identify the CMS origin. Write access is separate from public reading.

JSON uses the CMS `data` envelope. Lists also return `meta: {page, perPage, total, totalPages}` and `links: {self, next, previous}`. Detail data includes integer IDs, a nullable `type: {id, name, slug}`, `location: {name, address, city, state, zip, country}`, boolean `allDay` and `published`, ISO timestamps, local date/time fields, image URLs, and a `calendarUrl`.

### Filters and sorting

Filters can be combined, using either the friendly names below or the existing `filter_` prefix:

| Parameter | Meaning |
| --- | --- |
| `search` | Literal substring of event title; `%` and `_` are not wildcards |
| `type` | User-managed type slug or numeric ID |
| `date` | Local calendar date, `YYYY-MM-DD`; includes events spanning that day |
| `start`, `end` | Inclusive local date range; either boundary can be omitted; matches overlapping events |
| `upcoming=true` | Events whose end instant is still in the future, including events in progress |
| `past=true` | Events whose end instant has passed |
| `sort` | `date` (default), `date-desc`, `name`, `name-desc` |
| `direction` | Existing `asc`/`desc` convention; a `-desc` sort suffix takes precedence |
| `page`, `perPage` | Positive integers; defaults 1 and 20; `perPage` capped at 100 |

Without temporal filters, both past and future events are included. Ascending date sorting places the nearest upcoming event first within an upcoming query. Ties use Event ID for stable pagination. Invalid dates, reversed ranges, malformed booleans, and simultaneous `past=true&upcoming=true` return 422.

Example: `/api/v1/events.json?type=comedy-show&start=2026-10-01&end=2026-10-31&perPage=10`.

### Authenticated writes

Writes follow the CMS's existing session and CSRF conventions: an active logged-in CMS user with Events plugin access, the session cookie, and an `X-CSRF-Token` header (or `_csrf` in form submissions) are required. Public API access does not grant editing access. Bearer-token writes are not implemented by the existing plugin management convention used here.

- `POST /api/v1/events.json`: create
- `PATCH /api/v1/events/{id}.json`: update supplied fields
- `DELETE /api/v1/events/{id}.json`: delete
- `POST /api/v1/events/{id}/publish` or `/unpublish`
- `POST /api/v1/events/{id}/duplicate`: create an unpublished copy with a new ID
- `POST /api/v1/event-types.json`: create a type
- `PATCH /api/v1/event-types/{id}.json`: edit a type (send name, slug, description)
- `DELETE /api/v1/event-types/{id}.json`: delete a type

Use `Content-Type: application/json`. Write fields follow the existing CMS form/storage naming convention:

```json
{
  "title": "Comedy Night",
  "type_id": 3,
  "short_description": "Monthly comedy show.",
  "description": "<p>Full event description.</p>",
  "image_id": null,
  "location": "Devaney's Sports Pub",
  "address": "123 Example Street",
  "city": "Orlando",
  "state": "FL",
  "zip": "32801",
  "country": "US",
  "start_date": "2026-10-10",
  "start_time": "20:00",
  "end_date": "2026-10-10",
  "end_time": "22:00",
  "timezone": "America/New_York",
  "all_day": false,
  "url": "https://example.com/events/comedy-night",
  "published": true
}
```

The title, valid start date, IANA time zone, and both times are required for timed events. An omitted end date defaults to the start date. All-day events omit times. Optional fields default to empty values, no type/image, and unpublished. IDs, creation/update dates, and UTC comparison values are generated by the server.

## Dates and calendars

Dates/times are stored with the event's IANA zone, plus indexed UTC instants for chronological sorting and upcoming/past comparisons. JSON timestamps retain the zone's offset, such as `2026-10-10T20:00:00-04:00`; human-readable fields are additional values. Values are stored as ISO strings, avoiding the MySQL TIMESTAMP 2038 scheduling limit.

Timed event ends must be after starts. Invalid dates and nonexistent daylight-saving wall times are rejected. Repeated wall times during the fall clock change are also rejected because this editor has no offset selector; choose an unambiguous time. This avoids silently assigning the wrong instant.

An all-day end date is the **last included day** in the editor and `endDate`. Its `endDateTime` and ICS `DTEND` are midnight at the start of the following day, as required by iCalendar. All-day `startTime`/`endTime` are null in JSON.

Calendar responses use `text/calendar; charset=UTF-8`, CRLF line endings, escaped text, UTF-8-safe 75-octet line folding, a stable installation-specific UID, and created/updated timestamps. Timed events use UTC DTSTART/DTEND so clients can display them in their own zones without floating-time ambiguity; `X-REA-TIMEZONE` preserves the original zone name. All-day events use DATE values. ICS is generated from the current published record on every request with revalidation required. A downloaded/imported file is a snapshot, not a subscribed calendar.

Reference: [RFC 5545](https://www.rfc-editor.org/rfc/rfc5545), especially content-line folding, text escaping, and VEVENT date/end semantics.

## Validation

Run `composer check` and `npm run build`. Database integration tests create and remove randomly named databases on a disposable local MySQL/MariaDB server:

```sh
REA_EVENTS_TEST_SOCKET=/path/to/disposable/mysql.sock vendor/bin/phpunit tests/Integration/Events
```

Never point this test socket at a production server.
