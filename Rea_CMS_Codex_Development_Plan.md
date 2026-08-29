# Rea CMS — Codex Development Plan

## Project identity

**Name:** Rea CMS  
**Meaning:** RealTime Efficiency API  
**Product type:** Lightweight, headless-first, plugin-based content management system  
**Primary goal:** Provide fast content management and predictable API responses in JSON, HTML, and plain-text formats while remaining deployable on standard PHP/MySQL shared hosting such as HostGator.

Rea CMS must favor server-rendered HTML, progressive enhancement, small assets, secure defaults, and low hosting requirements. It is not a single-page application and must not depend on a permanently running application server, Redis, Docker, or a Node.js production runtime.

---

## Instructions for Codex

Build Rea CMS incrementally according to this specification. Start with Phase 0 and Phase 1 only. Do not implement later phases until the current phase passes its acceptance criteria.

Before changing code:

1. Inspect the repository and any `AGENTS.md`, README, configuration, or existing source files.
2. Preserve existing user changes and avoid destructive Git operations.
3. Create or update a short implementation plan for the active phase.
4. State any assumptions caused by missing hosting or repository information.
5. Prefer small, reviewable changes over a large generated codebase.

For every phase:

1. Implement the smallest complete vertical slice.
2. Add automated tests for security-sensitive and core behavior.
3. Run the available tests, static analysis, and build steps.
4. Update documentation and the example environment configuration.
5. Report changed files, tests run, unresolved risks, and the next recommended phase.

Do not silently weaken any security requirement. If a HostGator limitation prevents a requirement from being implemented safely, stop and explain the conflict.

---

## 1. Required technology

### Production stack

- PHP 8.2 or newer when supported by the hosting account.
- MySQL 8 or a compatible MariaDB version.
- Apache and `.htaccess` clean-URL routing.
- Composer with PSR-4 autoloading.
- PDO with native prepared statements.
- htmx 4, pinned to an exact tested version.
- Tailwind CSS, compiled and minified before deployment.
- Vanilla JavaScript by default.
- Alpine.js CSP build only when it materially simplifies local UI state.

### Prohibited production dependencies

- No Node.js production server.
- No client-side SPA framework.
- No jQuery.
- No Redis requirement.
- No daemon or permanently running queue worker.
- No Docker requirement on the production host.
- No runtime Tailwind CDN or Tailwind compiler.
- No unversioned JavaScript CDN dependencies.

Node.js may be used locally or in CI to compile Tailwind and other static assets. Deploy only the compiled results.

---

## 2. Architectural style

Use a modular monolith with these boundaries:

- The core owns routing, authentication, authorization, API policy, settings, plugin lifecycle, migrations, templates, media, cache, logging, background jobs, and security.
- Each plugin owns its content schema, plugin tables, templates, API resource declarations, permissions, translations, and optional static assets.
- Plugin templates never run SQL or arbitrary PHP.
- Plugins access shared user and site information through a controlled core context.
- Cross-plugin database reads and foreign keys are prohibited.
- Plugin routes exist only while that plugin is enabled.

Suggested top-level layout:

```text
rea-cms/
├── app/
│   ├── Api/
│   ├── Auth/
│   ├── Content/
│   ├── Core/
│   ├── Database/
│   ├── Media/
│   ├── Plugins/
│   ├── Security/
│   ├── Support/
│   └── View/
├── bin/
├── config/
├── database/
│   ├── migrations/
│   └── seeds/
├── plugins/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
├── storage/
│   ├── backups/
│   ├── cache/
│   ├── logs/
│   ├── plugins/staging/
│   ├── sessions/
│   └── uploads/
├── tests/
├── vendor/
└── public/
    ├── assets/
    ├── .htaccess
    └── index.php
```

Only `public/` should be web-accessible. When HostGator forces the application under `public_html`, include deny rules for application, configuration, storage, plugin, migration, vendor, test, and backup directories.

---

## 3. Core request lifecycle

Use one front controller:

```text
Apache rewrite
  -> public/index.php
  -> trusted-proxy and request normalization
  -> request ID
  -> security headers
  -> session/authentication
  -> API access policy
  -> router
  -> controller/application service
  -> response formatter
  -> HTTP response
```

Templates are presentation-only. Controllers coordinate request/response behavior. Application services contain use cases. Repositories perform parameterized database access. Validation and authorization occur before repository writes.

