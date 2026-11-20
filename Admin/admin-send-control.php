<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
require_once __DIR__ . '/includes/admin_transactions_data.php';
require_once dirname(__DIR__) . '/Userprofile/includes/user_db.php';

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

function asc_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asc_short_addr(string $addr): string {
  $a = trim($addr);
  if ($a === '') {
    return '—';
  }
  if (strlen($a) <= 14) {
    return $a;
  }
  return substr($a, 0, 10) . '...' . substr($a, -4);
}

function asc_ui_status_from_db(string $status): string {
  $s = strtolower(trim($status));
  if ($s === 'completed') {
    return 'approved';
  }
  if ($s === 'failed' || $s === 'cancelled' || $s === 'rejected') {
    return 'declined';
  }
  return 'pending';
}

function asc_risk_tier_from_usd(float $usd): string {
  if (!is_finite($usd) || $usd <= 0) {
    return 'low';
  }
  if ($usd >= 75000) {
    return 'high';
  }
  if ($usd >= 7500) {
    return 'medium';
  }
  return 'low';
}

$adminEmail = isset($_SESSION['admin_email']) && is_string($_SESSION['admin_email']) ? (string)$_SESSION['admin_email'] : '';
$adminUsername = isset($_SESSION['admin_username']) && is_string($_SESSION['admin_username']) ? (string)$_SESSION['admin_username'] : 'Admin';
$adminInitials = 'A';
if ($adminUsername !== '') {
  $parts = preg_split('/\s+/', trim($adminUsername)) ?: [];
  if (count($parts) >= 2) {
    $a = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1, 'UTF-8') : substr($parts[0], 0, 1);
    $b = function_exists('mb_substr') ? mb_substr($parts[1], 0, 1, 'UTF-8') : substr($parts[1], 0, 1);
    $adminInitials = strtoupper((string)$a . (string)$b);
  } else {
    $a = function_exists('mb_substr') ? mb_substr($adminUsername, 0, 2, 'UTF-8') : substr($adminUsername, 0, 2);
    $adminInitials = strtoupper((string)$a);
  }
}

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
  if (!admin_verify_csrf($csrf)) {
    $flash = 'Security check failed. Please refresh and try again.';
  } else {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
    $note = isset($_POST['note']) && is_string($_POST['note']) ? trim($_POST['note']) : '';

    try {
      $db = admin_tx_db();
      ensure_transactions_table($db);
      admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);

      if ($action === 'approve_one' || $action === 'decline_one') {
        $id = isset($_POST['tx_id']) ? (int)$_POST['tx_id'] : 0;
        $act = $action === 'approve_one' ? 'approve' : 'reject';
        $res = admin_tx_apply_action($db, $id, $act, $note !== '' ? $note : null);
        $db->close();
        $flash = ($res['ok'] ?? false) ? 'Updated successfully.' : ('Action failed' . (!empty($res['error']) ? (': ' . (string)$res['error']) : '') . '.');
      } elseif ($action === 'bulk_approve' || $action === 'bulk_decline') {
        $ids = isset($_POST['tx_ids']) && is_array($_POST['tx_ids']) ? $_POST['tx_ids'] : [];
        $act = $action === 'bulk_approve' ? 'approve' : 'reject';
        $ok = 0;
        $fail = 0;
        foreach ($ids as $raw) {
          $id = (int)$raw;
          if ($id <= 0) {
            continue;
          }
          $res = admin_tx_apply_action($db, $id, $act, $note !== '' ? $note : null);
          if ($res['ok'] ?? false) {
            $ok++;
          } else {
            $fail++;
          }
        }
        $db->close();
        $flash = "Bulk complete: {$ok} ok" . ($fail > 0 ? ", {$fail} failed" : '') . '.';
      } else {
        $db->close();
        $flash = 'Unknown action.';
      }
    } catch (Throwable $e) {
      error_log('[admin-send-control] ' . $e->getMessage());
      if (isset($db) && $db instanceof mysqli) {
        @$db->close();
      }
      $flash = 'Database error.';
    }
  }

  if ($flash !== '') {
    $_SESSION['admin_send_flash'] = $flash;
  }
  header('Location: ' . basename(__FILE__) . '?ok=1', true, 303);
  exit;
}

$flash = '';
if (isset($_GET['ok'])) {
  if (!empty($_SESSION['admin_send_flash']) && is_string($_SESSION['admin_send_flash'])) {
    $flash = (string)$_SESSION['admin_send_flash'];
    unset($_SESSION['admin_send_flash']);
  }
}

$metrics = [
  'total' => 0,
  'pending' => 0,
  'approved' => 0,
  'declined' => 0,
  'volume_usd' => 0.0,
  'avg_approval_seconds' => 0.0,
];

$sendRows = [];
$activityLogs = [];
$analytics24h = [
  'BTC' => ['count' => 0, 'usd' => 0.0],
  'ETH' => ['count' => 0, 'usd' => 0.0],
  'USDT' => ['count' => 0, 'usd' => 0.0],
  'SOL' => ['count' => 0, 'usd' => 0.0],
];

// Risk & compliance alerts (real-time from send rows).
$riskHigh = [
  'tx_id' => 0,
  'user' => '',
  'usd' => 0.0,
  'recipient' => '',
];
$riskVelocity = [
  'user_id' => 0,
  'user' => '',
  'count' => 0,
  'minutes' => 10,
  'total_usd' => 0.0,
];
$riskBlacklist = [
  'tx_id' => 0,
  'recipient' => '',
];

