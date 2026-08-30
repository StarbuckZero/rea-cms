# Phase 6 Blog reference plugin

The Blog reference package lives in `plugins/blog`. It contains only JSON,
restricted templates, and translations—never executable PHP. Its manifest,
permissions, owned tables, schema, validation rules, migrations, API formats,
and uninstall policy pass through the same Phase 4 validators as an uploaded
third-party package.

Install it into a migrated development database with:

```bash
php bin/install-reference-blog.php --enable
```

The installer records the package and migration checksums, applies only the
allowlisted declarative operations, and optionally enables routes. Re-running
it verifies applied migration checksums without replaying them.

Enabled routes are:

- `/blog` and `/blog/{slug}` for public list/detail output.
- `/api/v1/blog.json`, `/api/v1/blog.html`, and `/api/v1/blog.txt`.
- `/api/v1/blog/{id}.{format}` for an individual published post.

The same repository query enforces enabled-plugin state, published/public
visibility, locale, publish time, unpublish time, and soft deletion across all
representations. Exact-origin API controls remain in force. The versioned JSON
transfer service supports validation before import, plugin export metadata, and
sitemap generation. Editorial writes use `BlogDefinition` through the generic
content authorization and validation services.

The generic plugin lifecycle tests cover activation, upgrade rollback,
disable, data-preserving uninstall, restoration, and export-gated purge; Blog's
package test additionally proves it compiles without core-table or executable
capabilities.
