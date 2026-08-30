# Phase 4 plugin platform

Rea CMS plugins are declarative ZIP packages. Uploaded packages cannot contain
PHP, shell entrypoints, server configuration, hidden files, symlinks, or other
executable content. The core inspects every ZIP entry before writing any member
to a unique private staging directory.

## Package contract

Each ZIP contains exactly one root directory whose name equals the manifest
`id`. A `plugin.json` using schema version 1 is required at that root. The
machine-readable schema is in `resources/schemas/plugin-manifest-v1.json`.

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

Administrative upload controllers added later must perform authorization,
reauthentication, and CSRF validation before calling these lower-level package
and lifecycle services.
