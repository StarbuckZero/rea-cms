const ALLOWED_TAGS = new Set([
  "P", "BR", "H1", "H2", "H3", "H4", "H5", "H6", "STRONG", "EM", "U", "S",
  "UL", "OL", "LI", "BLOCKQUOTE", "A", "IMG", "FIGURE", "FIGCAPTION", "HR", "CODE", "PRE",
]);
const DANGEROUS_TAGS = new Set([
  "SCRIPT", "STYLE", "TEMPLATE", "IFRAME", "OBJECT", "EMBED", "FORM", "INPUT", "BUTTON", "SVG", "MATH",
]);
const TAG_ALIASES = new Map([["B", "STRONG"], ["I", "EM"], ["STRIKE", "S"], ["DIV", "P"]]);
const ALIGNMENT_CLASSES = ["align-left", "align-center", "align-right"];
const IMAGE_SIZE_CLASSES = ["size-small", "size-medium", "size-large", "size-original"];
const BLOCK_SELECTOR = "p,h1,h2,h3,h4,h5,h6,blockquote,pre,li";

function safeUrl(value, image = false) {
  const normalized = value.trim().replace(/[\u0000-\u001f\u007f\s]+/g, "");
  if (!normalized) return "";
  if (normalized.startsWith("#") || normalized.startsWith("/") || normalized.startsWith("./") || normalized.startsWith("../")) {
    return normalized;
  }
  try {
    const url = new URL(normalized);
    const protocols = image ? ["http:", "https:"] : ["http:", "https:", "mailto:", "tel:"];
    return protocols.includes(url.protocol) ? value.trim() : "";
  } catch {
    return /^[^:/?#]+(?:[/?#]|$)/.test(normalized) ? value.trim() : "";
  }
}

function replaceTag(element, tagName) {
  const replacement = element.ownerDocument.createElement(tagName);
  while (element.firstChild) replacement.append(element.firstChild);
  element.replaceWith(replacement);
  return replacement;
}

function cleanClasses(element) {
  const allowed = [];
  const candidates = element.className.split(/\s+/);
  if (element.matches(`${BLOCK_SELECTOR},figure`)) {
    allowed.push(...candidates.filter((name) => ALIGNMENT_CLASSES.includes(name)));
  }
  if (element.matches("figure,img")) {
    allowed.push(...candidates.filter((name) => IMAGE_SIZE_CLASSES.includes(name)));
  }
  if (allowed.length) element.className = [...new Set(allowed)].join(" ");
  else element.removeAttribute("class");
}

function normalizeElement(original) {
  let element = original;
  if (DANGEROUS_TAGS.has(element.tagName)) {
    element.remove();
    return;
  }
  if (TAG_ALIASES.has(element.tagName)) element = replaceTag(element, TAG_ALIASES.get(element.tagName));
  for (const child of [...element.children]) normalizeElement(child);
  for (const comment of [...element.childNodes].filter((node) => node.nodeType === Node.COMMENT_NODE)) comment.remove();
  if (!ALLOWED_TAGS.has(element.tagName)) {
    const parent = element.parentNode;
    if (!parent) return;
    while (element.firstChild) parent.insertBefore(element.firstChild, element);
    element.remove();
    return;
  }

  const attributes = Object.fromEntries([...element.attributes].map((attribute) => [attribute.name.toLowerCase(), attribute.value]));
  for (const attribute of [...element.attributes]) element.removeAttribute(attribute.name);

  if (attributes.class) {
    element.className = attributes.class;
    cleanClasses(element);
  }
  if (element.tagName === "A") {
    const href = safeUrl(attributes.href || "");
    if (href) element.setAttribute("href", href);
    if (attributes.title) element.setAttribute("title", attributes.title);
    if (attributes.target === "_blank") {
      element.setAttribute("target", "_blank");
      element.setAttribute("rel", "noopener noreferrer");
    }
  }
  if (element.tagName === "IMG") {
    const src = safeUrl(attributes.src || "", true);
    if (src) element.setAttribute("src", src);
    element.setAttribute("alt", attributes.alt || "");
    if (attributes.title) element.setAttribute("title", attributes.title);
    if (/^[1-9][0-9]{0,4}$/.test(attributes.width || "")) element.setAttribute("width", attributes.width);
    if (/^[1-9][0-9]{0,4}$/.test(attributes.height || "")) element.setAttribute("height", attributes.height);
    element.setAttribute("loading", "lazy");
  }

  if (element.tagName === "FIGCAPTION" && !element.textContent.trim()) element.remove();
}

export function sanitizeEditorHtml(html) {
  const documentNode = new DOMParser().parseFromString(`<body>${html}</body>`, "text/html");
  for (const child of [...documentNode.body.children]) normalizeElement(child);
  for (const comment of [...documentNode.body.childNodes].filter((node) => node.nodeType === Node.COMMENT_NODE)) comment.remove();
  return documentNode.body.innerHTML;
}

export class RichTextEditor {
  constructor(root) {
    this.root = root;
    this.form = root.closest("form");
    this.surface = root.querySelector("[data-editor-surface]");
    this.source = root.querySelector("[data-editor-source]");
    this.preview = root.querySelector("[data-editor-preview]");
    this.toolbar = root.querySelector("[data-editor-toolbar]");
    this.help = root.querySelector("[data-editor-help]");
    this.status = root.querySelector("[data-editor-status]");
    this.inspector = root.querySelector("[data-image-inspector]");
    this.dialog = document.querySelector("[data-media-picker]");
    this.uploadForm = this.dialog?.querySelector("[data-media-upload-form]");
    this.mode = "edit";
    this.lastRange = null;
    this.selectedFigure = null;
    this.replaceImage = false;
    this.uploading = 0;
  }

  init() {
    if (!this.form || !this.surface || !this.source || !this.preview) return;
    this.surface.innerHTML = sanitizeEditorHtml(this.surface.innerHTML);
    this.source.value = this.surface.innerHTML;
    this.bindModes();
    this.bindToolbar();
    this.bindSurface();
    this.bindImages();
    this.bindUpload();
    this.form.addEventListener("submit", (event) => this.handleSubmit(event));
    document.addEventListener("selectionchange", () => this.rememberSelection());
    document.addEventListener("fullscreenchange", () => this.updateFullscreen());
  }

  bindModes() {
    for (const button of this.root.querySelectorAll("[data-editor-mode]")) {
      button.addEventListener("click", () => this.setMode(button.dataset.editorMode));
    }
    this.root.querySelector("[data-source-toggle]")?.addEventListener("click", () => {
      this.setMode(this.mode === "html" ? "edit" : "html");
    });
  }

  bindToolbar() {
    this.toolbar?.addEventListener("mousedown", (event) => {
      if (event.target.closest("button")) event.preventDefault();
    });
    for (const button of this.root.querySelectorAll("[data-editor-command]")) {
      button.addEventListener("click", () => this.command(button.dataset.editorCommand));
    }
    for (const button of this.root.querySelectorAll("[data-editor-block]")) {
      button.addEventListener("click", () => this.formatBlock(button.dataset.editorBlock));
    }
    for (const button of this.root.querySelectorAll("[data-editor-align]")) {
      button.addEventListener("click", () => this.align(button.dataset.editorAlign));
    }
    this.root.querySelector("[data-block-format]")?.addEventListener("change", (event) => {
      this.formatBlock(event.target.value);
    });
    this.root.querySelector("[data-editor-link]")?.addEventListener("click", () => this.editLink());
    this.root.querySelector("[data-editor-fullscreen]")?.addEventListener("click", () => this.toggleFullscreen());
  }

  bindSurface() {
    this.surface.addEventListener("input", () => this.syncHidden());
    this.surface.addEventListener("keyup", () => this.updateToolbarState());
    this.surface.addEventListener("mouseup", () => this.updateToolbarState());
    this.surface.addEventListener("keydown", (event) => this.handleKeydown(event));
    this.surface.addEventListener("paste", (event) => this.handlePaste(event));
    this.surface.addEventListener("dragover", (event) => {
      if ([...event.dataTransfer.items].some((item) => item.kind === "file" && item.type.startsWith("image/"))) {
        event.preventDefault();
        this.surface.classList.add("is-dragging");
      }
    });
    this.surface.addEventListener("dragleave", () => this.surface.classList.remove("is-dragging"));
    this.surface.addEventListener("drop", (event) => this.handleDrop(event));
    this.surface.addEventListener("click", (event) => {
      const image = event.target.closest("img");
      if (image && this.surface.contains(image)) {
        event.preventDefault();
        this.selectImage(image);
      }
    });
  }

  bindImages() {
    this.root.querySelector("[data-media-open]")?.addEventListener("click", () => this.openMediaPicker(false));
    this.dialog?.querySelector("[data-media-close]")?.addEventListener("click", () => this.dialog.close());
    this.dialog?.addEventListener("click", (event) => {
      const choice = event.target.closest("[data-media-url]");
      if (!choice) return;
      this.useImage({url: choice.dataset.mediaUrl, alt: choice.dataset.mediaAlt || "", title: choice.dataset.mediaTitle || ""});
    });
    this.root.querySelector("[data-image-close]")?.addEventListener("click", () => this.closeImageInspector());
    this.root.querySelector("[data-image-replace]")?.addEventListener("click", () => this.openMediaPicker(true));
    this.root.querySelector("[data-image-remove]")?.addEventListener("click", () => {
      this.selectedFigure?.remove();
      this.closeImageInspector();
      this.syncHidden();
    });
    for (const field of this.inspector?.querySelectorAll("input,select") || []) {
      field.addEventListener("input", () => this.applyImageSettings());
      field.addEventListener("change", () => this.applyImageSettings());
    }
  }

  bindUpload() {
    this.uploadForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(this.uploadForm);
      const file = formData.get("image");
      if (!(file instanceof File) || !file.size) return;
      const asset = await this.uploadImage(file, formData.get("alt_text")?.toString() || "");
      if (asset) {
        this.addMediaChoice(asset);
        this.useImage(asset);
        this.uploadForm.reset();
      }
    });
  }

  setMode(nextMode) {
    if (!["edit", "html", "preview"].includes(nextMode)) return;
    if (this.mode === "html") this.surface.innerHTML = sanitizeEditorHtml(this.source.value);
    const clean = sanitizeEditorHtml(this.surface.innerHTML);
    this.source.value = clean;
    this.preview.innerHTML = clean;
    this.mode = nextMode;
    this.surface.hidden = nextMode !== "edit";
    this.source.hidden = nextMode !== "html";
    this.preview.hidden = nextMode !== "preview";
    if (this.toolbar) this.toolbar.hidden = nextMode !== "edit";
    if (this.help) this.help.hidden = nextMode !== "edit";
    if (nextMode !== "edit") this.closeImageInspector();
    for (const button of this.root.querySelectorAll("[data-editor-mode]")) {
      button.setAttribute("aria-selected", String(button.dataset.editorMode === nextMode));
    }
    if (nextMode === "edit") this.surface.focus();
    if (nextMode === "html") this.source.focus();
  }

  syncHidden() {
    if (this.mode === "html") return;
    this.source.value = this.surface.innerHTML;
  }

  handleSubmit(event) {
    if (this.uploading > 0) {
      event.preventDefault();
      this.announce("Please wait for image uploads to finish.", true);
      return;
    }
    if (this.mode === "html") this.surface.innerHTML = sanitizeEditorHtml(this.source.value);
    this.source.value = sanitizeEditorHtml(this.surface.innerHTML);
  }

  rememberSelection() {
    const selection = window.getSelection();
    if (!selection?.rangeCount) return;
    const range = selection.getRangeAt(0);
    if (this.surface.contains(range.commonAncestorContainer)) this.lastRange = range.cloneRange();
  }

  restoreSelection() {
    this.surface.focus();
    if (!this.lastRange || !document.contains(this.lastRange.commonAncestorContainer)) return;
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(this.lastRange);
  }

  command(command) {
    this.restoreSelection();
    document.execCommand(command, false, null);
    this.rememberSelection();
    this.syncHidden();
    this.updateToolbarState();
  }

  formatBlock(tagName) {
    this.restoreSelection();
    document.execCommand("formatBlock", false, tagName);
    this.rememberSelection();
    this.syncHidden();
  }

  closestBlock() {
    const selection = window.getSelection();
    let node = selection?.anchorNode;
    if (node?.nodeType === Node.TEXT_NODE) node = node.parentElement;
    const block = node?.closest?.(BLOCK_SELECTOR);
    return block && this.surface.contains(block) ? block : null;
  }

  align(value) {
    this.restoreSelection();
    const block = this.closestBlock();
    if (!block) return;
    block.classList.remove(...ALIGNMENT_CLASSES);
    block.classList.add(`align-${value}`);
    this.syncHidden();
  }

  editLink() {
    this.restoreSelection();
    const selection = window.getSelection();
    let node = selection?.anchorNode;
    if (node?.nodeType === Node.TEXT_NODE) node = node.parentElement;
    const current = node?.closest?.("a");
    const entered = window.prompt("Link URL", current?.getAttribute("href") || "https://");
    if (entered === null) return;
    const href = safeUrl(entered);
    if (!href) {
      if (current) current.replaceWith(...current.childNodes);
      this.announce(entered ? "That link URL is not allowed." : "Link removed.", Boolean(entered));
      this.syncHidden();
      return;
    }
    document.execCommand("createLink", false, href);
    this.syncHidden();
  }

  updateToolbarState() {
    for (const button of this.root.querySelectorAll("[data-editor-command]")) {
      const command = button.dataset.editorCommand;
      if (["bold", "italic", "underline", "strikeThrough", "insertUnorderedList", "insertOrderedList"].includes(command)) {
        button.setAttribute("aria-pressed", String(document.queryCommandState(command)));
      }
    }
    const block = this.closestBlock();
    const blockFormat = this.root.querySelector("[data-block-format]");
    if (block && blockFormat && [...blockFormat.options].some((option) => option.value === block.tagName.toLowerCase())) {
      blockFormat.value = block.tagName.toLowerCase();
    }
  }

  handleKeydown(event) {
    const modifier = event.ctrlKey || event.metaKey;
    const key = event.key.toLowerCase();
    if (modifier && ["b", "i", "u"].includes(key)) {
      event.preventDefault();
      this.command({b: "bold", i: "italic", u: "underline"}[key]);
      return;
    }
    if (modifier && key === "k") {
      event.preventDefault();
      this.editLink();
      return;
    }
    if (modifier && (key === "y" || (key === "z" && event.shiftKey))) {
      event.preventDefault();
      this.command("redo");
      return;
    }
    if (modifier && key === "z") {
      event.preventDefault();
      this.command("undo");
      return;
    }
    if (event.key === " " || event.key === "Enter") this.handleSlashCommand(event);
  }

  handleSlashCommand(event) {
    const block = this.closestBlock();
    if (!block) return;
    const command = block.textContent.trim().toLowerCase();
    const commands = {"/h1": "h1", "/quote": "blockquote", "/code": "pre"};
    if (![...Object.keys(commands), "/image", "/ul", "/ol"].includes(command)) return;
    event.preventDefault();
    const range = document.createRange();
    range.selectNodeContents(block);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    document.execCommand("delete", false, null);
    this.rememberSelection();
    if (command === "/image") this.openMediaPicker(false);
    else if (command === "/ul") this.command("insertUnorderedList");
    else if (command === "/ol") this.command("insertOrderedList");
    else this.formatBlock(commands[command]);
  }

  handlePaste(event) {
    const images = [...event.clipboardData.files].filter((file) => file.type.startsWith("image/"));
    if (images.length) {
      event.preventDefault();
      const range = this.lastRange?.cloneRange();
      this.uploadAndInsert(images, range);
      return;
    }
    const html = event.clipboardData.getData("text/html");
    if (html) {
      event.preventDefault();
      this.insertHtml(sanitizeEditorHtml(html));
    }
  }

  handleDrop(event) {
    this.surface.classList.remove("is-dragging");
    const images = [...event.dataTransfer.files].filter((file) => file.type.startsWith("image/"));
    if (!images.length) return;
    event.preventDefault();
    let range = null;
    if (document.caretRangeFromPoint) range = document.caretRangeFromPoint(event.clientX, event.clientY);
    else if (document.caretPositionFromPoint) {
      const position = document.caretPositionFromPoint(event.clientX, event.clientY);
      if (position) {
        range = document.createRange();
        range.setStart(position.offsetNode, position.offset);
        range.collapse(true);
      }
    }
    this.uploadAndInsert(images, range);
  }

  async uploadAndInsert(files, range) {
    if (range && this.surface.contains(range.commonAncestorContainer)) this.lastRange = range;
    for (const file of files) {
      const asset = await this.uploadImage(file, "");
      if (asset) {
        this.addMediaChoice(asset);
        this.useImage(asset, false);
      }
    }
  }

  async uploadImage(file, alt) {
    if (!file.type.startsWith("image/")) {
      this.announce("Only image files can be inserted.", true);
      return null;
    }
    this.uploading += 1;
    this.announce(`Uploading ${file.name}…`);
    const data = new FormData();
    data.append("_csrf", this.root.dataset.csrfToken || "");
    data.append("image", file, file.name);
    data.append("alt_text", alt);
    try {
      const response = await fetch(this.root.dataset.uploadUrl, {method: "POST", body: data, headers: {Accept: "application/json"}});
      const payload = await response.json();
      if (!response.ok || !payload.media) throw new Error(payload.error?.message || "Image upload failed.");
      this.announce(`${file.name} uploaded.`);
      return payload.media;
    } catch (error) {
      this.announce(error.message || "Image upload failed.", true);
      return null;
    } finally {
      this.uploading -= 1;
    }
  }

  openMediaPicker(replace) {
    this.replaceImage = replace;
    this.rememberSelection();
    this.dialog?.showModal();
  }

  useImage(asset, closeDialog = true) {
    if (this.replaceImage && this.selectedFigure) {
      const image = this.selectedFigure.querySelector("img");
      image.src = asset.url;
      image.alt = asset.alt || "";
      if (asset.title) image.title = asset.title;
      this.selectImage(image);
    } else {
      this.insertImage(asset);
    }
    this.replaceImage = false;
    if (closeDialog) this.dialog?.close();
    this.syncHidden();
  }

  insertImage(asset) {
    this.restoreSelection();
    const figure = document.createElement("figure");
    figure.className = "size-large";
    const image = document.createElement("img");
    image.src = asset.url;
    image.alt = asset.alt || "";
    image.loading = "lazy";
    if (asset.title) image.title = asset.title;
    figure.append(image);
    this.insertNode(figure);
    const paragraph = document.createElement("p");
    paragraph.append(document.createElement("br"));
    figure.after(paragraph);
    this.selectImage(image);
  }

  insertNode(node) {
    let range = this.lastRange;
    if (!range || !this.surface.contains(range.commonAncestorContainer)) {
      range = document.createRange();
      range.selectNodeContents(this.surface);
      range.collapse(false);
    }
    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);
    this.lastRange = range.cloneRange();
  }

  insertHtml(html) {
    this.restoreSelection();
    const template = document.createElement("template");
    template.innerHTML = html;
    const nodes = [...template.content.childNodes];
    for (const node of nodes) this.insertNode(node);
    this.syncHidden();
  }

  selectImage(image) {
    let figure = image.closest("figure");
    if (!figure) {
      figure = document.createElement("figure");
      image.replaceWith(figure);
      figure.append(image);
    }
    this.selectedFigure?.classList.remove("is-selected");
    this.selectedFigure = figure;
    figure.classList.add("is-selected");
    this.inspector.hidden = false;
    this.inspector.querySelector("[data-image-alt]").value = image.alt || "";
    this.inspector.querySelector("[data-image-title]").value = image.title || "";
    this.inspector.querySelector("[data-image-caption]").value = figure.querySelector("figcaption")?.textContent || "";
    this.inspector.querySelector("[data-image-alignment]").value = this.classValue(figure, "align", "none");
    this.inspector.querySelector("[data-image-size]").value = this.classValue(figure, "size", "large");
    this.inspector.querySelector("[data-image-link]").value = image.closest("a")?.getAttribute("href") || "";
  }

  classValue(element, prefix, fallback) {
    const entry = [...element.classList].find((name) => name.startsWith(`${prefix}-`));
    return entry ? entry.slice(prefix.length + 1) : fallback;
  }

  applyImageSettings() {
    if (!this.selectedFigure) return;
    const image = this.selectedFigure.querySelector("img");
    if (!image) return;
    image.alt = this.inspector.querySelector("[data-image-alt]").value.trim();
    const title = this.inspector.querySelector("[data-image-title]").value.trim();
    if (title) image.title = title;
    else image.removeAttribute("title");

    const captionText = this.inspector.querySelector("[data-image-caption]").value.trim();
    let caption = this.selectedFigure.querySelector("figcaption");
    if (captionText) {
      if (!caption) {
        caption = document.createElement("figcaption");
        this.selectedFigure.append(caption);
      }
      caption.textContent = captionText;
    } else caption?.remove();

    this.selectedFigure.classList.remove(...ALIGNMENT_CLASSES, ...IMAGE_SIZE_CLASSES);
    const alignment = this.inspector.querySelector("[data-image-alignment]").value;
    if (alignment !== "none") this.selectedFigure.classList.add(`align-${alignment}`);
    this.selectedFigure.classList.add(`size-${this.inspector.querySelector("[data-image-size]").value}`);

    const enteredLink = this.inspector.querySelector("[data-image-link]").value;
    const href = safeUrl(enteredLink);
    const currentLink = image.closest("a");
    if (href) {
      if (currentLink) currentLink.href = href;
      else {
        const link = document.createElement("a");
        link.href = href;
        image.replaceWith(link);
        link.append(image);
      }
    } else if (currentLink) currentLink.replaceWith(image);
    this.syncHidden();
  }

  closeImageInspector() {
    this.selectedFigure?.classList.remove("is-selected");
    this.selectedFigure = null;
    if (this.inspector) this.inspector.hidden = true;
  }

  addMediaChoice(asset) {
    const grid = this.dialog?.querySelector("[data-media-grid]");
    if (!grid || grid.querySelector(`[data-media-url="${CSS.escape(asset.url)}"]`)) return;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "media-choice";
    button.dataset.mediaUrl = asset.url;
    button.dataset.mediaAlt = asset.alt || "";
    button.dataset.mediaTitle = asset.title || "";
    const image = document.createElement("img");
    image.src = asset.thumbnailUrl || asset.url;
    image.alt = asset.alt || "";
    const label = document.createElement("span");
    label.textContent = asset.title || "Uploaded image";
    button.append(image, label);
    grid.prepend(button);
  }

  async toggleFullscreen() {
    if (document.fullscreenElement === this.root) {
      await document.exitFullscreen();
    } else if (this.root.requestFullscreen) {
      try {
        await this.root.requestFullscreen();
      } catch {
        this.root.classList.toggle("is-fullscreen");
      }
    } else this.root.classList.toggle("is-fullscreen");
    this.updateFullscreen();
  }

  updateFullscreen() {
    const active = document.fullscreenElement === this.root || this.root.classList.contains("is-fullscreen");
    this.root.querySelector("[data-editor-fullscreen]")?.setAttribute("aria-pressed", String(active));
  }

  announce(message, error = false) {
    if (!this.status) return;
    this.status.textContent = message;
    this.status.classList.toggle("is-error", error);
  }
}