Do not create a generalized service locator that plugins can use to bypass boundaries. Expose narrow interfaces through a `PluginContext`.

Example shared context capabilities:

```php
$context->currentUser();
$context->authorization()->allows('blog.posts.update');
$context->settings()->get('site.name');
$context->media()->find($mediaId);
$context->clock()->now();
```

---

## 4. API contract

Rea CMS uses explicit, versioned, extension-based representations.

### Collection endpoints

```http
GET /api/v1/blog.json
GET /api/v1/blog.html
GET /api/v1/blog.txt
```

### Individual resource endpoints

```http
GET /api/v1/blog/{id}.json
GET /api/v1/blog/{id}.html
GET /api/v1/blog/{id}.txt
```

### Mutating endpoints

```http
POST   /api/v1/blog.json
PATCH  /api/v1/blog/{id}.json
DELETE /api/v1/blog/{id}.json
```

HTML browser forms may use `POST` plus an allowlisted `_method` override for `PATCH` or `DELETE`. Method override must be accepted only from authenticated form submissions with a valid CSRF token.

### API rules

- Routes without an ID return a paginated collection.
- Routes with an ID return one resource.
- JSON, HTML, and text use the same query, visibility rules, authorization, filters, and pagination.
- Only the serializer changes.
- Public API HTML is separate from administrative htmx fragments.
- Unsupported formats return `406 Not Acceptable` or `404` according to the router design, consistently.
- Disabled plugins expose no route and return `404`.
- Draft, pending, scheduled, archived, and trashed content must not appear publicly.
- Use proper HTTP status codes and a consistent error envelope for JSON.
- Collection endpoints include pagination metadata and bounded page sizes.
- Filters and sorting use explicit allowlists.
- Public GET responses support `ETag` and/or `Last-Modified` where practical.
- API versions are explicit; breaking changes require a new version.

Suggested JSON collection shape:

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 0,
    "totalPages": 0
  },
  "links": {
    "self": "/api/v1/blog.json?page=1",
    "next": null,
    "previous": null
  }
}
```

Suggested JSON error shape:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The request could not be processed.",
    "fields": {}
  },
  "requestId": "..."
}
```

Do not reveal stack traces, SQL, filesystem paths, secrets, or internal class names in production responses.

---

## 5. API whitelist and access control

API access is deny-by-default. CORS is only a browser control and must never be treated as authentication.

### Supported policies

- `public`
- `same-origin`
- `authenticated`
- `token`
- `ip-allowlist`
- `server-only`
- `disabled`

Policies may be combined. The strictest applicable global, plugin, resource, and operation policy wins. A plugin is not allowed to weaken the global policy.

### Same-origin policy

- Compare exact scheme, host, and port.
- Allow only configured origins.
- Do not use `Access-Control-Allow-Origin: *` for private or credentialed APIs.
- Never blindly reflect the request `Origin`.
- Reject `Origin: null`.
- Return `Vary: Origin` when dynamically returning an approved origin.
- Return `Cross-Origin-Resource-Policy: same-origin` for same-origin resources.
- If cross-origin access is not configured, omit CORS allow headers entirely.
- State-changing session requests require CSRF protection even when origin checks exist.

### Authenticated policy

- Require a valid secure session.
- Rotate session identifiers after login and privilege changes.
- Enforce resource permissions server-side.
- Require CSRF tokens for all state-changing browser requests.
- Use Secure, HttpOnly, and appropriate SameSite cookies.

### Token policy

API tokens must be:

- Generated from cryptographically secure random bytes.
- Shown in plaintext only once.
- Stored only as a cryptographic hash.
- Named, scoped, revocable, and optionally expiring.
- Optionally restricted by IP/CIDR.
- Recorded with creation, last-used, and revocation timestamps.
- Compared in constant time where applicable.

Example scopes:

```text
blog:read
blog:create
blog:update
blog:delete
gallery:read
```

### IP allowlist policy

- Support IPv4, IPv6, and CIDR ranges.
- Normalize addresses before comparison.
- Do not trust `X-Forwarded-For` or similar headers unless the immediate proxy is explicitly configured as trusted.
- Provide an administrator safety check before enabling an allowlist that could lock out the current administrator.

### Server-only policy

Prefer direct PHP application-service calls rather than internal HTTP. If an internal HTTP route is unavoidable, require a secret, narrow permissions, logging, HTTPS, and an independently configured network restriction. Do not assume shared-hosting requests are trustworthy merely because they appear to originate on the same server.

