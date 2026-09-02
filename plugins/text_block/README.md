# Text Blocks

The Text Block plugin stores reusable, sanitized text content under a unique
URL-safe name. Enable the plugin and grant a user Text Blocks access from the
administrator user-management screen to show `/cms/text-block` in navigation.

API resources are available in JSON, HTML, and ASCII TXT representations:

- `GET /api/v1/text-block.{format}`
- `GET /api/v1/text-block/{id}.{format}`
- `GET /api/v1/text-block/name/{name}.{format}`

Replace `{format}` with `json`, `html`, or `txt`. API requests follow the global
origin allowlist and the plugin must be enabled. JSON uses the standard Rea CMS
`data`, `meta`, and `links` document structure.

HTML and TXT defaults can be overridden from **Plugin Management → API
templates**. The supported bindings are `{textBlock.id}`, `{textBlock.name}`,
`{textBlock.content}`, `{textBlock.createdAt}`, and `{textBlock.updatedAt}`.
Use `{textBlock.content | sanitized_html}` when an HTML template should render
stored formatting. TXT output always removes markup and transliterates content
to ASCII-compatible plain text.