try {
  $db = admin_tx_db();
  ensure_transactions_table($db);
  admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);

  // Pull sends with user info.
  $sql = "SELECT t.id, t.user_id, t.asset, t.amount, t.status, t.meta, t.created_at,
                 u.full_name, u.email, u.wallet_id
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id
          WHERE t.type = 'send'
          ORDER BY t.created_at DESC, t.id DESC
          LIMIT 500";
  $r = @$db->query($sql);
  if ($r) {
    while ($row = $r->fetch_assoc()) {
      $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
      $uid = (int)($row['user_id'] ?? 0);
      $name = trim((string)($row['full_name'] ?? ''));
      if ($name === '') {
        $name = trim((string)($row['email'] ?? ''));
      }
      if ($name === '') {
        $name = $uid > 0 ? ('User #' . $uid) : 'Unknown';
      }

      $assetId = isset($meta['asset_id']) ? strtolower((string)$meta['asset_id']) : strtolower((string)($row['asset'] ?? ''));
      $sym = strtoupper(preg_replace('/[^A-Z0-9_]/i', '', $assetId) ?? '');
      if ($sym === 'USDT_ERC20' || $sym === 'USDT_TRC20') {
        $sym = 'USDT';
      }
      if ($sym === '') {
        $sym = strtoupper((string)($row['asset'] ?? ''));
      }

      $to = isset($meta['to_address']) && is_string($meta['to_address']) ? trim($meta['to_address']) : '';
      $network = isset($meta['network']) && is_string($meta['network']) ? trim($meta['network']) : '';

      $crypto = isset($meta['crypto_amount']) && is_numeric($meta['crypto_amount']) ? (float)$meta['crypto_amount'] : (float)($row['amount'] ?? 0);
      $cryptoStr = is_finite($crypto) ? rtrim(rtrim(number_format($crypto, 8, '.', ''), '0'), '.') : '0';

      $px = 0.0;
      try {
        if ($sym !== '') {
          $px = user_price_usdt($sym, $db);
        }
      } catch (Throwable) {
        $px = 0.0;
      }
      $usd = (is_finite($crypto) ? $crypto : 0.0) * (is_finite($px) ? $px : 0.0);
      $risk = asc_risk_tier_from_usd($usd);

      $dbStatus = strtolower(trim((string)($row['status'] ?? 'pending')));
      $uiStatus = asc_ui_status_from_db($dbStatus);

      $metrics['total']++;
      if ($uiStatus === 'pending') {
        $metrics['pending']++;
      } elseif ($uiStatus === 'approved') {
        $metrics['approved']++;
      } else {
        $metrics['declined']++;
      }
      $metrics['volume_usd'] += max(0.0, $usd);

      $createdAt = (string)($row['created_at'] ?? '');
      $is24h = false;
      if ($createdAt !== '') {
        $ts = strtotime($createdAt);
        if ($ts !== false) {
          $is24h = ($ts >= (time() - 86400));
        }
      }
      if ($is24h) {
        if ($sym === 'BTC') {
          $analytics24h['BTC']['count']++;
          $analytics24h['BTC']['usd'] += max(0.0, $usd);
        } elseif ($sym === 'ETH') {
          $analytics24h['ETH']['count']++;
          $analytics24h['ETH']['usd'] += max(0.0, $usd);
        } elseif ($sym === 'USDT') {
          $analytics24h['USDT']['count']++;
          $analytics24h['USDT']['usd'] += max(0.0, $usd);
        } elseif ($sym === 'SOL') {
          $analytics24h['SOL']['count']++;
          $analytics24h['SOL']['usd'] += max(0.0, $usd);
        }
      }

      $reviewAt = isset($meta['admin_review_at']) && is_string($meta['admin_review_at']) ? trim($meta['admin_review_at']) : '';
      if ($uiStatus === 'approved' && $reviewAt !== '' && $createdAt !== '') {
        $t0 = strtotime($createdAt);
        $t1 = strtotime($reviewAt);
        if ($t0 !== false && $t1 !== false && $t1 >= $t0) {
          $metrics['avg_approval_seconds'] += ($t1 - $t0);
        }
      }

      $sendRows[] = [
        'id' => (int)($row['id'] ?? 0),
        'userId' => $uid,
        'userName' => $name,
        'recipient' => $to !== '' ? asc_short_addr($to) : '—',
        'recipient_full' => $to,
        'asset' => $sym,
        'amount' => $cryptoStr,
        'network' => $network !== '' ? $network : '—',
        'risk' => $risk,
        'status' => $uiStatus,
        'db_status' => $dbStatus,
        'submitted' => $createdAt,
        'usd' => $usd,
      ];

      if (!empty($meta['admin_review'])) {
        $actionLabel = strtoupper(trim((string)$meta['admin_review']));
        $note = '';
        if (isset($meta['admin_review_note']) && is_string($meta['admin_review_note'])) {
          $note = trim((string)$meta['admin_review_note']);
        }
        $activityLogs[] = [
          'time' => isset($meta['admin_review_at']) && is_string($meta['admin_review_at']) ? (string)$meta['admin_review_at'] : $createdAt,
          'admin' => $adminEmail !== '' ? $adminEmail : 'admin',
          'action' => $actionLabel,
          'request' => (string)(int)($row['id'] ?? 0),
          'note' => $note,
        ];
      }
    }
    $r->free();
  }

  if ($metrics['approved'] > 0 && $metrics['avg_approval_seconds'] > 0) {
    $metrics['avg_approval_seconds'] = $metrics['avg_approval_seconds'] / (float)$metrics['approved'];
  } else {
    $metrics['avg_approval_seconds'] = 0.0;
  }

  $db->close();
} catch (Throwable $e) {
  error_log('[admin-send-control:data] ' . $e->getMessage());
}