### Recommended defaults

| Resource | Default policy |
|---|---|
| Published public content | Configurable; start with `same-origin` |
| Draft or preview content | `authenticated` |
| Admin pages/fragments | `authenticated + same-origin` |
| Create/update/delete | `authenticated` or scoped token |
| Plugin management | Super-administrator session only |
| Internal CMS calls | Direct application-service call |

Add rate limiting by route class, identity, and IP using a MySQL or filesystem-compatible implementation. Rate-limit login, password reset, token use, plugin uploads, and public API traffic separately.

---

## 6. Plugin package specification

Plugins are server directories distributed as ZIP files.

Initial Rea CMS plugins are declarative. Uploaded plugins must not contain executable PHP or server configuration.

```text
plugins/blog/
├── plugin.json
├── schema/
│   ├── fields.json
│   └── validation.json
├── migrations/
│   ├── 001_install.json
│   └── 002_add_excerpt.json
├── templates/
│   ├── admin/
│   ├── public/
│   └── api/
├── assets/
│   ├── css/
│   └── js/
├── languages/
│   └── en.json
└── uninstall.json
```

Example manifest:

```json
{
  "id": "blog",
  "name": "Blog",
  "version": "1.0.0",
  "reaCmsVersion": "^1.0",
  "description": "Blog publishing for Rea CMS.",
  "tables": [
    "plugin_blog_posts",
    "plugin_blog_categories",
    "plugin_blog_post_categories",
    "plugin_blog_revisions"
  ],
  "permissions": [
    "blog.posts.view",
    "blog.posts.create",
    "blog.posts.update",
    "blog.posts.delete",
    "blog.settings.manage"
  ],
  "api": {
    "resource": "blog",
    "formats": ["json", "html", "txt"],
    "defaultPolicy": "same-origin"
  }
}
```

Validate the manifest against a versioned JSON Schema. Reject unknown security-sensitive capabilities. Record the manifest hash and installed package hash.

### Template rules

Use a restricted template syntax with automatic escaping:

```html
<article>
  <h2>{{ post.title }}</h2>
  <p>{{ post.excerpt }}</p>
  <a href="{{ post.url }}">Read more</a>
</article>
```

Sanitized rich text requires an explicit safe operation such as:

```html
{{ post.content | sanitized_html }}
```

Templates cannot execute PHP, SQL, shell commands, filesystem reads, environment reads, arbitrary functions, or cross-plugin data access.

### Plugin table rules

- Prefix every table with `plugin_{plugin_id}_`.
- The manifest must declare every owned table.
- Use foreign keys only between tables owned by the same plugin.
- Do not use cross-plugin foreign keys.
- Refer to core users and media through controlled logical IDs.
- Do not cascade-delete published plugin content when a user is deleted.
- The core migration runner must reject operations outside the plugin's declared table namespace.

---

## 7. Secure ZIP installation and update

Plugin installation is a super-administrator-only operation and requires reauthentication or another high-confidence confirmation for destructive/update actions.

### Upload validation

- Validate CSRF and authorization before accepting the upload.
- Store the ZIP outside the public web root in a unique staging directory.
- Verify ZIP signature and MIME type.
- Enforce compressed size, extracted size, per-file size, file count, nesting depth, and compression-ratio limits.
- Inspect every entry before extraction.
- Reject absolute paths, drive prefixes, `..`, null bytes, control characters, duplicate normalized paths, and hidden server-control files.
- Reject symlinks and other special filesystem entries.
- Require exactly one plugin root directory.
- Allowlist filenames and extensions.
- Reject PHP, PHAR, PHTML, CGI, shell scripts, `.htaccess`, server configuration, executable binaries, and polyglot files.
- Validate `plugin.json`, semantic version, CMS compatibility, schema, routes, permissions, and migration operations.
- Prevent overwriting another plugin or any path outside staging.
- Calculate and retain a package checksum.

Never extract directly into the active plugin directory.

### Install flow

1. Upload to private staging.
2. Inspect without executing anything.
3. Validate all files and declarations.
4. Display plugin identity, version, permissions, routes, tables, migrations, and checksum.
5. Require administrator confirmation.
6. Run validated migrations.
7. Move the package atomically into the plugin directory.
8. Register it as installed but disabled.
9. Clear manifest, template, permission, and route caches.
10. Record the action in the audit log.

### Update flow

