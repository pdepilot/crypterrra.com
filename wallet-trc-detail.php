<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover"
    />
    <title>Crypterra · Mobile Wallet</title>
    <?php include __DIR__ . '/includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 (free) -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    />
    <!-- Google Fonts Inter -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap"
      rel="stylesheet"
    />
    <!-- AOS scroll animation (subtle) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      /* ========== EXISTING CRYPTERRA VARIABLES (REUSED) ========== */
      :root {
        --primary: #22c55e;
        --primary-light: #4ade80;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --primary-soft: rgba(34, 197, 94, 0.15);
        --danger: #ef4444;
        --warning: #f59e0b;
        --success: #10b981;
        --info: #3b82f6;
        --orange: #f97316;

        --bg: #0a0f1a;
        --bg-secondary: #0e1422;
        --surface: #141b2b;
        --surface-light: #1e2740;
        --text: #f1f5f9;
        --text-secondary: #b0c0d0;
        --text-muted: #7f8ea3;
        --glass-bg: rgba(20, 30, 45, 0.5);
        --glass-edge: rgba(34, 197, 94, 0.25);
        --card-bg: rgba(20, 30, 50, 0.6);
        --shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.8);
        --shadow-glow: 0 0 30px var(--primary-glow);
        --border: #1f2a3f;
        --radius-card: 32px;
        --radius-element: 40px;
      }

      body {
        font-family: "Inter", sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.5;
        overflow-x: hidden;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
      }

      /* Mobile wallet container - centered, max-width 420px, mimics mobile app */
      .mobile-wallet-container {
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
        background: var(--bg-secondary);
        border-radius: 48px;
        overflow: hidden;
        box-shadow: var(--shadow);
        backdrop-filter: blur(2px);
        position: relative;
      }

      /* Inner content with padding */
      .wallet-content {
        padding: 1.2rem 1.2rem 2rem;
      }

      /* ========== TOP NAVBAR ========== */
      .navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0 1rem 0;
        background: transparent;
      }
      .nav-left,
      .nav-right {
        display: flex;
        align-items: center;
        gap: 1.2rem;
      }
      .nav-icon {
        width: 44px;
        height: 44px;
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-edge);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 1.3rem;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .nav-icon:hover {
        background: var(--primary);
        color: white;
        transform: scale(0.96);
        box-shadow: var(--shadow-glow);
      }
      .nav-icon:active {
        transform: scale(0.92);
      }

      /* Theme toggle (same style as dashboard) */
      .theme-toggle-wallet {
        width: 52px;
        height: 26px;
        background: #3a4a5a;
        border-radius: 34px;
        position: relative;
        cursor: pointer;
        border: 1px solid var(--glass-edge);
        flex-shrink: 0;
        transition: 0.2s;
      }
      .theme-toggle-wallet::before {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        top: 2px;
        left: 2px;
        transition: 0.3s;
      }
      body.dark .theme-toggle-wallet {
        background: var(--primary);
      }
      body.dark .theme-toggle-wallet::before {
        transform: translateX(26px);
      }

      /* ========== TOKEN HEADER SECTION ========== */
      .token-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin: 1.5rem 0 1.8rem 0;
      }
      .token-icon {
        width: 96px;
        height: 96px;
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.2rem;
        color: white;
        box-shadow: 0 0 35px var(--primary-glow);
        margin-bottom: 1rem;
        transition: all 0.3s ease;
      }
      .token-icon i {
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
      }
      .token-name {
        font-size: 1.3rem;
        font-weight: 600;
        background: linear-gradient(135deg, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        letter-spacing: -0.3px;
      }
      .token-balance {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--text);
        margin-top: 0.25rem;
        letter-spacing: -0.5px;
      }
      .token-fiat {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-top: 0.1rem;
      }

      /* ========== ACTION BUTTON ROW (GLASS CARD) ========== */
      .action-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: var(--radius-card);
        padding: 1.2rem 0.8rem;
        margin: 1rem 0 1.8rem 0;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        transition: all 0.2s;
      }
      .action-row {
        display: flex;
        justify-content: space-around;
        align-items: center;
        flex-wrap: nowrap;
        gap: 0.5rem;
      }
      .action-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: transform 0.2s ease;
        flex: 1;
        min-width: 0;
      }
      .action-item:hover {
        transform: translateY(-3px);
      }
      .action-item:active {
        transform: scale(0.94);
      }
      .action-circle {
        width: 56px;
        height: 56px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--primary);
        transition: all 0.2s;
      }
      .action-item:hover .action-circle {
        background: var(--primary-soft);
        border-color: var(--primary);
        box-shadow: 0 0 15px var(--primary-glow);
      }
      .action-label {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      /* ========== TRANSACTION SECTION ========== */
      .transactions-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: var(--radius-card);
        padding: 1.2rem;
        margin-top: 0.5rem;
      }
      .transactions-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
      }
      .transactions-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .transactions-title i {
        color: var(--primary);
        font-size: 1rem;
      }
      .see-all {
        font-size: 0.7rem;
        color: var(--primary);
        cursor: pointer;
        transition: 0.2s;
      }
      .see-all:hover {
        text-decoration: underline;
      }

      /* Table-like transaction header */
      .tx-table-header {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        padding: 0.6rem 0;
        border-bottom: 1px solid var(--glass-edge);
        margin-bottom: 0.5rem;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      .tx-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: 0.2s;
      }
      .tx-row:last-child {
        border-bottom: none;
      }
      .tx-amount {
        font-weight: 600;
        color: var(--text);
      }
      .tx-category {
        font-size: 0.8rem;
        color: var(--text-secondary);
      }
      .tx-date {
        font-size: 0.7rem;
        color: var(--text-muted);
        text-align: right;
      }
      .positive {
        color: var(--success);
      }
      .negative {
        color: var(--danger);
      }

      /* Empty state - soft red background like image */
      .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2.2rem 1rem;
        background: rgba(239, 68, 68, 0.08);
        border-radius: 28px;
        margin-top: 0.5rem;
        text-align: center;
        border: 1px dashed rgba(239, 68, 68, 0.3);
      }
      .empty-icon {
        font-size: 2.5rem;
        color: var(--danger);
        opacity: 0.7;
        margin-bottom: 0.8rem;
      }
      .empty-text {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--danger);
        background: rgba(239, 68, 68, 0.15);
        padding: 0.3rem 1rem;
        border-radius: 40px;
        backdrop-filter: blur(4px);
      }

      /* ========== ANIMATIONS & FADE-IN ========== */
      @keyframes fadeInUp {
        from {
          opacity: 0;
          transform: translateY(15px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      .mobile-wallet-container {
        animation: fadeInUp 0.5s ease-out;
      }
      .action-item,
      .token-header,
      .navbar {
        animation: fadeInUp 0.4s ease-out backwards;
      }
      .action-item:nth-child(1) {
        animation-delay: 0.05s;
      }
      .action-item:nth-child(2) {
        animation-delay: 0.1s;
      }
      .action-item:nth-child(3) {
        animation-delay: 0.15s;
      }
      .action-item:nth-child(4) {
        animation-delay: 0.2s;
      }

      /* ========== RESPONSIVENESS (320px, 360px, 390px) ========== */
      @media (max-width: 420px) {
        .wallet-content {
          padding: 1rem 1rem 1.8rem;
        }
        .action-circle {
          width: 50px;
          height: 50px;
          font-size: 1.3rem;
        }
        .action-label {
          font-size: 0.65rem;
        }
        .token-balance {
          font-size: 1.8rem;
        }
        .token-icon {
          width: 80px;
          height: 80px;
          font-size: 2.6rem;
        }
      }
      @media (max-width: 380px) {
        .action-circle {
          width: 46px;
          height: 46px;
          font-size: 1.2rem;
        }
        .action-label {
          font-size: 0.6rem;
        }
        .action-row {
          gap: 0.2rem;
        }
        .token-balance {
          font-size: 1.6rem;
        }
        .navbar {
          padding: 0.2rem 0 0.8rem;
        }
        .nav-icon {
          width: 38px;
          height: 38px;
          font-size: 1.1rem;
        }
      }
      @media (max-width: 340px) {
        .action-circle {
          width: 42px;
          height: 42px;
          font-size: 1rem;
        }
        .action-label {
          font-size: 0.55rem;
        }
        .token-name {
          font-size: 1.1rem;
        }
        .transactions-card {
          padding: 1rem;
        }
      }

      /* No overflow, smooth edges */
      * {
        -webkit-tap-highlight-color: transparent;
      }
    </style>
  </head>
  <body class="dark">
    <div class="mobile-wallet-container">
      <div class="wallet-content">
        <!-- TOP NAVBAR (back arrow, notification, settings, theme toggle) -->
        <div class="navbar" data-aos="fade-down" data-aos-duration="500">
          <div class="nav-left">
            <div class="nav-icon" id="backBtn">
              <i class="fa-solid fa-arrow-left"></i>
            </div>
          </div>
          <div class="nav-right">
            <div class="nav-icon" id="notificationBtn">
              <i class="fa-regular fa-bell"></i>
            </div>
            <div class="nav-icon" id="settingsBtn">
              <i class="fa-solid fa-sliders-h"></i>
            </div>
            <div class="theme-toggle-wallet" id="themeToggleWallet"></div>
          </div>
        </div>

        <!-- TOKEN HEADER SECTION (centered) -->
        <div class="token-header" data-aos="fade-up" data-aos-duration="600">
          <div class="token-icon">
            <i class="fa-brands fa-usdt"></i>
          </div>
          <div class="token-name">USDT (TRC20)</div>
          <div class="token-balance">0.00 USDT</div>
          <div class="token-fiat">$0.00 USD</div>
        </div>

        <!-- ACTION BUTTON ROW (glass card, 4 actions: Receive, Send, Swap, Buy) -->
        <div
          class="action-card"
          data-aos="fade-up"
          data-aos-duration="600"
          data-aos-delay="50"
        >
          <div class="action-row">
            <div class="action-item" id="receiveAction">
              <div class="action-circle">
                <i class="fa-solid fa-arrow-down-to-bracket"></i>
              </div>
              <span class="action-label">Receive</span>
            </div>
            <div class="action-item" id="sendAction">
              <div class="action-circle">
                <i class="fa-solid fa-paper-plane"></i>
              </div>
              <span class="action-label">Send</span>
            </div>
            <div class="action-item" id="swapAction">
              <div class="action-circle">
                <i class="fa-solid fa-arrows-spin"></i>
              </div>
              <span class="action-label">Swap</span>
            </div>
            <div class="action-item" id="buyAction">
              <div class="action-circle">
                <i class="fa-solid fa-cart-shopping"></i>
              </div>
              <span class="action-label">Buy</span>
            </div>
          </div>
        </div>

        <!-- TRANSACTION SECTION (empty state exactly like image with soft red background) -->
        <div
          class="transactions-card"
          data-aos="fade-up"
          data-aos-duration="600"
          data-aos-delay="100"
        >
          <div class="transactions-header">
            <div class="transactions-title">
              <i class="fa-solid fa-clock-rotate-left"></i> Recent Activity
            </div>
            <div class="see-all" id="seeAllBtn">See all</div>
          </div>

          <!-- Empty state (styled with soft red background, like provided image) -->
          <div class="empty-state" id="emptyState">
            <div class="empty-icon">
              <i class="fa-regular fa-rectangle-list"></i>
            </div>
            <div class="empty-text">No Transactions Found</div>
            <div
              style="
                font-size: 0.7rem;
                color: var(--text-muted);
                margin-top: 6px;
              "
            >
              Your activity will appear here
            </div>
          </div>

          <!-- Hidden transaction list (demo data, can be toggled but empty by default to match requirement) -->
          <div id="transactionList" style="display: none">
            <!-- sample rows will be injected if needed but we keep empty state per design -->
          </div>
        </div>

        <!-- subtle extra info (optional, preserves glass style) -->
        <div
          style="
            margin-top: 1rem;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-muted);
          "
          data-aos="fade-up"
        >
          <i class="fa-regular fa-shield"></i> Protected by Crypterra Security
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      (function () {
        // Initialize AOS for subtle scroll animations
        AOS.init({
          duration: 600,
          once: true,
          offset: 10,
          easing: "ease-out",
        });

        // Theme toggle (same as dashboard system)
        const themeToggle = document.getElementById("themeToggleWallet");
        if (themeToggle) {
          themeToggle.addEventListener("click", () => {
            document.body.classList.toggle("dark");
            // optional: store preference (reusing existing logic)
          });
        }

        // Simple interactive alerts to simulate actions (non-intrusive, keeps demo functional)
        const backBtn = document.getElementById("backBtn");
        const notificationBtn = document.getElementById("notificationBtn");
        const settingsBtn = document.getElementById("settingsBtn");
        const receiveAction = document.getElementById("receiveAction");
        const sendAction = document.getElementById("sendAction");
        const swapAction = document.getElementById("swapAction");
        const buyAction = document.getElementById("buyAction");
        const seeAllBtn = document.getElementById("seeAllBtn");
        const emptyStateDiv = document.getElementById("emptyState");
        const transactionListDiv = document.getElementById("transactionList");

        // Helper to show temporary feedback (consistent with dashboard style)
        function showAlertMessage(msg) {
          // create floating glass alert (reuse design)
          const alertDiv = document.createElement("div");
          alertDiv.innerText = msg;
          alertDiv.style.position = "fixed";
          alertDiv.style.bottom = "30px";
          alertDiv.style.left = "50%";
          alertDiv.style.transform = "translateX(-50%)";
          alertDiv.style.background = "var(--surface)";
          alertDiv.style.backdropFilter = "blur(20px)";
          alertDiv.style.border = "1px solid var(--glass-edge)";
          alertDiv.style.borderRadius = "40px";
          alertDiv.style.padding = "10px 20px";
          alertDiv.style.fontSize = "0.85rem";
          alertDiv.style.fontWeight = "500";
          alertDiv.style.color = "var(--text)";
          alertDiv.style.zIndex = "9999";
          alertDiv.style.boxShadow = "var(--shadow-glow)";
          alertDiv.style.fontFamily = "'Inter', sans-serif";
          document.body.appendChild(alertDiv);
          setTimeout(() => {
            alertDiv.style.opacity = "0";
            setTimeout(() => alertDiv.remove(), 300);
          }, 1800);
        }

        if (backBtn)
          backBtn.addEventListener("click", () =>
            showAlertMessage("← Navigate back to dashboard (simulated)"),
          );
        if (notificationBtn)
          notificationBtn.addEventListener("click", () =>
            showAlertMessage("🔔 No new notifications"),
          );
        if (settingsBtn)
          settingsBtn.addEventListener("click", () =>
            showAlertMessage("⚙️ Wallet settings (coming soon)"),
          );

        if (receiveAction)
          receiveAction.addEventListener("click", () =>
            showAlertMessage("📥 Receive USDT address: TYo...3fE (simulated)"),
          );
        if (sendAction)
          sendAction.addEventListener("click", () =>
            showAlertMessage("📤 Send funds - feature ready for integration"),
          );
        if (swapAction)
          swapAction.addEventListener("click", () =>
            showAlertMessage("🔄 Swap interface (integrate with DEX)"),
          );
        if (buyAction)
          buyAction.addEventListener("click", () =>
            showAlertMessage("💳 Buy crypto with fiat (simulated)"),
          );

        // See all: if no transactions, show empty message or could simulate but keep empty state
        if (seeAllBtn) {
          seeAllBtn.addEventListener("click", () => {
            showAlertMessage("📭 No transaction history available yet.");
          });
        }

        // Optional: To match design and ensure "No Transactions Found" stays with soft red background,
        // we keep empty state visible. If you wanted to add demo transactions later, but the spec says empty state.
        // For completeness, we guarantee empty state persists.
        // Additionally, we can hide the alternate transaction list if needed.
        if (transactionListDiv) transactionListDiv.style.display = "none";
        if (emptyStateDiv) emptyStateDiv.style.display = "flex";

        // Hover/tap scaling already handled by CSS (action-item, nav-icon, etc)
        // Additional micro-interaction: simulate that empty state can be refreshed? no, just perfect.

        // Ensure the theme toggle respects body class same as dashboard.
        // If user previously had dark mode preference, it's already set via body.dark.
        // We also sync any existing dashboard theme logic: if body has class "dark", keep; else default dark.
        if (!document.body.classList.contains("dark")) {
          // default is dark mode in this system, but force to keep consistent:
          document.body.classList.add("dark");
        }

        // Responsive touch feedback: add slight scale for action-item
        const allActions = document.querySelectorAll(".action-item, .nav-icon");
        allActions.forEach((el) => {
          el.addEventListener(
            "touchstart",
            function () {
              this.style.transform = "scale(0.96)";
            },
            { passive: true },
          );
          el.addEventListener("touchend", function () {
            this.style.transform = "";
          });
          el.addEventListener("touchcancel", function () {
            this.style.transform = "";
          });
        });

        // Also add for mouse
        allActions.forEach((el) => {
          el.addEventListener("mousedown", function () {
            this.style.transform = "scale(0.96)";
          });
          el.addEventListener("mouseup", function () {
            this.style.transform = "";
          });
          el.addEventListener("mouseleave", function () {
            this.style.transform = "";
          });
        });

        // Bonus: refresh token balance simulation? Not required, but keeps clean.
        // All design uses same variables, no breaking changes.
      })();
    </script>
  </body>
</html>
