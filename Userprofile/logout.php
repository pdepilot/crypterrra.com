<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';

function sh(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userFullName = (string)($_SESSION['user_full_name'] ?? '');
$userEmail = (string)($_SESSION['user_email'] ?? '');
$walletId = '';

try {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $schema = (string)$DB_NAME;
  ensure_user_dashboard_schema($db, $schema);
  $row = user_fetch_by_id($db, $userId);
  if (is_array($row)) {
    $userFullName = (string)($row['full_name'] ?? $userFullName);
    $userEmail = (string)($row['email'] ?? $userEmail);
    $_SESSION['user_full_name'] = $userFullName;
    $_SESSION['user_email'] = $userEmail;
    $walletId = is_string($row['wallet_id'] ?? null) ? (string)$row['wallet_id'] : '';
    if ($walletId === '') {
      $walletId = user_ensure_wallet_id($db, $userId);
    }
  }
  $db->close();
} catch (Throwable $e) {
  error_log('[logout] ' . $e->getMessage());
}

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);
$displayName = $ub['full_name'];
$avatarLetter = $ub['avatar_letter'];
$walletShort = $ub['wallet_short'];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <title>Crypterra | Logout</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family:
          "Inter",
          system-ui,
          -apple-system,
          "Segoe UI",
          Roboto,
          sans-serif;
      }

      @import url("https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap");

      /* ========== ROOT COLORS - GREEN THEME ========== */
      :root {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --primary-soft: rgba(34, 197, 94, 0.15);
        --secondary-color: #2ecc71;
        --danger-color: #ef5350;
        --warning-color: #ffa726;

        /* dark theme (default) */
        --bg: #0a1525;
        --bg-secondary: #07101f;
        --surface: #0f1e30;
        --surface-light: #1a2a40;
        --text: #f0f7ff;
        --text-secondary: #b0c8e0;
        --text-muted: #7f9bc0;
        --glass-bg: rgba(20, 40, 60, 0.4);
        --glass-edge: rgba(34, 197, 94, 0.25);
        --card-bg: rgba(15, 30, 45, 0.6);
        --positive: #26a69a;
        --negative: #ef5350;
        --shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        --shadow-glow: 0 0 30px var(--primary-glow);
        --light-border: #2d2d2d;
        --input-bg: rgba(10, 20, 35, 0.8);
      }

      /* light theme class */
      body.light {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-glow: rgba(34, 197, 94, 0.4);
        --bg: #f5f7fb;
        --bg-secondary: #eef2f6;
        --surface: #ffffff;
        --surface-light: #f0f4fa;
        --text: #333333;
        --text-secondary: #555555;
        --text-muted: #7f8c8d;
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-edge: rgba(34, 197, 94, 0.3);
        --card-bg: #ffffff;
        --positive: #2ecc71;
        --negative: #e74c3c;
        --shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        --shadow-glow: 0 0 20px rgba(34, 197, 94, 0.4);
        --light-border: #eaeaea;
        --input-bg: #f8fafc;
      }

      body {
        background-color: var(--bg);
        color: var(--text);
        transition:
          background-color 0.3s ease,
          color 0.2s ease;
      }

      /* Dashboard Layout */
      .dashboard {
        display: flex;
        min-height: 100vh;
      }

      /* SIDEBAR */
      .sidebar {
        width: 280px;
        background: var(--bg-secondary);
        border-right: 1px solid var(--light-border);
        transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 20;
      }

      .sidebar.collapsed {
        width: 80px;
      }

      .sidebar.collapsed .logo-wrapper h3,
      .sidebar.collapsed .user-info,
      .sidebar.collapsed .sidebar-link span:not(.action-badge),
      .sidebar.collapsed .nav-section {
        display: none;
      }

      .sidebar.collapsed .sidebar-link {
        justify-content: center;
        padding: 12px;
      }

      .sidebar.collapsed .sidebar-link i {
        margin-right: 0;
      }

      .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px 20px;
        border-bottom: 1px solid var(--light-border);
      }

      .logo-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .logo-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        box-shadow: 0 4px 10px var(--primary-glow);
      }

      .collapse-btn {
        cursor: pointer;
        background: var(--surface);
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: 0.2s;
        color: var(--text-secondary);
      }

      .sidebar-user {
        padding: 24px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        border-bottom: 1px solid var(--light-border);
      }

      .user-avatar-large {
        width: 52px;
        height: 52px;
        background: linear-gradient(
          145deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 24px;
      }

      .user-info h4 {
        font-size: 1rem;
        font-weight: 600;
      }

      .user-info p {
        font-size: 0.75rem;
        color: var(--text-muted);
      }

      .sidebar-nav {
        padding: 20px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }

      .nav-section {
        font-size: 0.7rem;
        letter-spacing: 1px;
        font-weight: 600;
        color: var(--text-muted);
        margin: 16px 0 8px 12px;
      }

      .sidebar-link {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 12px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: 0.2s;
      }

      .sidebar-link i {
        width: 22px;
        font-size: 1.1rem;
      }

      .sidebar-link:hover {
        background: var(--primary-soft);
        color: var(--primary-color);
      }

      .sidebar-link.active {
        background: var(--primary-soft);
        color: var(--primary-color);
        border-left: 3px solid var(--primary-color);
      }

      .action-badge {
        background: var(--primary-color);
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: auto;
        color: white;
      }

      /* MAIN CONTENT */
      .main-content {
        flex: 1;
        overflow-x: auto;
      }

      /* FIXED TOP NAVBAR — offset via includes/user_sidebar_branding.css (.top-bar--main) */
      .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 28px;
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--glass-edge);
        box-sizing: border-box;
      }

      .header-left {
        display: flex;
        align-items: center;
        gap: 20px;
      }

      .mobile-menu-btn {
        display: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text);
      }

      .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: inherit;
      }

      .user-avatar {
        width: 40px;
        height: 40px;
        background: var(--primary-soft);
        border-radius: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: var(--primary-color);
      }

      .header-right {
        display: flex;
        gap: 16px;
        align-items: center;
      }

      .icon-button {
        background: var(--surface);
        width: 40px;
        height: 40px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s;
        color: var(--text-secondary);
        border: 1px solid var(--light-border);
      }

      .theme-toggle {
        width: 40px;
        height: 40px;
        background: var(--surface);
        border-radius: 30px;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2322c55e'%3E%3Cpath d='M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: 20px;
      }

      /* Dashboard Content */
      .dashboard-content {
        padding: 24px 28px;
        margin-top: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      /* Logout Page Specific Styles */
      .logout-container {
        max-width: 500px;
        width: 100%;
        margin: 0 auto;
      }

      .logout-card {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 48px;
        padding: 48px 32px;
        text-align: center;
        transition:
          transform 0.3s,
          box-shadow 0.3s;
        animation: fadeInUp 0.6s ease-out;
      }

      @keyframes fadeInUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .logout-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-glow);
        border-color: var(--primary-light);
      }

      .logout-icon {
        font-size: 5rem;
        color: var(--primary-color);
        margin-bottom: 24px;
        animation: pulse 2s infinite;
      }

      @keyframes pulse {
        0%,
        100% {
          transform: scale(1);
        }
        50% {
          transform: scale(1.05);
        }
      }

      .logout-card h2 {
        font-size: 1.8rem;
        margin-bottom: 12px;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .logout-card p {
        color: var(--text-muted);
        margin-bottom: 32px;
        line-height: 1.6;
      }

      .user-info-card {
        background: var(--primary-soft);
        border-radius: 24px;
        padding: 20px;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 16px;
        text-align: left;
      }

      .user-avatar-large-logout {
        width: 60px;
        height: 60px;
        background: linear-gradient(
          145deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px var(--primary-glow);
      }

      .user-details h4 {
        font-size: 1.1rem;
        margin-bottom: 4px;
      }

      .user-details p {
        font-size: 0.8rem;
        margin-bottom: 0;
        color: var(--text-muted);
      }

      .button-group {
        display: flex;
        gap: 16px;
        margin-bottom: 24px;
      }

      .btn-logout {
        flex: 1;
        background: linear-gradient(135deg, var(--danger-color), #c0392b);
        border: none;
        padding: 14px 24px;
        border-radius: 60px;
        color: white;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }

      .btn-logout:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
      }

      .btn-cancel {
        flex: 1;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        padding: 14px 24px;
        border-radius: 60px;
        color: var(--text);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }

      .btn-cancel:hover {
        background: var(--primary-soft);
        border-color: var(--primary-color);
        color: var(--primary-color);
      }

      .security-note {
        background: rgba(255, 167, 38, 0.1);
        border-left: 3px solid var(--warning-color);
        padding: 12px 16px;
        border-radius: 16px;
        font-size: 0.8rem;
        color: var(--text-muted);
        text-align: left;
      }

      .security-note i {
        color: var(--warning-color);
        margin-right: 8px;
      }

      /* Success State */
      .success-card {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--primary-color);
        border-radius: 48px;
        padding: 48px 32px;
        text-align: center;
        animation: fadeInUp 0.6s ease-out;
      }

      .success-icon {
        font-size: 5rem;
        color: var(--primary-color);
        margin-bottom: 24px;
      }

      .success-card h2 {
        font-size: 1.8rem;
        margin-bottom: 12px;
        color: var(--primary-light);
      }

      .success-card p {
        color: var(--text-muted);
        margin-bottom: 32px;
      }

      .redirect-timer {
        margin-top: 24px;
        padding: 12px;
        background: var(--primary-soft);
        border-radius: 40px;
        font-size: 0.9rem;
      }

      .btn-login {
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border: none;
        padding: 14px 32px;
        border-radius: 60px;
        color: white;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
      }

      .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      @media (max-width: 768px) {
        .sidebar {
          position: fixed;
          transform: translateX(-100%);
          transition: transform 0.3s;
          z-index: 1000;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }
        .dashboard-content {
          padding: 16px;
          margin-top: 0;
        }
        .logout-card {
          padding: 32px 24px;
        }
        .button-group {
          flex-direction: column;
        }
      }
    </style>
  </head>
  <body class="dark">
    <div class="dashboard">

      <!-- MAIN CONTENT -->
      <main class="main-content">
        <div class="dashboard-content">
          <div class="logout-container" id="logoutContainer">
            <!-- Logout Confirmation Card -->
            <div id="confirmationCard" class="logout-card" data-aos="fade-up">
              <div class="logout-icon">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
              </div>
              <h2>Ready to leave?</h2>
              <p>
                You're about to sign out of your Crypterra account. Make sure
                you've saved any pending transactions.
              </p>

              <div class="user-info-card">
                <div class="user-avatar-large-logout"><?php echo sh($avatarLetter); ?></div>
                <div class="user-details">
                  <h4><?php echo sh($displayName); ?></h4>
                  <p><?php echo sh($userEmail !== '' ? $userEmail : '—'); ?></p>
                  <p>Wallet: <?php echo sh($walletShort); ?></p>
                </div>
              </div>

              <div class="button-group">
                <button class="btn-logout" id="confirmLogoutBtn">
                  <i class="fa-solid fa-sign-out-alt"></i> Yes, Logout
                </button>
                <button class="btn-cancel" id="cancelLogoutBtn">
                  <i class="fa-solid fa-times"></i> Cancel
                </button>
              </div>

              <div class="security-note">
                <i class="fa-solid fa-shield-haltered"></i>
                For your security, we recommend logging out when using public or
                shared computers.
              </div>
            </div>

            <!-- Success Card (hidden initially) -->
            <div
              id="successCard"
              class="success-card"
              style="display: none"
              data-aos="fade-up"
            >
              <div class="success-icon">
                <i class="fa-solid fa-check-circle"></i>
              </div>
              <h2>Logged Out Successfully</h2>
              <p>
                You have been securely logged out of your Crypterra account.
                Your session has been terminated.
              </p>
              <a href="./logout_do.php" class="btn-login">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Login Again
              </a>
              <div class="redirect-timer">
                <i class="fa-solid fa-clock"></i> Redirecting to login page in
                <span id="countdown">5</span> seconds...
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Logout Confirmation Modal (for additional verification) -->
    <div id="logoutModal" class="modal" style="display: none">
      <div class="modal-content">
        <i
          class="fa-solid fa-triangle-exclamation"
          style="font-size: 3rem; color: var(--warning-color)"
        ></i>
        <h3>Confirm Logout</h3>
        <p>
          Are you sure you want to log out? Any unsaved changes will be lost.
        </p>
        <div class="modal-buttons">
          <button class="modal-btn cancel" id="modalCancelBtn">Cancel</button>
          <button class="modal-btn confirm" id="modalConfirmBtn">
            Yes, Logout
          </button>
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>
      AOS.init({ duration: 500, once: true });

      // DOM Elements
      const confirmationCard = document.getElementById("confirmationCard");
      const successCard = document.getElementById("successCard");
      const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");
      const cancelLogoutBtn = document.getElementById("cancelLogoutBtn");
      const logoutModal = document.getElementById("logoutModal");
      const modalConfirmBtn = document.getElementById("modalConfirmBtn");
      const modalCancelBtn = document.getElementById("modalCancelBtn");
      const countdownSpan = document.getElementById("countdown");

      let countdownInterval = null;

      // Show notification function
      function showNotification(message, type = "info") {
        const notif = document.createElement("div");
        notif.className = "notification-popup show";
        notif.style.position = "fixed";
        notif.style.top = "24px";
        notif.style.right = "24px";
        notif.style.background = "var(--surface)";
        notif.style.padding = "12px 20px";
        notif.style.borderRadius = "40px";
        notif.style.borderLeft = `4px solid ${type === "success" ? "var(--primary-color)" : "var(--warning-color)"}`;
        notif.style.zIndex = "200";
        notif.style.boxShadow = "var(--shadow)";
        notif.innerHTML = `<i class="fa-solid ${type === "success" ? "fa-check-circle" : "fa-info-circle"}"></i> ${message}`;
        document.body.appendChild(notif);
        setTimeout(() => notif.remove(), 3000);
      }

      // Perform logout action
      function performLogout() {
        // Clear localStorage data (simulate session clear)
        localStorage.removeItem("userSettings");
        localStorage.removeItem("connectedWallets");
        localStorage.removeItem("userSession");

        // Clear any other stored data
        sessionStorage.clear();

        // Show success card
        confirmationCard.style.display = "none";
        successCard.style.display = "block";

        // Start countdown
        let seconds = 5;
        countdownSpan.textContent = seconds;

        countdownInterval = setInterval(() => {
          seconds--;
          countdownSpan.textContent = seconds;

          if (seconds <= 0) {
            clearInterval(countdownInterval);
            // Redirect to login page (user-wallet.php as homepage)
            window.location.href = "./logout_do.php";
          }
        }, 1000);

        // Show success notification
        showNotification("You have been successfully logged out!", "success");

        // Add logout to activity log (simulate)
        const logoutTime = new Date().toLocaleString();
        console.log(`[SECURITY] User logged out at ${logoutTime}`);
      }

      // Handle logout with modal
      confirmLogoutBtn.addEventListener("click", () => {
        // Show modal for extra confirmation
        logoutModal.style.display = "flex";
      });

      // Modal confirm
      modalConfirmBtn.addEventListener("click", () => {
        logoutModal.style.display = "none";
        performLogout();
      });

      // Modal cancel
      modalCancelBtn.addEventListener("click", () => {
        logoutModal.style.display = "none";
      });

      // Cancel button - go back to dashboard
      cancelLogoutBtn.addEventListener("click", () => {
        showNotification("Logout cancelled. Returning to dashboard...", "info");
        setTimeout(() => {
          window.location.href = "./userprofile.php";
        }, 500);
      });

      // Close modal on outside click
      window.addEventListener("click", (e) => {
        if (e.target === logoutModal) {
          logoutModal.style.display = "none";
        }
      });

      // Prevent accidental navigation before logout
      window.addEventListener("beforeunload", (e) => {
        // Only show warning if on confirmation card (not logged out yet)
        if (
          confirmationCard.style.display !== "none" &&
          confirmationCard.style.display !== ""
        ) {
          e.preventDefault();
          e.returnValue = "";
        }
      });
    </script>

    <style>
      /* Modal styles for logout confirmation */
      .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        justify-content: center;
        align-items: center;
      }

      .modal-content {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 32px;
        max-width: 400px;
        width: 90%;
        text-align: center;
      }

      .modal-content i {
        font-size: 3rem;
        margin-bottom: 16px;
      }

      .modal-content h3 {
        margin-bottom: 12px;
      }

      .modal-content p {
        color: var(--text-muted);
        margin-bottom: 24px;
      }

      .modal-buttons {
        display: flex;
        gap: 12px;
      }

      .modal-btn {
        flex: 1;
        padding: 12px;
        border-radius: 40px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: 0.2s;
      }

      .modal-btn.confirm {
        background: var(--danger-color);
        color: white;
      }

      .modal-btn.confirm:hover {
        background: #c0392b;
        transform: translateY(-1px);
      }

      .modal-btn.cancel {
        background: var(--surface);
        color: var(--text-secondary);
      }

      .modal-btn.cancel:hover {
        background: var(--primary-soft);
        color: var(--primary-color);
      }
    </style>
  </body>
</html>
