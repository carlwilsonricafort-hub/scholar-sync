/**
 * loader.js — Full-page overlay spinner controller
 * Student Payment Portal
 *
 * API:
 *   Loader.show(message?)     — show overlay with optional label
 *   Loader.hide()             — hide overlay
 *   Loader.attachToForms(map) — auto-attach to <form> elements
 *
 * @example
 *   Loader.show('Saving...');
 *   Loader.attachToForms({ 'login-form': 'Signing in...' });
 */

const Loader = (() => {
  function show(message = "Please wait...") {
    const overlay = document.getElementById("loading-overlay");
    const label = document.getElementById("loading-label");
    if (!overlay) return;
    if (label) label.textContent = message;
    overlay.classList.add("active");
  }

  function hide() {
    const overlay = document.getElementById("loading-overlay");
    if (overlay) overlay.classList.remove("active");
  }

  /**
   * @param {Object} labelMap  { formId: 'Loading message...' }
   */
  function attachToForms(labelMap = {}) {
    document.querySelectorAll("form").forEach((form) => {
      form.addEventListener("submit", function () {
        if (!form.checkValidity()) return;
        const key = form.id || form.name || "";
        const message = labelMap[key] || "Please wait...";
        show(message);
      });
    });
  }

  return { show, hide, attachToForms };
})();

/* ── Auto-init ───────────────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  // Hide on bfcache restore (browser back button)
  window.addEventListener("pageshow", (e) => {
    if (e.persisted) Loader.hide();
  });

  // Hide once page fully loads (covers redirect-back scenarios)
  window.addEventListener("load", () => Loader.hide());
});
