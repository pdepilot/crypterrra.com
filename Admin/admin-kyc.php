<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Admin · KYC Verification</title>
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
    <!-- PhotoSwipe for image zoom -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/5.4.2/photoswipe.min.css"
    />
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

      /* Fraud Detection Panel */
      .fraud-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
      }

      .fraud-card {
        background: rgba(239, 68, 68, 0.05);
        border: 1px solid var(--danger);
        border-radius: 30px;
        padding: 1.2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: 0.3s;
      }

      .fraud-card.warning {
        border-color: var(--orange);
        background: rgba(249, 115, 22, 0.05);
      }

      .fraud-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);
      }

      .fraud-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--danger);
        font-size: 1.4rem;
        flex-shrink: 0;
      }

      .fraud-icon.warning {
        background: rgba(249, 115, 22, 0.2);
        color: var(--orange);
      }

      .fraud-content h4 {
        font-size: 1rem;
        margin-bottom: 0.2rem;
      }

      .fraud-content p {
        font-size: 0.85rem;
        color: var(--text-secondary);
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
        transform: scale(1.02);
        box-shadow: 0 0 30px rgba(239, 68, 68, 0.5);
      }

      /* Bulk Actions Bar */
      .bulk-bar {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 0.8rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        margin: 1rem 0;
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
      }

      td {
        padding: 1rem 0.8rem;
        border-bottom: 1px solid var(--glass-edge);
      }

      tr {
        transition: 0.2s;
      }

      tr:hover {
        background: var(--primary-soft);
      }

      .checkbox-col {
        width: 40px;
        text-align: center;
      }

      input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
      }

      /* Status Badges */
      .status-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .status-pending {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .status-approved {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .status-rejected {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      /* Risk Level Indicators */
      .risk-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .risk-low {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .risk-medium {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .risk-high {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      /* Document Type Icons */
      .doc-type {
        display: flex;
        align-items: center;
        gap: 6px;
      }

      .doc-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .doc-passport {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }

      .doc-license {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .doc-id {
        background: rgba(139, 92, 246, 0.2);
        color: #a78bfa;
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
        min-width: 200px;
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

      .dropdown-item.danger:hover {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
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

      /* KYC Detail Modal */
      .kyc-modal {
        max-width: 1200px;
        width: 95%;
        max-height: 90vh;
        padding: 1.5rem;
      }

      .kyc-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
      }

      .document-preview {
        background: var(--glass-bg);
        border-radius: 30px;
        padding: 1.5rem;
        text-align: center;
      }

      .document-image {
        width: 100%;
        height: 200px;
        background: var(--surface-light);
        border-radius: 20px;
        margin: 1rem 0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.3s;
        border: 2px solid transparent;
      }

      .document-image:hover {
        border-color: var(--primary);
        transform: scale(1.02);
      }

      .document-image img {
        max-width: 100%;
        max-height: 100%;
        border-radius: 15px;
      }

      .document-controls {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        margin-top: 1rem;
      }

      .ai-scanning {
        background: var(--glass-bg);
        border-radius: 30px;
        padding: 1.5rem;
        margin-top: 1rem;
      }

      .ai-step {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.8rem;
        border-bottom: 1px solid var(--glass-edge);
      }

      .ai-step:last-child {
        border-bottom: none;
      }

      .ai-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
      }

      .ai-icon.completed {
        background: var(--success);
        color: white;
      }

      .ai-progress {
        flex: 1;
        height: 6px;
        background: var(--surface-light);
        border-radius: 10px;
        overflow: hidden;
      }

      .ai-progress-bar {
        height: 100%;
        background: var(--primary);
        width: 0%;
        transition: width 0.3s;
      }

      .match-score {
        text-align: center;
        margin-top: 1rem;
        padding: 1rem;
        background: var(--primary-soft);
        border-radius: 30px;
      }

      .score-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary);
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
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
      }

      .feed-icon.warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .feed-icon.success {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      /* Activity Logs */
      .log-item {
        font-size: 0.9rem;
        padding: 0.6rem 0;
        border-bottom: 1px dashed var(--glass-edge);
        display: flex;
        gap: 1rem;
      }

      .log-item:last-child {
        border-bottom: none;
      }

      /* Two column layout */
      .two-column {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-top: 1.5rem;
      }

      /* Modal */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
      }

      .modal-content {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 50px;
        padding: 2rem;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: var(--shadow-glow);
      }

      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
      }

      .modal-close {
        cursor: pointer;
        font-size: 1.5rem;
        color: var(--text-muted);
      }

      .modal-close:hover {
        color: var(--primary);
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
        .bulk-bar {
          flex-direction: column;
          align-items: flex-start;
        }
        .analytics-value {
          font-size: 1.5rem;
        }
        .two-column {
          grid-template-columns: 1fr;
        }
        .kyc-grid {
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

      /* Zoom controls */
      .zoom-controls {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        margin-top: 0.5rem;
      }

      .zoom-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        color: var(--text);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
      }

      .zoom-btn:hover {
        background: var(--primary);
        color: white;
      }
    </style>
  </head>
  <body class="dark">
    <div class="admin">
      <!-- SIDEBAR (active on KYC) -->
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
          <a href="./admin-kyc.php" class="sidebar-link active"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Management</span></a
          >
          <a href="./admin-wallet-control.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet Control</span></a
          >
          <a href="./admin-market-control.php" class="sidebar-link"
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

        <!-- KYC MANAGEMENT CONTENT -->
        <div class="content">
          <!-- Page Header -->
          <div class="page-header" data-aos="fade-up">
            <h1>KYC Verification</h1>
            <p>Review and manage user identity verification.</p>
            <div class="breadcrumb">
              <a href="#">Admin</a> <span>/</span>
              <span style="color: var(--primary)">KYC</span>
            </div>
          </div>

          <!-- Fraud Detection Panel -->
          <div class="fraud-grid" data-aos="fade-up">
            <div class="fraud-card warning" style="width: 100%">
              <div class="fraud-icon warning">
                <i class="fa-solid fa-shield-halved"></i>
              </div>
              <div class="fraud-content">
                <h4>Fraud Signals</h4>
                <p id="fraudSummary">No automated fraud signals configured.</p>
                <small id="fraudDetails">This panel can be wired to your risk engine later.</small>
              </div>
            </div>
          </div>

          <!-- Analytics Cards -->
          <div class="analytics-grid" data-aos="fade-up">
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-users"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 12.5%</span
                >
              </div>
              <div class="analytics-value" id="statTotal">—</div>
              <div class="analytics-label">Total Submissions</div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-clock"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 8</span
                >
              </div>
              <div class="analytics-value" id="statPending">—</div>
              <div class="analytics-label">Pending Reviews</div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-check-circle"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 5.2%</span
                >
              </div>
              <div class="analytics-value" id="statApproved">—</div>
              <div class="analytics-label">Approved Users</div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-times-circle"></i
                ></span>
                <span class="trend-down"
                  ><i class="fa-solid fa-arrow-down"></i> 2.1%</span
                >
              </div>
              <div class="analytics-value" id="statRejected">—</div>
              <div class="analytics-label">Rejected Users</div>
            </div>
          </div>

          <!-- Top Controls -->
          <div class="control-bar" data-aos="fade-up">
            <div class="control-search">
              <i class="fa-solid fa-search"></i>
              <input
                type="text"
                id="searchInput"
                placeholder="Search by user, name, email, ID..."
              />
            </div>
            <div class="control-filters">
              <select class="filter-select" id="statusFilter">
                <option>All Requests</option>
                <option>Pending</option>
                <option>Approved</option>
                <option>Rejected</option>
              </select>
              <select class="filter-select" id="sortFilter">
                <option>Newest</option>
                <option>Oldest</option>
              </select>
              <button class="btn-outline">
                <i class="fa-regular fa-file-pdf"></i> Report
              </button>
              <button class="btn-outline">
                <i class="fa-regular fa-file-excel"></i> Export
              </button>
            </div>
          </div>

          <!-- Bulk Actions Bar -->
          <div class="bulk-bar" id="bulkBar" style="display: none">
            <span><span id="selectedCount">0</span> KYC requests selected</span>
            <div style="display: flex; gap: 10px; flex-wrap: wrap">
              <button
                class="btn-primary"
                style="background: var(--success)"
                onclick="bulkApprove()"
              >
                Approve Selected
              </button>
              <button class="btn-danger" onclick="bulkReject()">
                Reject Selected
              </button>
            </div>
          </div>

          <!-- KYC Table -->
          <div class="table-wrapper" data-aos="fade-up">
            <table>
              <thead>
                <tr>
                  <th class="checkbox-col">
                    <input type="checkbox" id="selectAll" />
                  </th>
                  <th>User</th>
                  <th>Full Name</th>
                  <th>Country</th>
                  <th>Document Type</th>
                  <th>Submission Date</th>
                  <th>Status</th>
                  <th>Risk Level</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="kycTableBody">
                <!-- JS will populate -->
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="pagination" id="kycPagination" style="display: none">
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

          <!-- Live Feed & Activity Logs -->
          <div class="two-column">
            <!-- Live KYC Feed -->
            <div class="feed-panel" data-aos="fade-up">
              <h3>
                <i class="fa-solid fa-rss" style="color: var(--primary)"></i>
                Live KYC Feed
              </h3>
              <div id="liveFeed">
                <!-- filled by js -->
              </div>
            </div>

            <!-- Admin Activity Logs -->
            <div class="feed-panel" data-aos="fade-up">
              <h3>
                <i
                  class="fa-solid fa-history"
                  style="color: var(--primary)"
                ></i>
                Admin Actions
              </h3>
              <div id="activityLogs">
                <!-- filled by js -->
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- KYC Detail Modal (Full featured) -->
    <div class="modal-overlay" id="kycModal">
      <div class="modal-content kyc-modal">
        <div class="modal-header">
          <h2>KYC Verification Details</h2>
          <span class="modal-close" id="closeModal">&times;</span>
        </div>
        <div id="kycModalBody">
          <!-- Dynamic content will be inserted here -->
        </div>
      </div>
    </div>

    <!-- AI Scanning Modal -->
    <div class="modal-overlay" id="aiScanModal">
      <div class="modal-content" style="max-width: 500px">
        <div class="modal-header">
          <h3>AI Document Analysis</h3>
          <span class="modal-close" id="closeAIScan">&times;</span>
        </div>
        <div id="aiScanContent">
          <div class="ai-scanning">
            <div class="ai-step" id="aiStep1">
              <div class="ai-icon" id="aiIcon1">
                <i class="fa-solid fa-circle-notch"></i>
              </div>
              <span>Scanning document...</span>
              <div class="ai-progress">
                <div
                  class="ai-progress-bar"
                  id="aiProgress1"
                  style="width: 0%"
                ></div>
              </div>
            </div>
            <div class="ai-step" id="aiStep2">
              <div class="ai-icon" id="aiIcon2">
                <i class="fa-solid fa-circle-notch"></i>
              </div>
              <span>Matching face...</span>
              <div class="ai-progress">
                <div
                  class="ai-progress-bar"
                  id="aiProgress2"
                  style="width: 0%"
                ></div>
              </div>
            </div>
            <div class="ai-step" id="aiStep3">
              <div class="ai-icon" id="aiIcon3">
                <i class="fa-solid fa-circle-notch"></i>
              </div>
              <span>Analyzing authenticity...</span>
              <div class="ai-progress">
                <div
                  class="ai-progress-bar"
                  id="aiProgress3"
                  style="width: 0%"
                ></div>
              </div>
            </div>
          </div>
          <div class="match-score" id="matchScore" style="display: none">
            <div class="score-value" id="scoreValue">92%</div>
            <div>Match Score</div>
            <div style="margin-top: 0.5rem" id="riskLevel">
              Risk Level: <span class="risk-badge risk-low">Low</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal-overlay" id="rejectModal">
      <div class="modal-content" style="max-width: 450px">
        <div class="modal-header">
          <h3>Reject KYC</h3>
          <span class="modal-close" id="closeReject">&times;</span>
        </div>
        <div style="margin: 1rem 0">
          <label style="color: var(--text-muted)">Reason for rejection:</label>
          <select
            id="rejectReasonSelect"
            style="
              width: 100%;
              margin-top: 0.5rem;
              background: var(--glass-bg);
              border: 1px solid var(--glass-edge);
              border-radius: 40px;
              padding: 0.8rem;
              color: var(--text);
            "
          >
            <option value="Blurry image">Blurry image</option>
            <option value="Fake document">Fake document</option>
            <option value="Mismatch">Information mismatch</option>
            <option value="Incomplete data">Incomplete data</option>
            <option value="Expired document">Expired document</option>
            <option value="Face not matching">Face not matching</option>
          </select>
        </div>
        <div
          style="
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
          "
        >
          <button class="btn-danger" id="confirmReject">Reject KYC</button>
          <button class="btn-outline" id="cancelReject">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
      <div class="modal-content confirmation-modal">
        <div class="modal-header">
          <h3>Confirm Action</h3>
          <span class="modal-close" id="closeConfirm">&times;</span>
        </div>
        <p id="confirmMessage">Are you sure you want to approve this KYC?</p>
        <div
          style="
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
          "
        >
          <button class="btn-primary" id="confirmYes">Yes, Confirm</button>
          <button class="btn-outline" id="confirmNo">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Security Controls Modal -->
    <div class="modal-overlay" id="securityModal">
      <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
          <h3>Security Controls</h3>
          <span class="modal-close" id="closeSecurity">&times;</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1rem">
          <button
            class="btn-warning"
            style="width: 100%"
            onclick="requestReverification()"
          >
            <i class="fa-solid fa-rotate"></i> Request Re-verification
          </button>
          <button
            class="btn-danger"
            style="width: 100%"
            onclick="lockAccount()"
          >
            <i class="fa-solid fa-lock"></i> Lock Account
          </button>
          <button
            class="btn-primary"
            style="width: 100%"
            onclick="escalateReview()"
          >
            <i class="fa-solid fa-arrow-up"></i> Escalate to Manual Review
          </button>
        </div>
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

      const API_BASE = "./api/kyc_api.php";
      const CSRF_TOKEN = <?php echo json_encode(admin_csrf_token(), JSON_UNESCAPED_SLASHES); ?>;

      // Real KYC data loaded from server.
      let kycRequests = [];

      async function loadKyc() {
        const statusFilter = (document.getElementById("statusFilter")?.value || "All Requests").toLowerCase();
        const sortBy = (document.getElementById("sortFilter")?.value || "Newest").toLowerCase();
        const q = (document.getElementById("searchInput")?.value || "").trim();
        const url = new URL(API_BASE, window.location.href);
        url.searchParams.set("action", "list");
        url.searchParams.set("per_page", "200");
        if (q) url.searchParams.set("q", q);
        if (statusFilter.includes("pending")) url.searchParams.set("status", "pending");
        else if (statusFilter.includes("approved")) url.searchParams.set("status", "approved");
        else if (statusFilter.includes("rejected")) url.searchParams.set("status", "rejected");
        else url.searchParams.set("status", "all");
        url.searchParams.set("sort", sortBy.includes("oldest") ? "oldest" : "newest");

        const r = await fetch(url.toString(), { credentials: "same-origin" });
        const j = await r.json();
        if (!j || !j.ok) {
          kycRequests = [];
          renderTable();
          return;
        }
        kycRequests = Array.isArray(j.items) ? j.items : [];
        renderTable();
      }

      async function loadStats() {
        try {
          const url = new URL(API_BASE, window.location.href);
          url.searchParams.set("action", "stats");
          const r = await fetch(url.toString(), { credentials: "same-origin" });
          const j = await r.json();
          if (!j || !j.ok || !j.stats) return;
          const s = j.stats;
          const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(val ?? "—");
          };
          set("statTotal", s.total);
          set("statPending", s.pending);
          set("statApproved", s.approved);
          set("statRejected", s.rejected);
        } catch (e) {}
      }

      async function loadFeedAndActivity() {
        const feedEl = document.getElementById("liveFeed");
        const actEl = document.getElementById("activityLogs");
        try {
          // Feed
          if (feedEl) feedEl.innerHTML = "";
          const fu = new URL(API_BASE, window.location.href);
          fu.searchParams.set("action", "feed");
          fu.searchParams.set("limit", "8");
          const fr = await fetch(fu.toString(), { credentials: "same-origin" });
          const fj = await fr.json();
          const items = fj && fj.ok && Array.isArray(fj.items) ? fj.items : [];
          if (feedEl) {
            if (items.length === 0) {
              feedEl.innerHTML = '<div class="feed-item"><span class="feed-icon"><i class="fa-solid fa-circle-info"></i></span><span>No recent KYC activity</span><span style="color: var(--text-muted); margin-left:auto">—</span></div>';
            } else {
              items.forEach((it) => {
                const type = String(it.type || "");
                const user = String(it.user || "user");
                const time = String(it.time_ago || "");
                const reason = String(it.reason || "");
                let icon = "fa-upload";
                let iconClass = "";
                let text = "submitted KYC";
                if (type === "approved") {
                  icon = "fa-check";
                  iconClass = "success";
                  text = "KYC approved";
                } else if (type === "rejected") {
                  icon = "fa-times";
                  iconClass = "warning";
                  text = "KYC rejected" + (reason ? ` (${reason})` : "");
                }
                const div = document.createElement("div");
                div.className = "feed-item";
                div.innerHTML = `
                  <span class="feed-icon ${iconClass}"><i class="fa-solid ${icon}"></i></span>
                  <span><strong>${user}</strong> ${text}</span>
                  <span style="color: var(--text-muted); margin-left: auto">${time || "—"}</span>
                `;
                feedEl.appendChild(div);
              });
            }
          }
        } catch (e) {}

        try {
          // Admin activity
          if (actEl) actEl.innerHTML = "";
          const au = new URL(API_BASE, window.location.href);
          au.searchParams.set("action", "activity");
          au.searchParams.set("limit", "10");
          const ar = await fetch(au.toString(), { credentials: "same-origin" });
          const aj = await ar.json();
          const items = aj && aj.ok && Array.isArray(aj.items) ? aj.items : [];
          if (actEl) {
            if (items.length === 0) {
              actEl.innerHTML = '<div class="log-item"><span style="color: var(--text-muted)">•</span> No admin review actions yet</div>';
            } else {
              items.forEach((it) => {
                const st = String(it.status || "");
                const user = String(it.user || "user");
                const reason = String(it.reason || "");
                const time = String(it.time_ago || "");
                let icon = "⏳";
                let color = "var(--warning)";
                let label = "Reviewed";
                if (st === "approved") {
                  icon = "✓";
                  color = "var(--success)";
                  label = "Approved";
                } else if (st === "rejected") {
                  icon = "✗";
                  color = "var(--danger)";
                  label = "Rejected";
                }
                const detailText = st === "rejected" && reason ? ` (${reason})` : "";
                const div = document.createElement("div");
                div.className = "log-item";
                div.innerHTML = `<span style="color: ${color}">${icon}</span> ${label} KYC for ${user}${detailText} <span style="color: var(--text-muted); margin-left:auto">${time || ""}</span>`;
                actEl.appendChild(div);
              });
            }
          }
        } catch (e) {}
      }

      // Document type icons mapping
      function getDocIconClass(type) {
        switch (type) {
          case "Passport":
            return "doc-passport";
          case "Driver License":
            return "doc-license";
          default:
            return "doc-id";
        }
      }

      function getDocIcon(type) {
        switch (type) {
          case "Passport":
            return "fa-passport";
          case "Driver License":
            return "fa-id-card";
          default:
            return "fa-id-card";
        }
      }

      // Render table
      function renderTable() {
        const tbody = document.getElementById("kycTableBody");
        tbody.innerHTML = "";
        kycRequests.forEach((kyc) => {
          const statusClass = `status-${kyc.status.toLowerCase()}`;
          const riskClass = `risk-${kyc.riskLevel.toLowerCase()}`;
          const docIconClass = getDocIconClass(kyc.documentType);
          const docIcon = getDocIcon(kyc.documentType);

          const row = document.createElement("tr");
          if (kyc.riskLevel === "High") {
            row.style.background = "rgba(239, 68, 68, 0.05)";
          } else if (kyc.riskLevel === "Medium") {
            row.style.background = "rgba(249, 115, 22, 0.05)";
          }

          row.innerHTML = `
          <td class="checkbox-col"><input type="checkbox" class="kyc-checkbox" data-id="${kyc.id}"></td>
          <td><strong>${kyc.user}</strong></td>
          <td>${kyc.fullName}</td>
          <td>${kyc.country}</td>
          <td>
            <span class="doc-type">
              <span class="doc-icon ${docIconClass}"><i class="fa-solid ${docIcon}"></i></span>
              ${kyc.documentType}
            </span>
          </td>
          <td>${kyc.submissionDate}</td>
          <td><span class="status-badge ${statusClass}">${kyc.status}</span></td>
          <td><span class="risk-badge ${riskClass}">${kyc.riskLevel}</span></td>
          <td>
            <div class="actions-dropdown">
              <button class="actions-btn" onclick="toggleDropdown(this)"><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
              <div class="dropdown-menu">
                <a class="dropdown-item" onclick="viewKYCDocuments(${kyc.id})"><i class="fa-regular fa-eye"></i> View Documents</a>
                <a class="dropdown-item" onclick="approveKYC(${kyc.id})"><i class="fa-regular fa-circle-check"></i> Approve</a>
                <a class="dropdown-item" onclick="showRejectModal(${kyc.id})"><i class="fa-regular fa-circle-xmark"></i> Reject</a>
                <a class="dropdown-item" onclick="flagKYC(${kyc.id})"><i class="fa-solid fa-flag"></i> Flag as Suspicious</a>
                <a class="dropdown-item" onclick="showSecurityModal(${kyc.id})"><i class="fa-solid fa-shield"></i> Security Controls</a>
              </div>
            </div>
          </td>
        `;
          tbody.appendChild(row);
        });
      }

      // Dropdown toggle
      window.toggleDropdown = function (btn) {
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

      // Select all
      const selectAll = document.getElementById("selectAll");
      selectAll.addEventListener("change", (e) => {
        document
          .querySelectorAll(".kyc-checkbox")
          .forEach((cb) => (cb.checked = e.target.checked));
        updateBulkBar();
      });

      function updateBulkBar() {
        const checked = document.querySelectorAll(
          ".kyc-checkbox:checked",
        ).length;
        const bulkBar = document.getElementById("bulkBar");
        if (checked > 0) {
          bulkBar.style.display = "flex";
          document.getElementById("selectedCount").innerText = checked;
        } else {
          bulkBar.style.display = "none";
        }
      }

      document.addEventListener("change", (e) => {
        if (e.target.classList.contains("kyc-checkbox")) {
          updateBulkBar();
        }
      });

      // View KYC Documents with AI scanning
      window.viewKYCDocuments = function (id) {
        const kyc = kycRequests.find((k) => k.id === id);
        if (!kyc) return;

        const modalBody = document.getElementById("kycModalBody");

        const docId = (kyc.documents && (kyc.documents.id || kyc.documents.passport || kyc.documents.license)) ? (kyc.documents.id || kyc.documents.passport || kyc.documents.license) : "";
        const selfie = (kyc.documents && kyc.documents.selfie) ? kyc.documents.selfie : "";
        const address = (kyc.documents && kyc.documents.address) ? kyc.documents.address : "";

        modalBody.innerHTML = `
        <div class="kyc-grid">
          <div class="document-preview">
            <h4><i class="fa-regular fa-passport"></i> Identity Document</h4>
            <div class="document-image" onclick="zoomImage('${docId}')">
              ${docId ? `<img src="${docId}" alt="Identity Document">` : '<div style="color:var(--text-muted)">No document</div>'}
            </div>
            <div class="zoom-controls">
              <button class="zoom-btn" onclick="zoomIn()"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
              <button class="zoom-btn" onclick="zoomOut()"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
              <button class="zoom-btn" onclick="fullscreen()"><i class="fa-solid fa-expand"></i></button>
            </div>
          </div>
          
          <div class="document-preview">
            <h4><i class="fa-regular fa-user"></i> Selfie Verification</h4>
            <div class="document-image" onclick="zoomImage('${selfie}')">
              ${selfie ? `<img src="${selfie}" alt="Selfie">` : '<div style="color:var(--text-muted)">No selfie</div>'}
            </div>
          </div>
          
          <div class="document-preview">
            <h4><i class="fa-regular fa-address-card"></i> Proof of Address</h4>
            <div class="document-image" onclick="zoomImage('${address}')">
              ${address ? `<img src="${address}" alt="Proof of Address">` : '<div style="color:var(--text-muted)">No address proof</div>'}
            </div>
          </div>
          
          <div class="document-preview">
            <h4><i class="fa-solid fa-robot"></i> AI Verification</h4>
            <div style="padding: 1rem;">
              <button class="btn-primary" onclick="startAIScan(${id})" style="width: 100%;">
                <i class="fa-solid fa-microchip"></i> Run AI Document Analysis
              </button>
              
              <div style="margin-top: 1rem;">
                <p><strong>User Information:</strong></p>
                <p>Name: ${kyc.fullName}</p>
                <p>Country: ${kyc.country}</p>
                <p>Document: ${kyc.documentType}</p>
                <p>Status: <span class="status-badge status-${kyc.status.toLowerCase()}">${kyc.status}</span></p>
                <p>Risk Level: <span class="risk-badge risk-${kyc.riskLevel.toLowerCase()}">${kyc.riskLevel}</span></p>
              </div>
            </div>
          </div>
        </div>
      `;

        document.getElementById("kycModal").style.display = "flex";
      };

      // Zoom functionality (simulated)
      window.zoomImage = function (src) {
        alert("🔍 Zoom view would open here for: " + src);
      };

      window.zoomIn = function () {
        alert("🔍 Zooming in (simulated)");
      };

      window.zoomOut = function () {
        alert("🔍 Zooming out (simulated)");
      };

      window.fullscreen = function () {
        alert("🖥️ Fullscreen view (simulated)");
      };

      // AI Scan simulation
      window.startAIScan = function (id) {
        document.getElementById("kycModal").style.display = "none";
        document.getElementById("aiScanModal").style.display = "flex";

        // Reset AI scan
        for (let i = 1; i <= 3; i++) {
          document.getElementById(`aiIcon${i}`).className = "ai-icon";
          document.getElementById(`aiIcon${i}`).innerHTML =
            '<i class="fa-solid fa-circle-notch"></i>';
          document.getElementById(`aiProgress${i}`).style.width = "0%";
        }
        document.getElementById("matchScore").style.display = "none";

        // Step 1: Scanning document
        setTimeout(() => {
          document.getElementById("aiIcon1").className = "ai-icon completed";
          document.getElementById("aiIcon1").innerHTML =
            '<i class="fa-solid fa-check"></i>';
          document.getElementById("aiProgress1").style.width = "100%";
        }, 1500);

        // Step 2: Matching face
        setTimeout(() => {
          document.getElementById("aiIcon2").className = "ai-icon completed";
          document.getElementById("aiIcon2").innerHTML =
            '<i class="fa-solid fa-check"></i>';
          document.getElementById("aiProgress2").style.width = "100%";
        }, 3000);

        // Step 3: Analyzing authenticity
        setTimeout(() => {
          document.getElementById("aiIcon3").className = "ai-icon completed";
          document.getElementById("aiIcon3").innerHTML =
            '<i class="fa-solid fa-check"></i>';
          document.getElementById("aiProgress3").style.width = "100%";
        }, 4500);

        // Show results
        setTimeout(() => {
          document.getElementById("matchScore").style.display = "block";
        }, 5000);
      };

      // Approve KYC
      window.approveKYC = function (id) {
        const kyc = kycRequests.find((k) => k.id === id);
        if (!kyc) return;
        fetch(API_BASE, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "approve", id, csrf_token: CSRF_TOKEN }),
        })
          .then((r) => r.json())
          .then((j) => {
            if (!j || !j.ok) {
              alert("Could not approve KYC.");
              return;
            }
            addActivityLog("approved", kyc.user);
            addToLiveFeed("approved", kyc.user);
            loadKyc();
          })
          .catch(() => alert("Could not approve KYC."));
      };

      // Reject KYC
      let currentRejectId = null;

      window.showRejectModal = function (id) {
        currentRejectId = id;
        document.getElementById("rejectModal").style.display = "flex";
      };

      document.getElementById("confirmReject").addEventListener("click", () => {
        const reason = document.getElementById("rejectReasonSelect").value;
        const kyc = kycRequests.find((k) => k.id === currentRejectId);

        if (kyc) {
          fetch(API_BASE, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "reject",
              id: currentRejectId,
              reason,
              csrf_token: CSRF_TOKEN,
            }),
          })
            .then((r) => r.json())
            .then((j) => {
              if (!j || !j.ok) {
                alert("Could not reject KYC.");
                return;
              }
              addActivityLog("rejected", kyc.user, reason);
              addToLiveFeed("rejected", kyc.user);
              loadKyc();
            })
            .catch(() => alert("Could not reject KYC."));
        }

        document.getElementById("rejectModal").style.display = "none";
      });

      // Flag KYC
      window.flagKYC = function (id) {
        const kyc = kycRequests.find((k) => k.id === id);
        if (kyc) {
          kyc.riskLevel = "High";

          addActivityLog("flagged", kyc.user, "Suspicious");
          addToLiveFeed("flagged", kyc.user);

          renderTable();
          alert(`⚑ KYC flagged as suspicious for ${kyc.user}`);
        }
      };

      // Security controls
      let currentSecurityId = null;

      window.showSecurityModal = function (id) {
        currentSecurityId = id;
        document.getElementById("securityModal").style.display = "flex";
      };

      window.requestReverification = function () {
        const kyc = kycRequests.find((k) => k.id === currentSecurityId);
        alert(`📋 Re-verification requested for ${kyc.user}`);
        addActivityLog("reverification", kyc.user);
        document.getElementById("securityModal").style.display = "none";
      };

      window.lockAccount = function () {
        const kyc = kycRequests.find((k) => k.id === currentSecurityId);
        alert(`🔒 Account locked for ${kyc.user}`);
        addActivityLog("lock", kyc.user);
        document.getElementById("securityModal").style.display = "none";
      };

      window.escalateReview = function () {
        const kyc = kycRequests.find((k) => k.id === currentSecurityId);
        alert(`⬆️ KYC escalated for manual review for ${kyc.user}`);
        addActivityLog("escalate", kyc.user);
        document.getElementById("securityModal").style.display = "none";
      };

      // Bulk actions
      window.bulkApprove = function () {
        const selected = document.querySelectorAll(".kyc-checkbox:checked");
        selected.forEach((cb) => {
          const id = parseInt(cb.dataset.id);
          cb.checked = false;
          fetch(API_BASE, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "approve", id, csrf_token: CSRF_TOKEN }),
          }).catch(() => {});
        });
        updateBulkBar();
        setTimeout(loadKyc, 350);
      };

      window.bulkReject = function () {
        const selected = document.querySelectorAll(".kyc-checkbox:checked");
        selected.forEach((cb) => {
          const id = parseInt(cb.dataset.id);
          cb.checked = false;
          fetch(API_BASE, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "reject", id, reason: "Bulk action", csrf_token: CSRF_TOKEN }),
          }).catch(() => {});
        });
        updateBulkBar();
        setTimeout(loadKyc, 350);
      };

      // Add to activity log
      function addActivityLog(action, user, details = "") {
        const logDiv = document.getElementById("activityLogs");
        const newLog = document.createElement("div");
        newLog.className = "log-item";

        const actionIcons = {
          approved: "✓",
          rejected: "✗",
          flagged: "⚑",
          reverification: "📋",
          lock: "🔒",
          escalate: "⬆️",
        };

        const actionColors = {
          approved: "var(--success)",
          rejected: "var(--danger)",
          flagged: "var(--orange)",
          reverification: "var(--primary)",
          lock: "var(--danger)",
          escalate: "var(--warning)",
        };

        const detailText = details ? ` (${details})` : "";
        newLog.innerHTML = `<span style="color: ${actionColors[action]};">${actionIcons[action]}</span> ${action.charAt(0).toUpperCase() + action.slice(1)} KYC for ${user}${detailText} by Admin`;
        logDiv.prepend(newLog);

        if (logDiv.children.length > 5) {
          logDiv.removeChild(logDiv.lastChild);
        }
      }

      // Add to live feed
      function addToLiveFeed(action, user) {
        const feed = document.getElementById("liveFeed");
        const feedItem = document.createElement("div");
        feedItem.className = "feed-item";

        let icon = "fa-upload";
        let iconClass = "";

        if (action === "approved") {
          icon = "fa-check";
          iconClass = "success";
        } else if (action === "rejected") {
          icon = "fa-times";
          iconClass = "warning";
        } else if (action === "flagged") {
          icon = "fa-flag";
          iconClass = "warning";
        }

        feedItem.innerHTML = `
        <span class="feed-icon ${iconClass}"><i class="fa-solid ${icon}"></i></span>
        <span><strong>${user}</strong> KYC ${action}</span>
        <span style="color: var(--text-muted); margin-left: auto;">just now</span>
      `;

        feed.prepend(feedItem);
        if (feed.children.length > 5) {
          feed.removeChild(feed.lastChild);
        }
      }

      // Search functionality
      document.getElementById("searchInput").addEventListener("input", (e) => {
        // Server-side search (keeps pagination consistent).
        loadKyc();
      });

      // Filter functionality
      document
        .getElementById("statusFilter")
        .addEventListener("change", (e) => {
          loadKyc();
        });

      // Sort functionality
      document.getElementById("sortFilter").addEventListener("change", (e) => {
        loadKyc();
      });

      // Close modals
      document.getElementById("closeModal").addEventListener("click", () => {
        document.getElementById("kycModal").style.display = "none";
      });

      document.getElementById("closeAIScan").addEventListener("click", () => {
        document.getElementById("aiScanModal").style.display = "none";
      });

      document.getElementById("closeReject").addEventListener("click", () => {
        document.getElementById("rejectModal").style.display = "none";
      });

      document.getElementById("cancelReject").addEventListener("click", () => {
        document.getElementById("rejectModal").style.display = "none";
      });

      document.getElementById("closeConfirm").addEventListener("click", () => {
        document.getElementById("confirmModal").style.display = "none";
      });

      document.getElementById("closeSecurity").addEventListener("click", () => {
        document.getElementById("securityModal").style.display = "none";
      });

      window.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal-overlay")) {
          e.target.style.display = "none";
        }
      });

      // AI Assistant
      document.getElementById("aiBtn").addEventListener("click", () => {
        alert(
          "🤖 AI Compliance Assistant:\n\n• 12 pending KYC reviews\n• 3 high-risk verifications detected\n• 2 duplicate identity cases\n• 1 suspicious document flagged\n• Average processing time: 4.2 minutes",
        );
      });

      // Live feed simulation
      setInterval(() => {
        const users = ["john.crypto", "sarah.eth", "mike.sol", "emily.crypto"];
        const actions = [
          "submitted KYC",
          "KYC approved",
          "KYC rejected",
          "submitted documents",
        ];
        const user = users[Math.floor(Math.random() * users.length)];
        const action = actions[Math.floor(Math.random() * actions.length)];

        const feed = document.getElementById("liveFeed");
        const feedItem = document.createElement("div");
        feedItem.className = "feed-item";

        let icon = "fa-upload";
        let iconClass = "";

        if (action.includes("approved")) {
          icon = "fa-check";
          iconClass = "success";
        } else if (action.includes("rejected")) {
          icon = "fa-times";
          iconClass = "warning";
        }

        feedItem.innerHTML = `
        <span class="feed-icon ${iconClass}"><i class="fa-solid ${icon}"></i></span>
        <span><strong>${user}</strong> ${action}</span>
        <span style="color: var(--text-muted); margin-left: auto;">just now</span>
      `;

        feed.prepend(feedItem);
        if (feed.children.length > 5) {
          feed.removeChild(feed.lastChild);
        }
      }, 10000);

      // Initialize
      loadKyc();
      loadStats();
      loadFeedAndActivity();

      // Keep dashboard widgets fresh.
      setInterval(() => {
        loadStats();
        loadFeedAndActivity();
      }, 15000);

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
