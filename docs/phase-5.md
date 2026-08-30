# Phase 5 generic content, media, and jobs

The content engine operates only through validated `ResourceDefinition`
instances supplied by enabled plugins. Table names, fields, types, and
permissions are fixed by that definition rather than request input. CRUD
services validate unknown and required fields and enforce permissions before
repository access.

The lifecycle supports draft, pending, scheduled, published, archived, and
trashed states. Public visibility requires a published record whose UTC publish
time has arrived. Site-local scheduling is converted to UTC before persistence.
Revision rollback validates both snapshots and records the current state before
restoring an older one.

Core tables provide revisions, autosave kinds, expiring hashed preview tokens,
slug history, redirects and 404 counts, taxonomies, terms, relationships, media
metadata and usage, and the job/dead-letter queues. Plugin content tables remain
plugin-owned and namespace-isolated.

Imports use JSON and reject unknown fields. Validation-only mode performs no
writes. Search is provider-abstracted and the public facade filters disabled
plugins, non-published records, private records, and other locales.

Media is stored outside the public root with random names and restrictive file
permissions. Ingestion validates detected MIME, size, checksum, and an optional
malware-scanner hook. Deletion is refused while usage records exist. Variant
generation is intended for the job queue.

The MySQL/MariaDB queue supports idempotency keys, availability timestamps,
reservation expiry, bounded attempts, exponential retry delay, and failed-job
records. Workers dispatch only explicitly registered job handlers.
