# Gallery image editing and lightbox update

Deploy the updated CMS PHP files, Gallery views, and `public/assets/gallery-lightbox.js` and `.css` together. No database migration, Alpine.js, or other dependency is required. Reload open Gallery pages after deployment.

## Editing uploaded images

Open a Gallery album and choose **Rename image / edit alt text** next to an image. The same action appears on the image's Gallery edit page.

- The image name updates the Media library display name everywhere the uploaded asset is referenced. It does not rename the physical file, alter media URLs, or change separately assigned Gallery titles.
- Alt text updates the selected Gallery item and the Media library default. Other Gallery items that reuse the upload retain their own alt text.
- Names require 1–255 characters and cannot contain slashes or control characters. Alt text allows up to 500 characters; leave it blank for decorative images. Invalid submissions retain entered values and display validation errors.
- Changes require Gallery access and a valid CSRF token. Names and alt text are escaped in HTML and bound as SQL parameters. Both database updates are transactional.

## Lightbox

Click an image thumbnail on the CMS album page to open the full-size image in an accessible native dialog. Previous/Next buttons and Left/Right arrow keys navigate the album. Escape, Close, or a backdrop click closes the dialog. Focus returns to the thumbnail. The full-size link opens a new tab (or window, according to browser preferences). Modified link clicks retain normal browser behavior. Without JavaScript or dialog support, links still open normally.

Gallery HTML API responses include a lightbox wrapper and references to the vanilla JavaScript and CSS assets. The popup uses image links within figures; videos and album-navigation cards retain their existing behavior. JSON and text responses remain data-only.

### Embedding API HTML on another page

Browsers do not execute script tags inserted using `innerHTML`. If your site fetches and inserts Gallery HTML this way, load these assets once in the hosting page, using the correct CMS host and installation prefix:

```html
<link rel="stylesheet" href="/assets/gallery-lightbox.css?v=1">
<script src="/assets/gallery-lightbox.js?v=1" defer></script>
```

For example, a CMS exposed below `/cms` may require `/cms/assets/...` on the hosting site. Keep the returned `data-rea-gallery` wrapper so delegated click handling recognizes dynamically inserted figures. Alternatively, mark your own full-size image anchors with `data-gallery-lightbox`:

```html
<a data-gallery-lightbox href="FULL_SIZE_URL" data-gallery-name="Image name">
  <img src="THUMBNAIL_URL" alt="Image description">
</a>
```

Use URLs that resolve to your actual CMS installation. This update does not reconfigure reverse proxies or global installation prefixes. Escape dynamic attribute values when generating custom markup.

## Verification

PHP regression tests cover invalid and Unicode input, HTML escaping, bound metadata updates, transaction rollback, and earlier Gallery functionality. The browser test fixture covers popup opening, safe caption rendering, full-size links, buttons, arrow navigation, load failures, focus restoration, and modified clicks. The full database-backed production site was not exercised. Alt validation is shared by the image details and Gallery item editors.

## OffensiveLine.net integration

The public album page loads the CMS lightbox assets explicitly and marks the album content with `data-rea-gallery`, so dynamically loaded images work with existing templates. Its HTMX adapter resolves full-size links through `/cms/media/` and preserves empty alt attributes for decorative images. Deploy the CMS assets before the updated site templates.
