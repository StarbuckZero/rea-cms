(() => {
  "use strict";

  const parameters = new URLSearchParams(window.location.hash.slice(1));
  const token = parameters.get("token") || "";
  const email = parameters.get("email") || "";
  const tokenInput = document.getElementById("reset-token-value");
  const emailInput = document.getElementById("reset-email-value");
  const submitButton = document.getElementById("reset-submit");
  const valid = /^[a-f0-9]{64}$/.test(token) && email.includes("@");

  if (tokenInput && emailInput && submitButton && valid) {
    tokenInput.value = token;
    emailInput.value = email;
    submitButton.disabled = false;
  }

  document.documentElement.dataset.resetLinkReady = String(valid);

  if (window.location.hash) {
    window.history.replaceState(null, "", `${window.location.pathname}${window.location.search}`);
  }
})();
