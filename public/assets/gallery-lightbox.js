(() => {
  if (window.reaGalleryLightbox || typeof HTMLDialogElement === 'undefined') return;
  window.reaGalleryLightbox = true;
  const selector = '[data-gallery-lightbox], [data-rea-gallery] figure a';
  let dialog, image, caption, status, fullSize, previous, next, opener;
  let links = [];
  let index = 0;
  const valid = link => {
    if (!link.querySelector('img') || !link.getAttribute('href')) return false;
    const card = link.closest('[data-media-type]');
    if (card && card.dataset.mediaType === 'video') return false;
    try { return ['http:', 'https:'].includes(new URL(link.href, location.href).protocol); }
    catch { return false; }
  };
  function create() {
    dialog = document.createElement('dialog');
    dialog.className = 'rea-gallery-lightbox';
    dialog.setAttribute('aria-label', 'Image viewer');
    const toolbar = document.createElement('div');
    toolbar.className = 'rea-gallery-lightbox-toolbar';
    const button = (label, handler) => {
      const element = document.createElement('button');
      element.type = 'button';
      element.textContent = label;
      element.addEventListener('click', handler);
      toolbar.append(element);
      return element;
    };
    previous = button('Previous', () => show(index - 1));
    next = button('Next', () => show(index + 1));
    fullSize = document.createElement('a');
    fullSize.textContent = 'Open full-size image in a new tab';
    fullSize.target = '_blank';
    fullSize.rel = 'noopener noreferrer';
    toolbar.append(fullSize);
    button('Close', () => dialog.close());
    image = document.createElement('img');
    image.className = 'rea-gallery-lightbox-image';
    caption = document.createElement('p');
    status = document.createElement('p');
    status.setAttribute('role', 'status');
    image.addEventListener('load', () => { status.textContent = ''; });
    image.addEventListener('error', () => {
      status.textContent = 'The image could not be loaded. Try the full-size image link.';
    });
    dialog.append(toolbar, image, caption, status);
    document.body.append(dialog);
    dialog.addEventListener('click', event => {
      const rect = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right
        || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
      image.removeAttribute('src');
      if (opener?.isConnected) opener.focus();
    });
    dialog.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        show(index + (event.key === 'ArrowLeft' ? -1 : 1));
      }
    });
  }
  function show(target) {
    index = (target + links.length) % links.length;
    const link = links[index];
    const thumbnail = link.querySelector('img');
    image.alt = thumbnail.alt;
    status.textContent = 'Loading image…';
    image.src = link.href;
    fullSize.href = link.href;
    caption.textContent = `${index + 1} / ${links.length} — ${link.dataset.galleryName
      || link.closest('figure')?.querySelector('figcaption')?.textContent.trim() || thumbnail.alt}`;
    previous.disabled = next.disabled = links.length < 2;
  }
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey
      || event.shiftKey || event.altKey || !(event.target instanceof Element)) return;
    const link = event.target.closest(selector);
    if (!link || !valid(link)) return;
    const group = link.closest('[data-rea-gallery], section') || document;
    links = [...group.querySelectorAll(selector)].filter(valid);
    if (!links.includes(link)) return;
    event.preventDefault();
    opener = link;
    if (!dialog) create();
    show(links.indexOf(link));
    dialog.showModal();
  });
})();
