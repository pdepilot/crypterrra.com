(() => {
  const STATE = {
    root: null,
    backdrop: null,
    card: null,
    titleEl: null,
    msgEl: null,
    inputEl: null,
    btnRow: null,
    resolve: null,
    reject: null,
    mode: "alert", // alert | confirm | prompt
  };

  function ensureRoot() {
    if (STATE.root) return;

    const style = document.createElement("style");
    style.textContent = `
      .ui-modal-backdrop{
        position:fixed; inset:0; background:rgba(0,0,0,.82);
        backdrop-filter: blur(10px);
        display:none; align-items:center; justify-content:center;
        z-index: 5000;
        padding: 18px;
      }
      .ui-modal{
        width:min(520px, 100%);
        background: rgba(20, 30, 50, 0.92);
        border: 1px solid rgba(34, 197, 94, 0.35);
        border-radius: 28px;
        padding: 18px 18px 16px;
        color: #f1f5f9;
        box-shadow: 0 25px 70px -20px rgba(0,0,0,.85);
      }
      .ui-modal h3{ margin:0; font-size: 1.05rem; font-weight: 750; letter-spacing: .2px; }
      .ui-modal p{ margin:10px 0 0; color: rgba(241,245,249,.82); font-size: .92rem; line-height:1.45; white-space: pre-wrap; }
      .ui-modal .ui-modal-input{
        margin-top: 14px;
        width: 100%;
        background: rgba(10, 20, 35, 0.8);
        border: 1px solid rgba(34, 197, 94, 0.25);
        border-radius: 16px;
        padding: 12px 14px;
        color: #f1f5f9;
        outline: none;
      }
      .ui-modal .ui-modal-btnrow{
        display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
        margin-top: 16px;
      }
      .ui-modal .ui-btn{
        background: transparent;
        border: 1px solid rgba(34, 197, 94, 0.35);
        border-radius: 999px;
        padding: 8px 14px;
        color: #f1f5f9;
        cursor:pointer;
        font-size: .86rem;
        transition: .15s;
      }
      .ui-modal .ui-btn:hover{ background: rgba(34,197,94,.18); border-color: rgba(34,197,94,.65); }
      .ui-modal .ui-btn.primary{
        background: linear-gradient(135deg, #22c55e, #16a34a);
        border-color: transparent;
        color: #fff;
      }
      .ui-modal .ui-btn.danger{
        background: rgba(239, 68, 68, 0.14);
        border-color: rgba(239, 68, 68, 0.45);
      }
      .ui-modal .ui-btn.danger:hover{
        background: rgba(239, 68, 68, 0.22);
        border-color: rgba(239, 68, 68, 0.75);
      }
    `;
    document.head.appendChild(style);

    const backdrop = document.createElement("div");
    backdrop.className = "ui-modal-backdrop";
    backdrop.setAttribute("role", "dialog");
    backdrop.setAttribute("aria-modal", "true");

    const card = document.createElement("div");
    card.className = "ui-modal";

    const title = document.createElement("h3");
    const msg = document.createElement("p");
    const input = document.createElement("input");
    input.className = "ui-modal-input";
    input.type = "text";
    input.autocomplete = "off";
    input.spellcheck = false;
    input.style.display = "none";

    const btnRow = document.createElement("div");
    btnRow.className = "ui-modal-btnrow";

    card.appendChild(title);
    card.appendChild(msg);
    card.appendChild(input);
    card.appendChild(btnRow);
    backdrop.appendChild(card);
    document.body.appendChild(backdrop);

    backdrop.addEventListener("click", (e) => {
      if (e.target === backdrop) {
        // close only for alert/confirm; for prompt treat as cancel
        if (STATE.mode === "alert") close(true);
        else if (STATE.mode === "confirm") close(false);
        else close(null);
      }
    });
    document.addEventListener("keydown", (e) => {
      if (!STATE.backdrop || STATE.backdrop.style.display !== "flex") return;
      if (e.key === "Escape") {
        if (STATE.mode === "alert") close(true);
        else if (STATE.mode === "confirm") close(false);
        else close(null);
      }
      if (e.key === "Enter") {
        if (STATE.mode === "prompt") {
          close(STATE.inputEl ? STATE.inputEl.value : "");
        }
      }
    });

    STATE.root = backdrop;
    STATE.backdrop = backdrop;
    STATE.card = card;
    STATE.titleEl = title;
    STATE.msgEl = msg;
    STATE.inputEl = input;
    STATE.btnRow = btnRow;
  }

  function close(value) {
    if (!STATE.backdrop) return;
    STATE.backdrop.style.display = "none";
    const r = STATE.resolve;
    STATE.resolve = null;
    STATE.reject = null;
    if (typeof r === "function") r(value);
  }

  function open({ title, message, mode, okText, cancelText, placeholder, defaultValue, danger }) {
    ensureRoot();
    STATE.mode = mode;

    STATE.titleEl.textContent = String(title || "");
    STATE.msgEl.textContent = String(message || "");

    // input
    if (mode === "prompt") {
      STATE.inputEl.style.display = "block";
      STATE.inputEl.placeholder = placeholder ? String(placeholder) : "";
      STATE.inputEl.value = defaultValue ? String(defaultValue) : "";
      setTimeout(() => STATE.inputEl && STATE.inputEl.focus(), 0);
    } else {
      STATE.inputEl.style.display = "none";
      STATE.inputEl.value = "";
    }

    // buttons
    STATE.btnRow.innerHTML = "";

    const mkBtn = (label, cls, onClick) => {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "ui-btn" + (cls ? " " + cls : "");
      b.textContent = label;
      b.addEventListener("click", onClick);
      return b;
    };

    if (mode === "alert") {
      STATE.btnRow.appendChild(mkBtn(okText || "OK", "primary", () => close(true)));
    } else if (mode === "confirm") {
      STATE.btnRow.appendChild(mkBtn(cancelText || "Cancel", "", () => close(false)));
      STATE.btnRow.appendChild(mkBtn(okText || "Confirm", danger ? "danger" : "primary", () => close(true)));
    } else {
      STATE.btnRow.appendChild(mkBtn(cancelText || "Cancel", "", () => close(null)));
      STATE.btnRow.appendChild(mkBtn(okText || "Submit", danger ? "danger" : "primary", () => close(STATE.inputEl.value)));
    }

    STATE.backdrop.style.display = "flex";
  }

  const UiModal = {
    alert(message, opts = {}) {
      return new Promise((resolve) => {
        open({
          title: opts.title || "Message",
          message,
          mode: "alert",
          okText: opts.okText || "OK",
        });
        STATE.resolve = resolve;
      });
    },
    confirm(message, opts = {}) {
      return new Promise((resolve) => {
        open({
          title: opts.title || "Confirm",
          message,
          mode: "confirm",
          okText: opts.okText || "Yes",
          cancelText: opts.cancelText || "No",
          danger: !!opts.danger,
        });
        STATE.resolve = resolve;
      });
    },
    prompt(message, opts = {}) {
      return new Promise((resolve) => {
        open({
          title: opts.title || "Input",
          message,
          mode: "prompt",
          okText: opts.okText || "Submit",
          cancelText: opts.cancelText || "Cancel",
          placeholder: opts.placeholder || "",
          defaultValue: opts.defaultValue || "",
          danger: !!opts.danger,
        });
        STATE.resolve = resolve;
      });
    },
  };

  window.UiModal = UiModal;

  // Route native alert() through the modal (avoids browser "localhost says" dialogs).
  window.alert = function (message) {
    void UiModal.alert(String(message ?? ""));
  };
})();

