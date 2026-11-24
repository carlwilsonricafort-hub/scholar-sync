/**
 * validate.js — Inline client-side field validation
 * Student Payment Portal
 *
 * Provides real-time feedback as the user types or leaves a field.
 * Works alongside server-side validation — not a replacement.
 *
 * PUBLIC API:
 *   Validator.init(formId, rules)   — attach validation to a form
 *   Validator.check(formId)         — manually run all rules, returns bool
 *
 * RULE SHAPE:
 *   {
 *     field:    'input name or id',
 *     rules:    ['required', 'email', 'min:8', 'match:#other-id', 'phone', 'date'],
 *     label:    'Human-readable field name'   // optional, used in messages
 *   }
 *
 * EXAMPLE:
 *   Validator.init('login-form', [
 *     { field: 'email',    rules: ['required', 'email'],  label: 'Email' },
 *     { field: 'password', rules: ['required', 'min:8'],  label: 'Password' }
 *   ]);
 */

const Validator = (() => {
  /* ── Internal registry: formId → rule list ──────────────────── */
  const _registry = {};

  /* ── Built-in rule functions ─────────────────────────────────── */
  const _builtins = {
    required(val) {
      return val.trim() !== "" ? null : "This field is required.";
    },

    email(val) {
      if (val.trim() === "") return null; // let required handle empty
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val.trim())
        ? null
        : "Enter a valid email address.";
    },

    phone(val) {
      if (val.trim() === "") return null;
      return /^[0-9+\-\s]{7,15}$/.test(val.trim())
        ? null
        : "Enter a valid phone number (7–15 digits).";
    },

    date(val) {
      if (val.trim() === "") return null;
      return isNaN(Date.parse(val)) ? "Enter a valid date." : null;
    },

    /** min:N — minimum string length */
    min(val, param) {
      if (val.trim() === "") return null;
      const n = parseInt(param, 10);
      return val.length >= n ? null : `Must be at least ${n} characters.`;
    },

    /** match:#selector — value must equal another field's value */
    match(val, param, formEl) {
      if (val.trim() === "") return null;
      const other = formEl.querySelector(param);
      if (!other) return null;
      return val === other.value ? null : "Passwords do not match.";
    },
  };

  /* ── Parse rule string like "min:8" or "match:#new_password" ── */
  function _parseRule(ruleStr) {
    const colonIdx = ruleStr.indexOf(":");
    if (colonIdx === -1) return { name: ruleStr, param: null };
    return {
      name: ruleStr.slice(0, colonIdx),
      param: ruleStr.slice(colonIdx + 1),
    };
  }

  /* ── Get or create the error/hint elements below a field ─────── */
  function _getMessageEl(field, type) {
    const id = `${field.id || field.name}__${type}`;
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement("span");
      el.id = id;
      el.className = type === "error" ? "field-error" : "field-hint";
      field.parentNode.insertBefore(el, field.nextSibling);
    }
    return el;
  }

  /* ── Validate a single field against its rules ───────────────── */
  function _validateField(field, rules, formEl) {
    const val = field.value;
    let errMsg = null;

    for (const ruleStr of rules) {
      const { name, param } = _parseRule(ruleStr);
      const fn = _builtins[name];
      if (!fn) continue;
      const result = param !== null ? fn(val, param, formEl) : fn(val);
      if (result !== null) {
        errMsg = result;
        break;
      }
    }

    const errEl = _getMessageEl(field, "error");
    const hintEl = _getMessageEl(field, "hint");

    if (errMsg) {
      field.classList.add("is-invalid");
      field.classList.remove("is-valid");
      errEl.textContent = errMsg;
      errEl.classList.add("visible");
      hintEl.classList.remove("visible");
      return false;
    } else if (val.trim() !== "") {
      field.classList.add("is-valid");
      field.classList.remove("is-invalid");
      errEl.classList.remove("visible");
      hintEl.classList.add("visible");
      hintEl.textContent = "✓ Looks good";
      return true;
    } else {
      // Empty, no error yet — neutral state
      field.classList.remove("is-valid", "is-invalid");
      errEl.classList.remove("visible");
      hintEl.classList.remove("visible");
      return true;
    }
  }

  /* ── Attach live listeners to a form ─────────────────────────── */
  function init(formId, ruleDefs) {
    _registry[formId] = ruleDefs;

    const formEl = document.getElementById(formId);
    if (!formEl) return;

    ruleDefs.forEach(({ field: fieldName, rules }) => {
      // Support both name= and id= selectors
      const fieldEl =
        formEl.querySelector(`[name="${fieldName}"]`) ||
        formEl.querySelector(`#${fieldName}`);
      if (!fieldEl) return;

      /* Validate on blur (when user leaves the field) */
      fieldEl.addEventListener("blur", () => {
        _validateField(fieldEl, rules, formEl);
      });

      /* Re-validate on input after first blur (live feedback) */
      let touched = false;
      fieldEl.addEventListener("blur", () => {
        touched = true;
      });
      fieldEl.addEventListener("input", () => {
        if (touched) _validateField(fieldEl, rules, formEl);
      });
    });

    /* Validate everything on submit attempt */
    formEl.addEventListener(
      "submit",
      (e) => {
        const valid = check(formId);
        if (!valid) {
          e.preventDefault();
          e.stopImmediatePropagation();
          // Scroll to first error
          const firstError = formEl.querySelector(".form-control.is-invalid");
          if (firstError)
            firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      },
      true,
    ); // capture phase so it fires before Loader's submit listener
  }

  /* ── Manually run all rules for a form; returns true if valid ── */
  function check(formId) {
    const ruleDefs = _registry[formId];
    if (!ruleDefs) return true;

    const formEl = document.getElementById(formId);
    if (!formEl) return true;

    let allValid = true;
    ruleDefs.forEach(({ field: fieldName, rules }) => {
      const fieldEl =
        formEl.querySelector(`[name="${fieldName}"]`) ||
        formEl.querySelector(`#${fieldName}`);
      if (!fieldEl) return;
      const ok = _validateField(fieldEl, rules, formEl);
      if (!ok) allValid = false;
    });

    return allValid;
  }

  return { init, check };
})();