1. Validate the new package independently.
2. Require an exact plugin ID match.
3. Confirm CMS compatibility and version ordering.
4. Back up the active plugin directory and affected tables.
5. Put the plugin in maintenance mode.
6. Apply forward migrations in order.
7. Atomically switch directories.
8. Clear caches and run health checks.
9. Roll back files and data on failure where possible.
10. Record the result in the audit log.

Block downgrades unless an explicit, validated downgrade path exists.

### Lifecycle meanings

| Action | Files | Tables/data | Routes |
|---|---|---|---|
| Install | Present | Created | Not active until enabled |
| Enable | Present | Preserved | Registered |
| Disable | Present | Preserved | Removed |
| Uninstall | Removed | Preserved by default | Removed |
| Purge | Removed | Exported, then deleted | Removed |

Purge requires typed confirmation, a final export when possible, and a permanent audit entry.

---

## 8. Core data model

Use a configurable table prefix, with `rea_` as the documented default.

Initial core tables:

```text
rea_users
rea_user_profiles
rea_roles
rea_permissions
rea_role_permissions
rea_user_roles
rea_sessions
rea_password_resets
rea_mfa_methods
rea_settings
rea_plugins
rea_plugin_migrations
rea_plugin_backups
rea_api_tokens
rea_api_token_scopes
rea_api_policies
rea_api_allowed_origins
rea_api_allowed_networks
rea_audit_log
rea_jobs
rea_failed_jobs
rea_media
rea_media_variants
rea_media_usage
rea_redirects
rea_webhooks
rea_webhook_deliveries
```

Use UTC database timestamps and convert to the configured site or user timezone at display time. Include created/updated timestamps consistently. Use soft deletion only where the domain requires recovery; do not apply it mechanically to every table.

Do not store plaintext passwords, reset tokens, API tokens, recovery codes, webhook secrets, or encryption keys.

---

## 9. Identity, roles, and permissions

Built-in roles:

- Super Administrator
- Administrator
- Editor
- Author
- Contributor
- Viewer

Support custom roles. Permissions are granular and plugin-namespaced. Never authorize solely by hiding a button. Controllers and application services must enforce authorization.

Required identity features:

- Secure password hashing and verification.
- Password reset with hashed, expiring, single-use tokens.
- Optional TOTP two-factor authentication.
- Hashed backup recovery codes.
- Session listing and revocation.
- Login throttling and temporary lockouts.
- Reauthentication for sensitive operations.
- User status: active, suspended, invited, deleted.
- Safe handling of content after an author account is removed.

---

## 10. Content capabilities

The generic content engine must support:

### Lifecycle

- Draft
- Pending review
- Scheduled
- Published
- Archived
- Trashed
- Publish and unpublish scheduling
- Soft deletion and configurable trash retention
- Permanent purge
- Revision history, comparison, attribution, and rollback
- Autosaved drafts
- Expiring preview links

### Organization

- Slugs and uniqueness rules
- Slug history and redirects
- Categories
- Tags
- Plugin-defined taxonomies
- Relationships
- Menu integration
- Featured/pinned state
- Custom ordering

### SEO

- SEO title
- Meta description
- Canonical URL
- Robots controls
- Open Graph data
- Social image
- Structured-data fields
- XML sitemap integration
- Redirect management and 404 logging

### Editorial workflow

- Author and owner
- Approval workflow
- Editorial notes
- Content-level permissions
- Revision attribution
- Scheduled transitions

### Search

Begin with MySQL full-text search when supported. Search must enforce plugin activation, publication state, permissions, language, and visibility. Abstract indexing sufficiently to allow a different search provider later without changing plugin templates.

### Localization

- UTF-8 everywhere using `utf8mb4`.
- Translatable interface strings.
- Site and user locales.
- Site and user timezones.
- Plugin language files.
- Architecture ready for later multilingual content relationships.

---

## 11. Shared media service

The core owns physical media and reusable metadata. Plugin tables reference media IDs without owning duplicate files.

Required capabilities:

- Images, documents, audio, and video metadata.
- Secure MIME/content validation.
- Random stored filenames.
- Original filename retained only as metadata.
- Storage outside the public root where practical.
- Prevention of script execution in upload directories.
- File size and dimension limits.
- Alt text, caption, credit, and description.
- Folders or collections.
- File hashes and duplicate detection.
- Thumbnail and responsive image variants.
- Usage tracking before deletion.
- Public/private visibility.
- Safe download controller for private files.
- Extension point for malware scanning.

