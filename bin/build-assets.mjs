import { copyFile, mkdir } from "node:fs/promises";

await mkdir(new URL("../public/assets/", import.meta.url), { recursive: true });
await copyFile(
  new URL("../node_modules/htmx.org/dist/htmx.min.js", import.meta.url),
  new URL("../public/assets/htmx.min.js", import.meta.url),
);
await copyFile(
  new URL("../resources/js/theme.js", import.meta.url),
  new URL("../public/assets/theme.js", import.meta.url),
);
await copyFile(
  new URL("../resources/js/navigation.js", import.meta.url),
  new URL("../public/assets/navigation.js", import.meta.url),
);
await copyFile(
  new URL("../resources/js/reset-password.js", import.meta.url),
  new URL("../public/assets/reset-password.js", import.meta.url),
);
await copyFile(
  new URL("../resources/js/editor.js", import.meta.url),
  new URL("../public/assets/editor.js", import.meta.url),
);
