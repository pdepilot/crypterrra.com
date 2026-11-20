<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
$ADMIN_PAGE_CSRF = admin_csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Admin · Market Control</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    />
    <!-- Google Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <!-- AOS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
      /* === EXACT STYLES FROM ADMIN DASHBOARD === */
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

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
      }

      body {
        font-family: "Inter", sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.5;
        overflow-x: hidden;
      }

      .admin {
        display: flex;
        min-height: 100vh;
      }

      /* ========== SIDEBAR ========== */
      .sidebar {
        width: 280px;
        background: rgba(10, 20, 35, 0.9);
        backdrop-filter: blur(20px) saturate(200%);
        -webkit-backdrop-filter: blur(20px) saturate(200%);
        border-right: 1px solid var(--glass-edge);
        padding: 1.8rem 1.2rem;
        transition:
          width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1),
          left 0.3s;
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        scrollbar-width: none;
        z-index: 100;
        box-shadow: 10px 0 40px -15px rgba(0, 0, 0, 0.8);
      }
      .sidebar::-webkit-scrollbar {
        display: none;
      }
      .sidebar.collapsed {
        width: 95px;
      }
      .sidebar.collapsed .sidebar-link span,
      .sidebar.collapsed .sidebar-header span,
      .sidebar.collapsed .admin-info {
        display: none;
      }

      @media (max-width: 1100px) {
        .sidebar {
          position: fixed;
          left: -300px;
        }
        .sidebar.mobile-open {
          left: 0;
        }
      }

      .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2.2rem;
        padding: 0 0.5rem;
      }
      .logo {
        display: flex;
        align-items: center;
        gap: 0.8rem;
      }
      .logo-icon {
        width: 42px;
        height: 42px;
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        box-shadow: 0 0 30px var(--primary-glow);
      }
      .logo span {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .collapse-btn {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 50%;
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--primary);
        transition: 0.3s;
      }
      .collapse-btn:hover {
        background: var(--primary);
        color: white;
        transform: rotate(180deg);
        box-shadow: var(--shadow-glow);
      }

      .admin-info {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
      }
      .admin-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        font-weight: 600;
        border: 2px solid var(--primary);
        box-shadow: 0 0 20px var(--primary-glow);
      }
      .admin-details h4 {
        font-size: 0.95rem;
      }
      .admin-details p {
        font-size: 0.7rem;
        color: var(--text-muted);
      }

      .sidebar-nav {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
      }
      .nav-section {
        margin: 1rem 0 0.3rem;
        padding-left: 1rem;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
      }
      .sidebar.collapsed .nav-section {
        display: none;
      }

      .sidebar-link {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        padding: 0.8rem 1.2rem;
        border-radius: 40px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: 0.3s;
        white-space: nowrap;
        position: relative;
        overflow: hidden;
      }
      .sidebar-link i {
        width: 24px;
        font-size: 1.2rem;
        text-align: center;
      }
      .sidebar-link::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 0;
        background: linear-gradient(90deg, var(--primary-soft), transparent);
        transition: width 0.3s;
        z-index: -1;
      }
      .sidebar-link:hover::before,
      .sidebar-link.active::before {
        width: 100%;
      }
      .sidebar-link:hover,
      .sidebar-link.active {
        color: var(--primary);
        transform: translateX(5px);
        box-shadow: 0 0 20px var(--primary-glow);
      }
      .sidebar-link.active {
        background: var(--primary-soft);
        border: 1px solid var(--glass-edge);
      }

      /* ========== MAIN CONTENT ========== */
      .main {
        flex: 1;
        background: var(--bg-secondary);
        width: 100%;
        overflow-x: hidden;
      }

      .top-bar {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-edge);
        padding: 0.8rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.8rem;
        position: sticky;
        top: 0;
        z-index: 90;
      }
      .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
      }
      .mobile-menu-btn {
        display: none;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 50%;
        width: 42px;
        height: 42px;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        cursor: pointer;
        color: var(--primary);
        flex-shrink: 0;
      }
      .search-box {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .search-box i {
        color: var(--text-muted);
      }
      .search-box input {
        background: transparent;
        border: none;
        color: var(--text);
        outline: none;
        font-size: 0.9rem;
        width: 200px;
      }
      .header-right {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
      }
      .system-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
      }
      .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--success);
        box-shadow: 0 0 15px #10b981;
        animation: pulse 2s infinite;
      }
      @keyframes pulse {
        0% {
          opacity: 0.8;
        }
        50% {
          opacity: 1;
        }
        100% {
          opacity: 0.8;
        }
      }
      .icon-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        cursor: pointer;
        transition: 0.2s;
        position: relative;
        flex-shrink: 0;
      }
      .icon-btn:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }
      .badge {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 8px;
        height: 8px;
        background: var(--danger);
        border-radius: 50%;
        border: 2px solid var(--glass-bg);
      }
      .theme-toggle {
        width: 52px;
        height: 26px;
        background: #3a4a5a;
        border-radius: 34px;
        position: relative;
        cursor: pointer;
        border: 1px solid var(--glass-edge);
        flex-shrink: 0;
      }
      .theme-toggle::before {
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
      body.dark .theme-toggle {
        background: var(--primary);
      }
      body.dark .theme-toggle::before {
        transform: translateX(26px);
      }

      .content {
        padding: 1.5rem;
      }

      /* Page Header */
      .page-header {
        margin-bottom: 1.8rem;
      }
      .page-header h1 {
        font-size: 2rem;
        background: linear-gradient(135deg, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: inline-block;
      }
      .page-header p {
        color: var(--text-secondary);
      }
      .breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 5px;
      }
      .breadcrumb a {
        color: var(--text-secondary);
        text-decoration: none;
      }
      .breadcrumb a:hover {
        color: var(--primary);
      }

      /* Analytics Cards */
      .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.8rem;
      }

      .analytics-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1.2rem;
        transition: 0.3s;
        position: relative;
        overflow: hidden;
      }

      .analytics-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
        transform: translateY(-3px);
      }

      .analytics-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
      }

      .analytics-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
      }

      .analytics-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
      }

      .analytics-label {
        color: var(--text-muted);
        font-size: 0.85rem;
      }

      .trend-up {
        color: var(--success);
        font-size: 0.8rem;
        background: rgba(16, 185, 129, 0.1);
        padding: 0.2rem 0.5rem;
        border-radius: 40px;
      }

      .trend-down {
        color: var(--danger);
        font-size: 0.8rem;
        background: rgba(239, 68, 68, 0.1);
        padding: 0.2rem 0.5rem;
        border-radius: 40px;
      }

      .mini-chart {
        height: 40px;
        width: 100%;
        margin-top: 0.5rem;
      }

      /* Market Alert Panel */
      .market-alerts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
      }

      .alert-card {
        background: rgba(239, 68, 68, 0.05);
        border: 1px solid var(--danger);
        border-radius: 30px;
        padding: 1.2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .alert-card.warning {
        border-color: var(--orange);
        background: rgba(249, 115, 22, 0.05);
      }

      .alert-card.info {
        border-color: var(--info);
        background: rgba(59, 130, 246, 0.05);
      }

      .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
      }

      .alert-icon.critical {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .alert-icon.warning {
        background: rgba(249, 115, 22, 0.2);
        color: var(--orange);
      }

      .alert-icon.info {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }

      /* Add Trading Pair Form */
      .add-pair-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .add-pair-card h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
      }

      /* Fund trading account (spot) — mirrors admin-wallet-control fund card */
      .fund-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
        transition: 0.3s;
      }

      .fund-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
      }

      .fund-card h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
      }

      .fund-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .fund-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .fund-field label {
        font-size: 0.85rem;
        color: var(--text-muted);
      }

      .fund-field input,
      .fund-field select,
      .fund-field textarea {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 1rem;
        color: var(--text);
        font-size: 0.95rem;
        outline: none;
        transition: 0.2s;
      }

      .fund-field input:focus,
      .fund-field select:focus,
      .fund-field textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-soft);
      }

      .fund-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
      }

      .suggestions-box {
        position: absolute;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        margin-top: 5px;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
      }

      .suggestion-item {
        padding: 0.8rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid var(--glass-edge);
      }

      .suggestion-item:hover {
        background: var(--primary-soft);
      }

      .suggestion-item:last-child {
        border-bottom: none;
      }

      .validation-feedback {
        font-size: 0.8rem;
        margin-top: 0.3rem;
        display: none;
      }

      .validation-feedback.valid {
        color: var(--success);
        display: block;
      }

      .validation-feedback.invalid {
        color: var(--danger);
        display: block;
      }

      .pair-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .pair-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .pair-field label {
        font-size: 0.85rem;
        color: var(--text-muted);
      }

      .pair-field input,
      .pair-field select {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 1rem;
        color: var(--text);
        font-size: 0.95rem;
        outline: none;
        transition: 0.2s;
      }

      .pair-field input:focus,
      .pair-field select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-soft);
      }

      /* Price Control Panel */
      .price-control-panel {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.8rem;
      }

      @media (max-width: 768px) {
        .price-control-panel {
          grid-template-columns: 1fr;
        }
      }

      .price-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .price-card h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
      }

      .price-field {
        margin-bottom: 1rem;
      }

      .price-field label {
        display: block;
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 0.3rem;
      }

      .price-field input,
      .price-field select {
        width: 100%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 1rem;
        color: var(--text);
      }

      .price-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
      }

      @media (max-width: 480px) {
        .price-actions {
          flex-direction: column;
        }
        .price-actions button {
          width: 100%;
        }
      }

      /* Market Status Control */
      .market-status {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
      }

      .status-badge-large {
        padding: 0.5rem 1.5rem;
        border-radius: 40px;
        font-size: 1rem;
        font-weight: 600;
      }

      .status-badge-large.active {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .status-badge-large.paused {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .market-toggle-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
      }

      @media (max-width: 480px) {
        .market-toggle-buttons {
          flex-direction: column;
          width: 100%;
        }
        .market-toggle-buttons button {
          width: 100%;
        }
      }

      /* Fee Control Section */
      .fee-control {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .fee-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
      }

      .fee-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.8rem;
        background: var(--glass-bg);
        border-radius: 40px;
      }

      .fee-item span:first-child {
        color: var(--text-muted);
      }

      .fee-item span:last-child {
        font-weight: 600;
        color: var(--primary);
      }

      .fee-input-group {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
      }

      .fee-input-group input {
        width: 80px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.4rem 0.8rem;
        color: var(--text);
      }

      @media (max-width: 480px) {
        .fee-input-group {
          flex-direction: column;
          align-items: flex-start;
        }
        .fee-input-group input {
          width: 100%;
        }
        .fee-input-group button {
          width: 100%;
        }
      }

      /* Control Bar */
      .control-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 0.8rem 1.5rem;
      }

      .control-search {
        flex: 2;
        min-width: 250px;
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--glass-bg);
        border-radius: 40px;
        padding: 0.5rem 1rem;
      }

      .control-search i {
        color: var(--text-muted);
      }

      .control-search input {
        background: transparent;
        border: none;
        color: var(--text);
        outline: none;
        width: 100%;
      }

      .control-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
      }

      .filter-select {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.5rem 2rem 0.5rem 1rem;
        color: var(--text);
        font-size: 0.9rem;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2322c55e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
      }

      .btn-primary {
        background: var(--primary);
        border: none;
        border-radius: 40px;
        padding: 0.6rem 1.5rem;
        color: white;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
      }

      .btn-primary:hover {
        background: var(--primary-dark);
        transform: scale(1.02);
        box-shadow: var(--shadow-glow);
      }

      .btn-success {
        background: var(--success);
        border: none;
        border-radius: 40px;
        padding: 0.6rem 1.5rem;
        color: white;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
      }

      .btn-success:hover {
        background: #0f9e6a;
        box-shadow: 0 0 30px rgba(16, 185, 129, 0.5);
      }

      .btn-danger {
        background: var(--danger);
        border: none;
        border-radius: 40px;
        padding: 0.6rem 1.5rem;
        color: white;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
      }

      .btn-danger:hover {
        background: #dc2626;
        box-shadow: 0 0 30px rgba(239, 68, 68, 0.5);
      }

      .btn-outline {
        background: transparent;
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.5rem 1rem;
        color: var(--text);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
      }

      .btn-outline:hover {
        background: var(--primary-soft);
        border-color: var(--primary);
        color: var(--primary);
      }

      /* Table */
      .table-wrapper {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.2rem;
        overflow-x: auto;
        margin: 1.5rem 0;
        max-height: 600px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary) var(--surface);
      }

      .table-wrapper::-webkit-scrollbar {
        width: 6px;
        height: 6px;
      }

      .table-wrapper::-webkit-scrollbar-track {
        background: var(--surface);
        border-radius: 10px;
      }

      .table-wrapper::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 10px;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1200px;
      }

      th {
        position: sticky;
        top: 0;
        background: var(--surface-light);
        z-index: 10;
        padding: 1.2rem 0.8rem;
        color: var(--primary);
        border-bottom: 2px solid var(--glass-edge);
        font-weight: 600;
        backdrop-filter: blur(10px);
        text-align: left;
      }

      td {
        padding: 1rem 0.8rem;
        border-bottom: 1px solid var(--glass-edge);
        text-align: left;
      }

      tr {
        transition: 0.2s;
      }

      tr:hover {
        background: var(--primary-soft);
      }

      /* Status Badges */
      .status-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .status-active {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .status-paused {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      /* Change indicators */
      .change-positive {
        color: var(--success);
      }

      .change-negative {
        color: var(--danger);
      }

      /* Actions Dropdown */
      .actions-dropdown {
        position: relative;
        display: inline-block;
      }

      .actions-btn {
        background: transparent;
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 0.4rem 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
      }

      .actions-btn:hover {
        background: var(--primary);
        color: white;
      }

      .dropdown-menu {
        position: absolute;
        right: 0;
        top: 110%;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 0.5rem 0;
        min-width: 180px;
        z-index: 50;
        display: none;
        box-shadow: var(--shadow-glow);
      }

      .dropdown-menu.show {
        display: block;
      }

      .dropdown-item {
        padding: 0.6rem 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: 0.2s;
        cursor: pointer;
      }

      .dropdown-item:hover {
        background: var(--primary-soft);
        color: var(--primary);
      }

      /* Processing Overlay */
      .processing-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
      }

      .processing-card {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 50px;
        padding: 2.5rem;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: var(--shadow-glow);
      }

      .processing-spinner {
        width: 60px;
        height: 60px;
        border: 3px solid var(--primary-soft);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1.5rem;
      }

      @keyframes spin {
        to {
          transform: rotate(360deg);
        }
      }

      .processing-step {
        margin: 1rem 0;
        padding: 0.8rem;
        border-radius: 40px;
        background: var(--glass-bg);
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .step-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
      }

      .step-icon.completed {
        background: var(--success);
        color: white;
      }

      /* Pagination */
      .pagination {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 1rem;
        flex-wrap: wrap;
      }

      .page-btn {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 0.5rem 1rem;
        cursor: pointer;
      }

      .page-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
      }

      /* Feed Panel */
      .feed-panel {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .feed-panel h3 {
        margin-bottom: 1.2rem;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .feed-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.8rem 0;
        border-bottom: 1px solid var(--glass-edge);
        animation: slideIn 0.3s;
        flex-wrap: wrap;
      }

      .feed-item:last-child {
        border-bottom: none;
      }

      @keyframes slideIn {
        from {
          opacity: 0;
          transform: translateX(-10px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }

      .feed-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .feed-icon.price {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary);
      }

      .feed-icon.pause {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .feed-icon.add {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }

      /* Two column layout */
      .two-column {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-top: 1.5rem;
      }

      @media (max-width: 768px) {
        .two-column {
          grid-template-columns: 1fr;
        }
      }

      /* Chart container */
      .chart-container {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
      }

      .chart-wrapper {
        height: 250px;
        position: relative;
      }

      /* Responsive */
      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
        }
        .control-bar {
          border-radius: 30px;
        }
      }

      @media (max-width: 768px) {
        .content {
          padding: 1rem;
        }
        .control-bar {
          flex-direction: column;
          align-items: stretch;
        }
        .analytics-value {
          font-size: 1.5rem;
        }
        .pair-grid {
          grid-template-columns: 1fr;
        }
      }

      @media (max-width: 480px) {
        .top-bar {
          padding: 0.6rem 1rem;
        }
        .search-box input {
          width: 140px;
        }
        .header-right .system-status span:last-child {
          display: none;
        }
        .control-filters {
          flex-direction: column;
          width: 100%;
        }
        .filter-select {
          width: 100%;
        }
        .control-filters button {
          width: 100%;
        }
      }

      /* AI Assistant */
      .ai-assistant {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        box-shadow: var(--shadow-glow);
        z-index: 99;
        transition: 0.3s;
      }

      .ai-assistant:hover {
        transform: scale(1.1);
      }

      /* Toggle Switch */
      .market-toggle {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
      }

      .market-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
      }

      .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--danger);
        transition: 0.3s;
        border-radius: 34px;
      }

      .toggle-slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
      }

      input:checked + .toggle-slider {
        background-color: var(--success);
      }

      input:checked + .toggle-slider:before {
        transform: translateX(30px);
      }

      /* Result modal (replaces alert()) */
      .admin-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.68);
        backdrop-filter: blur(6px);
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
      }
      .admin-modal-overlay.is-open {
        display: flex;
      }
      .admin-modal-card {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 1.5rem 1.5rem 1.25rem;
        max-width: 440px;
        width: 100%;
        box-shadow: var(--shadow);
      }
      .admin-modal-card.modal-success {
        border-color: rgba(16, 185, 129, 0.55);
      }
      .admin-modal-card.modal-error {
        border-color: rgba(239, 68, 68, 0.55);
      }
      .admin-modal-card.modal-info {
        border-color: rgba(59, 130, 246, 0.45);
      }
      .admin-modal-body {
        white-space: pre-wrap;
        color: var(--text-secondary);
        font-size: 0.95rem;
        line-height: 1.55;
        margin-top: 0.65rem;
      }
    </style>
  </head>
  <body class="dark">
    <div class="admin">
      <!-- SIDEBAR (active on Market Control) -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="logo">
            <div class="logo-icon"><i class="fa-solid fa-globe"></i></div>
            <span>Crypterra</span>
          </div>
          <div class="collapse-btn" id="collapseSidebar">
            <i class="fa-solid fa-chevron-left"></i>
          </div>
        </div>
        <div class="admin-info">
          <div class="admin-avatar">SA</div>
          <div class="admin-details">
            <h4>Super Admin</h4>
            <p>sysadmin@crypterra.io</p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./admin-dashboard.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="./admin-users.php" class="sidebar-link"
            ><i class="fa-solid fa-users"></i> <span>Users</span></a
          >
          <a href="./admin-send.php" class="sidebar-link"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send Mgmt</span></a
          >
          <a href="./admin-receive.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Receive Mgmt</span></a
          >
          <a href="./admin-transactions.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-right-arrow-left"></i>
            <span>Transactions</span></a
          >
          <a href="./admin-contact.php" class="sidebar-link"
            ><i class="fa-solid fa-envelope"></i>
            <span>Contact Mgmt</span></a
          >
          <a href="./admin-help-center.php" class="sidebar-link"
            ><i class="fa-solid fa-life-ring"></i>
            <span>Help Center</span></a
          >
                     <a href="./admin-deposit.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Deposits</span></a
          >
          <a href="./admin-withdrawal.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdrawals</span></a
          >
          <a href="./admin-kyc.php" class="sidebar-link"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Management</span></a
          >
          <a href="./admin-wallet-control.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet Control</span></a
          >
          <a href="./admin-market-control.php" class="sidebar-link active"
            ><i class="fa-solid fa-chart-line"></i>
            <span>Market Control</span></a
          >
          <a href="./admin-security-center.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Security Center</span></a
          >
          <a href="./admin-analytics.php" class="sidebar-link"
            ><i class="fa-solid fa-magnifying-glass-chart"></i>
            <span>Analytics</span></a
          >
          <a href="./admin-notifications.php" class="sidebar-link"
            ><i class="fa-solid fa-bell"></i> <span>Notifications</span></a
          >
          <a href="./admin-settings.php" class="sidebar-link"
            ><i class="fa-solid fa-gear"></i> <span>Settings</span></a
          >
          <div style="margin-top: 2rem"></div>
          <a href="./admin-logout.php" class="sidebar-link"
            ><i class="fa-solid fa-sign-out-alt"></i> <span>Logout</span></a
          >
        </nav>
      </aside>

      <!-- MAIN CONTENT -->
      <main class="main">
        <!-- TOP BAR -->
        <div class="top-bar">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <div class="search-box">
              <i class="fa-solid fa-search"></i>
              <input type="text" placeholder="Search admin..." />
            </div>
          </div>
          <div class="header-right">
            <div class="system-status">
              <span class="status-dot"></span>
              <span>System Online</span>
            </div>
            <div class="icon-btn">
              <i class="fa-regular fa-bell"></i>
              <span class="badge"></span>
            </div>
            <div class="icon-btn"><i class="fa-regular fa-envelope"></i></div>
            <div class="theme-toggle" id="themeToggle"></div>
            <div
              class="icon-btn"
              style="
                width: auto;
                padding: 0 12px;
                border-radius: 40px;
                gap: 6px;
              "
            >
              <i class="fa-regular fa-user"></i>
              <span class="admin-short">SA</span>
            </div>
          </div>
        </div>

        <!-- MARKET CONTROL CONTENT -->
        <div class="content">
          <!-- Page Header -->
          <div class="page-header" data-aos="fade-up">
            <h1>Market Control</h1>
            <p>Manage trading pairs, prices, and market activity.</p>
            <div class="breadcrumb">
              <a href="#">Admin</a> <span>/</span>
              <span style="color: var(--primary)">Market Control</span>
            </div>
          </div>

          <!-- Fund user trading balance (spot — trade.php) -->
          <div class="fund-card" data-aos="fade-up">
            <h3>
              <i class="fa-solid fa-chart-line"></i> Fund User Trading Balance
            </h3>
            <p
              style="
                font-size: 0.85rem;
                color: var(--text-muted);
                margin: 0 0 1rem;
                line-height: 1.45;
              "
            >
              Credits or debits the user’s <strong>trading account</strong> only
              (<code style="color: var(--text-secondary)">spot_balance_usd</code> — same as
              <strong>Trade</strong>). This does not change the main wallet
              (withdrawals / sends). Use
              <a href="./admin-wallet-control.php" style="color: var(--primary)">Wallet Control</a>
              to fund the main wallet.
            </p>
            <input type="hidden" id="tradeFundUserId" value="" />
            <div class="fund-grid">
              <div class="fund-field">
                <label
                  >User Email or Wallet ID
                  <span style="color: var(--danger)">*</span></label
                >
                <div style="position: relative">
                  <input
                    type="text"
                    id="tradeFundUserSearch"
                    placeholder="Start typing to search..."
                    autocomplete="off"
                  />
                  <div class="suggestions-box" id="tradeFundSuggestions"></div>
                </div>
                <div class="validation-feedback" id="tradeFundUserValidation"></div>
              </div>
              <div class="fund-field">
                <label
                  >Amount (USD) <span style="color: var(--danger)">*</span></label
                >
                <input
                  type="number"
                  id="tradeFundAmount"
                  placeholder="0.00"
                  min="0.01"
                  step="0.01"
                />
                <div class="validation-feedback" id="tradeFundAmountValidation"></div>
              </div>
              <div class="fund-field">
                <label>Action</label>
                <select id="tradeFundAction">
                  <option value="fund_trade">Credit trading balance</option>
                  <option value="debit_trade">Debit trading balance</option>
                </select>
              </div>
              <div class="fund-field">
                <label>Note (optional)</label>
                <input
                  type="text"
                  id="tradeFundReason"
                  placeholder="Reason / reference"
                />
              </div>
            </div>
            <div class="fund-actions">
              <button type="button" class="btn-primary" id="tradeFundSubmitBtn">
                <i class="fa-solid fa-bolt"></i> Apply
              </button>
            </div>
          </div>

          <!-- Market Alerts (live from DB + computed movers) -->
          <div class="market-alerts" id="marketAlertsContainer" data-aos="fade-up">
            <div class="alert-card">
              <div class="alert-icon info">
                <i class="fa-solid fa-satellite-dish"></i>
              </div>
              <div>
                <h4>Loading live alerts…</h4>
                <p>Connecting to market data</p>
                <small>—</small>
              </div>
            </div>
          </div>

          <!-- Market Overview Cards -->
          <div class="analytics-grid" data-aos="fade-up">
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-chart-simple"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 2</span
                >
              </div>
              <div class="analytics-value">—</div>
              <div class="analytics-label">Total Trading Pairs</div>
              <div class="mini-chart">
                <canvas id="chartPairs" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-circle-check"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 4</span
                >
              </div>
              <div class="analytics-value">—</div>
              <div class="analytics-label">Active Markets</div>
              <div class="mini-chart">
                <canvas id="chartActive" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-circle-pause"></i
                ></span>
                <span class="trend-down"
                  ><i class="fa-solid fa-arrow-down"></i> 2</span
                >
              </div>
              <div class="analytics-value">—</div>
              <div class="analytics-label">Paused Markets</div>
              <div class="mini-chart">
                <canvas id="chartPaused" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-chart-pie"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 8.3%</span
                >
              </div>
              <div class="analytics-value">—</div>
              <div class="analytics-label">24h Trading Volume</div>
              <div class="mini-chart">
                <canvas id="chartVolume" width="100" height="30"></canvas>
              </div>
            </div>
          </div>
          <p
            id="liveMarketDataStamp"
            style="
              text-align: right;
              font-size: 0.78rem;
              color: var(--text-muted);
              margin: 0 0 1rem;
            "
          >
            —
          </p>

          <!-- Add New Trading Pair Form -->
          <div class="add-pair-card" data-aos="fade-up">
            <h3>
              <i class="fa-solid fa-circle-plus"></i> Add New Trading Pair
            </h3>
            <div class="pair-grid">
              <div class="pair-field">
                <label>Base Asset</label>
                <select id="baseAsset">
                  <option value="BTC">Bitcoin (BTC)</option>
                  <option value="ETH">Ethereum (ETH)</option>
                  <option value="SOL">Solana (SOL)</option>
                  <option value="BNB">Binance Coin (BNB)</option>
                  <option value="XRP">XRP</option>
                </select>
              </div>
              <div class="pair-field">
                <label>Quote Asset</label>
                <select id="quoteAsset">
                  <option value="USDT">Tether (USDT)</option>
                  <option value="BUSD">Binance USD (BUSD)</option>
                  <option value="USDC">USD Coin (USDC)</option>
                  <option value="DAI">Dai (DAI)</option>
                </select>
              </div>
              <div class="pair-field">
                <label>Initial Price (USD)</label>
                <input
                  type="number"
                  id="initialPrice"
                  placeholder="0.00"
                  step="0.01"
                />
              </div>
              <div class="pair-field">
                <label>Trading Fee (%)</label>
                <input
                  type="number"
                  id="tradingFee"
                  placeholder="0.1"
                  step="0.01"
                  value="0.1"
                />
              </div>
            </div>
            <button class="btn-primary" onclick="createTradingPair()">
              <i class="fa-solid fa-plus"></i> Create Trading Pair
            </button>
          </div>

          <!-- Price Control Panel -->
          <div class="price-control-panel" data-aos="fade-up">
            <!-- Manual Price Update -->
            <div class="price-card">
              <h4><i class="fa-solid fa-sliders"></i> Manual Price Control</h4>
              <div class="price-field">
                <label>Select Trading Pair</label>
                <select id="pricePair">
                  <option value="BTC/USDT">BTC/USDT</option>
                  <option value="ETH/USDT">ETH/USDT</option>
                  <option value="SOL/USDT">SOL/USDT</option>
                  <option value="BNB/USDT">BNB/USDT</option>
                </select>
              </div>
              <div class="price-field">
                <label>Current Price</label>
                <input
                  type="text"
                  id="currentPrice"
                  value="$68,245.50"
                  readonly
                />
              </div>
              <div class="price-field">
                <label>New Price</label>
                <input
                  type="number"
                  id="newPrice"
                  placeholder="Enter new price"
                  step="0.01"
                />
              </div>
              <div class="price-field">
                <label>Or Price Change (%)</label>
                <div class="fee-input-group">
                  <input
                    type="number"
                    id="priceChangePercent"
                    placeholder="±%"
                    step="0.1"
                  />
                  <button class="btn-outline" onclick="applyPercentChange()">
                    Apply
                  </button>
                </div>
              </div>
              <div class="price-actions">
                <button class="btn-primary" onclick="updatePrice()">
                  <i class="fa-solid fa-pen"></i> Update Price
                </button>
                <button class="btn-outline" onclick="simulateMarketMovement()">
                  <i class="fa-solid fa-chart-line"></i> Simulate Movement
                </button>
              </div>
            </div>

            <!-- Market Movement Simulation -->
            <div class="price-card">
              <h4><i class="fa-solid fa-robot"></i> Auto Fluctuation</h4>
              <div style="text-align: center; padding: 1rem">
                <div style="margin-bottom: 1.5rem">
                  <label class="market-toggle">
                    <input
                      type="checkbox"
                      id="autoFluctuation"
                      onchange="toggleAutoFluctuation()"
                    />
                    <span class="toggle-slider"></span>
                  </label>
                  <span style="margin-left: 1rem"
                    >Enable Auto Price Fluctuation</span
                  >
                </div>
                <div class="price-field">
                  <label>Volatility Level</label>
                  <select id="volatilityLevel">
                    <option value="low">Low (±2%)</option>
                    <option value="medium" selected>Medium (±5%)</option>
                    <option value="high">High (±10%)</option>
                  </select>
                </div>
                <div class="price-field">
                  <label>Update Interval (seconds)</label>
                  <input
                    type="number"
                    id="updateInterval"
                    value="30"
                    min="5"
                    max="300"
                  />
                </div>
                <button
                  class="btn-outline"
                  style="width: 100%; margin-top: 1rem"
                  onclick="applyFluctuationSettings()"
                >
                  <i class="fa-solid fa-play"></i> Start Simulation
                </button>
              </div>
            </div>
          </div>

          <!-- Market Status & Fee Control -->
          <div class="market-status" data-aos="fade-up">
            <div
              style="
                display: flex;
                align-items: center;
                gap: 1rem;
                flex-wrap: wrap;
              "
            >
              <i
                class="fa-solid fa-chart-line"
                style="font-size: 2rem; color: var(--primary)"
              ></i>
              <div>
                <h4>Global Market Status</h4>
                <div>
                  <span
                    class="status-badge-large active"
                    id="globalMarketStatus"
                    >Active</span
                  >
                </div>
              </div>
            </div>
            <div class="market-toggle-buttons">
              <button class="btn-success" onclick="toggleGlobalMarket(true)">
                <i class="fa-solid fa-play"></i> Resume All Trading
              </button>
              <button class="btn-danger" onclick="toggleGlobalMarket(false)">
                <i class="fa-solid fa-pause"></i> Pause All Trading
              </button>
            </div>
          </div>

          <!-- Trading Fee Control -->
          <div class="fee-control" data-aos="fade-up">
            <h4><i class="fa-solid fa-percent"></i> Trading Fee Control</h4>
            <div class="fee-grid">
              <div>
                <h5 style="margin-bottom: 0.5rem">Global Fee</h5>
                <div class="fee-item">
                  <span>Default Trading Fee</span>
                  <span>0.1%</span>
                </div>
                <div style="margin-top: 1rem">
                  <div class="fee-input-group">
                    <input
                      type="number"
                      id="globalFee"
                      placeholder="0.1"
                      step="0.01"
                      value="0.1"
                    />
                    <button class="btn-primary" onclick="updateGlobalFee()">
                      Update
                    </button>
                  </div>
                </div>
              </div>
              <div>
                <h5 style="margin-bottom: 0.5rem">Per-Pair Fees</h5>
                <div class="fee-item">
                  <span>BTC/USDT</span>
                  <span>0.1%</span>
                </div>
                <div class="fee-item">
                  <span>ETH/USDT</span>
                  <span>0.2%</span>
                </div>
                <div class="fee-item">
                  <span>SOL/USDT</span>
                  <span>0.15%</span>
                </div>
                <div class="fee-item">
                  <span>BNB/USDT</span>
                  <span>0.1%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Search & Filters -->
          <div class="control-bar" data-aos="fade-up">
            <div class="control-search">
              <i class="fa-solid fa-search"></i>
              <input
                type="text"
                id="searchInput"
                placeholder="Search trading pairs..."
              />
            </div>
            <div class="control-filters">
              <select class="filter-select" id="pairFilter">
                <option>All Pairs</option>
                <option>Active</option>
                <option>Paused</option>
                <option>High Volume</option>
              </select>
              <button class="btn-outline">
                <i class="fa-regular fa-file-pdf"></i> PDF
              </button>
              <button class="btn-outline">
                <i class="fa-regular fa-file-excel"></i> CSV
              </button>
            </div>
          </div>

          <!-- Trading Pairs Table -->
          <div class="table-wrapper" data-aos="fade-up">
            <table>
              <thead>
                <tr>
                  <th>Pair</th>
                  <th>Base Asset</th>
                  <th>Quote Asset</th>
                  <th>Price</th>
                  <th>24h Change</th>
                  <th>Volume (24h)</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="pairsTableBody">
                <!-- JS will populate -->
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="pagination">
            <button class="page-btn">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">4</button>
            <button class="page-btn">5</button>
            <button class="page-btn">
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>

          <!-- Market Analytics & Activity Feed -->
          <div class="two-column">
            <!-- Price/Volume Chart -->
            <div class="chart-container">
              <h3 style="margin-bottom: 1rem">
                <i
                  class="fa-solid fa-chart-line"
                  style="color: var(--primary)"
                ></i>
                Market Trends
              </h3>
              <div class="chart-wrapper">
                <canvas id="marketChart"></canvas>
              </div>
              <div
                id="marketChartLegend"
                style="
                  display: flex;
                  gap: 2rem;
                  justify-content: center;
                  margin-top: 1rem;
                  flex-wrap: wrap;
                "
              >
                <span style="color: var(--text-muted)">Loading legend…</span>
              </div>
            </div>

            <!-- Market Activity Feed -->
            <div class="feed-panel">
              <h3>
                <i class="fa-solid fa-rss" style="color: var(--primary)"></i>
                Market Activity Feed
              </h3>
              <div id="marketFeed"></div>
            </div>
          </div>

          <!-- Order Monitoring Panel -->
          <div
            class="chart-container"
            style="margin-top: 1.5rem"
            data-aos="fade-up"
          >
            <h3 style="margin-bottom: 1rem">
              <i class="fa-solid fa-clock"></i> Open Orders Monitor
            </h3>
            <div class="table-wrapper" style="margin: 0; max-height: 300px">
              <table style="min-width: 800px">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Pair</th>
                    <th>Order Type</th>
                    <th>Amount</th>
                    <th>Price</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="openOrdersTbody">
                  <tr>
                    <td colspan="6" style="padding: 16px; color: var(--text-muted)">
                      Loading open orders…
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Processing Overlay -->
    <div class="processing-overlay" id="processingOverlay">
      <div class="processing-card">
        <div class="processing-spinner"></div>
        <h3 style="margin-bottom: 1.5rem">Updating Market</h3>

        <div class="processing-step" id="step1">
          <div class="step-icon" id="step1Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Updating market price...</span>
        </div>

        <div class="processing-step" id="step2">
          <div class="step-icon" id="step2Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Syncing order books...</span>
        </div>

        <div class="processing-step" id="step3">
          <div class="step-icon" id="step3Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Price updated successfully!</span>
        </div>
      </div>
    </div>

    <div
      class="admin-modal-overlay"
      id="adminResultModal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="adminModalTitle"
    >
      <div class="admin-modal-card modal-info" id="adminModalCard">
        <h3 id="adminModalTitle" style="margin: 0; font-size: 1.15rem">Notice</h3>
        <p id="adminModalBody" class="admin-modal-body"></p>
        <button type="button" class="btn-primary" id="adminModalClose" style="width: 100%; margin-top: 1rem">
          OK
        </button>
      </div>
    </div>

    <!-- AI Assistant -->
    <div class="ai-assistant" id="aiBtn">
      <i class="fa-solid fa-robot"></i>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      AOS.init({ duration: 700, once: true });

      // Sidebar collapse
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      collapseBtn.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        const icon = collapseBtn.querySelector("i");
        icon.classList.toggle("fa-chevron-left");
        icon.classList.toggle("fa-chevron-right");
      });

      // Mobile menu
      const mobileBtn = document.getElementById("mobileMenuBtn");
      mobileBtn.addEventListener("click", () => {
        sidebar.classList.toggle("mobile-open");
      });
      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 1100 &&
          !sidebar.contains(e.target) &&
          !mobileBtn.contains(e.target)
        ) {
          sidebar.classList.remove("mobile-open");
        }
      });

      // Theme toggle
      const themeToggle = document.getElementById("themeToggle");
      themeToggle.addEventListener("click", () => {
        document.body.classList.toggle("dark");
      });

      const MARKET_API = "./api/market_control_api.php";
      const WALLET_API = "./api/wallet_control_api.php";
      let adminCsrf = <?php echo json_encode($ADMIN_PAGE_CSRF, JSON_UNESCAPED_SLASHES); ?>;
      let tradingPairs = [];
      window.lastMarketBootstrap = null;

      function escapeHtml(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;");
      }

      function esc(s) {
        return escapeHtml(s);
      }

      function escAttr(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/"/g, "&quot;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;");
      }

      function showAdminModal(title, message, variant = "info") {
        const ov = document.getElementById("adminResultModal");
        const card = document.getElementById("adminModalCard");
        const t = document.getElementById("adminModalTitle");
        const b = document.getElementById("adminModalBody");
        if (!ov || !card || !t || !b) return;
        t.textContent = title;
        b.textContent = message;
        card.classList.remove("modal-success", "modal-error", "modal-info");
        card.classList.add(
          variant === "success"
            ? "modal-success"
            : variant === "error"
              ? "modal-error"
              : "modal-info",
        );
        ov.classList.add("is-open");
        ov.style.display = "flex";
      }

      function hideAdminModal() {
        const ov = document.getElementById("adminResultModal");
        if (ov) {
          ov.classList.remove("is-open");
          ov.style.display = "none";
        }
      }

      document.getElementById("adminModalClose")?.addEventListener("click", hideAdminModal);
      document.getElementById("adminResultModal")?.addEventListener("click", (e) => {
        if (e.target && e.target.id === "adminResultModal") hideAdminModal();
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") hideAdminModal();
      });

      function alertIconClass(icon) {
        if (icon === "critical") return "critical";
        if (icon === "warning") return "warning";
        return "info";
      }

      function alertFa(icon) {
        if (icon === "critical") return "fa-chart-line";
        if (icon === "warning") return "fa-wave-square";
        return "fa-chart-pie";
      }

      function renderMarketAlerts(alerts) {
        const el = document.getElementById("marketAlertsContainer");
        if (!el || !Array.isArray(alerts) || !alerts.length) return;
        el.innerHTML = alerts
          .map((a) => {
            const ic = alertIconClass(a.icon);
            const fa = alertFa(a.icon);
            return `<div class="alert-card">
              <div class="alert-icon ${ic}"><i class="fa-solid ${fa}"></i></div>
              <div>
                <h4>${escapeHtml(a.title)}</h4>
                <p>${escapeHtml(a.line)}</p>
                <small>${escapeHtml(a.hint)}</small>
              </div>
            </div>`;
          })
          .join("");
      }

      function feedIconClass(icon) {
        if (icon === "pause") return "pause";
        if (icon === "add") return "add";
        if (icon === "settings") return "price";
        if (icon === "info") return "price";
        return "price";
      }

      function feedFa(icon) {
        if (icon === "pause") return "fa-pause";
        if (icon === "add") return "fa-plus";
        if (icon === "settings") return "fa-gear";
        if (icon === "info") return "fa-circle-info";
        return "fa-chart-line";
      }

      function renderMarketFeed(items) {
        const feed = document.getElementById("marketFeed");
        if (!feed) return;
        if (!items || !items.length) {
          feed.innerHTML = `<div class="feed-item">
            <span class="feed-icon price"><i class="fa-solid fa-circle-info"></i></span>
            <span>No activity logged yet.</span>
            <span style="color:var(--text-muted);margin-left:auto">—</span>
          </div>`;
          return;
        }
        feed.innerHTML = items
          .map((it) => {
            const ic = feedIconClass(it.icon);
            const fa = feedFa(it.icon);
            const detail = it.detail ? ` — ${escapeHtml(it.detail)}` : "";
            return `<div class="feed-item">
              <span class="feed-icon ${ic}"><i class="fa-solid ${fa}"></i></span>
              <span><strong>${escapeHtml(it.title)}</strong>${detail}</span>
              <span style="color:var(--text-muted);margin-left:auto">${escapeHtml(it.time_label || "")}</span>
            </div>`;
          })
          .join("");
      }

      function renderOpenOrders(items) {
        const tb = document.getElementById("openOrdersTbody");
        if (!tb) return;
        if (!items || !items.length) {
          tb.innerHTML =
            '<tr><td colspan="6" style="padding:16px; color:var(--text-muted)">No open orders right now.</td></tr>';
          return;
        }
        tb.innerHTML = items
          .map((o) => {
            const user = escapeHtml(o.user || ("UID-" + o.user_id));
            const pair = escapeHtml(o.pair || "");
            const side = String(o.side || "—").toUpperCase();
            const sideBadge =
              '<span class="status-badge status-active">' +
              escapeHtml(side) +
              "</span>";
            const amt = Number(o.amount_usd || 0);
            const qty = Number(o.qty || 0);
            const asset = escapeHtml((o.asset || "").toUpperCase());
            const amountStr =
              "$" +
              amt.toLocaleString(undefined, { maximumFractionDigits: 2 }) +
              (qty > 0 && asset ? ` · ${qty.toFixed(6)} ${asset}` : "");
            const price = Number(o.price_usd || 0);
            const priceStr = price > 0 ? "$" + price.toLocaleString() : "—";
            return `<tr>
              <td>${user}</td>
              <td>${pair}</td>
              <td>${sideBadge}</td>
              <td>${escapeHtml(amountStr)}</td>
              <td>${escapeHtml(priceStr)}</td>
              <td><span class="status-badge status-active">Open</span></td>
            </tr>`;
          })
          .join("");
      }

      function updateChartLegend() {
        const leg = document.getElementById("marketChartLegend");
        if (!leg) return;
        const cols = [
          "var(--primary)",
          "var(--success)",
          "var(--info)",
        ];
        const top = tradingPairs.slice(0, 3);
        if (!top.length) {
          leg.innerHTML = '<span style="color:var(--text-muted)">No pairs</span>';
          return;
        }
        leg.innerHTML = top
          .map(
            (p, i) =>
              `<div><span style="color:${cols[i % cols.length]}">●</span> ${escapeHtml(p.pair)}</div>`,
          )
          .join("");
      }

      function mapRowToUi(row) {
        const sym = row.symbol;
        const base = row.base_asset;
        const quote = row.quote_asset;
        const act = parseInt(row.is_active, 10) === 1;
        const ch = parseFloat(row.change_24h_pct);
        const chStr = (ch >= 0 ? "+" : "") + ch.toFixed(2) + "%";
        return {
          pair: base + "/" + quote,
          symbol: sym,
          base,
          quote,
          price: parseFloat(row.price_usd),
          change: chStr,
          volume: parseFloat(row.volume_24h_usd),
          status: act ? "Active" : "Paused",
        };
      }

      async function marketApiPost(payload) {
        const body = Object.assign({ csrf_token: adminCsrf }, payload);
        const res = await fetch(MARKET_API, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify(body),
          credentials: "same-origin",
        });
        const j = await res.json().catch(() => ({}));
        if (j && j.csrf) adminCsrf = j.csrf;
        return j;
      }

      // Fund trading balance: user search (wallet API) + apply (market API)
      const tradeFundUserSearch = document.getElementById("tradeFundUserSearch");
      const tradeFundSuggestions = document.getElementById("tradeFundSuggestions");
      const tradeFundUserIdEl = document.getElementById("tradeFundUserId");
      const tradeFundUserValidation = document.getElementById("tradeFundUserValidation");
      let tradeFundSearchTimer = null;

      if (tradeFundUserSearch && tradeFundSuggestions) {
        tradeFundUserSearch.addEventListener("input", (e) => {
          const value = String(e.target.value || "").trim();
          if (tradeFundSearchTimer) clearTimeout(tradeFundSearchTimer);
          if (tradeFundUserIdEl) tradeFundUserIdEl.value = "";
          if (value.length < 1) {
            tradeFundSuggestions.style.display = "none";
            tradeFundSuggestions.innerHTML = "";
            return;
          }
          tradeFundSearchTimer = setTimeout(async () => {
            const r = await fetch(
              WALLET_API + "?action=search&q=" + encodeURIComponent(value),
              { credentials: "same-origin" },
            );
            const ct = (r.headers.get("content-type") || "").toLowerCase();
            if (!r.ok || !ct.includes("application/json")) {
              tradeFundSuggestions.innerHTML =
                '<div class="suggestion-item" style="color:var(--danger)">Search unavailable.</div>';
              tradeFundSuggestions.style.display = "block";
              return;
            }
            const d = await r.json().catch(() => ({}));
            if (!d.ok || !Array.isArray(d.users)) {
              tradeFundSuggestions.innerHTML = "";
              tradeFundSuggestions.style.display = "none";
              return;
            }
            tradeFundSuggestions.innerHTML = d.users
              .map(
                (u) =>
                  `<div class="suggestion-item" role="button" data-uid="${Number(u.id)}" data-label="${escAttr(u.label)}" data-email="${escAttr(u.email)}"><strong>${esc(u.label)}</strong> — ${esc(u.email)}</div>`,
              )
              .join("");
            tradeFundSuggestions.style.display = d.users.length ? "block" : "none";
          }, 280);
        });

        tradeFundSuggestions.addEventListener("click", (e) => {
          const item = e.target.closest(".suggestion-item");
          if (!item) return;
          e.preventDefault();
          const uid = parseInt(item.getAttribute("data-uid") || "0", 10);
          const name = item.getAttribute("data-label") || "";
          const email = item.getAttribute("data-email") || "";
          if (uid > 0 && tradeFundUserIdEl) {
            tradeFundUserIdEl.value = String(uid);
          }
          tradeFundUserSearch.value = name + " (" + email + ")";
          tradeFundSuggestions.style.display = "none";
          if (tradeFundUserValidation) {
            tradeFundUserValidation.className = "validation-feedback valid";
            tradeFundUserValidation.textContent = "✓ User selected";
          }
        });

        document.addEventListener("click", (e) => {
          if (
            !tradeFundUserSearch.contains(e.target) &&
            !tradeFundSuggestions.contains(e.target)
          ) {
            tradeFundSuggestions.style.display = "none";
          }
        });
      }

      document.getElementById("tradeFundSubmitBtn")?.addEventListener("click", async () => {
        const uid = parseInt((tradeFundUserIdEl && tradeFundUserIdEl.value) || "0", 10);
        const amt = parseFloat(
          document.getElementById("tradeFundAmount").value || "0",
        );
        const action =
          document.getElementById("tradeFundAction").value || "fund_trade";
        const reasonEl = document.getElementById("tradeFundReason");
        const reason = reasonEl ? String(reasonEl.value || "") : "";
        const av = document.getElementById("tradeFundAmountValidation");
        if (av) {
          av.className = "validation-feedback";
          av.textContent = "";
        }
        if (!uid) {
          if (tradeFundUserValidation) {
            tradeFundUserValidation.className = "validation-feedback invalid";
            tradeFundUserValidation.textContent = "Search and select a user.";
          }
          return;
        }
        if (!isFinite(amt) || amt < 0.01) {
          if (av) {
            av.className = "validation-feedback invalid";
            av.textContent = "Enter a valid amount (min 0.01).";
          }
          return;
        }
        const j = await marketApiPost({
          action,
          user_id: uid,
          amount: amt,
          reason,
        });
        if (!j.ok) {
          const err =
            j.error === "csrf"
              ? "Session expired — refresh the page."
              : j.error === "insufficient"
                ? "Insufficient trading balance to debit."
                : j.error || "Could not update trading balance.";
          showAdminModal("Error", err, "error");
          return;
        }
        showAdminModal(
          "Success",
          "Trading balance updated. New spot balance: $" + (j.spot_balance_usd || "0.00"),
          "success",
        );
        const amtEl = document.getElementById("tradeFundAmount");
        if (amtEl) amtEl.value = "";
        if (reasonEl) reasonEl.value = "";
        await refreshMarketPage();
      });

      let marketChartsInitialized = false;
      async function refreshMarketPage() {
        const res = await fetch(MARKET_API + "?action=bootstrap", {
          credentials: "same-origin",
        });
        const d = await res.json().catch(() => null);
        if (!d || !d.ok) return;
        window.lastMarketBootstrap = d;
        adminCsrf = d.csrf || adminCsrf;
        tradingPairs = (d.pairs || []).map(mapRowToUi);
        if (d.stats) reflectStats(d.stats);
        if (d.settings) applySettingsUi(d.settings);
        renderMarketAlerts(d.alerts || []);
        renderMarketFeed(d.feed || []);
        renderOpenOrders(d.open_orders || []);
        updateChartLegend();
        const stamp = document.getElementById("liveMarketDataStamp");
        if (stamp && d.server_time) {
          stamp.textContent =
            "Live data · updated " +
            new Date(d.server_time).toLocaleString(undefined, {
              hour: "2-digit",
              minute: "2-digit",
              second: "2-digit",
            });
        }
        renderPairsTable();
        populatePricePairSelect();
        syncCurrentPriceField();
        if (!marketChartsInitialized) {
          initCharts(d.stats);
          marketChartsInitialized = true;
        }
      }

      function reflectStats(s) {
        const grid = document.querySelectorAll(".analytics-grid .analytics-card");
        if (grid[0])
          grid[0].querySelector(".analytics-value").textContent = String(
            s.total_pairs ?? 0,
          );
        if (grid[1])
          grid[1].querySelector(".analytics-value").textContent = String(
            s.active_markets ?? 0,
          );
        if (grid[2])
          grid[2].querySelector(".analytics-value").textContent = String(
            s.paused_markets ?? 0,
          );
        if (grid[3]) {
          const v = Number(s.volume_24h_usd || 0);
          grid[3].querySelector(".analytics-value").textContent =
            "$" +
            (v >= 1e6 ? (v / 1e6).toFixed(1) + "M" : v.toLocaleString());
        }
        const live = "● Live";
        for (let i = 0; i < grid.length; i++) {
          const tr = grid[i].querySelector(".trend-up, .trend-down");
          if (tr) {
            tr.classList.remove("trend-down");
            tr.classList.add("trend-up");
            tr.textContent = live;
          }
        }
      }

      function applySettingsUi(settings) {
        const st = document.getElementById("globalMarketStatus");
        if (st) {
          st.className = settings.trading_enabled
            ? "status-badge-large active"
            : "status-badge-large paused";
          st.textContent = settings.trading_enabled ? "Active" : "Paused";
        }
        const gf = document.getElementById("globalFee");
        if (gf && settings.global_fee_pct !== undefined)
          gf.value = String(settings.global_fee_pct);
        const af = document.getElementById("autoFluctuation");
        if (af) af.checked = !!settings.auto_fluctuation_enabled;
        const vl = document.getElementById("volatilityLevel");
        if (vl && settings.volatility) vl.value = settings.volatility;
        const ui = document.getElementById("updateInterval");
        if (ui && settings.fluctuation_interval_sec)
          ui.value = String(settings.fluctuation_interval_sec);
      }

      function populatePricePairSelect() {
        const sel = document.getElementById("pricePair");
        if (!sel) return;
        sel.innerHTML = tradingPairs
          .map((p) => `<option value="${p.symbol}">${p.pair}</option>`)
          .join("");
      }

      function syncCurrentPriceField() {
        const sel = document.getElementById("pricePair");
        const cur = document.getElementById("currentPrice");
        if (!sel || !cur) return;
        const sym = sel.value;
        const hit = tradingPairs.find((x) => x.symbol === sym);
        if (hit) cur.value = "$" + hit.price.toLocaleString();
      }

      document
        .getElementById("pricePair")
        ?.addEventListener("change", syncCurrentPriceField);

      // Mini charts
      function initCharts(stats) {
        const sp = stats && stats.spark_pairs ? stats.spark_pairs : [1, 2, 3, 4, 5, 6, 7];
        const sa = stats && stats.spark_active ? stats.spark_active : sp;
        const spa = stats && stats.spark_paused ? stats.spark_paused : [0, 0, 0, 0, 0, 0, 0];
        const sv = stats && stats.spark_volume ? stats.spark_volume : [1, 2, 3, 4, 5, 6, 7];
        new Chart(document.getElementById("chartPairs"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sp,
                borderColor: "#22c55e",
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { display: false }, y: { display: false } },
            plugins: { legend: { display: false } },
          },
        });
        new Chart(document.getElementById("chartActive"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sa,
                borderColor: "#10b981",
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { display: false }, y: { display: false } },
            plugins: { legend: { display: false } },
          },
        });
        new Chart(document.getElementById("chartPaused"), {
          type: "line",
          data: {
            datasets: [
              {
                data: spa,
                borderColor: "#ef4444",
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { display: false }, y: { display: false } },
            plugins: { legend: { display: false } },
          },
        });
        new Chart(document.getElementById("chartVolume"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sv,
                borderColor: "#3b82f6",
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { display: false }, y: { display: false } },
            plugins: { legend: { display: false } },
          },
        });

        const labels = ["00:00", "04:00", "08:00", "12:00", "16:00", "20:00", "Now"];
        const top = tradingPairs.slice(0, 3);
        const colors = ["#22c55e", "#10b981", "#3b82f6"];
        const datasets = top.map((p, i) => {
          const base = p.price * (0.96 + i * 0.01);
          const step = (p.price - base) / 6;
          const arr = [];
          for (let k = 0; k < 7; k++) arr.push(base + step * k);
          return {
            label: p.pair,
            data: arr,
            borderColor: colors[i % colors.length],
            tension: 0.4,
            fill: false,
          };
        });
        new Chart(document.getElementById("marketChart"), {
          type: "line",
          data: { labels, datasets },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                grid: { color: "rgba(255,255,255,0.1)" },
                ticks: { color: "#7f8ea3" },
              },
              x: { grid: { display: false }, ticks: { color: "#7f8ea3" } },
            },
          },
        });
      }

      // Render trading pairs table
      function renderPairsTable() {
        const tbody = document.getElementById("pairsTableBody");
        tbody.innerHTML = "";
        tradingPairs.forEach((pair) => {
          const statusClass =
            pair.status === "Active" ? "status-active" : "status-paused";
          const changeClass = pair.change.startsWith("+")
            ? "change-positive"
            : "change-negative";
          const sym = pair.symbol.replace(/'/g, "\\'");

          const row = document.createElement("tr");
          row.innerHTML = `
            <td><strong>${pair.pair}</strong></td>
            <td>${pair.base}</td>
            <td>${pair.quote}</td>
            <td>$${pair.price.toLocaleString()}</td>
            <td class="${changeClass}">${pair.change}</td>
            <td>$${(pair.volume / 1000000).toFixed(1)}M</td>
            <td><span class="status-badge ${statusClass}">${pair.status}</span></td>
            <td>
              <div class="actions-dropdown">
                <button class="actions-btn" onclick="toggleDropdown(event, this)"><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" onclick="enablePair('${sym}')"><i class="fa-regular fa-circle-check"></i> Enable Pair</a>
                  <a class="dropdown-item" onclick="disablePair('${sym}')"><i class="fa-regular fa-circle-pause"></i> Disable Pair</a>
                  <a class="dropdown-item" onclick="editPair('${sym}')"><i class="fa-regular fa-pen-to-square"></i> Edit Pair</a>
                  <a class="dropdown-item" style="color: var(--danger);" onclick="deletePair('${sym}')"><i class="fa-regular fa-trash-can"></i> Delete Pair</a>
                </div>
              </div>
            </td>
          `;
          tbody.appendChild(row);
        });
      }

      // Dropdown toggle
      window.toggleDropdown = function (event, btn) {
        event.stopPropagation();
        const allMenus = document.querySelectorAll(".dropdown-menu");
        allMenus.forEach((m) => m.classList.remove("show"));
        const menu = btn.nextElementSibling;
        menu.classList.toggle("show");
      };

      document.addEventListener("click", (e) => {
        if (!e.target.closest(".actions-dropdown")) {
          document
            .querySelectorAll(".dropdown-menu")
            .forEach((m) => m.classList.remove("show"));
        }
      });

      // Create new trading pair
      window.createTradingPair = async function () {
        const base = document.getElementById("baseAsset").value;
        const quote = document.getElementById("quoteAsset").value;
        const price = document.getElementById("initialPrice").value;
        const feeRaw = document.getElementById("tradingFee").value;

        if (!price) {
          showAdminModal(
            "Missing price",
            "Please enter an initial price.",
            "info",
          );
          return;
        }

        const j = await marketApiPost({
          action: "pair_create",
          base,
          quote,
          price_usd: parseFloat(price),
          fee_pct: feeRaw === "" ? null : parseFloat(feeRaw),
        });
        if (!j.ok) {
          showAdminModal(
            "Could not create pair",
            j.error || "Duplicate symbol or invalid data.",
            "error",
          );
          return;
        }

        document.getElementById("initialPrice").value = "";
        await refreshMarketPage();
        showAdminModal(
          "Pair saved",
          `Trading pair ${base}/${quote} was created.`,
          "success",
        );
      };

      // Update price with animation
      window.updatePrice = async function () {
        const symbol = document.getElementById("pricePair").value;
        const newPrice = document.getElementById("newPrice").value;

        if (!newPrice) {
          showAdminModal(
            "Missing price",
            "Please enter a new price.",
            "info",
          );
          return;
        }

        const overlay = document.getElementById("processingOverlay");
        overlay.style.display = "flex";

        for (let i = 1; i <= 3; i++) {
          document.getElementById(`step${i}Icon`).className = "step-icon";
          document.getElementById(`step${i}Icon`).innerHTML =
            '<i class="fa-solid fa-circle-notch"></i>';
        }

        setTimeout(() => {
          document.getElementById("step1Icon").className =
            "step-icon completed";
          document.getElementById("step1Icon").innerHTML =
            '<i class="fa-solid fa-check"></i>';
        }, 600);
        setTimeout(() => {
          document.getElementById("step2Icon").className =
            "step-icon completed";
          document.getElementById("step2Icon").innerHTML =
            '<i class="fa-solid fa-check"></i>';
        }, 1200);

        const j = await marketApiPost({
          action: "pair_update_price",
          symbol,
          price_usd: parseFloat(newPrice),
        });

        setTimeout(() => {
          document.getElementById("step3Icon").className =
            "step-icon completed";
          document.getElementById("step3Icon").innerHTML =
            '<i class="fa-solid fa-check"></i>';
          setTimeout(async () => {
            overlay.style.display = "none";
            if (!j.ok) {
              showAdminModal(
                "Update failed",
                j.error || "Price update failed.",
                "error",
              );
              return;
            }
            await refreshMarketPage();
            document.getElementById("newPrice").value = "";
            showAdminModal("Price updated", "Market price saved.", "success");
          }, 400);
        }, 1800);
      };

      // Apply percentage change
      window.applyPercentChange = async function () {
        const symbol = document.getElementById("pricePair").value;
        const percent = parseFloat(
          document.getElementById("priceChangePercent").value,
        );

        if (!isFinite(percent)) {
          showAdminModal(
            "Missing value",
            "Please enter a percentage change.",
            "info",
          );
          return;
        }

        const j = await marketApiPost({
          action: "pair_apply_percent",
          symbol,
          percent,
        });
        if (!j.ok) {
          showAdminModal(
            "Apply failed",
            j.error || "Could not apply percentage.",
            "error",
          );
          return;
        }
        await refreshMarketPage();
        const hit = tradingPairs.find((x) => x.symbol === symbol);
        if (hit)
          document.getElementById("newPrice").value = String(hit.price);
        showAdminModal(
          "Percentage applied",
          `Adjusted ${symbol} by ${percent}%.`,
          "success",
        );
      };

      // Simulate market movement (server-side random tick)
      window.simulateMarketMovement = async function () {
        await marketApiPost({ action: "tick_random" });
        await refreshMarketPage();
        showAdminModal(
          "Random tick",
          "A random active pair was nudged. Check the activity feed.",
          "success",
        );
      };

      window.toggleAutoFluctuation = function () {
        /* preference applied when "Start simulation" / save runs */
      };

      // Apply fluctuation settings
      window.applyFluctuationSettings = async function () {
        const enabled = document.getElementById("autoFluctuation").checked;
        const volatility = document.getElementById("volatilityLevel").value;
        const interval = parseInt(
          document.getElementById("updateInterval").value,
          10,
        );
        const j = await marketApiPost({
          action: "settings_save",
          auto_fluctuation_enabled: enabled,
          volatility,
          fluctuation_interval_sec: interval,
        });
        if (!j.ok) {
          showAdminModal(
            "Save failed",
            "Could not save fluctuation settings.",
            "error",
          );
          return;
        }
        await refreshMarketPage();
        showAdminModal(
          "Settings saved",
          `Auto fluctuation ${enabled ? "enabled" : "disabled"} (${volatility}).`,
          "success",
        );
      };

      // Toggle global market
      window.toggleGlobalMarket = async function (resume) {
        const j = await marketApiPost({
          action: "settings_save",
          trading_enabled: !!resume,
        });
        if (!j.ok) {
          showAdminModal(
            "Update failed",
            "Could not update global market status.",
            "error",
          );
          return;
        }
        await refreshMarketPage();
        showAdminModal(
          resume ? "Trading resumed" : "Trading paused",
          resume
            ? "All trading is enabled again."
            : "New user orders are blocked until you resume.",
          "success",
        );
      };

      // Update global fee
      window.updateGlobalFee = async function () {
        const fee = parseFloat(document.getElementById("globalFee").value);
        const j = await marketApiPost({
          action: "settings_save",
          global_fee_pct: fee,
        });
        if (!j.ok) {
          showAdminModal(
            "Update failed",
            "Could not update global fee.",
            "error",
          );
          return;
        }
        await refreshMarketPage();
        showAdminModal(
          "Fee updated",
          "Global trading fee was saved.",
          "success",
        );
      };

      // Pair actions (symbol = e.g. BTCUSDT)
      window.enablePair = async function (symbol) {
        const j = await marketApiPost({
          action: "pair_set_active",
          symbol,
          active: true,
        });
        if (!j.ok) {
          showAdminModal("Action failed", j.error || "Request failed.", "error");
          return;
        }
        await refreshMarketPage();
        showAdminModal("Pair enabled", symbol + " is active.", "success");
      };

      window.disablePair = async function (symbol) {
        const j = await marketApiPost({
          action: "pair_set_active",
          symbol,
          active: false,
        });
        if (!j.ok) {
          showAdminModal("Action failed", j.error || "Request failed.", "error");
          return;
        }
        await refreshMarketPage();
        showAdminModal("Pair paused", symbol + " is paused.", "success");
      };

      window.editPair = function (symbol) {
        const sel = document.getElementById("pricePair");
        if (sel) {
          sel.value = symbol;
          syncCurrentPriceField();
        }
        document
          .querySelector(".price-control-panel")
          ?.scrollIntoView({ behavior: "smooth" });
      };

      window.deletePair = async function (symbol) {
        if (!(await UiModal.confirm(`Delete pair ${symbol}? Users will no longer trade it.`, { title: "Delete pair", danger: true }))) {
          return;
        }
        const j = await marketApiPost({ action: "pair_delete", symbol });
        if (!j.ok) {
          showAdminModal(
            "Delete failed",
            j.error || "Could not remove pair.",
            "error",
          );
          return;
        }
        await refreshMarketPage();
        showAdminModal("Pair removed", symbol + " was deleted.", "success");
      };

      // Search functionality
      document.getElementById("searchInput").addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll("#pairsTableBody tr");

        rows.forEach((row) => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? "" : "none";
        });
      });

      // Filter functionality
      document.getElementById("pairFilter").addEventListener("change", (e) => {
        const filter = e.target.value.toLowerCase();
        const rows = document.querySelectorAll("#pairsTableBody tr");

        rows.forEach((row) => {
          const status = row.cells[6].textContent.toLowerCase();
          const volume = parseFloat(
            row.cells[5].textContent.replace(/[$,M]/g, ""),
          );

          if (filter === "all pairs") {
            row.style.display = "";
          } else if (filter === "active" && status === "active") {
            row.style.display = "";
          } else if (filter === "paused" && status === "paused") {
            row.style.display = "";
          } else if (filter === "high volume" && volume > 50) {
            row.style.display = "";
          } else {
            row.style.display = "none";
          }
        });
      });

      // AI Assistant (snapshot from last bootstrap)
      document.getElementById("aiBtn").addEventListener("click", () => {
        const d = window.lastMarketBootstrap;
        const s = d && d.stats ? d.stats : {};
        const pairs = (d && d.pairs ? d.pairs : []).length;
        const vol = Number(s.volume_24h_usd || 0);
        const volStr =
          vol >= 1e6 ? (vol / 1e6).toFixed(1) + "M" : vol.toLocaleString();
        const st = d && d.settings ? d.settings : {};
        const msg = [
          `Listed pairs: ${pairs}`,
          `Active markets: ${s.active_markets ?? "—"} · Paused: ${s.paused_markets ?? "—"}`,
          `24h volume (catalog): $${volStr}`,
          `Global trading: ${st.trading_enabled ? "ON" : "OFF"}`,
          `Auto fluctuation: ${st.auto_fluctuation_enabled ? "on" : "off"} (${st.volatility || "—"})`,
        ].join("\n");
        showAdminModal("Market snapshot", msg, "info");
      });

      // Initialize + poll for live sections
      refreshMarketPage();
      setInterval(refreshMarketPage, 25000);

      async function refreshOpenOrdersOnly() {
        try {
          const res = await fetch(MARKET_API + "?action=open_orders&limit=40", {
            credentials: "same-origin",
          });
          const d = await res.json().catch(() => null);
          if (!d || !d.ok) return;
          if (d.csrf) adminCsrf = d.csrf;
          renderOpenOrders(d.open_orders || []);
        } catch (e) {}
      }
      setInterval(refreshOpenOrdersOnly, 9000);

      // Resize handler
      window.addEventListener("resize", () => {
        if (window.innerWidth > 1100) {
          sidebar.classList.remove("mobile-open");
        }
      });
    </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
  </body>
</html>
