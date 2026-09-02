(() => {
  "use strict";

  const form = document.querySelector("[data-template-editor]");
  if (!form) return;

  const textareas = [...form.querySelectorAll("[data-template-textarea]")];
  const activeLabel = form.querySelector("[data-active-template-label]");
  const status = document.querySelector("#status-region");
  const positions = new Map();
  let active = textareas[0] || null;

  const remember = (textarea) => {
    positions.set(textarea.id, {
      start: textarea.selectionStart ?? textarea.value.length,
      end: textarea.selectionEnd ?? textarea.value.length,
    });
  };

  const activate = (textarea) => {
    active = textarea;
    remember(textarea);
    for (const item of textareas) {
      item.closest("[data-template-card]")?.classList.toggle("is-active", item === textarea);
    }
    if (activeLabel) activeLabel.textContent = textarea.dataset.templateLabel || "selected template";
  };

  for (const textarea of textareas) {
    for (const event of ["focus", "click", "keyup", "select", "input"]) {
      textarea.addEventListener(event, () => activate(textarea));
    }
  }
  if (active) activate(active);

  for (const button of form.querySelectorAll("[data-template-binding]")) {
    button.addEventListener("click", () => {
      if (!active) return;
      const binding = button.dataset.templateBinding || "";
      const position = positions.get(active.id) || { start: active.value.length, end: active.value.length };
      active.setRangeText(binding, position.start, position.end, "end");
      active.dispatchEvent(new Event("input", { bubbles: true }));
      active.focus();
      remember(active);
      if (status) status.textContent = `${binding} inserted into ${active.dataset.templateLabel}.`;
    });
  }

  const showPreview = async (slot, button) => {
    const textarea = form.querySelector(`#${CSS.escape(slot)}`);
    const panel = form.querySelector(`[data-template-preview-panel="${CSS.escape(slot)}"]`);
    if (!textarea || !panel) return;
    const message = panel.querySelector("[data-template-preview-message]");
    const html = panel.querySelector("[data-template-preview-html]");
    const text = panel.querySelector("[data-template-preview-text]");
    const csrf = form.querySelector('input[name="_csrf"]')?.value || "";
    panel.hidden = false;
    message.textContent = "Rendering sample data…";
    html.hidden = true;
    text.hidden = true;
    button.disabled = true;

    try {
      const body = new URLSearchParams({ _csrf: csrf, slot, template: textarea.value });
      const response = await fetch(form.dataset.previewUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded", "Accept": "application/json" },
        body,
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "The preview could not be rendered.");
      message.textContent = "Preview uses generated sample values for the fields supported by this plugin.";
      if (result.format === "html") {
        html.srcdoc = result.output;
        html.hidden = false;
      } else {
        text.textContent = result.output;
        text.hidden = false;
      }
      panel.scrollIntoView({ block: "nearest" });
    } catch (error) {
      message.textContent = error instanceof Error ? error.message : "The preview could not be rendered.";
    } finally {
      button.disabled = false;
    }
  };

  for (const button of form.querySelectorAll("[data-template-preview]")) {
    button.addEventListener("click", () => showPreview(button.dataset.templatePreview || "", button));
  }

  for (const button of form.querySelectorAll("[data-template-preview-close]")) {
    button.addEventListener("click", () => {
      const panel = form.querySelector(
        `[data-template-preview-panel="${CSS.escape(button.dataset.templatePreviewClose || "")}"]`,
      );
      if (panel) panel.hidden = true;
    });
  }

  form.addEventListener("reset", () => {
    window.setTimeout(() => {
      for (const textarea of textareas) remember(textarea);
      if (active) activate(active);
    });
  });

  document.querySelector("[data-template-reset-defaults]")?.addEventListener("submit", (event) => {
    if (!window.confirm("Restore all four packaged templates and remove the saved overrides?")) {
      event.preventDefault();
    }
  });
})();
