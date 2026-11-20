/**
 * Real-time admin header bell: polls notifications API, updates badge, opens dropdown under bell on click.
 */
(function () {
  const BC_NAME = "crypterra-admin-notify";

  function adminBaseUrl() {
    const scripts = document.getElementsByTagName("script");
    for (let i = scripts.length - 1; i >= 0; i--) {
      const s = scripts[i].src;
      if (s && s.indexOf("admin-notify-live.js") !== -1) {
        return s.replace(/\/js\/admin-notify-live\.js(\?.*)?$/i, "");
      }
    }
    return "";
  }

  function pollIntervalMs() {
    const scripts = document.getElementsByTagName("script");
    for (let i = scripts.length - 1; i >= 0; i--) {
      const el = scripts[i];
      if (!el.src || el.src.indexOf("admin-notify-live.js") === -1) continue;
      const raw = el.getAttribute("data-poll-ms");
      if (raw == null || raw === "") return 5000;
      const n = parseInt(String(raw), 10);
      if (isFinite(n) && n >= 3000 && n <= 120000) return n;
      return 5000;
    }
    return 5000;
  }

  const POLL_MS = pollIntervalMs();
  const API = adminBaseUrl() + "/api/notifications_poll.php";

  function escapeHtml(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function injectStyles() {
    if (document.getElementById("admin-notify-live-styles")) return;
    const st = document.createElement("style");
    st.id = "admin-notify-live-styles";
    st.textContent = `
      .header-right .icon-btn.admin-notify-bell-wrap {
        cursor: pointer;
        position: relative;
      }
      .header-right .icon-btn .badge.admin-notify-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        font-size: 10px;
        font-weight: 700;
        line-height: 18px;
        color: #fff;
        background: var(--danger, #ef4444);
        border-radius: 9px;
        border: 2px solid rgba(15, 23, 42, 0.95);
        display: none;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        width: auto;
      }
      .header-right .icon-btn .badge.admin-notify-badge.is-on {
        display: inline-flex;
      }
      .header-right .icon-btn .badge.admin-notify-badge.is-dot {
        width: 8px;
        min-width: 8px;
        height: 8px;
        padding: 0;
        font-size: 0;
        line-height: 0;
        color: transparent;
      }

      #admin-notify-modal {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: none;
        pointer-events: none;
      }
      #admin-notify-modal.is-open {
        display: block;
      }
      .admin-notify-modal-panel {
        position: fixed;
        left: 0;
        top: 0;
        width: 360px;
        max-width: min(380px, calc(100vw - 16px));
        max-height: min(420px, 65vh);
        display: flex;
        flex-direction: column;
        background: var(--surface, #141b2b);
        border: 1px solid var(--glass-edge, rgba(34, 197, 94, 0.25));
        border-radius: 20px;
        box-shadow: 0 20px 50px -12px rgba(0,0,0,0.75), 0 0 0 1px rgba(0,0,0,0.2), 0 0 32px rgba(34, 197, 94, 0.1);
        overflow: hidden;
        pointer-events: auto;
        z-index: 10051;
      }
      .admin-notify-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid var(--glass-edge, rgba(34, 197, 94, 0.2));
        background: rgba(20, 30, 50, 0.5);
      }
      .admin-notify-modal-head h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text, #f1f5f9);
      }
      .admin-notify-modal-close {
        border: none;
        background: transparent;
        color: var(--text-muted, #7f8ea3);
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 12px;
      }
      .admin-notify-modal-close:hover {
        color: var(--primary, #22c55e);
        background: rgba(34, 197, 94, 0.12);
      }
      .admin-notify-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 0.75rem 0;
        -webkit-overflow-scrolling: touch;
      }
      .admin-notify-section-label {
        padding: 0.35rem 1.1rem 0.5rem;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-muted, #7f8ea3);
      }
      .admin-notify-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 0.65rem 1.1rem;
        text-decoration: none;
        color: var(--text-secondary, #b0c0d0);
        transition: background 0.15s;
        border-left: 3px solid transparent;
      }
      a.admin-notify-row:hover {
        background: rgba(34, 197, 94, 0.08);
        color: var(--text, #f1f5f9);
      }
      .admin-notify-row-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: rgba(34, 197, 94, 0.12);
        color: var(--primary, #22c55e);
        font-size: 1rem;
      }
      .admin-notify-row-main {
        flex: 1;
        min-width: 0;
      }
      .admin-notify-row-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text, #f1f5f9);
        margin-bottom: 2px;
      }
      .admin-notify-row-sub {
        font-size: 0.78rem;
        color: var(--text-muted, #7f8ea3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .admin-notify-row-meta {
        font-size: 0.72rem;
        color: var(--text-muted, #7f8ea3);
        white-space: nowrap;
        margin-top: 4px;
      }
      .admin-notify-modal-foot {
        padding: 0.85rem 1.1rem;
        border-top: 1px solid var(--glass-edge, rgba(34, 197, 94, 0.2));
        display: flex;
        justify-content: center;
        background: rgba(10, 15, 26, 0.35);
      }
      .admin-notify-modal-foot a {
        color: var(--primary, #22c55e);
        font-weight: 600;
        font-size: 0.88rem;
        text-decoration: none;
      }
      .admin-notify-modal-foot a:hover {
        text-decoration: underline;
      }
      .admin-notify-empty {
        padding: 2rem 1.25rem;
        text-align: center;
        color: var(--text-muted, #7f8ea3);
        font-size: 0.9rem;
      }
    `;
    document.head.appendChild(st);
  }

  function findBellButton() {
    const hr = document.querySelector(".header-right");
    if (!hr) return null;
    const btns = hr.querySelectorAll(".icon-btn");
    for (let i = 0; i < btns.length; i++) {
      if (btns[i].querySelector(".fa-bell")) {
        return btns[i];
      }
    }
    return null;
  }

  function applyBadge(count) {
    const bell = findBellButton();
    if (!bell) return;
    bell.classList.add("admin-notify-bell-wrap");
    let badge = bell.querySelector(".badge");
    if (!badge) {
      badge = document.createElement("span");
      badge.className = "badge admin-notify-badge";
      bell.appendChild(badge);
    } else {
      badge.classList.add("admin-notify-badge");
    }

    if (count > 0) {
      badge.classList.add("is-on");
      badge.classList.remove("is-dot");
      badge.textContent = count > 99 ? "99+" : String(count);
      badge.setAttribute("aria-label", count + " notifications");
    } else {
      badge.classList.remove("is-on");
      badge.textContent = "";
      badge.removeAttribute("aria-label");
    }
  }

  let lastPayloadSig = null;

  let bcGlobal = null;
  try {
    bcGlobal = new BroadcastChannel(BC_NAME);
    bcGlobal.onmessage = function (ev) {
      if (!ev.data || !ev.data.ok) return;
      const sig = ev.data.payload_sig || String(ev.data.badge_total);
      if (sig === lastPayloadSig) return;
      lastPayloadSig = sig;
      const n = parseInt(ev.data.badge_total, 10) || 0;
      applyBadge(n);
      window.dispatchEvent(
        new CustomEvent("adminNotifyUpdate", { detail: ev.data })
      );
      if (modalEl && modalBodyEl && modalEl.classList.contains("is-open")) {
        renderModalContent(ev.data);
      }
    };
  } catch (e) {
    bcGlobal = null;
  }

  function handlePayload(data) {
    if (!data || !data.ok) return;
    const sig = data.payload_sig || String(data.badge_total);
    if (sig === lastPayloadSig) return;
    lastPayloadSig = sig;
    const n = parseInt(data.badge_total, 10) || 0;
    applyBadge(n);
    window.dispatchEvent(
      new CustomEvent("adminNotifyUpdate", { detail: data })
    );
    if (bcGlobal) {
      try {
        bcGlobal.postMessage(data);
      } catch (e) {
        /* ignore */
      }
    }
  }

  function fetchOnce() {
    fetch(API, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then(function (r) {
        if (r.status === 401) {
          return null;
        }
        return r.json();
      })
      .then(function (data) {
        if (data) {
          handlePayload(data);
        }
      })
      .catch(function () {
        /* keep last badge */
      });
  }

  function fetchSnapshot() {
    return fetch(API, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    }).then(function (r) {
      if (r.status === 401) return null;
      return r.json();
    });
  }

  let modalEl = null;
  let modalBodyEl = null;
  let panelEl = null;
  let outsideCloseBound = false;
  let repositionScheduled = false;

  function positionDropdown() {
    const bell = findBellButton();
    if (!modalEl || !panelEl || !bell) return;
    const r = bell.getBoundingClientRect();
    const margin = 8;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const width = Math.min(380, vw - 2 * margin);
    let left = r.right - width;
    if (left < margin) left = margin;
    if (left + width > vw - margin) left = vw - width - margin;
    const maxH = Math.min(420, Math.floor(vh * 0.65));
    let top = r.bottom + margin;
    if (top + maxH > vh - margin) {
      top = Math.max(margin, r.top - maxH - margin);
    }
    panelEl.style.width = width + "px";
    panelEl.style.maxHeight = maxH + "px";
    panelEl.style.left = left + "px";
    panelEl.style.top = top + "px";
  }

  function scheduleReposition() {
    if (repositionScheduled) return;
    repositionScheduled = true;
    requestAnimationFrame(function () {
      repositionScheduled = false;
      positionDropdown();
    });
  }

  function onOutsidePointerDown(e) {
    if (!modalEl || !modalEl.classList.contains("is-open") || !panelEl) return;
    const bell = findBellButton();
    const t = e.target;
    if (bell && bell.contains(t)) return;
    if (panelEl.contains(t)) return;
    closeModal();
  }

  function attachOutsideClose() {
    if (outsideCloseBound) return;
    outsideCloseBound = true;
    document.addEventListener("pointerdown", onOutsidePointerDown, true);
    window.addEventListener("resize", scheduleReposition);
    window.addEventListener("scroll", scheduleReposition, true);
  }

  function detachOutsideClose() {
    if (!outsideCloseBound) return;
    outsideCloseBound = false;
    document.removeEventListener("pointerdown", onOutsidePointerDown, true);
    window.removeEventListener("resize", scheduleReposition);
    window.removeEventListener("scroll", scheduleReposition, true);
  }

  function ensureModal() {
    if (modalEl) return;
    modalEl = document.createElement("div");
    modalEl.id = "admin-notify-modal";
    modalEl.setAttribute("aria-modal", "true");
    modalEl.setAttribute("role", "dialog");
    modalEl.setAttribute("aria-labelledby", "admin-notify-modal-title");
    modalEl.innerHTML =
      '<div class="admin-notify-modal-panel">' +
      '<div class="admin-notify-modal-head">' +
      '<h3 id="admin-notify-modal-title">Notifications</h3>' +
      '<button type="button" class="admin-notify-modal-close" data-admin-notify-close="1" aria-label="Close">&times;</button>' +
      "</div>" +
      '<div class="admin-notify-modal-body" id="admin-notify-modal-body"></div>' +
      '<div class="admin-notify-modal-foot">' +
      '<a href="' +
      escapeHtml(adminBaseUrl() + "/admin-notifications.php") +
      '">Open notification center</a>' +
      "</div>" +
      "</div>";
    document.body.appendChild(modalEl);
    modalBodyEl = document.getElementById("admin-notify-modal-body");
    panelEl = modalEl.querySelector(".admin-notify-modal-panel");

    modalEl.addEventListener("click", function (e) {
      const t = e.target;
      if (t && t.getAttribute && t.getAttribute("data-admin-notify-close") === "1") {
        closeModal();
      }
    });
  }

  function renderModalContent(data) {
    if (!modalBodyEl) return;
    const base = adminBaseUrl();
    const p = data.platform || {};
    const pw = parseInt(p.pending_withdrawals, 10) || 0;
    const pd = parseInt(p.pending_deposits, 10) || 0;
    const kyc = parseInt(p.kyc_pending, 10) || 0;
    const items = Array.isArray(data.items) ? data.items : [];

    const platformRows = [];
    if (pw > 0) {
      platformRows.push({
        href: base + "/admin-withdrawal.php",
        icon: "fa-solid fa-arrow-up",
        title: pw + " pending withdrawal" + (pw === 1 ? "" : "s"),
        sub: "Review and approve",
      });
    }
    if (pd > 0) {
      platformRows.push({
        href: base + "/admin-deposit.php",
        icon: "fa-solid fa-arrow-down",
        title: pd + " pending deposit" + (pd === 1 ? "" : "s"),
        sub: "Confirm deposits",
      });
    }
    if (kyc > 0) {
      platformRows.push({
        href: base + "/admin-kyc.php",
        icon: "fa-solid fa-id-card",
        title: kyc + " KYC pending",
        sub: "Verify submissions",
      });
    }

    let html = "";
    if (platformRows.length) {
      html += '<div class="admin-notify-section-label">Platform</div>';
      platformRows.forEach(function (row) {
        html +=
          '<a class="admin-notify-row" href="' +
          escapeHtml(row.href) +
          '">' +
          '<span class="admin-notify-row-icon"><i class="' +
          escapeHtml(row.icon) +
          '"></i></span>' +
          '<span class="admin-notify-row-main">' +
          '<div class="admin-notify-row-title">' +
          escapeHtml(row.title) +
          "</div>" +
          '<div class="admin-notify-row-sub">' +
          escapeHtml(row.sub) +
          "</div>" +
          "</span>" +
          "</a>";
      });
    }

    if (items.length) {
      html += '<div class="admin-notify-section-label">Contact inbox</div>';
      items.forEach(function (it) {
        const href = base + "/" + String(it.href || "admin-contact.php");
        const title = it.title || "Message";
        const sub = it.subtitle || it.ticket || "";
        const time = it.time || "";
        const pri = it.priority ? String(it.priority) : "";
        html +=
          '<a class="admin-notify-row" href="' +
          escapeHtml(href) +
          '">' +
          '<span class="admin-notify-row-icon"><i class="fa-solid fa-envelope"></i></span>' +
          '<span class="admin-notify-row-main">' +
          '<div class="admin-notify-row-title">' +
          escapeHtml(title) +
          "</div>" +
          '<div class="admin-notify-row-sub">' +
          escapeHtml(sub) +
          "</div>" +
          (time
            ? '<div class="admin-notify-row-meta">' + escapeHtml(time) + (pri ? " · " + escapeHtml(pri) : "") + "</div>"
            : "") +
          "</span>" +
          "</a>";
      });
    }

    if (!html) {
      const total = parseInt(data.badge_total, 10) || 0;
      html =
        '<div class="admin-notify-empty">' +
        (total === 0
          ? "You're all caught up. No pending items."
          : "No detailed items to show. Open the notification center for more.") +
        "</div>";
    }

    modalBodyEl.innerHTML = html;
    scheduleReposition();
  }

  function openModal() {
    ensureModal();
    modalBodyEl.innerHTML =
      '<div class="admin-notify-empty">Loading…</div>';
    modalEl.classList.add("is-open");
    positionDropdown();
    attachOutsideClose();

    fetchSnapshot()
      .then(function (data) {
        if (data && data.ok) {
          renderModalContent(data);
        } else {
          modalBodyEl.innerHTML =
            '<div class="admin-notify-empty">Could not load notifications.</div>';
          scheduleReposition();
        }
      })
      .catch(function () {
        modalBodyEl.innerHTML =
          '<div class="admin-notify-empty">Could not load notifications.</div>';
        scheduleReposition();
      });
  }

  function closeModal() {
    if (!modalEl) return;
    modalEl.classList.remove("is-open");
    detachOutsideClose();
  }

  function onBellClick(e) {
    const bell = findBellButton();
    if (!bell || !bell.contains(e.target)) return;
    e.preventDefault();
    e.stopPropagation();
    if (modalEl && modalEl.classList.contains("is-open")) {
      closeModal();
      return;
    }
    openModal();
  }

  function onKeyDown(e) {
    if (e.key === "Escape" && modalEl && modalEl.classList.contains("is-open")) {
      closeModal();
    }
  }

  injectStyles();
  document.addEventListener("click", onBellClick, true);
  document.addEventListener("keydown", onKeyDown);

  fetchOnce();
  let timer = setInterval(fetchOnce, POLL_MS);

  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "visible") {
      fetchOnce();
    }
  });

  window.addEventListener("focus", fetchOnce);

  window.AdminNotifyRefresh = fetchOnce;
  window.AdminNotifyOpenModal = openModal;
  window.AdminNotifyCloseModal = closeModal;
})();
