# Phase 7 Gallery and operational hardening

## Gallery

`plugins/gallery` is a declarative reference package. Album and item records
store only logical core `media_id` values plus ordering, caption, and alt-text
metadata. The package owns no physical filename, file hash, blob, or duplicate
media table. Core media usage tracking therefore remains authoritative.

## Backups and restore

Backups are canonical versioned JSON documents stored with mode `0600` in a
private directory. They include selected table rows, plugin IDs and versions,
media manifests, scope, creation time, and a SHA-256 integrity checksum. Restore
rejects modified documents and non-empty targets. The test suite creates a
backup and restores it into a separate empty database adapter, then compares
the resulting tables to the source.

## Webhooks

Webhook destinations require credential-free HTTPS and port 443. Every resolved
IPv4 and IPv6 address must be globally routable. Loopback, private, reserved,
link-local, and cloud metadata networks are rejected. DNS is resolved and
compared again immediately before delivery to reduce rebinding risk.

Deliveries sign `timestamp.deliveryId.body` using HMAC-SHA-256. Verification
checks constant-time signatures and a bounded timestamp window. Delivery uses
strict caller-provided connect/read timeouts, bounded response bodies, explicit
handler queues, capped exponential retry, and persistent delivery IDs/history.
Secrets remain encrypted at rest and never enter logs.

## Accessibility and performance

Critical rendered pages have one main landmark and level-one heading, a working
skip link, document language, named theme controls, visible focus, responsive
viewport metadata, alternative text requirements, reduced-motion behavior, and
no browser-console warnings. Public Blog pages now use the same accessible
tokens and skip-link behavior as the core layout.

Performance budgets default to at most 25 queries per baseline request and
250 KB of generated first-party static assets. Collections remain paginated,
plugin route state comes from the registry rather than directory scans, and
plugin lifecycle changes invalidate route/manifest/template caches.