$pendingRows = array_values(array_filter(
  $sendRows,
  static fn($r) => isset($r['status']) && is_string($r['status']) && strtolower($r['status']) === 'pending'
));

// 1) High-risk send: largest pending (estimated USD).
foreach ($pendingRows as $r) {
  $usd = isset($r['usd']) ? (float)$r['usd'] : 0.0;
  if (!is_finite($usd)) {
    $usd = 0.0;
  }
  if ($usd > $riskHigh['usd']) {
    $riskHigh = [
      'tx_id' => isset($r['id']) ? (int)$r['id'] : 0,
      'user' => isset($r['userName']) ? (string)$r['userName'] : '',
      'usd' => $usd,
      'recipient' => isset($r['recipient_full']) ? (string)$r['recipient_full'] : '',
    ];
  }
}

// 2) Unusual velocity: >= 3 pending sends by same user in last 10 minutes.
$windowSeconds = 10 * 60;
$now = time();
$byUser = [];
foreach ($pendingRows as $r) {
  $uid = isset($r['userId']) ? (int)$r['userId'] : 0;
  if ($uid <= 0) continue;
  $createdAt = isset($r['submitted']) ? (string)$r['submitted'] : '';
  $ts = $createdAt !== '' ? strtotime($createdAt) : false;
  if ($ts === false) continue;
  if ($ts < ($now - $windowSeconds)) continue;

  $usd = isset($r['usd']) ? (float)$r['usd'] : 0.0;
  if (!is_finite($usd)) $usd = 0.0;
  if (!isset($byUser[$uid])) {
    $byUser[$uid] = [
      'user_id' => $uid,
      'user' => isset($r['userName']) ? (string)$r['userName'] : ('User #' . $uid),
      'count' => 0,
      'total_usd' => 0.0,
    ];
  }
  $byUser[$uid]['count']++;
  $byUser[$uid]['total_usd'] += max(0.0, $usd);
}
foreach ($byUser as $cand) {
  $cnt = (int)($cand['count'] ?? 0);
  $tot = (float)($cand['total_usd'] ?? 0.0);
  if ($cnt < 3) continue;
  if ($cnt > $riskVelocity['count'] || ($cnt === $riskVelocity['count'] && $tot > $riskVelocity['total_usd'])) {
    $riskVelocity = [
      'user_id' => (int)($cand['user_id'] ?? 0),
      'user' => (string)($cand['user'] ?? ''),
      'count' => $cnt,
      'minutes' => 10,
      'total_usd' => $tot,
    ];
  }
}

// 3) Blacklisted address match: optional table or hardcoded list not found in this project.
// If you add a table like `blacklisted_addresses(address VARCHAR(255) PRIMARY KEY)` you can wire it here.
$riskBlacklist = [
  'tx_id' => 0,
  'recipient' => '',
];

