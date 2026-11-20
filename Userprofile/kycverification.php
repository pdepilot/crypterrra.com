<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_country_nationality_data.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$fullNameSession = isset($_SESSION['user_full_name']) && is_string($_SESSION['user_full_name']) ? $_SESSION['user_full_name'] : '';

$errorMsg = '';
$successMsg = '';
$kycRow = null;
$kycStatus = 'not_submitted';
$kycRejectReason = '';

try {
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  $schema = (string)$DB_NAME;
  ensure_users_kyc_columns($db, $schema);
  ensure_kyc_requests_table($db);

  // Handle submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    if (!user_verify_csrf($csrf)) {
      $errorMsg = 'Security verification failed. Please refresh and try again.';
    } else {
      // Upload helper
      $uploadBaseFs = dirname(__DIR__) . '/uploads/kyc/user_' . $userId;
      $uploadBaseUrl = '../uploads/kyc/user_' . $userId;
      if (!is_dir($uploadBaseFs)) {
        @mkdir($uploadBaseFs, 0775, true);
      }

      $maxBytes = 10 * 1024 * 1024; // 10MB
      $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
      $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];

      $moveUpload = function (string $field, string $prefix, bool $required, array $mimeAllow, array $extAllow) use ($uploadBaseFs, $uploadBaseUrl, $maxBytes, &$errorMsg): ?string {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
          return $required ? null : '';
        }
        $f = $_FILES[$field];
        $err = isset($f['error']) ? (int)$f['error'] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
          return $required ? null : '';
        }
        if ($err !== UPLOAD_ERR_OK) {
          $errorMsg = 'File upload failed. Please try again.';
          return null;
        }
        $size = isset($f['size']) ? (int)$f['size'] : 0;
        if ($size <= 0 || $size > $maxBytes) {
          $errorMsg = 'One of the uploaded files is too large (max 10MB).';
          return null;
        }
        $orig = isset($f['name']) && is_string($f['name']) ? $f['name'] : 'file';
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $extAllow, true)) {
          $errorMsg = 'Unsupported file type. Please upload JPG, PNG or PDF.';
          return null;
        }
        $tmp = isset($f['tmp_name']) && is_string($f['tmp_name']) ? $f['tmp_name'] : '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
          $errorMsg = 'Invalid upload.';
          return null;
        }
        $mime = '';
        if (function_exists('finfo_open')) {
          $fi = finfo_open(FILEINFO_MIME_TYPE);
          if ($fi) {
            $mime = (string)finfo_file($fi, $tmp);
            finfo_close($fi);
          }
        }
        if ($mime !== '' && !in_array($mime, $mimeAllow, true)) {
          $errorMsg = 'Unsupported file content.';
          return null;
        }
        $safe = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', pathinfo($orig, PATHINFO_FILENAME) ?? 'file');
        $safe = substr((string)$safe, 0, 60);
        $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe . '.' . $ext;
        $destFs = $uploadBaseFs . '/' . $filename;
        if (!@move_uploaded_file($tmp, $destFs)) {
          $errorMsg = 'Could not save uploaded file.';
          return null;
        }
        return $uploadBaseUrl . '/' . $filename;
      };

      $docIdUrl = $moveUpload('doc_id', 'id', true, $allowedMime, $allowedExt);
      $proofUrl = $moveUpload('proof_address', 'address', true, $allowedMime, $allowedExt);
      $selfieUrl = $moveUpload('selfie', 'selfie', false, ['image/jpeg', 'image/png'], ['jpg', 'jpeg', 'png']);

      if ($errorMsg === '' && $docIdUrl !== null && $proofUrl !== null && $selfieUrl !== null) {
        $data = [
          'full_name' => (string)($_POST['full_name'] ?? ''),
          'dob' => (string)($_POST['dob'] ?? ''),
          'country' => (string)($_POST['country'] ?? ''),
          'nationality' => (string)($_POST['nationality'] ?? ''),
          'address_line' => (string)($_POST['address_line'] ?? ''),
          'city_state_postal' => (string)($_POST['city_state_postal'] ?? ''),
          'id_number' => (string)($_POST['id_number'] ?? ''),
          'source_of_funds' => (string)($_POST['source_of_funds'] ?? ''),
          'transaction_volume' => (string)($_POST['transaction_volume'] ?? ''),
          'purpose' => (string)($_POST['purpose'] ?? ''),
        ];
        $files = [
          'doc_id_path' => $docIdUrl,
          'proof_address_path' => $proofUrl,
          'selfie_path' => $selfieUrl,
        ];
        $res = user_submit_kyc_request($db, $schema, $userId, $data, $files);
        if (!($res['ok'] ?? false)) {
          $errorMsg = 'Could not submit KYC. Please try again.';
        } else {
          $successMsg = 'KYC submitted successfully. Our team will review your application.';
        }
      }
    }
  }

  // Fetch latest
  $kycRow = user_fetch_kyc_request($db, $userId);
  if ($kycRow && isset($kycRow['status'])) {
    $kycStatus = strtolower((string)$kycRow['status']);
    $kycRejectReason = (string)($kycRow['rejection_reason'] ?? '');
  } else {
    // fall back to users.kyc_status
    $st = $db->prepare('SELECT kyc_status FROM users WHERE id = ? LIMIT 1');
    if ($st) {
      $st->bind_param('i', $userId);
      $st->execute();
      $st->store_result();
      $s = '';
      $st->bind_result($s);
      $st->fetch();
      $st->free_result();
      $st->close();
      $kycStatus = $s !== '' ? strtolower($s) : 'not_submitted';
    }
  }
  $db->close();
} catch (Throwable $e) {
  $errorMsg = 'KYC is temporarily unavailable. Please try again later.';
  error_log('[kyc] ' . $e->getMessage());
}

