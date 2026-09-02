import {RichTextEditor} from "/assets/rich-text-editor.js";

for (const root of document.querySelectorAll("[data-rich-text-editor]")) {
  new RichTextEditor(root).init();
}
