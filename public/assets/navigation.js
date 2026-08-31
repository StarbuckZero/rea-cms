(() => {
  "use strict";

  const menuSelector = "details[data-navigation-menu]";

  const closeMenus = (except = null) => {
    for (const menu of document.querySelectorAll(`${menuSelector}[open]`)) {
      if (menu !== except) {
        menu.open = false;
      }
    }
  };

  document.addEventListener("toggle", (event) => {
    const menu = event.target;

    if (menu.matches?.(menuSelector) && menu.open) {
      closeMenus(menu);
    }
  }, true);

  document.addEventListener("click", (event) => {
    const openMenu = document.querySelector(`${menuSelector}[open]`);

    if (openMenu && !openMenu.contains(event.target)) {
      closeMenus();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenus();
    }
  });
})();