Image processing must respect shared-hosting memory limits. Heavy variant generation should use the database-backed job queue and cron.

---

## 12. Theme and accessibility requirements

Rea CMS provides four explicit theme choices:

- `system`
- `light`
- `dark`
- `high-contrast`

Requirements:

- Anonymous users default to `system`.
- Store anonymous preference in a small cookie or local storage.
- Store authenticated preference in the user profile.
- Apply the preference before first paint to avoid theme flashing.
- `system` follows `prefers-color-scheme`.
- Respect `forced-colors: active`.
- Respect `prefers-reduced-motion`.
- Use semantic design tokens instead of hard-coded plugin colors.
- Ensure keyboard navigation, visible focus, semantic landmarks, form labels, useful error summaries, and accessible htmx status announcements.
- Target WCAG 2.2 AA.

Suggested semantic tokens:

```text
surface
surface-raised
surface-muted
text-primary
text-secondary
border-default
accent-primary
status-success
status-warning
status-danger
focus-ring
```

Plugins may consume approved tokens but cannot redefine core theme variables without a declared and approved capability.

---

## 13. Security baseline

Implement security as core infrastructure, not as a later polish phase.

- PDO prepared statements with emulation disabled where supported.
- Output escaping by default.
- Allowlist-based rich-text sanitization.
- CSRF protection for every state-changing session request.
- Server-side authorization for every protected action.
- Secure session cookies and session fixation prevention.
- Content Security Policy compatible with htmx and Alpine CSP build.
- `X-Content-Type-Options: nosniff`.
- Suitable `Referrer-Policy`.
- Frame protection using CSP `frame-ancestors`.
- HSTS only after HTTPS and subdomain impact are verified.
- Secure upload validation.
- Rate limiting.
- Request IDs and structured security logging.
- Production-safe error handling.
- Secrets outside the public web root and excluded from version control.
- Audit sensitive settings, permission, token, plugin, login, export, purge, and restore events.
- Redact passwords, tokens, cookies, authorization headers, secrets, and sensitive personal data from logs.
- Signed webhooks with replay protection, timeouts, bounded retries, and destination validation.
- Backup files must never be web-accessible.
- Provide backup restoration tests, not only backup creation.

Add dependency auditing to development/CI. Document the update process for Composer, htmx, Alpine, Tailwind, and shipped plugins.

---

## 14. Jobs, cron, backups, and operations

Use a MySQL-backed job queue compatible with HostGator cron. Jobs must be idempotent where practical and support attempts, availability time, reservation timeout, failure reason, and dead-letter handling.

Jobs include:

- Scheduled publishing/unpublishing
- Image variants
- Webhook delivery/retry
- Sitemap generation
- Expired session/token cleanup
- Trash cleanup
- Backup rotation
- Search maintenance

Protect web-triggered cron with a secret if CLI cron is unavailable, but prefer CLI PHP cron. Prevent concurrent duplicate cron execution using a database or filesystem lock with expiration.

Backups must include:

- Core tables
- Selected plugin tables
- Plugin manifests and versions
- Uploaded media manifest/files according to configuration
- Restore metadata
- Integrity checksum

Provide full-site, core-only, and plugin export options. Test restoration into an empty database before calling the feature complete.

---

## 15. Import, export, and webhooks

### Import/export

- JSON is the canonical structured interchange format.
- CSV is optional for flat resources.
- Exports include schema/plugin version metadata.
- Imports support validation and dry-run mode.
- Reject unknown fields unless the import version explicitly permits them.
- Provide a plugin export before uninstall/purge.

### Webhooks

Example events:

```text
blog.post.created
blog.post.updated
blog.post.published
blog.post.deleted
plugin.enabled
plugin.disabled
```

Webhook requirements:

- HTTPS by default.
- Per-hook secret and HMAC signature.
- Timestamp and unique delivery ID for replay protection.
- Strict connection/read timeouts.
- Bounded response size.
- Retry policy with backoff.
- Delivery history.
- Block localhost, link-local, private, metadata, and otherwise disallowed destinations unless explicitly permitted by a super administrator.
- Re-resolve and validate DNS to reduce SSRF and DNS-rebinding risk.

---

## 16. Performance requirements

