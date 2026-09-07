import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const listeners = {};
const album = {};
const preview = {};
const image = {
  removeAttribute() { delete this.src; },
  addEventListener(event, callback) { listeners[event] = callback; },
};
const caption = {};
const status = {};
const nodes = {
  '[data-gallery-preview]': preview,
  '[data-gallery-preview-image]': image,
  '[data-gallery-preview-caption]': caption,
  '[data-gallery-selection-status]': status,
};
const selection = {
  selectedOptions: [],
  form: { elements: { album_id: album }, querySelector: selector => nodes[selector] },
  setCustomValidity(message) { this.error = message; },
  addEventListener(event, callback) { listeners[event] = callback; },
};
const option = (value, type = 'image') => ({ value, dataset: { mediaType: type }, textContent: `File ${value}` });
runInNewContext(readFileSync(new URL('../../resources/js/gallery-editor.js', import.meta.url), 'utf8'), {
  document: { querySelector: () => selection },
});
assert.equal(preview.hidden, true);
selection.selectedOptions = [option('3')];
listeners.change();
assert.equal(preview.hidden, false);
assert.equal(image.src, '/cms/media/3?thumbnail=1');
assert.equal(album.required, false);
selection.selectedOptions.push(option('4'));
listeners.change();
assert.equal(preview.hidden, true);
assert.equal(image.src, undefined);
assert.equal(album.required, true);
selection.selectedOptions.push(option('5', 'video'));
listeners.change();
assert.ok(selection.error);
selection.selectedOptions = [option('5', 'video')];
listeners.change();
assert.equal(selection.error, '');
assert.equal(album.required, false);
assert.equal(preview.hidden, true);
selection.selectedOptions = [option('3')];
listeners.change();
listeners.error();
assert.equal(preview.hidden, true);
assert.match(status.textContent, /could not be loaded/);
console.log('Gallery preview and selection checks passed.');
