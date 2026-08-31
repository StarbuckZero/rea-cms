(() => {
  const editor = document.querySelector('#blog-editor');
  const content = document.querySelector('#blog-content');
  if (!editor || !content) return;
  document.querySelectorAll('[data-editor-command]').forEach((button) => button.addEventListener('click', () => {
    document.execCommand(button.dataset.editorCommand, false, button.dataset.editorValue || null);
    editor.focus();
  }));
  document.querySelector('[data-editor-link]')?.addEventListener('click', () => {
    const url = window.prompt('Link URL (https://…)');
    if (url) document.execCommand('createLink', false, url);
  });
  const dialog = document.querySelector('#media-picker');
  document.querySelector('[data-media-open]')?.addEventListener('click', () => dialog?.showModal());
  document.querySelectorAll('[data-media-url]').forEach((button) => button.addEventListener('click', () => {
    editor.focus();
    document.execCommand('insertHTML', false, `<img src="${button.dataset.mediaUrl}" alt="${button.dataset.mediaAlt || ''}">`);
    dialog?.close();
  }));
  editor.closest('form')?.addEventListener('submit', () => { content.value = editor.innerHTML; });
})();