- Cache parsed plugin manifests, route maps, permissions, and templates.
- Never scan every plugin directory on every request.
- Clear relevant caches after plugin lifecycle operations.
- Use OPcache when available.
- Use versioned static assets.
- Support gzip/Brotli through hosting configuration when available.
- Lazy-load media and use responsive variants.
- Paginate every unbounded collection.
- Add indexes based on actual query paths.
- Detect and prevent avoidable N+1 queries.
- Use HTTP cache validators for public content.
- Keep baseline JavaScript small and load Alpine only on pages that require it, if practical.

Add simple performance budgets after the first vertical slice, including query count and generated asset sizes. Do not optimize blindly before measuring.

---

## 17. Reference plugins

### Blog plugin

The Blog plugin is the first full reference plugin and should prove:

- Declarative schema and migrations
- Posts, categories, tag/taxonomy support, and revisions
- Draft/review/schedule/publish/archive/trash workflow
- Featured media
- Author reference
- SEO fields and slug history
- Admin CRUD with htmx
- Public list/detail templates
- JSON, HTML, and text API output
- All API access policies
- Import/export
- Search and sitemap integration
- Disable, update, uninstall, restore, and purge behavior

Suggested tables:

```text
plugin_blog_posts
plugin_blog_categories
plugin_blog_post_categories
plugin_blog_revisions
```

### Gallery plugin

Build after the Blog plugin validates the architecture. It should prove shared-media reuse, albums, ordering, captions, API formats, and plugin isolation.

---

## 18. Development phases

### Phase 0 — Repository and hosting discovery

Tasks:

- Inspect existing repository state.
- Identify PHP, Composer, Node, MySQL/MariaDB, Apache, and test-tool availability.
- Confirm HostGator PHP version and required PHP extensions where information is available.
- Document required extensions: PDO MySQL, JSON, mbstring, OpenSSL, fileinfo, ZIP, and an image library when available.
- Decide the minimal Composer packages, favoring well-maintained focused libraries.
- Add `.env.example`, secure configuration loading, and deployment notes.
- Establish coding standards, static analysis, test framework, and CI commands.

Acceptance criteria:

- Repository structure and constraints are documented.
- No secrets are committed.
- Local setup instructions work from a clean checkout.
- Required and optional hosting capabilities are clearly separated.

### Phase 1 — Minimal secure core

Tasks:

- Front controller and Apache rewrite rules.
- Request/response abstraction.
- Router with route parameters.
- Configuration and environment loading.
- PDO database connection.
- Core migration runner.
- Error handling, request IDs, and structured logs.
- Baseline security headers.
- Server-rendered base layout.
- Tailwind build and pinned htmx 4 asset.
- Light, dark, system, and high-contrast themes.
- Health endpoint that exposes no secrets.

Acceptance criteria:

- A clean installation can run core migrations.
- `/` renders through the front controller.
- An htmx request successfully swaps a server-rendered fragment.
- All four theme modes work without a first-paint flash.
- Unknown routes return safe 404 responses in HTML and JSON contexts.
- Production mode never exposes stack traces.
- Automated tests cover router, configuration, migrations, error responses, and theme preference parsing.

### Phase 2 — Authentication and authorization

Tasks:

- Users, profiles, sessions, roles, permissions, password reset, throttling, and optional TOTP foundations.
- CSRF middleware.
- Admin layout and navigation.
- Audit log.
- Reauthentication for critical actions.

Acceptance criteria:

- Login/logout/session rotation work.
- Disabled and unauthorized users are blocked server-side.
- CSRF failures are rejected.
- Role and plugin permission checks have tests.
- Sensitive events appear in the audit log without secret leakage.

### Phase 3 — API security platform

Tasks:

- Format-aware API router.
- Response serializers.
- Global and resource policy evaluation.
- Exact-origin allowlist.
- API token scopes and hashing.
- IP/CIDR allowlist.
- Rate limiting.
- Pagination/filter/sort helpers.

Acceptance criteria:

- Same-origin policy does not authorize non-browser callers by itself.
- Unauthorized origins receive no permissive CORS headers.
- Token, permission, IP, and combined policies are tested.
- Global restrictions cannot be weakened by plugin configuration.
- JSON, HTML, and text representations share one authorized query path.

### Phase 4 — Plugin platform

Tasks:

- Manifest JSON Schema.
- Plugin registry and state machine.
- Secure ZIP staging and inspection.
- Declarative migrations and safe template engine.
- Install/update/enable/disable/uninstall/purge.
- Atomic activation, backup, rollback, caches, and audit events.

Acceptance criteria:

