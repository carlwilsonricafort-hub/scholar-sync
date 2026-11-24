/**
 * lockout-timer.js — Visible countdown timer for login lockout
 * Student Payment Portal
 *
 * Activates automatically when a #lockout-alert element is present
 * in the page (injected by login.php on HTTP 429 responses).
 *
 * Counts down from LOCKOUT_SECONDS, disables the submit button,
 * and re-enables the form + hides the alert when the timer reaches 0.
 *
 * To change the duration, update LOCKOUT_SECONDS below.
 */

(function () {
  "use strict";

  var LOCKOUT_SECONDS = 30; // ← change this number to adjust duration

  var alertEl = document.getElementById("lockout-alert");
  var timerEl = document.getElementById("lockout-timer");
  var submitBtn = document.querySelector('#login-form button[type="submit"]');
  var progressEl = document.getElementById("lockout-progress");

  /* Only run if the lockout alert is present in the page */
  if (!alertEl || !timerEl) return;

  var seconds = LOCKOUT_SECONDS;
  var total = LOCKOUT_SECONDS;

  /* Disable submit button while locked out */
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.classList.add("btn--disabled");
  }

  /* Update display immediately so there's no 1s blank */
  timerEl.textContent = seconds;
  if (progressEl) progressEl.style.width = "100%";

  var interval = setInterval(function () {
    seconds--;

    /* Update countdown text */
    if (timerEl) timerEl.textContent = seconds;

    /* Update progress bar width */
    if (progressEl) {
      var pct = (seconds / total) * 100;
      progressEl.style.width = pct + "%";
    }

    if (seconds <= 0) {
      clearInterval(interval);

      /* Hide the alert */
      if (alertEl) {
        alertEl.classList.add("lockout-alert--done");
        setTimeout(function () {
          alertEl.style.display = "none";
        }, 400);
      }

      /* Re-enable the submit button */
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove("btn--disabled");
      }
    }
  }, 1000);
})();