$sendRowsJson = json_encode($sendRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($sendRowsJson === false) {
  $sendRowsJson = '[]';
}

$activityLogs = array_slice($activityLogs, 0, 25);

$csrf = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
  <title>Crypterra · Send Management | Admin Center</title>
  <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
  <!-- Font Awesome 6 (free) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <!-- Google Fonts Inter & Space Grotesk -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <!-- AOS scroll animation -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
  <style>
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
      font-family: "Inter", "Space Grotesk", sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
      overflow-x: hidden;
    }

    .admin {
      display: flex;
      min-height: 100vh;
    }

    /* SIDEBAR */
    .sidebar {
      width: 280px;
      background: rgba(10, 20, 35, 0.9);
      backdrop-filter: blur(20px) saturate(200%);
      -webkit-backdrop-filter: blur(20px) saturate(200%);
      border-right: 1px solid var(--glass-edge);
      padding: 1.8rem 1.2rem;
      transition: width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1), left 0.3s;
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
    .sidebar::-webkit-scrollbar { display: none; }
    .sidebar.collapsed { width: 95px; }
    .sidebar.collapsed .sidebar-link span,
    .sidebar.collapsed .sidebar-header span,
    .sidebar.collapsed .admin-info { display: none; }

    @media (max-width: 1100px) {
      .sidebar { position: fixed; left: -300px; }
      .sidebar.mobile-open { left: 0; }
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
      background: linear-gradient(145deg, var(--primary), var(--primary-dark));
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
    .collapse-btn:hover { background: var(--primary); color: white; transform: rotate(180deg); }

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
      background: linear-gradient(145deg, var(--primary), var(--primary-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      border: 2px solid var(--primary);
      box-shadow: 0 0 20px var(--primary-glow);
    }
    .admin-details h4 { font-size: 0.95rem; }
    .admin-details p { font-size: 0.7rem; color: var(--text-muted); }

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
    .sidebar.collapsed .nav-section { display: none; }
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
    .sidebar-link i { width: 24px; font-size: 1.2rem; text-align: center; }
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
    .sidebar-link:hover::before, .sidebar-link.active::before { width: 100%; }
    .sidebar-link:hover, .sidebar-link.active {
      color: var(--primary);
      transform: translateX(5px);
      box-shadow: 0 0 20px var(--primary-glow);
    }
    .sidebar-link.active { background: var(--primary-soft); border: 1px solid var(--glass-edge); }

    /* MAIN CONTENT */
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
    .header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .mobile-menu-btn {
      display: none;
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 50%;
      width: 42px;
      height: 42px;
      align-items: center;
      justify-content: center;
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
    .search-box input {
      background: transparent;
      border: none;
      color: var(--text);
      outline: none;
      font-size: 0.9rem;
      width: 200px;
    }
    .header-right { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
    .system-status { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; }
    .status-dot {
      width: 10px; height: 10px; border-radius: 50%;
      background: var(--success);
      box-shadow: 0 0 15px #10b981;
      animation: pulse 2s infinite;
    }
    @keyframes pulse { 0% { opacity: 0.8; } 50% { opacity: 1; } 100% { opacity: 0.8; } }
    .icon-btn {
      width: 40px; height: 40px; border-radius: 50%;
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      cursor: pointer;
      transition: 0.2s;
      position: relative;
    }
    .icon-btn:hover { background: var(--primary); color: white; transform: translateY(-2px); box-shadow: var(--shadow-glow); }
    .badge {
      position: absolute;
      top: 5px; right: 5px;
      width: 8px; height: 8px;
      background: var(--danger);
      border-radius: 50%;
    }
    .theme-toggle {
      width: 52px; height: 26px;
      background: #3a4a5a;
      border-radius: 34px;
      position: relative;
      cursor: pointer;
    }
    .theme-toggle::before {
      content: "";
      position: absolute;
      width: 20px; height: 20px;
      background: white;
      border-radius: 50%;
      top: 2px; left: 2px;
      transition: 0.3s;
    }
    body.dark .theme-toggle { background: var(--primary); }
    body.dark .theme-toggle::before { transform: translateX(26px); }

    .content { padding: 1.5rem; }

    /* metric cards */
    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.2rem;
      margin-bottom: 1.8rem;
    }
    .metric-card {
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge);
      border-radius: 30px;
      padding: 1.5rem;
      transition: 0.3s;
    }
    .metric-card:hover { border-color: var(--primary); box-shadow: var(--shadow-glow); transform: translateY(-5px); }
    .metric-header { display: flex; justify-content: space-between; margin-bottom: 1rem; }
    .metric-icon {
      width: 48px; height: 48px;
      border-radius: 20px;
      background: var(--primary-soft);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: var(--primary);
    }
    .metric-value { font-size: 2rem; font-weight: 700; }
    .metric-change { font-size: 0.8rem; display: flex; align-items: center; gap: 4px; }
    .positive { color: var(--success); }
    .negative { color: var(--danger); }
    .spark { height: 30px; width: 100%; background: linear-gradient(90deg, var(--primary-soft) 0%, var(--primary-light) 50%, var(--primary-soft) 100%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 20px; margin-top: 8px; }
    @keyframes shimmer { 0% { background-position: -100% 0; } 100% { background-position: 100% 0; } }

    .glass-table-container {
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 1.2rem;
      overflow-x: auto;
      margin-bottom: 1.5rem;
      width: 100%;
    }
    table { width: 100%; border-collapse: collapse; min-width: 1000px; }
    th { text-align: left; padding: 1rem 0.5rem; color: var(--primary); border-bottom: 2px solid var(--glass-edge); }
    td { padding: 1rem 0.5rem; border-bottom: 1px solid var(--glass-edge); vertical-align: middle; }
    tr:hover { background: var(--primary-soft); }
    .badge-status { padding: 0.2rem 0.8rem; border-radius: 40px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .badge-green { background: rgba(16, 185, 129, 0.2); color: var(--success); }
    .badge-yellow { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
    .badge-red { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
    .badge-blue { background: rgba(59, 130, 246, 0.2); color: var(--info); }
    .action-btn {
      background: transparent;
      border: 1px solid var(--glass-edge);
      border-radius: 30px;
      padding: 0.3rem 0.8rem;
      color: var(--text);
      cursor: pointer;
      transition: 0.2s;
      margin: 0 2px;
      font-size: 0.75rem;
    }
    .action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .btn-approve:hover { background: var(--success); border-color: var(--success); }
    .btn-decline:hover { background: var(--danger); border-color: var(--danger); }

    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.2rem;
      margin-bottom: 1.5rem;
    }
    @media (max-width: 800px) { .two-col { grid-template-columns: 1fr; } }

    .filter-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1.5rem;
      align-items: center;
    }
    .filter-select {
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 8px 16px;
      color: var(--text);
    }

    .modal {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.85);
      backdrop-filter: blur(12px);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-card {
      background: var(--surface);
      border-radius: 40px;
      padding: 2rem;
      width: 90%;
      max-width: 500px;
      border: 1px solid var(--primary);
    }
    .modal-card textarea {
      width: 100%;
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 20px;
      padding: 0.8rem;
      color: var(--text);
      margin: 1rem 0;
    }

    .ai-assistant {
      position: fixed;
      bottom: 1.5rem;
      right: 1.5rem;
      z-index: 200;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      color: white;
      cursor: pointer;
      box-shadow: var(--shadow-glow);
      transition: 0.3s;
      animation: float 4s infinite;
    }
    .ai-assistant:hover { transform: scale(1.1); background: var(--primary-dark); }
    @keyframes float { 0% { transform: translateY(0); } 50% { transform: translateY(-8px); } 100% { transform: translateY(0); } }

    @media (max-width: 1100px) { .mobile-menu-btn { display: flex; } }
    @media (max-width: 768px) { .content { padding: 1rem; } .top-bar { padding: 0.8rem 1rem; } .search-box { width: 100%; } .search-box input { width: 100%; } .metric-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); } }
  </style>
</head>
<body class="dark">
<div class="admin">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header"><div class="logo"><div class="logo-icon"><i class="fa-solid fa-globe"></i></div><span>Crypterra</span></div><div class="collapse-btn" id="collapseSidebar"><i class="fa-solid fa-chevron-left"></i></div></div>
    <div class="admin-info"><div class="admin-avatar"><?php echo asc_h($adminInitials); ?></div><div class="admin-details"><h4><?php echo asc_h($adminUsername); ?></h4><p><?php echo asc_h($adminEmail !== '' ? $adminEmail : 'Admin'); ?></p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">MAIN</div>
      <a href="./admin-dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
      <a href="./admin-users.php" class="sidebar-link"><i class="fa-solid fa-users"></i><span>Users</span></a>
      <a href="./admin-send-control.php" class="sidebar-link active"><i class="fa-solid fa-paper-plane"></i><span>Send Mgmt</span></a>
      <a href="./admin-transactions.php" class="sidebar-link"><i class="fa-solid fa-arrow-right-arrow-left"></i><span>Transactions</span></a>
      <a href="./admin-kyc.php" class="sidebar-link"><i class="fa-solid fa-id-card"></i><span>KYC Management</span></a>
      <a href="./admin-security-center.php" class="sidebar-link"><i class="fa-solid fa-shield"></i><span>Security Center</span></a>
      <a href="./admin-settings.php" class="sidebar-link"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      <div style="margin-top: 2rem"></div>
      <a href="./admin-logout.php" class="sidebar-link"><i class="fa-solid fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
  </aside>

  <main class="main">
    <div class="top-bar">
      <div class="header-left"><div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fa-solid fa-bars"></i></div><div class="search-box"><i class="fa-solid fa-search"></i><input type="text" id="searchInput" placeholder="Search by user, txid or address..."></div></div>
      <div class="header-right"><div class="system-status"><span class="status-dot"></span><span>System Online</span></div><div class="icon-btn"><i class="fa-regular fa-bell"></i><span class="badge"></span></div><div class="icon-btn"><i class="fa-regular fa-envelope"></i></div><div class="theme-toggle" id="themeToggle"></div><div class="icon-btn" style="width: auto; padding: 0 12px; border-radius: 40px; gap: 6px;"><i class="fa-regular fa-user"></i><span><?php echo asc_h($adminInitials); ?></span></div></div>
    </div>

    <div class="content">
      <?php if ($flash !== '') { ?>
        <div class="glass-table-container" style="margin-bottom:1rem;border-color:rgba(34,197,94,0.55)">
          <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
            <div style="font-weight:700;color:var(--primary)"><i class="fa-solid fa-circle-check"></i> <?php echo asc_h($flash); ?></div>
            <div style="font-size:0.85rem;color:var(--text-muted)">Source: <code>transactions.type = send</code></div>
          </div>
        </div>
      <?php } ?>
      <!-- Send Metrics -->
      <div class="metric-grid" data-aos="fade-up">
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-paper-plane"></i></span><span class="metric-change positive"><i class="fa-solid fa-database"></i></span></div><div class="metric-value"><?php echo asc_h(number_format((int)$metrics['total'])); ?></div><div>Total Send Requests</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-regular fa-hourglass-half"></i></span><span class="metric-change positive"><i class="fa-solid fa-clock"></i></span></div><div class="metric-value"><?php echo asc_h(number_format((int)$metrics['pending'])); ?></div><div>Pending Approval</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-check-double"></i></span><span class="metric-change positive"><i class="fa-solid fa-check"></i></span></div><div class="metric-value"><?php echo asc_h(number_format((int)$metrics['approved'])); ?></div><div>Approved</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-ban"></i></span><span class="metric-change negative"><i class="fa-solid fa-xmark"></i></span></div><div class="metric-value"><?php echo asc_h(number_format((int)$metrics['declined'])); ?></div><div>Declined</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-dollar-sign"></i></span><span class="metric-change positive"><i class="fa-solid fa-chart-line"></i></span></div><div class="metric-value">$<?php echo asc_h(number_format((float)$metrics['volume_usd'], 0)); ?></div><div>Total Volume (est. USD)</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-clock"></i></span><span class="metric-change positive"><i class="fa-solid fa-stopwatch"></i></span></div><div class="metric-value"><?php
          $secs = (float)$metrics['avg_approval_seconds'];
          if (!is_finite($secs) || $secs <= 0) {
            echo asc_h('—');
          } elseif ($secs < 60) {
            echo asc_h(number_format($secs, 0) . 's');
          } elseif ($secs < 3600) {
            echo asc_h(number_format($secs / 60.0, 1) . 'm');
          } else {
            echo asc_h(number_format($secs / 3600.0, 1) . 'h');
          }
        ?></div><div>Avg Approval Time</div><div class="spark"></div></div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <select class="filter-select" id="statusFilter"><option value="all">All Status</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="declined">Declined</option></select>
        <select class="filter-select" id="assetFilter"><option value="all">All Assets</option><option value="BTC">BTC</option><option value="ETH">ETH</option><option value="USDT">USDT</option><option value="SOL">SOL</option></select>
        <select class="filter-select" id="riskFilter"><option value="all">Risk Level</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select>
        <button class="action-btn" id="applyFilters">Apply Filters</button>
        <button class="action-btn" id="exportCSVBtn"><i class="fa-regular fa-file-excel"></i> Export CSV</button>
      </div>

      <!-- Main Send Requests Table (from frontend send.html) -->
      <div class="glass-table-container">
        <h3 style="margin-bottom:1rem"><i class="fa-solid fa-paper-plane" style="color:var(--primary)"></i> User Send Requests · Approve or Decline</h3>
        <table id="sendTable">
          <thead>
            <tr><th style="width:44px"></th><th>Request ID</th><th>User</th><th>Recipient Address</th><th>Asset</th><th>Amount</th><th>Network</th><th>Risk Score</th><th>Status</th><th>Submitted</th><th>Actions</th></tr>
          </thead>
          <tbody id="sendTableBody"></tbody>
        </table>
      </div>

      <!-- Analytics & AI Insights -->
      <div class="two-col">
        <div class="glass-table-container">
          <h3><i class="fa-solid fa-chart-line"></i> Send Volume Analytics (24h)</h3>
          <div class="asset-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-brands fa-bitcoin"></i><div><strong>BTC</strong><br><?php echo asc_h(number_format((int)$analytics24h['BTC']['count'])); ?> txs · $<?php echo asc_h(number_format((float)$analytics24h['BTC']['usd'], 0)); ?></div></div>
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-brands fa-ethereum"></i><div><strong>ETH</strong><br><?php echo asc_h(number_format((int)$analytics24h['ETH']['count'])); ?> txs · $<?php echo asc_h(number_format((float)$analytics24h['ETH']['usd'], 0)); ?></div></div>
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-solid fa-dollar-sign"></i><div><strong>USDT</strong><br><?php echo asc_h(number_format((int)$analytics24h['USDT']['count'])); ?> txs · $<?php echo asc_h(number_format((float)$analytics24h['USDT']['usd'], 0)); ?></div></div>
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-brands fa-solana"></i><div><strong>SOL</strong><br><?php echo asc_h(number_format((int)$analytics24h['SOL']['count'])); ?> txs · $<?php echo asc_h(number_format((float)$analytics24h['SOL']['usd'], 0)); ?></div></div>
          </div>
        </div>
        <div class="glass-table-container">
          <h3><i class="fa-solid fa-shield-haltered"></i> Risk & Compliance Alerts</h3>
          <div class="alert-card">
            <i class="fa-solid fa-flag"></i>
            <div>
              <strong>High-risk send detected</strong><br>
              <?php if (($riskHigh['tx_id'] ?? 0) > 0 && (float)($riskHigh['usd'] ?? 0) > 0) { ?>
                <?php echo asc_h((string)($riskHigh['user'] ?? 'User')); ?> trying to send
                $<?php echo asc_h(number_format((float)($riskHigh['usd'] ?? 0), 0)); ?>
                to <?php echo asc_h(asc_short_addr((string)($riskHigh['recipient'] ?? ''))); ?>
              <?php } else { ?>
                No high-risk pending sends right now.
              <?php } ?>
            </div>
            <?php if (($riskHigh['tx_id'] ?? 0) > 0) { ?>
              <button class="action-btn" type="button" onclick="viewDetails('<?php echo asc_h((string)(int)$riskHigh['tx_id']); ?>')">Review</button>
            <?php } ?>
          </div>

          <div class="alert-card">
            <i class="fa-solid fa-clock"></i>
            <div>
              <strong>Unusual velocity</strong><br>
              <?php if (($riskVelocity['count'] ?? 0) >= 3) { ?>
                <?php echo asc_h((string)($riskVelocity['count'] ?? 0)); ?> pending sends in
                <?php echo asc_h((string)($riskVelocity['minutes'] ?? 10)); ?> minutes from
                <?php echo asc_h((string)($riskVelocity['user'] ?? 'User')); ?>
                (≈ $<?php echo asc_h(number_format((float)($riskVelocity['total_usd'] ?? 0.0), 0)); ?>)
              <?php } else { ?>
                No unusual send velocity detected in the last 10 minutes.
              <?php } ?>
            </div>
          </div>

          <div class="alert-card">
            <i class="fa-solid fa-skull"></i>
            <div>
              <strong>Blacklisted address match</strong><br>
              <?php if (($riskBlacklist['tx_id'] ?? 0) > 0 && (string)($riskBlacklist['recipient'] ?? '') !== '') { ?>
                Recipient <?php echo asc_h(asc_short_addr((string)$riskBlacklist['recipient'])); ?> matched blacklist
              <?php } else { ?>
                No blacklisted recipients detected (no blacklist configured).
              <?php } ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin Activity Logs for Send Approvals -->
      <div class="glass-table-container">
        <h3><i class="fa-regular fa-clock"></i> Admin Approval Activity Logs</h3>
        <table style="min-width:700px"><thead><tr><th>Timestamp</th><th>Admin</th><th>Action</th><th>Request ID</th><th>Note</th></tr></thead><tbody id="activityLogsBody">
          <?php if ($activityLogs === []) { ?>
            <tr><td colspan="5" style="color:var(--text-muted)">No admin actions logged yet.</td></tr>
          <?php } else { ?>
            <?php foreach ($activityLogs as $log) { ?>
              <tr>
                <td><?php echo asc_h((string)($log['time'] ?? '')); ?></td>
                <td><?php echo asc_h((string)($log['admin'] ?? '')); ?></td>
                <td><?php echo asc_h((string)($log['action'] ?? '')); ?></td>
                <td><?php echo asc_h((string)($log['request'] ?? '')); ?></td>
                <td><?php echo asc_h((string)($log['note'] ?? '')); ?></td>
              </tr>
            <?php } ?>
          <?php } ?>
        </tbody></table>
      </div>

      <!-- Bulk Action Panel -->
      <div class="glass-table-container">
        <h3><i class="fa-solid fa-layer-group"></i> Bulk Actions</h3>
        <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
          <button class="action-btn" id="bulkApproveBtn">✅ Approve Selected</button>
          <button class="action-btn" id="bulkDeclineBtn">❌ Decline Selected</button>
          <span style="font-size:0.8rem;">Select checkboxes in table</span>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="ai-assistant" id="aiBtn"><i class="fa-solid fa-robot"></i></div>

<!-- Decline Modal -->
<div id="declineModal" class="modal"><div class="modal-card"><h3><i class="fa-solid fa-ban" style="color:var(--danger)"></i> Decline Send Request</h3><p id="declineRequestInfo"></p><textarea id="declineReason" rows="3" placeholder="Reason for declining (will be sent to user)"></textarea><div style="display:flex; gap:1rem;"><button class="action-btn" id="confirmDeclineBtn">Confirm Decline</button><button class="action-btn" id="closeDeclineModal">Cancel</button></div></div></div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ duration: 700, once: true });

  window.__ADMIN_SEND_BOOT = <?php echo json_encode(
    [
      'csrf' => $csrf,
      'rows' => $sendRows,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ); ?>;

  // Sidebar & theme
  const sidebar = document.getElementById("sidebar");
  document.getElementById("collapseSidebar").addEventListener("click", () => { sidebar.classList.toggle("collapsed"); const icon = document.querySelector("#collapseSidebar i"); icon.classList.toggle("fa-chevron-left"); icon.classList.toggle("fa-chevron-right"); });
  document.getElementById("mobileMenuBtn").addEventListener("click", () => { sidebar.classList.toggle("mobile-open"); });
  document.addEventListener("click", (e) => { if(window.innerWidth <= 1100 && !sidebar.contains(e.target) && !document.getElementById("mobileMenuBtn").contains(e.target)) sidebar.classList.remove("mobile-open"); });
  document.getElementById("themeToggle").addEventListener("click", () => { document.body.classList.toggle("dark"); });

  const boot = window.__ADMIN_SEND_BOOT || { csrf: "", rows: [] };
  let sendRequests = Array.isArray(boot.rows) ? boot.rows : [];

  function postForm(payload) {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "<?php echo asc_h(basename(__FILE__)); ?>";
    const add = (name, value) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = String(value);
      form.appendChild(input);
    };
    Object.keys(payload).forEach((k) => {
      const v = payload[k];
      if (Array.isArray(v)) {
        v.forEach((item) => add(k + "[]", item));
      } else {
        add(k, v);
      }
    });
    document.body.appendChild(form);
    form.submit();
  }

  function renderSendTable() {
    const tbody = document.getElementById("sendTableBody");
    tbody.innerHTML = "";
    const statusFilter = document.getElementById("statusFilter").value;
    const assetFilter = document.getElementById("assetFilter").value;
    const riskFilter = document.getElementById("riskFilter").value;
    const searchTerm = document.getElementById("searchInput").value.toLowerCase();
    
    const filtered = sendRequests.filter(r => 
      (statusFilter === "all" || r.status === statusFilter) &&
      (assetFilter === "all" || r.asset === assetFilter) &&
      (riskFilter === "all" || r.risk === riskFilter) &&
      (
        String(r.userName || "").toLowerCase().includes(searchTerm) ||
        String(r.userId || "").includes(searchTerm) ||
        String(r.id || "").toLowerCase().includes(searchTerm) ||
        String(r.recipient || "").toLowerCase().includes(searchTerm) ||
        String(r.recipient_full || "").toLowerCase().includes(searchTerm)
      )
    );
    
    filtered.forEach(r => {
      const riskClass = r.risk === "low" ? "badge-green" : (r.risk === "medium" ? "badge-yellow" : "badge-red");
      const statusClass = r.status === "pending" ? "badge-yellow" : (r.status === "approved" ? "badge-green" : "badge-red");
      const id = String(r.id);
      const row = `<tr id="row-${id}">
        <td><input type="checkbox" class="select-request" data-id="${id}"></td>
        <td>#${id}</td>
        <td>${r.userName} (#${r.userId})</td>
        <td><small>${r.recipient}</small></td>
        <td>${r.asset}</td>
        <td>${r.amount}</td>
        <td>${r.network}</td>
        <td><span class="badge-status ${riskClass}">${r.risk}</span></td>
        <td><span class="badge-status ${statusClass}">${r.status}</span></td>
        <td>${r.submitted}</td>
        <td>
          ${r.status === "pending" ? `<button type="button" class="action-btn btn-approve" data-tx="${id}"><i class="fa-regular fa-thumbs-up"></i> Approve</button>
          <button type="button" class="action-btn btn-decline" data-tx="${id}"><i class="fa-regular fa-thumbs-down"></i> Decline</button>` : 
          `<span class="badge-status ${statusClass}">${r.status === "approved" ? "Approved" : "Declined"}</span>`}
          <button type="button" class="action-btn" data-view="${id}">View</button>
        </td>
       </tr>`;
      tbody.insertAdjacentHTML('beforeend', row);
    });

    tbody.querySelectorAll("button[data-tx].btn-approve").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-tx");
        postForm({ csrf_token: boot.csrf, action: "approve_one", tx_id: id });
      });
    });
    tbody.querySelectorAll("button[data-tx].btn-decline").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-tx");
        declineTargetId = id;
        const req = sendRequests.find((x) => String(x.id) === String(id));
        document.getElementById("declineRequestInfo").innerHTML = `Request #${id} from ${req ? req.userName : "user"} (${req ? req.asset : ""} ${req ? req.amount : ""})`;
        document.getElementById("declineModal").style.display = "flex";
      });
    });
    tbody.querySelectorAll("button[data-view]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-view");
        viewDetails(id);
      });
    });
  }

  let declineTargetId = null;
  document.getElementById("confirmDeclineBtn").addEventListener("click", () => {
    if(declineTargetId) {
      const reason = document.getElementById("declineReason").value || "";
      postForm({ csrf_token: boot.csrf, action: "decline_one", tx_id: declineTargetId, note: reason });
      document.getElementById("declineModal").style.display = "none";
      document.getElementById("declineReason").value = "";
      declineTargetId = null;
    }
  });
  document.getElementById("closeDeclineModal").addEventListener("click", () => {
    document.getElementById("declineModal").style.display = "none";
    declineTargetId = null;
  });

  window.viewDetails = (id) => {
    const req = sendRequests.find(r => String(r.id) === String(id));
    if (!req) return;
    const full = req.recipient_full ? `\nFull recipient: ${req.recipient_full}` : "";
    UiModal.alert(
      `Send Request Details\n\nID: #${req.id}\nUser: ${req.userName} (#${req.userId})\nRecipient: ${req.recipient}${full}\nAsset: ${req.asset}\nAmount: ${req.amount}\nNetwork: ${req.network}\nRisk: ${req.risk}\nStatus: ${req.status}\nSubmitted: ${req.submitted}`,
      { title: "Send details", okText: "Close" },
    );
  };

  // Bulk actions
  document.getElementById("bulkApproveBtn").addEventListener("click", () => {
    const checkboxes = document.querySelectorAll(".select-request:checked");
    const ids = [];
    checkboxes.forEach(cb => {
      const id = cb.getAttribute("data-id");
      const req = sendRequests.find(r => String(r.id) === String(id));
      if(req && req.status === "pending") ids.push(id);
    });
    if(ids.length === 0) {
      UiModal.alert("No pending requests selected.", { title: "Bulk approve" });
      return;
    }
    postForm({ csrf_token: boot.csrf, action: "bulk_approve", tx_ids: ids });
  });

  document.getElementById("bulkDeclineBtn").addEventListener("click", async () => {
    const checkboxes = document.querySelectorAll(".select-request:checked");
    const ids = [];
    checkboxes.forEach(cb => {
      const id = cb.getAttribute("data-id");
      const req = sendRequests.find(r => String(r.id) === String(id));
      if(req && req.status === "pending") ids.push(id);
    });
    if(ids.length === 0) {
      await UiModal.alert("No pending requests selected.", { title: "Bulk decline" });
      return;
    }
    const reason = await UiModal.prompt(
      "Decline reason (optional, applies to all selected):",
      { title: "Bulk decline", okText: "Decline", cancelText: "Cancel" }
    );
    if (reason === null) return;
    postForm({ csrf_token: boot.csrf, action: "bulk_decline", tx_ids: ids, note: reason });
  });

  document.getElementById("applyFilters").addEventListener("click", () => { renderSendTable(); });
  document.getElementById("searchInput").addEventListener("input", () => { renderSendTable(); });
  
  document.getElementById("exportCSVBtn").addEventListener("click", () => {
    let csv = "Request ID,UserId,User,Recipient,Asset,Amount,Network,Risk,Status,Submitted\n";
    sendRequests.forEach(r => { csv += `${r.id},${r.userId},${r.userName},${r.recipient},${r.asset},${r.amount},${r.network},${r.risk},${r.status},${r.submitted}\n`; });
    const blob = new Blob([csv], {type: "text/csv"});
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob); link.download = "crypterra_send_requests.csv"; link.click();
  });

  // AI Assistant
  document.getElementById("aiBtn").addEventListener("click", () => {
    const pendingCount = sendRequests.filter(r => r.status === "pending").length;
    const highRiskCount = sendRequests.filter(r => r.risk === "high" && r.status === "pending").length;
    const vol = sendRequests.reduce((sum, r) => sum + (Number(r.usd) || 0), 0);
    UiModal.alert(
      `Send queue insights\n\n• ${pendingCount} pending\n• ${highRiskCount} high-risk pending\n• Estimated total volume (page list): $${vol.toFixed(2)}`,
      { title: "Insights", okText: "Close" },
    );
  });

  renderSendTable();
</script>
</body>
</html>