- Malicious traversal ZIPs, ZIP bombs, forbidden executable files, invalid manifests, namespace escapes, and incompatible versions are rejected.
- Installation never executes uploaded content.
- Disabled plugins expose no routes.
- Failed updates preserve or restore the previous working version.
- Uninstall preserves data by default; purge requires explicit confirmation.

### Phase 5 — Generic content engine and media

Tasks:

- Metadata-driven CRUD.
- Validation and permissions.
- Content lifecycle, revisions, scheduling, slugs, redirects, taxonomy, SEO, search, import/export, and shared media.
- Database-backed jobs and cron runner.

Acceptance criteria:

- CRUD cannot access tables outside the registered plugin namespace.
- Draft content is never public.
- Scheduled publication is timezone-correct.
- Revisions restore safely.
- Media deletion is blocked while in use unless explicitly resolved.
- Imports support validation-only mode.

### Phase 6 — Blog reference plugin

Tasks:

- Implement the complete Blog plugin using only public plugin capabilities.
- Add all three API representations and access modes.
- Add admin and public templates.

Acceptance criteria:

- All documented Blog endpoints work.
- Blog proves installation, activation, upgrade, disable, export, uninstall, restoration, and purge.
- Blog does not require privileged internal access beyond the documented `PluginContext`.

### Phase 7 — Gallery and operational hardening

Tasks:

- Gallery plugin.
- Backups and verified restore.
- Webhooks and SSRF defenses.
- Performance profiling and cache validation.
- Accessibility audit.
- HostGator deployment checklist.

Acceptance criteria:

- Backup restoration works into a clean environment.
- Gallery reuses core media without duplicating physical files.
- WCAG 2.2 AA issues in critical flows are resolved.
- Production deployment requires no Node process or unsupported daemon.

---

## 19. Testing requirements

Use unit, integration, and end-to-end HTTP tests where appropriate.

Minimum security regression tests:

- SQL injection attempts.
- Stored and reflected XSS.
- CSRF failure/success.
- Session fixation.
- IDOR/broken object authorization.
- Role escalation.
- Origin spoofing assumptions.
- CORS allow/deny behavior.
- API scope enforcement.
- IP and CIDR matching, including IPv6.
- Rate-limit boundaries.
- Path traversal and encoded traversal.
- ZIP slip, ZIP bombs, duplicate entries, symlinks, and forbidden extensions.
- Plugin migration namespace escape.
- Template escaping and rich-text sanitization.
- Unsafe media upload.
- Webhook SSRF and DNS-rebinding scenarios.
- Secret redaction in errors and logs.

Tests must not depend on execution order. Database integration tests should isolate or reset their state safely.

---

## 20. Coding rules

- Enable strict typing in first-party PHP files.
- Follow PSR-12 or a documented stricter project standard.
- Prefer immutable request/value objects where useful.
- Use dependency injection through constructors.
- Avoid static global state for request services.
- Validate external input at boundaries.
- Keep SQL in repositories or migration definitions, never templates/controllers.
- Escape output by default.
- Return generic public errors and log actionable private details with a request ID.
- Never log secrets.
- Document public plugin interfaces and schema versions.
- Treat plugin manifests, templates, migrations, imports, API input, headers, and uploads as untrusted.
- Do not add Alpine.js to a page unless htmx and a small Vanilla JavaScript module are insufficient.
- Keep generated/build files separate from authored source.

---

## 21. Definition of done

A feature is complete only when:

- Its behavior is implemented end to end.
- Authorization and validation exist server-side.
- Success and failure paths have tests.
- Database changes have reversible or recovery-aware migrations.
- Accessibility is considered for rendered interfaces.
- Security-sensitive events are audited.
- Documentation and example configuration are updated.
- Tests, static analysis, and asset builds pass.
- The implementation remains compatible with the documented shared-hosting constraints.

---

## 22. First Codex assignment

Begin with **Phase 0 — Repository and hosting discovery**.

Do not scaffold all later modules yet. Inspect the current workspace, report what already exists, propose the minimum dependency set, and create only the foundational project/configuration files needed to make local setup reproducible. Then run validation and present the Phase 0 results for review before beginning Phase 1.

When a design choice is not fixed by this specification, prefer the option that is:

1. Secure by default.
2. Compatible with HostGator-style shared hosting.
3. Lightweight at runtime.
4. Easy to test.
5. Easy to replace later through a narrow interface.

