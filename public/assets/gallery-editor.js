const selection = document.querySelector('[data-gallery-selection]');
if (selection) {
  const form = selection.form;
  const album = form.elements.album_id;
  const preview = form.querySelector('[data-gallery-preview]');
  const image = form.querySelector('[data-gallery-preview-image]');
  const caption = form.querySelector('[data-gallery-preview-caption]');
  const status = form.querySelector('[data-gallery-selection-status]');
  const update = () => {
    const options = [...selection.selectedOptions];
    const multiple = options.length > 1;
    const singleImage = options.length === 1 && options[0].dataset.mediaType === 'image';
    selection.setCustomValidity(multiple && options.some(option => option.dataset.mediaType !== 'image')
      ? 'Select images only for a batch. Videos can be saved individually.' : '');
    album.required = multiple;
    preview.hidden = !singleImage;
    status.textContent = multiple ? `${options.length} files selected.` : '';
    image.removeAttribute('src');
    if (singleImage) {
      caption.textContent = options[0].textContent.trim();
      image.src = `/cms/media/${options[0].value}?thumbnail=1`;
    }
  };
  image.addEventListener('error', () => {
    preview.hidden = true;
    status.textContent = 'The image preview could not be loaded. You can still save the selection.';
  });
  selection.addEventListener('change', update);
  update();
}
