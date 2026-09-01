# Phase 4 plugin platform

Rea CMS plugins are declarative ZIP packages. Uploaded packages cannot contain
PHP, shell entrypoints, server configuration, hidden files, symlinks, or other
executable content. The core inspects every ZIP entry before writing any member
to a unique private staging directory.

## Package contract

Each ZIP contains exactly one root directory whose name equals the manifest
`id`. A `plugin.json` using schema version 1 is required at that root. The
machine-readable schema is in `resources/schemas/plugin-manifest-v1.json`.
The optional `author` field is displayed by Plugin Management; packages that
omit it remain compatible and are shown as having no author information.

Plugin IDs, permissions, and table declarations are namespaced. A plugin named
`notes` can declare tables such as `plugin_notes_entries` and permissions such
as `notes.entries.view`; it cannot declare core or another plugin's resources.

Migrations are ordered JSON files under `migrations/`, for example
`001_install.json`. Only `create_table`, `add_column`, and `create_index` are
accepted. Raw SQL is never accepted from a plugin package. Applied migration
checksums are immutable.

Templates support escaped dotted lookups:

```html
<h2>{{ post.title }}</h2>
```

Rich text must explicitly use `sanitized_html`:

```html
{{ post.content | sanitized_html }}
```

PHP blocks, control syntax, arbitrary functions, filesystem access, and
unrecognized expressions are rejected.

## Lifecycle guarantees

- Install uses private staging and an atomic directory rename. New plugins are
  registered disabled.
- Only plugins in the `enabled` state may expose routes.
- Update requires a strictly newer semantic version, enters maintenance mode,
  backs up the active directory, and restores the prior directory and state if
  activation fails.
- Disable removes routes while retaining files and data.
- Uninstall moves files out of service and preserves the registry/data record.
- Purge works only after uninstall, requires the exact text `PURGE {pluginId}`,
  requires a successful final export, and permanently audits the action.

## Administrator Plugin Management

Super administrators with `core.plugins.view` can open `/admin/plugins` and
see every registry record, including disabled and uninstalled plugins. Mutating
routes additionally require `core.plugins.manage`; data backup and permanent
purge require `core.plugins.purge`. Every route also requires
`core.admin.access`, so hiding the navigation link is never the authorization
boundary.

The web installation flow is deliberately two-step:

1. CSRF and permissions are checked and the upload is charged to the separate
   `plugin-upload` rate-limit bucket.
2. The ZIP is inspected into private staging without executing its contents.
3. The administrator reviews identity, author, version, compatibility,
   permissions, tables, and package checksum.
4. A session-bound confirmation valid for 30 minutes and reauthentication
   within the last 10 minutes are required before atomic installation.

An uploaded package whose ID is already registered is rejected. Plugin updates
must continue to use the separate version-increasing lifecycle path; ordinary
installation never overwrites files.

Enable and disable actions retain files and tables. The registry remains the
single source of truth for route and navigation activation, so disabled plugins
do not expose CMS navigation or plugin routes.

## Standard plugin data backup and removal

Declarative plugins participate in backup and cleanup through their manifest's
validated `tables` list. The core `PluginDataManager` summarizes row counts,
creates an integrity-checksummed JSON export, and drops only tables inside the
plugin's validated namespace. It also handles core records scoped by plugin ID,
including revisions, slugs, previews, taxonomies, relationships, redirects,
and media usage. Shared media files are not deleted; the export includes their
core media and variant manifests so they can be matched during restore or
migration. The export contains the full manifest, plugin identity and version,
package checksum, table schemas and rows, core-scoped data, restore metadata,
and its own checksum. This keeps the administration UI independent of each
plugin's database shape.

Removal always has an intermediate confirmation page that identifies the
plugin and summarizes its stored data. The choices are:

- **Remove Plugin, Keep Data** — requires `REMOVE {pluginId}`; moves files to
  private backup storage, disables all routes, and preserves registry metadata
  and tables.
- **Remove Plugin and Delete Data** — requires the stronger purge permission
  and exact `PURGE {pluginId}` text. A final private export must succeed before
  declared tables are dropped and the registry record is deleted.

Administrators can download a separate data backup before either choice.
Neither disabling nor data-preserving removal deletes plugin data.