function kyc_h(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function kyc_status_ui(string $status): array {
  $s = strtolower(trim($status));
  return match ($s) {
    'approved' => [
      'icon' => 'fa-solid fa-circle-check',
      'icon_color' => 'var(--primary-color)',
      'title' => 'Verification Approved',
      'message' => 'Your identity has been verified. Full features are now unlocked.',
      'badge' => 'Approved',
      'badge_bg' => 'var(--primary-color)',
    ],
    'rejected' => [
      'icon' => 'fa-solid fa-circle-xmark',
      'icon_color' => 'var(--danger-color)',
      'title' => 'Verification Rejected',
      'message' => 'Your submission was rejected. Please correct the issue and resubmit.',
      'badge' => 'Rejected',
      'badge_bg' => 'var(--danger-color)',
    ],
    'pending' => [
      'icon' => 'fa-solid fa-clock',
      'icon_color' => 'var(--warning-color)',
      'title' => 'Verification Pending',
      'message' => 'Your KYC application is being reviewed. This usually takes 24-48 hours.',
      'badge' => 'Under Review',
      'badge_bg' => 'var(--warning-color)',
    ],
    default => [
      'icon' => 'fa-solid fa-id-card',
      'icon_color' => 'var(--text-muted)',
      'title' => 'Verification Not Submitted',
      'message' => 'Submit your documents to start verification.',
      'badge' => 'Not Submitted',
      'badge_bg' => 'var(--surface)',
    ],
  };
}

require_once __DIR__ . '/includes/kyc_sidebar_state.php';
$badge = kyc_sidebar_badge_for_session();

$ui = kyc_status_ui($kycStatus);
$prefillName = $kycRow && isset($kycRow['full_name']) && (string)$kycRow['full_name'] !== '' ? (string)$kycRow['full_name'] : $fullNameSession;
$prefillDob = $kycRow && isset($kycRow['dob']) ? (string)$kycRow['dob'] : '';
$prefillCountry = $kycRow && isset($kycRow['country']) ? (string)$kycRow['country'] : '';
$prefillNationality = $kycRow && isset($kycRow['nationality']) ? (string)$kycRow['nationality'] : '';
$prefillCountry = kyc_normalize_country_prefill($prefillCountry);
$prefillNationality = kyc_normalize_nationality_prefill($prefillNationality);
$kycCountryNames = array_column(kyc_countries_sorted(), 'name');
$kycNationalityList = kyc_nationalities_sorted();
$prefillCountryInList = $prefillCountry !== '' && in_array($prefillCountry, $kycCountryNames, true);
$prefillNationalityInList = $prefillNationality !== '' && in_array($prefillNationality, $kycNationalityList, true);
$prefillAddress = $kycRow && isset($kycRow['address_line']) ? (string)$kycRow['address_line'] : '';
$prefillCity = $kycRow && isset($kycRow['city_state_postal']) ? (string)$kycRow['city_state_postal'] : '';
$prefillIdNum = $kycRow && isset($kycRow['id_number']) ? (string)$kycRow['id_number'] : '';
$prefillSof = $kycRow && isset($kycRow['source_of_funds']) ? (string)$kycRow['source_of_funds'] : '';
$prefillTv = $kycRow && isset($kycRow['transaction_volume']) ? (string)$kycRow['transaction_volume'] : '';
$prefillPurpose = $kycRow && isset($kycRow['purpose']) ? (string)$kycRow['purpose'] : '';

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <title>Crypterra | KYC Verification</title>
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

      /* KYC Page Specific Styles */
      .kyc-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 28px;
        margin-top: 70px;
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

      /* Status Cards */
      .status-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 28px;
        margin-bottom: 32px;
        text-align: center;
      }

      .status-icon {
        font-size: 4rem;
        margin-bottom: 16px;
      }

      .status-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 8px;
      }

      .status-message {
        color: var(--text-muted);
        margin-bottom: 20px;
      }

      .status-badge-large {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 40px;
        font-weight: 600;
        background: var(--warning-color);
        color: white;
      }

      /* Progress Steps */
      .progress-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 40px;
        position: relative;
      }

      .progress-steps::before {
        content: "";
        position: absolute;
        top: 30px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--glass-edge);
        z-index: 1;
      }

      .step {
        text-align: center;
        position: relative;
        z-index: 2;
        flex: 1;
      }

      .step-circle {
        width: 60px;
        height: 60px;
        background: var(--surface);
        border: 2px solid var(--glass-edge);
        border-radius: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-weight: 700;
        font-size: 1.2rem;
        transition: all 0.3s;
      }

      .step.active .step-circle {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: 0 0 20px var(--primary-glow);
      }

      .step.completed .step-circle {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
      }

      .step.completed .step-circle i {
        font-size: 1.5rem;
      }

      .step-label {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-muted);
      }

      .step.active .step-label {
        color: var(--primary-color);
      }

      /* Form Sections */
      .form-section {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 28px;
        margin-bottom: 24px;
      }

      .section-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--primary-light);
      }

      .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
      }

      .form-group {
        margin-bottom: 20px;
      }

      .form-group.full-width {
        grid-column: span 2;
      }

      .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-secondary);
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
        font-size: 1rem;
        transition: all 0.2s;
      }

      .form-group input:focus,
      .form-group select:focus,
      .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
      }

      /* Document Upload */
      .upload-area {
        background: var(--input-bg);
        border: 2px dashed var(--glass-edge);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
      }

      .upload-area:hover {
        border-color: var(--primary-color);
        background: var(--primary-soft);
      }

      .upload-area i {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 12px;
      }

      .upload-area p {
        color: var(--text-muted);
      }

      .file-info {
        margin-top: 12px;
        font-size: 0.85rem;
        color: var(--primary-light);
      }

      /* Submit Button */
      .submit-btn {
        width: 100%;
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border: none;
        padding: 16px;
        border-radius: 60px;
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        margin-top: 20px;
      }

      .submit-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      .submit-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      /* Benefits List */
      .benefits-list {
        background: var(--primary-soft);
        border-radius: 20px;
        padding: 20px;
        margin-top: 24px;
      }

      .benefits-list h4 {
        margin-bottom: 12px;
        color: var(--primary-light);
      }

      .benefits-list ul {
        list-style: none;
        padding-left: 0;
      }

      .benefits-list li {
        padding: 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .benefits-list li i {
        color: var(--primary-color);
        width: 24px;
      }

      /* Modal */
      .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
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
        font-size: 4rem;
        margin-bottom: 16px;
      }

      .modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 24px;
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
          z-index: 1000;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }
        .kyc-container {
          padding: 16px;
        }
        .form-grid {
          grid-template-columns: 1fr;
        }
        .form-group.full-width {
          grid-column: span 1;
        }
        .progress-steps {
          flex-direction: column;
          gap: 20px;
        }
        .progress-steps::before {
          display: none;
        }
        .step {
          display: flex;
          align-items: center;
          gap: 16px;
        }
        .step-circle {
          margin: 0;
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
          <div class="user-avatar-large"><?php echo kyc_h($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo kyc_h($ub['full_name']); ?></h4>
            <p><?php echo kyc_h($ub['wallet_short']); ?></p>
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
          <a href="./kycverification.php" class="sidebar-link active"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span>
            <?php echo kyc_sidebar_badge_span($badge); ?></a
          >
                    <a href="./security.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Security</span></a
          >
          <a href="./settings.php" class="sidebar-link"
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
        <div class="top-bar top-bar--main">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <a href="./userprofile.php" class="user-profile">
              <div class="user-avatar"><?php echo kyc_h($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo kyc_h($ub['full_name']); ?></h3>
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

        <div class="kyc-container">
          <div class="page-header" data-aos="fade-up">
            <h1>
              <i class="fa-solid fa-id-card"></i> Identity Verification (KYC)
            </h1>
            <p style="color: var(--text-muted); margin-top: 8px">
              Verify your identity to unlock full platform features and higher
              limits
            </p>
          </div>

          <!-- Status Card -->
          <div class="status-card" data-aos="fade-up">
            <div class="status-icon">
              <i class="<?php echo kyc_h($ui['icon']); ?>" style="color: <?php echo kyc_h($ui['icon_color']); ?>"></i>
            </div>
            <div class="status-title"><?php echo kyc_h($ui['title']); ?></div>
            <div class="status-message">
              <?php echo kyc_h($ui['message']); ?>
              <?php if ($kycStatus === 'rejected' && $kycRejectReason !== ''): ?>
                <div style="margin-top: 10px; color: var(--text-secondary);">
                  <strong>Reason:</strong> <?php echo kyc_h($kycRejectReason); ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="status-badge-large" style="background: <?php echo kyc_h($ui['badge_bg']); ?>"><?php echo kyc_h($ui['badge']); ?></div>
          </div>

          <!-- Progress Steps -->
          <div class="progress-steps" data-aos="fade-up">
            <div class="step <?php echo ($kycStatus === 'not_submitted') ? 'active' : 'completed'; ?>">
              <div class="step-circle"><i class="fa-solid fa-check"></i></div>
              <div class="step-label">Submit Documents</div>
            </div>
            <div class="step <?php echo ($kycStatus === 'pending') ? 'active' : (($kycStatus === 'approved' || $kycStatus === 'rejected') ? 'completed' : ''); ?>">
              <div class="step-circle">2</div>
              <div class="step-label">Under Review</div>
            </div>
            <div class="step <?php echo ($kycStatus === 'approved') ? 'completed active' : ''; ?>">
              <div class="step-circle">3</div>
              <div class="step-label">Verification Complete</div>
            </div>
          </div>

          <?php if ($errorMsg !== ''): ?>
            <div class="form-section" style="border-color: rgba(239,83,80,.7);">
              <div style="color: var(--danger-color); font-weight: 600;"><?php echo kyc_h($errorMsg); ?></div>
            </div>
          <?php endif; ?>
          <?php if ($successMsg !== ''): ?>
            <div class="form-section" style="border-color: rgba(34,197,94,.7);">
              <div style="color: var(--primary-color); font-weight: 600;"><?php echo kyc_h($successMsg); ?></div>
            </div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo kyc_h(user_csrf_token()); ?>">

          <!-- Personal Information Form -->
          <div class="form-section" data-aos="fade-up">
            <div class="section-title">
              <i class="fa-solid fa-user-pen"></i>
              <span>Personal Information</span>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Full Name (as per ID)</label>
                <input
                  type="text"
                  id="fullName"
                  name="full_name"
                  value="<?php echo kyc_h($prefillName); ?>"
                  placeholder="Enter your full name"
                />
              </div>
              <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" id="dob" name="dob" value="<?php echo kyc_h($prefillDob); ?>" />
              </div>
              <div class="form-group">
                <label>Country of Residence</label>
                <select id="country" name="country">
                  <option value="">— Select country —</option>
                  <?php foreach (kyc_countries_sorted() as $c) {
                    $name = $c['name'];
                    $sel = $prefillCountryInList && $prefillCountry === $name ? ' selected' : '';
                    echo '<option value="' . kyc_h($name) . '"' . $sel . '>' . kyc_h($name) . "</option>\n";
                  } ?>
                  <?php if ($prefillCountry !== '' && !$prefillCountryInList) { ?>
                  <option value="<?php echo kyc_h($prefillCountry); ?>" selected><?php echo kyc_h($prefillCountry); ?> (saved)</option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label>Nationality</label>
                <select id="nationality" name="nationality">
                  <option value="">— Select nationality —</option>
                  <?php foreach ($kycNationalityList as $nat) {
                    $sel = $prefillNationalityInList && $prefillNationality === $nat ? ' selected' : '';
                    echo '<option value="' . kyc_h($nat) . '"' . $sel . '>' . kyc_h($nat) . "</option>\n";
                  } ?>
                  <?php if ($prefillNationality !== '' && !$prefillNationalityInList) { ?>
                  <option value="<?php echo kyc_h($prefillNationality); ?>" selected><?php echo kyc_h($prefillNationality); ?> (saved)</option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label>Street Address</label>
                <input type="text" id="addressLine" name="address_line" placeholder="Street address" />
              </div>
              <div class="form-group">
                <label>City, State, Postal Code</label>
                <input type="text" id="cityStatePostal" name="city_state_postal" placeholder="City, state, postal code" />
              </div>
              <div class="form-group">
                <label>Government ID Number</label>
                <input type="text" id="idNumber" name="id_number" placeholder="ID number" />
              </div>
              <div class="form-group">
                <label>Source of Funds</label>
                <select id="sourceOfFunds" name="source_of_funds">
                  <option value="">— Select —</option>
                  <option value="Salary">Salary</option>
                  <option value="Business">Business</option>
                  <option value="Investments">Investments</option>
                  <option value="Savings">Savings</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="form-group">
                <label>Expected Transaction Volume</label>
                <select id="transactionVolume" name="transaction_volume">
                  <option value="">— Select —</option>
                  <option value="Under $1,000">Under $1,000</option>
                  <option value="$1,000–$10,000">$1,000–$10,000</option>
                  <option value="$10,000–$50,000">$10,000–$50,000</option>
                  <option value="Over $50,000">Over $50,000</option>
                </select>
              </div>
              <div class="form-group">
                <label>Purpose of Account</label>
                <input type="text" id="purpose" name="purpose" placeholder="Trading, payments, savings" />
              </div>
            </div>
          </div>

          <div class="form-section" data-aos="fade-up">
            <div class="section-title">
              <i class="fa-solid fa-id-card"></i>
              <span>Documents</span>
            </div>
            <div class="upload-box" id="uploadFront">
              <i class="fa-solid fa-upload"></i>
              <p>Government ID</p>
              <div id="frontFileInfo">JPG, PNG, or PDF</div>
              <input type="file" id="frontFile" name="doc_id" accept=".jpg,.jpeg,.png,.pdf" hidden />
            </div>
            <div class="upload-box" id="uploadProof">
              <i class="fa-solid fa-file-lines"></i>
              <p>Proof of Address</p>
              <div id="proofFileInfo">JPG, PNG, or PDF</div>
              <input type="file" id="proofFile" name="proof_address" accept=".jpg,.jpeg,.png,.pdf" hidden />
            </div>
            <div class="upload-box" id="uploadSelfie">
              <i class="fa-solid fa-camera"></i>
              <p>Selfie</p>
              <div id="selfieFileInfo">JPG or PNG</div>
              <input type="file" id="selfieFile" name="selfie" accept=".jpg,.jpeg,.png" hidden />
            </div>
            <button type="submit" class="submit-btn" id="submitKYC">Submit Verification</button>
          </div>
          </form>
        </div>
      </main>
    </div>

    <div id="confirmationModal" class="modal" style="display: none">
      <div class="modal-content">
        <h3>Application received</h3>
        <p>Your KYC documents were submitted for review.</p>
        <button type="button" class="modal-btn confirm" id="modalConfirmBtn">OK</button>
      </div>
    </div>

    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>

      // Prefill selects from server
      (function () {
        const prefill = {
          country: <?php echo json_encode($prefillCountry, JSON_UNESCAPED_SLASHES); ?>,
          nationality: <?php echo json_encode($prefillNationality, JSON_UNESCAPED_SLASHES); ?>,
          source_of_funds: <?php echo json_encode($prefillSof, JSON_UNESCAPED_SLASHES); ?>,
          transaction_volume: <?php echo json_encode($prefillTv, JSON_UNESCAPED_SLASHES); ?>,
        };
        try {
          if (prefill.country) document.getElementById("country").value = prefill.country;
          if (prefill.nationality) document.getElementById("nationality").value = prefill.nationality;
          if (prefill.source_of_funds) document.getElementById("sourceOfFunds").value = prefill.source_of_funds;
          if (prefill.transaction_volume) document.getElementById("transactionVolume").value = prefill.transaction_volume;
        } catch (e) {}
      })();

      // File upload handlers
      const frontUpload = document.getElementById("uploadFront");
      const frontFile = document.getElementById("frontFile");
      const frontFileInfo = document.getElementById("frontFileInfo");

      const proofUpload = document.getElementById("uploadProof");
      const proofFile = document.getElementById("proofFile");
      const proofFileInfo = document.getElementById("proofFileInfo");

      const selfieUpload = document.getElementById("uploadSelfie");
      const selfieFile = document.getElementById("selfieFile");
      const selfieFileInfo = document.getElementById("selfieFileInfo");

      frontUpload.addEventListener("click", () => frontFile.click());
      frontFile.addEventListener("change", (e) => {
        if (e.target.files[0]) {
          frontFileInfo.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${e.target.files[0].name}`;
          frontFileInfo.style.color = "var(--primary-color)";
        }
      });

      proofUpload.addEventListener("click", () => proofFile.click());
      proofFile.addEventListener("change", (e) => {
        if (e.target.files[0]) {
          proofFileInfo.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${e.target.files[0].name}`;
          proofFileInfo.style.color = "var(--primary-color)";
        }
      });

      selfieUpload.addEventListener("click", () => selfieFile.click());
      selfieFile.addEventListener("change", (e) => {
        if (e.target.files[0]) {
          selfieFileInfo.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${e.target.files[0].name}`;
          selfieFileInfo.style.color = "var(--primary-color)";
        }
      });

      // Submit handler
      const submitBtn = document.getElementById("submitKYC");
      const modal = document.getElementById("confirmationModal");
      const modalConfirm = document.getElementById("modalConfirmBtn");

      submitBtn.addEventListener("click", (e) => {
        // Let the real form submit happen after passing client-side checks.
        // Validate required fields
        const fullName = document.getElementById("fullName").value;
        const dob = document.getElementById("dob").value;
        const country = document.getElementById("country").value;
        const nationality = document.getElementById("nationality").value;
        const idNumber = document.getElementById("idNumber").value;

        if (!fullName || !dob || !country || !nationality || !idNumber) {
          e.preventDefault();
          alert("Please fill in all required personal information fields.");
          return;
        }

        if (!frontFile.files[0]) {
          e.preventDefault();
          alert("Please upload a government ID document.");
          return;
        }

        if (!proofFile.files[0]) {
          e.preventDefault();
          alert("Please upload a proof of address document.");
          return;
        }

        // Show modal
        modal.style.display = "flex";

        // Show notification
        showNotification(
          "KYC application updated successfully! Review in progress.",
        );
      });

      modalConfirm.addEventListener("click", () => {
        modal.style.display = "none";
      });

      function showNotification(message) {
        const notif = document.createElement("div");
        notif.className = "notification-popup show";
        notif.style.position = "fixed";
        notif.style.top = "80px";
        notif.style.right = "24px";
        notif.style.background = "var(--surface)";
        notif.style.padding = "12px 20px";
        notif.style.borderRadius = "40px";
        notif.style.borderLeft = `4px solid var(--primary-color)`;
        notif.style.zIndex = "200";
        notif.innerHTML = message;
        document.body.appendChild(notif);
        setTimeout(() => notif.remove(), 4000);
      }

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
          "📢 KYC verification typically takes 24-48 hours. We'll notify you once completed!",
        );
      });

      // Close modal on outside click
      window.addEventListener("click", (e) => {
        if (e.target === modal) {
          modal.style.display = "none";
        }
      });
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
