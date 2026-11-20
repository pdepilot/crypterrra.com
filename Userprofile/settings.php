<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userFullName = (string)($_SESSION['user_full_name'] ?? '');
$userEmail = (string)($_SESSION['user_email'] ?? '');
$userPhone = '';
$walletId = '';
$settingsPayload = user_settings_default_payload();

try {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $bundle = user_fetch_settings_bundle($db, (string)$DB_NAME, $userId);
  $db->close();
  if (is_array($bundle)) {
    $userFullName = $bundle['full_name'];
    $userEmail = $bundle['email'];
    $userPhone = $bundle['phone'];
    $walletId = $bundle['wallet_id'];
    $settingsPayload = $bundle['settings'];
    $_SESSION['user_full_name'] = $userFullName;
    $_SESSION['user_email'] = $userEmail;
  }
} catch (Throwable $e) {
  error_log('[settings] ' . $e->getMessage());
}

$kycSidebarBadge = kyc_sidebar_badge_for_session();
$csrfToken = user_csrf_token();

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);

$settingsJsonForJs = json_encode(
  [
    'user' => [
      'full_name' => $userFullName,
      'email' => $userEmail,
      'phone' => $userPhone,
      'wallet_id' => $walletId,
    ],
    'settings' => $settingsPayload,
    'csrf' => $csrfToken,
  ],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($settingsJsonForJs === false) {
  $settingsJsonForJs = '{"user":{"full_name":"","email":"","phone":"","wallet_id":""},"settings":{},"csrf":""}';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <title>Crypterra | Settings</title>
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
        z-index: 1002;
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

      /* FIXED TOP NAVBAR — horizontal offset: includes/user_sidebar_branding.css (.top-bar--main) */
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

      @media (max-width: 768px) {
        #scrollTop {
          right: 1.5rem;
          bottom: 5.4rem;
        }
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
        margin-top: 80px;
      }

      /* Settings Page Specific Styles */
      .settings-container {
        max-width: 1200px;
        margin: 0 auto;
      }

      .page-header {
        margin-bottom: 32px;
      }

      .page-header h1 {
        font-size: 2rem;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      /* Settings Grid */
      .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
      }

      .settings-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 24px;
        transition:
          transform 0.2s,
          box-shadow 0.2s;
      }

      .settings-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--glass-edge);
      }

      .card-header i {
        font-size: 1.8rem;
        color: var(--primary-color);
      }

      .card-header h3 {
        font-size: 1.2rem;
        font-weight: 600;
      }

      /* Form Groups */
      .form-group {
        margin-bottom: 20px;
      }

      .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-secondary);
        font-size: 0.85rem;
      }

      .form-group input,
      .form-group select,
      .form-group textarea {
        width: 100%;
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 16px;
        padding: 12px 16px;
        color: var(--text);
        font-size: 0.95rem;
        transition: all 0.2s;
      }

      .form-group input:focus,
      .form-group select:focus,
      .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
      }

      /* Toggle Switch */
      .toggle-switch {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
      }

      .toggle-label {
        font-weight: 500;
      }

      .toggle-desc {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 4px;
      }

      .toggle {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
      }

      .toggle input {
        opacity: 0;
        width: 0;
        height: 0;
      }

      .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--surface-light);
        transition: 0.3s;
        border-radius: 34px;
      }

      .slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
      }

      input:checked + .slider {
        background-color: var(--primary-color);
      }

      input:checked + .slider:before {
        transform: translateX(24px);
      }

      /* Currency Selector */
      .currency-options {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 8px;
      }

      .currency-option {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 8px 20px;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.9rem;
      }

      .currency-option.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
      }

      /* Save Button */
      .save-btn {
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border: none;
        padding: 12px 24px;
        border-radius: 40px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        width: 100%;
        margin-top: 16px;
      }

      .save-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      /* Danger Zone */
      .danger-zone {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 28px;
        padding: 24px;
        margin-top: 24px;
      }

      .danger-zone h3 {
        color: var(--negative);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .danger-buttons {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
      }

      .danger-btn {
        background: rgba(239, 68, 68, 0.2);
        border: 1px solid var(--negative);
        padding: 10px 20px;
        border-radius: 40px;
        color: var(--negative);
        cursor: pointer;
        transition: 0.2s;
        font-weight: 500;
      }

      .danger-btn:hover {
        background: var(--negative);
        color: white;
      }

      /* Modal */
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
        max-width: 450px;
        width: 90%;
        text-align: center;
      }

      .modal-content i {
        font-size: 3rem;
        margin-bottom: 16px;
      }

      .modal-content input {
        width: 100%;
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 16px;
        padding: 14px;
        color: var(--text);
        margin: 16px 0;
      }

      .modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 20px;
      }

      .modal-btn {
        flex: 1;
        padding: 12px;
        border-radius: 40px;
        border: none;
        cursor: pointer;
        font-weight: 600;
      }

      .modal-btn.confirm {
        background: var(--primary-color);
        color: white;
      }

      .modal-btn.cancel {
        background: var(--surface);
        color: var(--text-secondary);
      }

      .modal-btn.danger {
        background: var(--negative);
        color: white;
      }

      #scrollTop {
        position: fixed;
        right: 1.5rem;
        bottom: 5.4rem;
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99;
      }

      @media (max-width: 768px) {
        .sidebar {
          position: fixed;
          transform: translateX(-100%);
          transition: transform 0.3s;
          z-index: 1002;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }
        .dashboard-content {
          padding: 16px;
          margin-top: 70px;
        }
        .settings-grid {
          grid-template-columns: 1fr;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.4rem;
        }
      }
    </style>
    <link rel="stylesheet" href="includes/user_sidebar_branding.css" />
  </head>
  <body class="dark">
    <div class="dashboard">
      <!-- SIDEBAR -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="logo-wrapper">
            <div class="logo-icon"><i class="fa-solid fa-globe"></i></div>
            <h3><span>Crypterra</span></h3>
          </div>
          <div class="collapse-btn" id="collapseSidebar">
            <i class="fa-solid fa-chevron-left"></i>
          </div>
        </div>
        <div class="sidebar-user">
          <div class="user-avatar-large"><?php echo h($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo h($ub['full_name']); ?></h4>
            <p><?php echo h($ub['wallet_short']); ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./userprofile.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="./trade.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a
          >
          <a href="./wallet.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a
          >
          <div class="nav-section">TRADING</div>
          <!-- <a href="./deposit.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span>
            <span class="action-badge">NEW</span></a
          >
          <a href="./withdraw.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a
          > -->
          <a href="./send.php" class="sidebar-link"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a
          >
          <a href="./receive.php" class="sidebar-link"
            ><i class="fa-solid fa-inbox"></i> <span>Receive</span></a
          >
          <div class="nav-section">ACCOUNT</div>
          <a href="./kycverification.php" class="sidebar-link"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span>
            <?php echo kyc_sidebar_badge_span($kycSidebarBadge); ?></a
          >
                    <a href="./security.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Security</span></a
          >
          <a href="./settings.php" class="sidebar-link active"
            ><i class="fa-solid fa-gear"></i> <span>Settings</span></a
          >
          <div class="nav-section">SUPPORT</div>
          <a href="./support.php" class="sidebar-link"
            ><i class="fa-solid fa-headset"></i> <span>Support</span></a
          >
          <a href="./logout.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Logout</span></a
          >
        </nav>
      </aside>

      <!-- MAIN CONTENT -->
      <main class="main-content">
        <!-- FIXED TOP NAVBAR -->
        <div class="top-bar top-bar--main">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <a href="./userprofile.php" class="user-profile">
              <div class="user-avatar"><?php echo h($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo h($ub['full_name']); ?></h3>
              </div>
            </a>
          </div>
          <div class="header-right">
            <div class="icon-button" id="notifications-btn">
              <i class="fa-solid fa-bell"></i>
              <span class="notification-badge"></span>
            </div>
            <div class="theme-toggle" id="theme-toggle"></div>
          </div>
        </div>

        <div class="dashboard-content">
          <div class="settings-container">
            <div class="page-header" data-aos="fade-up">
              <h1><i class="fa-solid fa-sliders-h"></i> Account Settings</h1>
              <p style="color: var(--text-muted); margin-top: 8px">
                Manage your account preferences and personal information
              </p>
            </div>

            <div class="settings-grid" data-aos="fade-up">
              <!-- Profile Settings -->
              <div class="settings-card">
                <div class="card-header">
                  <i class="fa-solid fa-user-circle"></i>
                  <h3>Profile Information</h3>
                </div>
                <div class="form-group">
                  <label>Display Name</label>
                  <input
                    type="text"
                    id="displayName"
                    value="<?php echo h($userFullName); ?>"
                    placeholder="Your display name"
                  />
                </div>
                <div class="form-group">
                  <label>Email Address</label>
                  <input
                    type="email"
                    id="email"
                    value="<?php echo h($userEmail); ?>"
                    placeholder="Email address"
                  />
                </div>
                <div class="form-group">
                  <label>Phone Number</label>
                  <input
                    type="tel"
                    id="phone"
                    value="<?php echo h($userPhone); ?>"
                    placeholder="Phone number"
                  />
                </div>
                <button class="save-btn" id="saveProfileBtn">
                  Save Profile Changes
                </button>
              </div>

              <!-- Notification Settings -->
              <div class="settings-card">
                <div class="card-header">
                  <i class="fa-solid fa-bell"></i>
                  <h3>Notifications</h3>
                </div>
                <div class="toggle-switch">
                  <div>
                    <div class="toggle-label">Email Notifications</div>
                    <div class="toggle-desc">
                      Receive email updates about your account
                    </div>
                  </div>
                  <label class="toggle">
                    <input type="checkbox" id="emailNotifications" <?php echo !empty($settingsPayload['emailNotifications']) ? 'checked' : ''; ?> />
                    <span class="slider"></span>
                  </label>
                </div>
                <div class="toggle-switch">
                  <div>
                    <div class="toggle-label">Push Notifications</div>
                    <div class="toggle-desc">
                      Get real-time alerts on your device
                    </div>
                  </div>
                  <label class="toggle">
                    <input type="checkbox" id="pushNotifications" <?php echo !empty($settingsPayload['pushNotifications']) ? 'checked' : ''; ?> />
                    <span class="slider"></span>
                  </label>
                </div>
                <div class="toggle-switch">
                  <div>
                    <div class="toggle-label">Transaction Alerts</div>
                    <div class="toggle-desc">
                      Notify when deposits/withdrawals occur
                    </div>
                  </div>
                  <label class="toggle">
                    <input type="checkbox" id="transactionAlerts" <?php echo !empty($settingsPayload['transactionAlerts']) ? 'checked' : ''; ?> />
                    <span class="slider"></span>
                  </label>
                </div>
                <button class="save-btn" id="saveNotificationsBtn">
                  Save Notification Settings
                </button>
              </div>

              <!-- Preferences -->
              <div class="settings-card">
                <div class="card-header">
                  <i class="fa-solid fa-globe"></i>
                  <h3>Preferences</h3>
                </div>
                <div class="form-group">
                  <label>Language</label>
                  <select id="language">
                    <?php
                    $lang = (string)($settingsPayload['language'] ?? 'en');
                    foreach (['en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'zh' => '中文'] as $code => $label) {
                      $sel = ($lang === $code) ? ' selected' : '';
                      echo '<option value="' . h($code) . '"' . $sel . '>' . h($label) . '</option>';
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Time Zone</label>
                  <select id="timezone">
                    <?php
                    $tz = (string)($settingsPayload['timezone'] ?? 'UTC');
                    foreach (['UTC' => 'UTC (Universal Time)', 'EST' => 'EST (Eastern Time)', 'CST' => 'CST (Central Time)', 'PST' => 'PST (Pacific Time)', 'GMT' => 'GMT (London)'] as $code => $label) {
                      $sel = ($tz === $code) ? ' selected' : '';
                      echo '<option value="' . h($code) . '"' . $sel . '>' . h($label) . '</option>';
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Default Currency</label>
                  <div class="currency-options">
                    <?php
                    $cur = strtoupper((string)($settingsPayload['currency'] ?? 'USD'));
                    foreach (['USD', 'EUR', 'GBP', 'JPY', 'CAD'] as $c) {
                      $active = ($cur === $c) ? ' active' : '';
                      echo '<div class="currency-option' . $active . '" data-currency="' . h($c) . '">' . h($c) . '</div>';
                    }
                    ?>
                  </div>
                </div>
                <button class="save-btn" id="savePreferencesBtn">
                  Save Preferences
                </button>
              </div>

              <!-- Display Settings -->
              <div class="settings-card">
                <div class="card-header">
                  <i class="fa-solid fa-palette"></i>
                  <h3>Display</h3>
                </div>
                <div class="toggle-switch">
                  <div>
                    <div class="toggle-label">Compact View</div>
                    <div class="toggle-desc">Show more items per page</div>
                  </div>
                  <label class="toggle">
                    <input type="checkbox" id="compactView" <?php echo !empty($settingsPayload['compactView']) ? 'checked' : ''; ?> />
                    <span class="slider"></span>
                  </label>
                </div>
                <div class="toggle-switch">
                  <div>
                    <div class="toggle-label">Animated Charts</div>
                    <div class="toggle-desc">
                      Enable chart animations for better visualization
                    </div>
                  </div>
                  <label class="toggle">
                    <input type="checkbox" id="animatedCharts" <?php echo !empty($settingsPayload['animatedCharts']) ? 'checked' : ''; ?> />
                    <span class="slider"></span>
                  </label>
                </div>
                <div class="form-group">
                  <label>Price Update Frequency</label>
                  <select id="updateFrequency">
                    <?php
                    $uf = (string)($settingsPayload['updateFrequency'] ?? 'realtime');
                    foreach (['realtime' => 'Real-time', '5s' => 'Every 5 seconds', '10s' => 'Every 10 seconds', '30s' => 'Every 30 seconds'] as $code => $label) {
                      $sel = ($uf === $code) ? ' selected' : '';
                      echo '<option value="' . h($code) . '"' . $sel . '>' . h($label) . '</option>';
                    }
                    ?>
                  </select>
                </div>
                <button class="save-btn" id="saveDisplayBtn">Save Display</button>
              </div>

              <div class="settings-card">
                <div class="card-header">
                  <i class="fa-solid fa-user-shield"></i>
                  <h3>Account</h3>
                </div>
                <button class="save-btn" id="exportDataBtn" type="button">Export Data</button>
                <button class="save-btn" id="deactivateAccountBtn" type="button">Deactivate Account</button>
                <button class="save-btn" id="deleteAccountBtn" type="button">Delete Account</button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <div id="settingsModal" class="modal" style="display: none">
      <div class="modal-content">
        <i id="modalIcon" class="fa-solid fa-circle-info"></i>
        <h3 id="modalTitle">Confirm</h3>
        <p id="modalMessage"></p>
        <div id="modalExtra"></div>
        <div class="modal-buttons">
          <button class="modal-btn cancel" id="modalCancelBtn" type="button">Cancel</button>
          <button class="modal-btn confirm" id="modalConfirmBtn" type="button">Confirm</button>
        </div>
      </div>
    </div>

    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>

      const API_URL = "./api/settings_api.php";
      let csrfToken =
        (window.__SETTINGS_BOOT && window.__SETTINGS_BOOT.csrf) || "";

      function applyBootToForm(boot) {
        if (!boot || !boot.settings) return;
        const u = boot.user || {};
        const s = boot.settings;
        const dn = document.getElementById("displayName");
        const em = document.getElementById("email");
        const ph = document.getElementById("phone");
        if (dn) dn.value = u.full_name || "";
        if (em) em.value = u.email || "";
        if (ph) ph.value = u.phone || "";
        const en = document.getElementById("emailNotifications");
        const pn = document.getElementById("pushNotifications");
        const ta = document.getElementById("transactionAlerts");
        if (en) en.checked = !!s.emailNotifications;
        if (pn) pn.checked = !!s.pushNotifications;
        if (ta) ta.checked = !!s.transactionAlerts;
        const lang = document.getElementById("language");
        const tz = document.getElementById("timezone");
        const uf = document.getElementById("updateFrequency");
        if (lang) lang.value = s.language || "en";
        if (tz) tz.value = s.timezone || "UTC";
        if (uf) uf.value = s.updateFrequency || "realtime";
        const cv = document.getElementById("compactView");
        const ac = document.getElementById("animatedCharts");
        if (cv) cv.checked = !!s.compactView;
        if (ac) ac.checked = s.animatedCharts !== false;
        const cur = s.currency || "USD";
        document.querySelectorAll(".currency-option").forEach((opt) => {
          opt.classList.toggle(
            "active",
            opt.getAttribute("data-currency") === cur,
          );
        });
      }

      async function postSettings(body) {
        const payload = { ...body, csrf_token: csrfToken };
        const res = await fetch(API_URL, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          const err = data.error || "Request failed.";
          throw new Error(err);
        }
        if (data.csrf) csrfToken = data.csrf;
        return data;
      }

      function showNotification(message, type = "success") {
        const notif = document.createElement("div");
        notif.className = "notification-popup show";
        notif.style.position = "fixed";
        notif.style.top = "90px";
        notif.style.right = "24px";
        notif.style.background = "var(--surface)";
        notif.style.padding = "12px 20px";
        notif.style.borderRadius = "40px";
        const borderColor =
          type === "success"
            ? "var(--primary-color)"
            : type === "error"
              ? "var(--negative)"
              : "var(--warning-color)";
        notif.style.borderLeft = `4px solid ${borderColor}`;
        notif.style.zIndex = "200";
        notif.style.boxShadow = "var(--shadow)";
        const icon =
          type === "success"
            ? "fa-check-circle"
            : type === "error"
              ? "fa-circle-exclamation"
              : "fa-info-circle";
        notif.innerHTML = `<i class="fa-solid ${icon}"></i> ${message}`;
        document.body.appendChild(notif);
        setTimeout(() => notif.remove(), 3500);
      }

      // Currency selector
      const currencyOptions = document.querySelectorAll(".currency-option");
      currencyOptions.forEach((opt) => {
        opt.addEventListener("click", () => {
          currencyOptions.forEach((o) => o.classList.remove("active"));
          opt.classList.add("active");
        });
      });

      // Save buttons
      document
        .getElementById("saveProfileBtn")
        .addEventListener("click", async () => {
          const btn = document.getElementById("saveProfileBtn");
          btn.disabled = true;
          try {
            await postSettings({
              action: "save_profile",
              full_name: document.getElementById("displayName").value,
              email: document.getElementById("email").value,
              phone: document.getElementById("phone").value,
            });
            showNotification(
              "Profile information saved successfully!",
              "success",
            );
          } catch (e) {
            showNotification(e.message || "Could not save profile.", "error");
          } finally {
            btn.disabled = false;
          }
        });

      document
        .getElementById("saveNotificationsBtn")
        .addEventListener("click", async () => {
          const btn = document.getElementById("saveNotificationsBtn");
          btn.disabled = true;
          try {
            await postSettings({
              action: "save_notifications",
              emailNotifications:
                document.getElementById("emailNotifications").checked,
              pushNotifications:
                document.getElementById("pushNotifications").checked,
              transactionAlerts:
                document.getElementById("transactionAlerts").checked,
            });
            showNotification("Notification settings saved!", "success");
          } catch (e) {
            showNotification(e.message || "Could not save.", "error");
          } finally {
            btn.disabled = false;
          }
        });

      document
        .getElementById("savePreferencesBtn")
        .addEventListener("click", async () => {
          const btn = document.getElementById("savePreferencesBtn");
          btn.disabled = true;
          try {
            const cur =
              document
                .querySelector(".currency-option.active")
                ?.getAttribute("data-currency") || "USD";
            await postSettings({
              action: "save_preferences",
              language: document.getElementById("language").value,
              timezone: document.getElementById("timezone").value,
              currency: cur,
            });
            showNotification("Preferences saved!", "success");
          } catch (e) {
            showNotification(e.message || "Could not save.", "error");
          } finally {
            btn.disabled = false;
          }
        });

      document
        .getElementById("saveDisplayBtn")
        .addEventListener("click", async () => {
          const btn = document.getElementById("saveDisplayBtn");
          btn.disabled = true;
          try {
            await postSettings({
              action: "save_display",
              compactView: document.getElementById("compactView").checked,
              animatedCharts: document.getElementById("animatedCharts").checked,
              updateFrequency: document.getElementById("updateFrequency").value,
            });
            showNotification("Display settings saved!", "success");
          } catch (e) {
            showNotification(e.message || "Could not save.", "error");
          } finally {
            btn.disabled = false;
          }
        });

      // Modal handling
      const modal = document.getElementById("settingsModal");
      const modalIcon = document.getElementById("modalIcon");
      const modalTitle = document.getElementById("modalTitle");
      const modalMessage = document.getElementById("modalMessage");
      const modalExtra = document.getElementById("modalExtra");
      const modalConfirm = document.getElementById("modalConfirmBtn");
      const modalCancel = document.getElementById("modalCancelBtn");
      let currentAction = null;

      function closeModal() {
        modal.style.display = "none";
        currentAction = null;
        modalExtra.innerHTML = "";
      }

      modalCancel.addEventListener("click", closeModal);
      window.addEventListener("click", (e) => {
        if (e.target === modal) closeModal();
      });

      // Deactivate Account
      document
        .getElementById("deactivateAccountBtn")
        .addEventListener("click", () => {
          modalIcon.className = "fa-solid fa-user-slash";
          modalIcon.style.color = "var(--warning-color)";
          modalTitle.innerText = "Deactivate Account";
          modalMessage.innerText =
            "Are you sure you want to deactivate your account? You can reactivate anytime by logging in.";
          modalExtra.innerHTML =
            '<input type="password" id="confirmPassword" placeholder="Enter your password">';
          currentAction = "deactivate";
          modal.style.display = "flex";
        });

      // Export Data
      document.getElementById("exportDataBtn").addEventListener("click", async () => {
        try {
          const res = await fetch(API_URL, {
            method: "GET",
            credentials: "same-origin",
          });
          const data = await res.json();
          if (!data.ok) throw new Error(data.error || "Could not load data.");
          const exportData = {
            user: data.user,
            settings: data.settings,
            exportDate: new Date().toISOString(),
          };
          const dataStr = JSON.stringify(exportData, null, 2);
          const blob = new Blob([dataStr], { type: "application/json" });
          const url = URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = `crypterra_data_${Date.now()}.json`;
          a.click();
          URL.revokeObjectURL(url);
          showNotification(
            "Your data has been exported successfully!",
            "success",
          );
        } catch (e) {
          showNotification(e.message || "Export failed.", "error");
        }
      });

      // Delete Account
      document
        .getElementById("deleteAccountBtn")
        .addEventListener("click", () => {
          modalIcon.className = "fa-solid fa-trash-can";
          modalIcon.style.color = "var(--negative)";
          modalTitle.innerText = "Delete Account Permanently";
          modalMessage.innerText =
            "This action cannot be undone. All your data, funds, and transaction history will be permanently deleted.";
          modalExtra.innerHTML = `
      <input type="text" id="confirmText" placeholder='Type "DELETE" to confirm'>
      <input type="password" id="deletePassword" placeholder="Enter your password">
    `;
          currentAction = "delete";
          modal.style.display = "flex";
        });

      // Modal Confirm
      modalConfirm.addEventListener("click", async () => {
        if (currentAction === "deactivate") {
          const password = document.getElementById("confirmPassword")?.value;
          if (!password) {
            showNotification("Please enter your password to confirm", "error");
            return;
          }
          modalConfirm.disabled = true;
          try {
            const data = await postSettings({
              action: "deactivate_account",
              password,
            });
            showNotification(data.message || "Account deactivated.", "success");
            closeModal();
            if (data.redirect) {
              window.location.href = data.redirect;
            }
          } catch (e) {
            showNotification(e.message || "Could not deactivate.", "error");
          } finally {
            modalConfirm.disabled = false;
          }
        } else if (currentAction === "delete") {
          const confirmText = document.getElementById("confirmText")?.value;
          const password = document.getElementById("deletePassword")?.value;
          if (confirmText !== "DELETE" || !password) {
            showNotification(
              'Please type "DELETE" and enter your password to confirm',
              "error",
            );
            return;
          }
          modalConfirm.disabled = true;
          try {
            const data = await postSettings({
              action: "delete_account",
              password,
              confirm_text: confirmText,
            });
            showNotification(data.message || "Account deleted.", "success");
            closeModal();
            if (data.redirect) {
              window.location.href = data.redirect;
            }
          } catch (e) {
            showNotification(e.message || "Could not delete account.", "error");
          } finally {
            modalConfirm.disabled = false;
          }
        }
      });

      // Sidebar functionality
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      const mobileBtn = document.getElementById("mobileMenuBtn");

      collapseBtn.addEventListener("click", () =>
        sidebar.classList.toggle("collapsed"),
      );
      mobileBtn.addEventListener("click", () =>
        sidebar.classList.toggle("mobile-open"),
      );

      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 768 &&
          !sidebar.contains(e.target) &&
          !mobileBtn.contains(e.target) &&
          sidebar.classList.contains("mobile-open")
        ) {
          sidebar.classList.remove("mobile-open");
        }
      });

      // Scroll to top
      const scrollBtn = document.getElementById("scrollTop");
      window.addEventListener("scroll", () => {
        if (window.scrollY > 400) scrollBtn.style.display = "flex";
        else scrollBtn.style.display = "none";
      });
      scrollBtn.addEventListener("click", () =>
        window.scrollTo({ top: 0, behavior: "smooth" }),
      );

      // Notification bell
      const notifBtn = document.getElementById("notifications-btn");
      notifBtn.addEventListener("click", () => {
        showNotification(
          "⚙️ Customize your experience by adjusting your settings!",
          "info",
        );
      });

      // Apply server boot (refreshes form if __SETTINGS_BOOT was updated)
      if (window.__SETTINGS_BOOT) {
        applyBootToForm(window.__SETTINGS_BOOT);
      }
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
