(() => {
  "use strict";

  const status = document.querySelector("#status-region");

  const copyText = async (value) => {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(value);
      return;
    }
    const field = document.createElement("textarea");
    field.value = value;
    field.setAttribute("readonly", "");
    field.style.position = "fixed";
    field.style.opacity = "0";
    document.body.append(field);
    field.select();
    const copied = document.execCommand("copy");
    field.remove();
    if (!copied) throw new Error("Copy is unavailable.");
  };

  for (const button of document.querySelectorAll("[data-copy-value]")) {
    button.addEventListener("click", async () => {
      const value = button.dataset.copyValue || "";
      const label = button.dataset.copyLabel || "value";
      try {
        await copyText(value);
        if (status) status.textContent = `Copied ${label}.`;
        const original = button.textContent;
        button.textContent = "Copied";
        window.setTimeout(() => { button.textContent = original; }, 1500);
      } catch {
        if (status) status.textContent = `Could not copy ${label}.`;
      }
    });
  }

  for (const form of document.querySelectorAll("[data-confirm-delete]")) {
    form.addEventListener("submit", (event) => {
      const name = form.dataset.confirmDelete || "this text block";
      if (!window.confirm(`Delete “${name}”? This cannot be undone.`)) event.preventDefault();
    });
  }
})();
