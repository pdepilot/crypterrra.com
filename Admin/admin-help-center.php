<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
require_once dirname(__DIR__) . '/Userprofile/includes/user_db.php';

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
  if (!admin_verify_csrf($token)) {
    $flash = 'Invalid security token.';
  } else {
    $tid = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    if ($tid > 0 && $status !== '') {
      try {
        $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
        $ok = admin_support_update_status($db, $tid, $status);
        $db->close();
        $flash = $ok ? 'Ticket updated.' : 'Could not update ticket.';
      } catch (Throwable $e) {
        error_log('[admin-help-center] ' . $e->getMessage());
        $flash = 'Database error.';
      }
    }
  }
  if ($flash !== '') {
    header('Location: admin-help-center.php?msg=' . rawurlencode($flash));
    exit;
  }
  header('Location: admin-help-center.php');
  exit;
}

if (isset($_GET['msg']) && is_string($_GET['msg'])) {
  $flash = substr($_GET['msg'], 0, 500);
}

$tickets = [];
$counts = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  $tickets = admin_support_list_all($db, 400);
  $counts = admin_support_status_counts($db);
  $db->close();
} catch (Throwable $e) {
  error_log('[admin-help-center] ' . $e->getMessage());
}

function ah(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_status_label(string $s): string {
  return match (strtolower($s)) {
    'open' => 'Open',
    'in_progress' => 'In progress',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
    default => ucfirst($s),
  };
}

$csrf = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
  <title>Crypterra · Help Center | Admin</title>
  <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&display=swap" rel="stylesheet" />
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root {
      --primary: #22c55e;
      --primary-dark: #16a34a;
      --danger: #ef4444;
      --warning: #f59e0b;
      --success: #10b981;
      --info: #3b82f6;
      --bg: #0a0f1a;
      --bg-secondary: #0e1422;
      --surface: #141b2b;
      --text: #f1f5f9;
      --text-muted: #7f8ea3;
      --glass-edge: rgba(34, 197, 94, 0.25);
      --card-bg: rgba(20, 30, 50, 0.6);
      --shadow-glow: 0 0 30px rgba(34, 197, 94, 0.35);
    }
    body { font-family: "Inter", sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
    .admin { display: flex; min-height: 100vh; }
    .sidebar {
      width: 280px;
      background: rgba(10, 20, 35, 0.95);
      border-right: 1px solid var(--glass-edge);
      padding: 1.5rem 1rem;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }
    .sidebar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
    .logo { display: flex; align-items: center; gap: 0.6rem; font-weight: 700; font-size: 1.1rem; }
    .logo-icon {
      width: 40px; height: 40px; border-radius: 12px;
      background: linear-gradient(145deg, var(--primary), var(--primary-dark));
      display: flex; align-items: center; justify-content: center; color: #fff;
    }
    .sidebar-nav a {
      display: flex; align-items: center; gap: 0.6rem;
      padding: 0.65rem 0.75rem; border-radius: 12px; color: var(--text-muted);
      text-decoration: none; margin-bottom: 4px; font-size: 0.9rem;
    }
    .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(34, 197, 94, 0.12); color: var(--primary); }
    .sidebar-nav a.active { border-left: 3px solid var(--primary); }
    .nav-section { font-size: 0.65rem; letter-spacing: 1px; color: var(--text-muted); margin: 1rem 0 0.5rem 0.5rem; }
    .main { flex: 1; overflow-x: hidden; }
    .top-bar {
      background: rgba(20, 30, 45, 0.6);
      border-bottom: 1px solid var(--glass-edge);
      padding: 0.9rem 1.25rem;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 50;
    }
    .content { padding: 1.5rem; }
    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .metric-card {
      background: var(--card-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 20px;
      padding: 1rem 1.25rem;
    }
    .metric-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
    .glass-table-container {
      background: var(--card-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 24px;
      padding: 1.25rem;
      overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; min-width: 900px; }
    th { text-align: left; padding: 0.75rem 0.5rem; color: var(--primary); border-bottom: 2px solid var(--glass-edge); font-size: 0.8rem; }
    td { padding: 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); vertical-align: top; font-size: 0.85rem; }
    tr:hover { background: rgba(34, 197, 94, 0.06); }
    .msg { padding: 0.75rem 1rem; border-radius: 12px; margin-bottom: 1rem; background: rgba(34, 197, 94, 0.15); border: 1px solid var(--glass-edge); }
    .badge { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 40px; font-size: 0.7rem; font-weight: 600; }
    .b-open { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
    .b-prog { background: rgba(59, 130, 246, 0.2); color: var(--info); }
    .b-res { background: rgba(16, 185, 129, 0.2); color: var(--success); }
    .b-closed { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
    .msg-cell { max-width: 280px; max-height: 72px; overflow: auto; font-size: 0.8rem; color: var(--text-muted); }
    select.status-sel {
      background: var(--surface);
      border: 1px solid var(--glass-edge);
      color: var(--text);
      border-radius: 20px;
      padding: 0.35rem 0.6rem;
      font-size: 0.8rem;
    }
    .btn-up { background: var(--primary); color: #fff; border: none; border-radius: 20px; padding: 0.35rem 0.75rem; font-size: 0.75rem; cursor: pointer; margin-left: 6px; }
    .btn-up:hover { filter: brightness(1.08); }
    @media (max-width: 900px) { .sidebar { display: none; } }
  </style>
</head>
<body>
<div class="admin">
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo"><div class="logo-icon"><i class="fa-solid fa-globe"></i></div><span>Crypterra</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">MAIN</div>
      <a href="./admin-dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
      <a href="./admin-users.php"><i class="fa-solid fa-users"></i> Users</a>
      <a href="./admin-send.php"><i class="fa-solid fa-paper-plane"></i> Send Mgmt</a>
      <a href="./admin-receive.php"><i class="fa-solid fa-arrow-down"></i> Receive Mgmt</a>
      <a href="./admin-contact.php"><i class="fa-solid fa-envelope"></i> Contact Mgmt</a>
      <a href="./admin-help-center.php" class="active"><i class="fa-solid fa-life-ring"></i> Help Center</a>
      <a href="./admin-transactions.php"><i class="fa-solid fa-arrow-right-arrow-left"></i> Transactions</a>
      <a href="./admin-kyc.php"><i class="fa-solid fa-id-card"></i> KYC</a>
      <a href="./admin-security-center.php"><i class="fa-solid fa-shield"></i> Security</a>
      <a href="./admin-deposit.php"><i class="fa-solid fa-arrow-down"></i> Deposits</a>
      <a href="./admin-withdrawal.php"><i class="fa-solid fa-arrow-up"></i> Withdrawals</a>
      <a href="./admin-market-control.php"><i class="fa-solid fa-chart-line"></i> Market Control</a>
      <a href="./admin-wallet-control.php"><i class="fa-solid fa-wallet"></i> Wallet Control</a>
      <a href="./admin-analytics.php"><i class="fa-solid fa-magnifying-glass-chart"></i> Analytics</a>
      <a href="./admin-notifications.php"><i class="fa-solid fa-bell"></i> Notifications</a>
      <a href="./admin-settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
      <div class="nav-section">SESSION</div>
      <a href="./admin-logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>
  <main class="main">
    <div class="top-bar">
      <h1 style="font-size:1.1rem;font-weight:600"><i class="fa-solid fa-headset" style="color:var(--primary)"></i> Help Center — User support tickets</h1>
      <span style="color:var(--text-muted);font-size:0.85rem">Synced with Userprofile/support.php · DB: <code style="color:var(--text-muted)"><?php echo ah((string)$DB_NAME); ?></code></span>
    </div>
    <div class="content">
      <?php if ($flash !== '') { ?>
      <div class="msg"><?php echo ah($flash); ?></div>
      <?php } ?>

      <div class="metric-grid" data-aos="fade-up">
        <div class="metric-card"><div class="metric-value"><?php echo (int)($counts['total'] ?? 0); ?></div><div style="color:var(--text-muted);font-size:0.85rem">Total tickets</div></div>
        <div class="metric-card"><div class="metric-value"><?php echo (int)($counts['open'] ?? 0); ?></div><div style="color:var(--text-muted);font-size:0.85rem">Open</div></div>
        <div class="metric-card"><div class="metric-value"><?php echo (int)($counts['in_progress'] ?? 0); ?></div><div style="color:var(--text-muted);font-size:0.85rem">In progress</div></div>
        <div class="metric-card"><div class="metric-value"><?php echo (int)($counts['resolved'] ?? 0); ?></div><div style="color:var(--text-muted);font-size:0.85rem">Resolved</div></div>
        <div class="metric-card"><div class="metric-value"><?php echo (int)($counts['closed'] ?? 0); ?></div><div style="color:var(--text-muted);font-size:0.85rem">Closed</div></div>
      </div>

      <div class="glass-table-container" data-aos="fade-up">
        <h3 style="margin-bottom:1rem;font-size:1rem"><i class="fa-solid fa-ticket" style="color:var(--primary)"></i> Tickets from users</h3>
        <?php if ($tickets === []) { ?>
        <p style="color:var(--text-muted);padding:1rem">No tickets yet. When users submit from Support in their profile, they appear here.</p>
        <?php } else { ?>
        <table>
          <thead>
            <tr>
              <th>Ref</th>
              <th>User ID</th>
              <th>Name / Email</th>
              <th>Type</th>
              <th>Priority</th>
              <th>Subject</th>
              <th>Message</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $t) {
              $st = (string)($t['status'] ?? 'open');
              $badge = 'b-open';
              if ($st === 'in_progress') {
                $badge = 'b-prog';
              } elseif ($st === 'resolved') {
                $badge = 'b-res';
              } elseif ($st === 'closed') {
                $badge = 'b-closed';
              }
              ?>
            <tr>
              <td><strong><?php echo ah((string)$t['ticket_ref']); ?></strong></td>
              <td><?php echo (int)$t['user_id']; ?></td>
              <td><?php echo ah((string)$t['full_name']); ?><br><small style="color:var(--text-muted)"><?php echo ah((string)$t['email']); ?></small></td>
              <td><?php echo ah((string)$t['issue_type']); ?></td>
              <td><?php echo ah((string)$t['priority']); ?></td>
              <td><?php echo ah((string)$t['subject']); ?></td>
              <td><div class="msg-cell"><?php echo nl2br(ah((string)$t['message'])); ?></div></td>
              <td>
                <span class="badge <?php echo ah($badge); ?>"><?php echo ah(admin_status_label($st)); ?></span>
                <form method="post" style="margin-top:8px;display:flex;flex-wrap:wrap;align-items:center;gap:0.25rem">
                  <input type="hidden" name="csrf_token" value="<?php echo ah($csrf); ?>" />
                  <input type="hidden" name="ticket_id" value="<?php echo (int)$t['id']; ?>" />
                  <select name="status" class="status-sel">
                    <option value="open" <?php echo $st === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $st === 'in_progress' ? 'selected' : ''; ?>>In progress</option>
                    <option value="resolved" <?php echo $st === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $st === 'closed' ? 'selected' : ''; ?>>Closed</option>
                  </select>
                  <button type="submit" class="btn-up" name="update_ticket_status" value="1">Save</button>
                </form>
              </td>
              <td><small><?php echo ah((string)$t['created_at']); ?></small></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
        <?php } ?>
      </div>
    </div>
  </main>
</div>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>AOS.init({ duration: 500, once: true });</script>
</body>
</html>
