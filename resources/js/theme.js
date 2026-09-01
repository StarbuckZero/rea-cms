(() => {
  "use strict";

  const allowedThemes = new Set(["system", "light", "dark", "high-contrast"]);
  const storedTheme = window.localStorage.getItem("rea_theme");
  const serverTheme = document.documentElement.dataset.theme || "system";
  const hasAccountTheme = document.documentElement.dataset.themeAccount === "true";
  const initialTheme = hasAccountTheme && allowedThemes.has(serverTheme)
    ? serverTheme
    : allowedThemes.has(storedTheme)
      ? storedTheme
      : serverTheme;

  const applyTheme = (theme) => {
    const selected = allowedThemes.has(theme) ? theme : "system";
    document.documentElement.dataset.theme = selected;
    window.localStorage.setItem("rea_theme", selected);
    document.cookie = `rea_theme=${encodeURIComponent(selected)}; Path=/; Max-Age=31536000; SameSite=Lax`;

    for (const button of document.querySelectorAll("[data-theme-choice]")) {
      button.setAttribute("aria-pressed", String(button.dataset.themeChoice === selected));
    }

    for (const select of document.querySelectorAll("[data-theme-select]")) {
      select.value = selected;
    }
  };

  applyTheme(initialTheme);

  document.addEventListener("DOMContentLoaded", () => {
    applyTheme(document.documentElement.dataset.theme);

    document.addEventListener("click", (event) => {
      const button = event.target.closest("[data-theme-choice]");

      if (button) {
        applyTheme(button.dataset.themeChoice);
      }
    });

    document.addEventListener("change", (event) => {
      const select = event.target.closest("[data-theme-select]");

      if (select) {
        applyTheme(select.value);
      }
    });

    document.body.addEventListener("htmx:after:settle", () => {
      const status = document.querySelector("#htmx-result [role='status']");
      const region = document.getElementById("status-region");

      if (status && region) {
        region.textContent = status.textContent.trim();
      }
    });
  });
})();
