# Gallery selection update

Extract the ZIP in the application root, allowing the included files to replace their existing versions. No database migration, dependency installation, or asset build is required. Reload the Gallery page after extraction.

The Gallery form selects existing files from the Media library. Upload new files through Media first.

- Select one image to display its preview. Single videos remain supported.
- Use Ctrl (Windows/Linux) or Command (Mac) to select multiple images, then choose an album and save.
- Multiple selections share title, caption, accessible label, and publication status. Display order increments from the entered starting value.
- On edit, the first selected image in list order replaces the current item; additional selected images create new album items.
- Multiple selections do not display a preview. Videos must be saved individually.
- Invalid submissions retain valid selections and entered text and display errors. Database save failures roll back the entire selection.

Verification: 227 PHP tests (860 assertions), PHPStan, PHP coding standards for changed PHP files, and JavaScript selection/preview checks passed. Browser interaction against a running database-backed installation was not performed.

## Request parsing fix

This replacement package fixes the request parser dropping the media_ids[] field. It includes app/Core/Http/Request.php and preserves existing scalar form parsing. Regression tests cover single and multiple selections, legacy single-image fields, malformed lists, and multipart form data.

## Uploaded-image thumbnails

Gallery lists, album covers, album item lists, and single-image previews now request thumbnails of uploaded images. The Gallery API includes a `thumbnail` URL for images (null for videos); existing full-size URLs are unchanged. Add `?thumbnail=1` to a media URL to request a thumbnail, subject to the original access rules.

Thumbnails are PNG images contained within 320 × 320 pixels, preserving aspect ratio and transparency without enlarging small images. They are generated on demand from existing or newly uploaded originals and cached by the browser. No migration or re-upload is needed. PHP GD is used; if unavailable, or if an image is invalid or exceeds 12 megapixels, the original is served instead. Originals are never modified. Video thumbnails are not generated.

Verification: 229 PHP tests passed; additional API thumbnail assertions, JavaScript checks, coding standards, and static analysis passed. No live browser verification was performed.

## Production thumbnail class-loading fix

Production release builds use Composer's authoritative class map, which does not include the thumbnail class added by this update. The controller now loads that class explicitly, allowing extraction-only deployment without changing vendor files or running Composer. This fixes the reproduced class-loading failure; other live-server failures still require the actual error or server log to diagnose.

The full PHP suite passes (230 tests, 874 assertions), including a regression test with authoritative class loading enabled.

## Album HTML compatibility fix

Album API data now includes media, image, thumbnail, altText, caption, and mediaType fields so existing shared Gallery image templates can render album covers. Private or missing covers retain the default cover. This change does not alter publication rules or route IDs.

Album details use /api/v1/gallery/albums/ALBUM_ID.json (or .html).
Album image lists use /api/v1/gallery/albums/ALBUM_ID/items.json (or .html).
Individual Gallery items use /api/v1/gallery/ITEM_ID.json (or .html).
Uploaded-media IDs are not interchangeable with album or Gallery item IDs. Album detail endpoints require published albums; item endpoints require active items with public media.

Album template image fields now use the thumbnail URL, matching the thumbnail field. The media link and cover field retain the original full-size URL. Default covers remain unchanged. Presenter regression tests and coding standards pass.
