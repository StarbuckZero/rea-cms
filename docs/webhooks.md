# Content webhooks

REA sends signed JSON notifications when content changes through the CMS. Configure
subscriptions at **Administration → Webhooks** (`/admin/webhooks`). Access requires
both `core.admin.access` and `core.webhooks.manage`; the existing core migration
assigns webhook management to super administrators.

## Installation and operation

1. Run `php bin/migrate.php` after updating the application. Migration 008 adds
   delivery status tracking. Apply migrations before allowing editors to save.
2. Open Webhooks, enter a descriptive name and your website's public HTTPS receiver
   URL, and select events. Copy the generated signing secret into your receiver's
   server-side configuration. REA shows it once and stores it encrypted with `APP_KEY`.
3. Run `php bin/deliver-webhooks.php --limit=100` to process available deliveries.
   Schedule this command every minute with your hosting provider's scheduler, or
   invoke it repeatedly through your process supervisor. It exits when no jobs are
   currently due or the limit is reached. No worker is started automatically.
4. Use **Send test** and check **Recent deliveries**. Test notifications have event
   `webhook.test`, use the same signing protocol, and do not change content.

The destination must resolve entirely to public addresses and use credential-free
HTTPS on port 443. Redirects are treated as failures. The sender validates DNS
again immediately before sending and pins the connection to an approved IP,
verifies TLS, disables proxies, limits connection time to 3 seconds and total
request time to 5 seconds, and caps response bodies at 64 KiB and headers at 16 KiB.

Changing subscribed events affects future events. Disabling a destination stops
new notifications; the worker marks outstanding deliveries cancelled when it sees
the destination disabled. An already-running request may finish. To replace a URL
or secret, disable the old destination and create a new one. Destinations and
secrets are immutable so pending deliveries cannot accidentally go to a different
receiver. Preserve `APP_KEY` when deploying so stored secrets remain decryptable.

## Events

| Resource | Events |
| --- | --- |
| Blog posts | `blog.post.created`, `.updated`, `.deleted`, `.published`, `.unpublished` |
| Text blocks | `text_block.block.created`, `.updated`, `.deleted` |
| Gallery albums | `gallery.album.created`, `.updated`, `.deleted`, `.published`, `.unpublished`, `.reordered` |
| Gallery items | `gallery.item.created`, `.updated`, `.deleted` |

Each abbreviated suffix in this table uses its row's full prefix. For example,
`gallery.album.updated` is an event name.

Create/update/delete events include draft edits. Payloads contain identifiers and
state, never titles, content bodies, captions, or media files. Blog public-state
transitions also emit published/unpublished events; changing visibility from
public to private unpublishes a blog post for this purpose. Deleting a published
post or album emits both deleted and unpublished notifications. Text blocks have
no separate publishing state.

Gallery item updates cover metadata edits and moves between albums. A shared image
metadata edit notifies every item referencing that media. Reordering emits an
album event (album ID `0` represents the unassigned collection). Deleting an album
also emits item updates for items moved to the unassigned collection.

These events follow CMS write operations. Direct SQL changes, plugin lifecycle
changes, and time passing across manually set publication dates do not emit events.
The current CMS blog editor publishes immediately; scheduled publication would
need its own scheduler integration before using webhooks for timed invalidation.

## Delivery contract

A notification looks like this:

```json
{
  "version": 1,
  "event_id": "995e67167455c0eaa9a32abe17954493",
  "event": "text_block.block.updated",
  "occurred_at": "2026-09-07T19:00:00Z",
  "data": {
    "resource": "text_block.block",
    "id": 123,
    "before": {"id": 123, "name": "old-name"},
    "after": {"id": 123, "name": "welcome-message"}
  }
}
```

Available identifiers depend on the resource: `id`, `slug`, `name`, `locale`,
`status`, `visibility`, `album_id`, `media_id`, and `position`. `before` is null
on creation; `after` is null on deletion. An event ID identifies the content event
across subscribed destinations. Each destination has a separate delivery ID.

Headers:

- `Content-Type: application/json`
- `X-Rea-Delivery`: unique delivery ID, unchanged across retries.
- `X-Rea-Timestamp`: Unix timestamp for this attempt.
- `X-Rea-Signature`: lowercase hexadecimal HMAC-SHA-256 of
  `timestamp + "." + deliveryId + "." + rawRequestBody`, using the signing secret
  as the literal UTF-8 string shown by REA (do not hex-decode it).

Verify the signature against the **raw body bytes before parsing JSON**, use a
constant-time comparison, and reject timestamps more than five minutes away from
your server clock. Keep the secret on your server. Timestamp validation alone does
not prevent duplicate processing: persist delivery IDs and atomically accept each
ID once, together with its refresh job.

Any 2xx response acknowledges acceptance. All non-2xx responses, redirects,
timeouts, and transport failures retry through the job queue, with three attempts
per run by default (60 seconds then 120 seconds between attempts, plus scheduler
latency). Manual retry of failed/cancelled deliveries starts another three-attempt
run with the same delivery ID and payload. Delivery history retains the total
attempt count and last HTTP status; response bodies are discarded.

Content saves, delivery records, and jobs commit together. A queue insertion
failure rolls back the content save. Delivery is asynchronous and at least once:
a receiver may see a duplicate if a request succeeds but the acknowledgment is
lost. Events can arrive out of order after retries. Fetch current API content
instead of applying old payloads as authoritative content.

## Connecting a frontend

Add a backend/serverless POST endpoint to the consuming website. The browser does
not receive REA webhooks. The endpoint should:

1. Verify the headers, timestamp, and raw-body HMAC.
2. Validate payload version 1 and supported events. Treat `webhook.test` as a
   connection check.
3. Atomically store the delivery ID and enqueue cache invalidation or a rebuild.
   A previously accepted delivery ID should receive a successful response again.
4. Return 2xx promptly after durable acceptance; process expensive rebuilds later.

Refresh both old and new identifiers after renames. Blog changes should invalidate
the post and blog listings. Text-block changes should invalidate every page using
the block. Gallery changes should invalidate listings and both affected albums;
reordering invalidates the named album. Deleted/unpublished content may no longer
be returned by the public API, so clear cached content even when refetch returns
404. Gallery item notifications contain both old and new `album_id` values.

Existing APIs remain the source of content, including `/api/v1/blog/{id}.json`
and `/api/v1/text-block/name/{name}.json`. A static site can trigger a rebuild;
a cached server-rendered site can invalidate affected pages. To refresh already
open browser pages, the website's backend can additionally signal browsers with
SSE or WebSockets, then have them refetch.

## Database integration tests

The regular test suite includes signing, destination security, and admin access
checks. Database tests require a disposable MySQL/MariaDB server accessible through
a Unix socket with a local root account and no password:

```sh
REA_WEBHOOK_TEST_SOCKET=/path/to/test-mysql.sock vendor/bin/phpunit tests/Integration/Webhook
```

They never load application credentials. Each test creates and drops a randomly
named `rea_webhook_test_*` database, applies the real core and plugin migrations,
and exercises saves, rollback, delivery, retry, and cancellation.
