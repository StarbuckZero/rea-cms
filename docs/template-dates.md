# Dates and times in blog and text block templates

HTML and text endpoints format these fields on the server, so consumers need no JavaScript or date library:

| Plugin | Date fields | Time fields |
| --- | --- | --- |
| Blog | `{blog.publishedDate}` | `{blog.publishedTime}` |
| Text Blocks | `{textBlock.createdDate}`, `{textBlock.updatedDate}` | `{textBlock.createdTime}`, `{textBlock.updatedTime}` |

Dates use `September 8, 2026` and times use `8:30 PM`, matching the RSS plugin. They use `APP_TIMEZONE`, falling back to UTC when missing or invalid, and account for daylight saving time. A blog post without a publication timestamp has empty display fields.

These fields appear in the API template editor and JSON responses, and work in HTML and text list/detail templates (including named text block endpoints). Existing ISO 8601 fields (`publishedAt`, `createdAt`, `updatedAt`) remain available for machine-readable values and HTML `datetime` attributes.

For example:

```html
<time datetime="{blog.publishedAt}">{blog.publishedDate} {blog.publishedTime}</time>
```

```text
Updated: {textBlock.updatedDate} at {textBlock.updatedTime}
```

Default blog templates now display readable publication dates and times. Text block defaults still return reusable content; add the fields above wherever metadata is wanted. Saved custom templates are preserved; insert the new fields in the API template editor or reset blog templates to use the updated defaults.
