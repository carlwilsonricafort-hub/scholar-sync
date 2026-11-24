/**
 * modal.js — Payment confirmation modal controller
 * Student Payment Portal
 *
 * Intercepts every "Pay Now" link on the dashboard and shows
 * a confirmation modal before redirecting to PayPal.
 *
 * PUBLIC API:
 *   PayModal.init()   — call once on DOMContentLoaded
 *
 * The modal reads data attributes from the anchor tag:
 *   data-due-type   — e.g. "Prelim"
 *   data-amount     — e.g. "₱1,500.00"
 *   data-href       — the actual PayPal initiation URL
 *   data-label      — Loader label, e.g. "Redirecting to PayPal..."
 *
 * HTML required in the page (injected automatically if absent):
 *   <div id="pay-modal" class="modal-backdrop"> ... </div>
 */

const PayModal = (() => {
  /* ── Inject modal HTML into the page if not already present ──── */
  function _inject() {
    if (document.getElementById("pay-modal")) return;

    const html = `
<div id="pay-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="pay-modal-title">
  <div class="modal-card">

    <div class="modal-card__header">
      <span class="modal-card__icon">💳</span>
      <span class="modal-card__title" id="pay-modal-title">Confirm Payment</span>
    </div>

    <div class="modal-card__body">
      <p>You are about to be redirected to <strong>PayPal</strong> to complete the following payment:</p>

      <div class="modal-card__detail">
        <div class="modal-card__detail-row">
          <span class="modal-card__detail-label">Due Type</span>
          <span class="modal-card__detail-value" id="pay-modal-due-type">—</span>
        </div>
        <div class="modal-card__detail-row">
          <span class="modal-card__detail-label">Amount</span>
          <span class="modal-card__detail-value" id="pay-modal-amount">—</span>
        </div>
        <div class="modal-card__detail-row">
          <span class="modal-card__detail-label">Processor</span>
          <span class="modal-card__detail-value">PayPal Sandbox</span>
        </div>
      </div>

      <p style="font-size:.8rem;color:var(--gray-500);">
        You will be redirected to PayPal's secure checkout page.
        Once approved, you will be returned to this portal automatically.
      </p>
    </div>

    <div class="modal-card__footer">
      <button id="pay-modal-cancel" class="btn btn--outline">Cancel</button>
      <button id="pay-modal-confirm" class="btn btn--primary">Continue to PayPal →</button>
    </div>

  </div>
</div>`;

    document.body.insertAdjacentHTML("beforeend", html);
  }

  /* ── Open the modal with payment details ─────────────────────── */
  function _open(dueType, amount, href, loaderLabel) {
    const backdrop = document.getElementById("pay-modal");
    if (!backdrop) return;

    // Populate detail fields
    document.getElementById("pay-modal-due-type").textContent = dueType || "—";
    document.getElementById("pay-modal-amount").textContent = amount || "—";

    // Store where to go on confirm
    backdrop.dataset.href = href || "#";
    backdrop.dataset.loaderLabel = loaderLabel || "Redirecting to PayPal...";

    // Animate open: add active first (display:flex), then remove entering on next frame
    backdrop.classList.add("active", "entering");
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        backdrop.classList.remove("entering");
      });
    });

    // Trap focus on the confirm button
    setTimeout(() => {
      const confirmBtn = document.getElementById("pay-modal-confirm");
      if (confirmBtn) confirmBtn.focus();
    }, 50);
  }

  /* ── Close the modal ─────────────────────────────────────────── */
  function _close() {
    const backdrop = document.getElementById("pay-modal");
    if (!backdrop) return;
    backdrop.classList.remove("active");
  }

  /* ── Wire up all Pay Now links and modal buttons ─────────────── */
  function init() {
    _inject();

    /* Intercept Pay Now links — they carry data attributes */
    document.addEventListener("click", (e) => {
      const link = e.target.closest("a.pay-link");
      if (!link) return;

      e.preventDefault(); // stop immediate navigation

      const dueType = link.dataset.dueType || "N/A";
      const amount = link.dataset.amount || "N/A";
      const href = link.dataset.href || link.href;
      const loaderLabel = link.dataset.label || "Redirecting to PayPal...";

      _open(dueType, amount, href, loaderLabel);
    });

    /* Cancel button closes modal */
    document.addEventListener("click", (e) => {
      if (e.target.id === "pay-modal-cancel") _close();
    });

    /* Backdrop click also closes */
    document.addEventListener("click", (e) => {
      if (e.target.id === "pay-modal") _close();
    });

    /* Escape key closes */
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") _close();
    });

    /* Confirm button navigates and shows loader */
    document.addEventListener("click", (e) => {
      if (e.target.id !== "pay-modal-confirm") return;

      const backdrop = document.getElementById("pay-modal");
      const href = backdrop?.dataset.href || "#";
      const loaderLabel =
        backdrop?.dataset.loaderLabel || "Redirecting to PayPal...";

      _close();

      if (typeof Loader !== "undefined") {
        Loader.show(loaderLabel);
      }

      window.location.href = href;
    });
  }

  return { init };
})();
