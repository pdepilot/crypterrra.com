<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';
require_once dirname(__DIR__, 2) . '/includes/contact_helpers.php';

function admin_dash_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_dash_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_dash_table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = @$db->query("SHOW TABLES LIKE '{$t}'");
  if (!$r) {
    return false;
  }
  $ok = $r->num_rows > 0;
  $r->free();
  return $ok;
}

function admin_dash_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
  $s = $db->real_escape_string($schema);
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($column);
  $r = @$db->query(
    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$s}' AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'"
  );
  if (!$r) return false;
  $row = $r->fetch_assoc();
  $r->free();
  return isset($row['n']) && (int)$row['n'] > 0;
}

function admin_dash_badge_class_status(string $status): string {
  $s = strtolower(trim($status));
  return match ($s) {
    'active', 'completed', 'verified', 'approved' => 'badge-green',
    'pending', 'maintenance', 'review' => 'badge-yellow',
    'frozen', 'suspended', 'rejected', 'failed' => 'badge-red',
    default => 'badge-yellow',
  };
}

function admin_dash_money(float $n): string {
  return '$' . number_format($n, 2);
}

/**
 * @return array<int, array{id:int,name:string,email:string,country:string,status:string,balance_usd:float,kyc:string}>
 */
function admin_dash_fetch_users(mysqli $db, int $limit = 8): array {
  $limit = max(1, min(30, $limit));

  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;

  $hasCountry = admin_dash_column_exists($db, $schema, 'users', 'country');
  $hasKyc = admin_dash_column_exists($db, $schema, 'users', 'kyc_status');
  $hasBal = admin_dash_column_exists($db, $schema, 'users', 'available_balance_usd');

  $cols = [
    'id',
    'full_name',
    'email',
    'is_active',
    $hasCountry ? 'country' : "'' AS country",
    $hasKyc ? 'kyc_status' : "'' AS kyc_status",
    $hasBal ? 'available_balance_usd' : '0.00 AS available_balance_usd',
  ];

  $sql = 'SELECT ' . implode(', ', $cols) . ' FROM users ORDER BY id DESC LIMIT ' . (int)$limit;
  $r = @$db->query($sql);
  if (!$r) return [];

  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $isActive = isset($row['is_active']) ? (int)$row['is_active'] : 0;
    $rows[] = [
      'id' => (int)($row['id'] ?? 0),
      'name' => (string)($row['full_name'] ?? ''),
      'email' => (string)($row['email'] ?? ''),
      'country' => ((string)($row['country'] ?? '')) !== '' ? (string)$row['country'] : '—',
      'status' => $isActive === 1 ? 'Active' : 'Frozen',
      'balance_usd' => (float)($row['available_balance_usd'] ?? 0),
      'kyc' => ((string)($row['kyc_status'] ?? '')) !== '' ? (string)$row['kyc_status'] : 'N/A',
    ];
  }
  $r->free();
  return $rows;
}

/**
 * @return array<int, array{id:int,type:string,type_raw:string,amount_label:string,user_label:string,status:string,status_raw:string,created_at:string}>
 */
function admin_dash_fetch_recent_transactions(mysqli $db, int $limit = 6): array {
  $limit = max(1, min(20, $limit));
  if (!admin_dash_table_exists($db, 'transactions')) return [];

  $sql = 'SELECT t.id, t.user_id, t.type, t.asset, t.amount, t.status, t.created_at, u.full_name, u.wallet_id
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id
          ORDER BY t.created_at DESC, t.id DESC
          LIMIT ' . (int)$limit;
  $r = @$db->query($sql);
  if (!$r) return [];

  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $typeRaw = strtolower((string)($row['type'] ?? ''));
    $asset = strtoupper((string)($row['asset'] ?? ''));
    $amount = (float)($row['amount'] ?? 0);
    $statusRaw = strtolower((string)($row['status'] ?? ''));
    $createdAt = (string)($row['created_at'] ?? '');
    $name = (string)($row['full_name'] ?? '');
    $walletId = (string)($row['wallet_id'] ?? '');

    $userLabel = $name !== '' ? $name : ($walletId !== '' ? $walletId : ('User #' . (int)($row['user_id'] ?? 0)));

    $amountLabel = '';
    if ($typeRaw === 'trade' && $asset !== '') {
      $amountLabel = $asset . ' @ ' . admin_dash_money($amount);
    } elseif ($asset !== '') {
      $amountLabel = admin_dash_money($amount) . ' ' . $asset;
    } else {
      $amountLabel = admin_dash_money($amount);
    }

    $rows[] = [
      'id' => (int)($row['id'] ?? 0),
      'type_raw' => $typeRaw,
      'type' => ucfirst($typeRaw !== '' ? $typeRaw : 'transaction'),
      'amount_label' => $amountLabel,
      'user_label' => $userLabel,
      'status_raw' => $statusRaw,
      'status' => $statusRaw !== '' ? $statusRaw : 'pending',
      'created_at' => $createdAt,
    ];
  }
  $r->free();
  return $rows;
}

/**
 * @return array<int, array{title:string,detail:string,time:string,icon:string}>
 */
function admin_dash_fetch_fraud_alerts(mysqli $db, int $limit = 2): array {
  $limit = max(1, min(6, $limit));
  $alerts = [];

  // Pending withdrawals above a threshold.
  if (admin_dash_table_exists($db, 'transactions')) {
    $sql = "SELECT t.amount, t.asset, t.created_at, u.full_name, u.wallet_id
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.type = 'withdrawal' AND t.status = 'pending'
            ORDER BY t.amount DESC, t.created_at DESC
            LIMIT " . (int)$limit;
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $amt = (float)($row['amount'] ?? 0);
        $asset = strtoupper((string)($row['asset'] ?? ''));
        $who = (string)($row['full_name'] ?? '');
        $wallet = (string)($row['wallet_id'] ?? '');
        $by = $who !== '' ? $who : ($wallet !== '' ? $wallet : 'unknown user');
        $alerts[] = [
          'title' => 'Pending withdrawal review',
          'detail' => admin_dash_money($amt) . ($asset !== '' ? " {$asset}" : '') . " • {$by}",
          'time' => (string)($row['created_at'] ?? ''),
          'icon' => 'fa-solid fa-bug',
        ];
      }
      $r->free();
    }
  }

  // If nothing found, return an empty list (UI will show "No alerts").
  return array_slice($alerts, 0, $limit);
}

/**
 * KYC queue (best-effort).
 * If `users.kyc_status` exists → show pending.
 * Else returns [].
 *
 * @return array<int, array{id:int,name:string}>
 */
function admin_dash_fetch_kyc_pending(mysqli $db, int $limit = 6): array {
  $limit = max(1, min(20, $limit));
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  if (!admin_dash_column_exists($db, $schema, 'users', 'kyc_status')) {
    return [];
  }
  $sql = "SELECT id, full_name FROM users WHERE LOWER(kyc_status) = 'pending' ORDER BY id DESC LIMIT " . (int)$limit;
  $r = @$db->query($sql);
  if (!$r) return [];
  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $rows[] = ['id' => (int)($row['id'] ?? 0), 'name' => (string)($row['full_name'] ?? '')];
  }
  $r->free();
  return $rows;
}

/**
 * Wallet control table: show wallets from users table.
 * @return array<int, array{wallet:string,name:string}>
 */
function admin_dash_fetch_wallet_controls(mysqli $db, int $limit = 6): array {
  $limit = max(1, min(20, $limit));
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  if (!admin_dash_column_exists($db, $schema, 'users', 'wallet_id')) {
    return [];
  }
  $sql = "SELECT wallet_id, full_name FROM users WHERE wallet_id IS NOT NULL AND wallet_id <> '' ORDER BY id DESC LIMIT " . (int)$limit;
  $r = @$db->query($sql);
  if (!$r) return [];
  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $rows[] = ['wallet' => (string)($row['wallet_id'] ?? ''), 'name' => (string)($row['full_name'] ?? '')];
  }
  $r->free();
  return $rows;
}

/**
 * Admin activity logs: uses contact module log as a real DB source.
 * @return array<int, array{time:string,admin:string,action:string}>
 */
function admin_dash_fetch_admin_activity_logs(int $limit = 20): array {
  $limit = max(1, min(100, $limit));
  try {
    $db = contact_db_connect();
    contact_ensure_tables($db);
    $logs = contact_fetch_activity_logs($limit);
    $db->close();
  } catch (Throwable) {
    return [];
  }

  $rows = [];
  foreach ($logs as $l) {
    $ticket = isset($l['ticket_ref']) ? (string)$l['ticket_ref'] : '';
    $details = isset($l['details']) ? (string)$l['details'] : '';
    $rows[] = [
      'time' => (string)($l['created_at'] ?? ''),
      'admin' => (string)($l['admin_email'] ?? ''),
      'action' => trim((string)($l['action'] ?? '') . ($ticket !== '' ? " {$ticket}" : '') . ($details !== '' ? " ({$details})" : '')),
    ];
  }
  return $rows;
}

/**
 * Market pairs: derive from traded assets.
 * @return array<int, array{pair:string,status:string,fee:string,action:string}>
 */
function admin_dash_fetch_market_pairs(mysqli $db, int $limit = 6): array {
  $limit = max(1, min(20, $limit));
  if (!admin_dash_table_exists($db, 'transactions')) return [];

  $sql = "SELECT asset, COUNT(*) AS n
          FROM transactions
          WHERE type = 'trade' AND asset <> ''
          GROUP BY asset
          ORDER BY n DESC
          LIMIT " . (int)$limit;
  $r = @$db->query($sql);
  if (!$r) return [];

  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $asset = strtoupper((string)($row['asset'] ?? ''));
    if ($asset === '') continue;
    $rows[] = [
      'pair' => $asset . '/USDT',
      'status' => 'active',
      'fee' => '0.1%',
      'action' => 'disable',
    ];
  }
  $r->free();
  return $rows;
}

function admin_dash_date_range_days(int $daysBack): array {
  $daysBack = max(1, min(365, $daysBack));
  $labels = [];
  for ($i = $daysBack - 1; $i >= 0; $i--) {
    $labels[] = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'))->format('D');
  }
  return $labels;
}

/**
 * User growth chart: last 6 months. Uses users.created_at if present; else falls back to total user count.
 * @return array{labels:array<int,string>, data:array<int,int>}
 */
function admin_dash_chart_user_growth(mysqli $db): array {
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  $hasCreated = admin_dash_column_exists($db, $schema, 'users', 'created_at');

  $labels = [];
  $data = [];
  for ($i = 5; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('first day of this month'))->sub(new DateInterval('P' . $i . 'M'));
    // Make labels unambiguous across year boundaries.
    $labels[] = $dt->format('M Y');
    $data[] = 0;
  }

  if (!$hasCreated) {
    if ($r = @$db->query('SELECT COUNT(*) AS c FROM users')) {
      $row = $r->fetch_assoc();
      $c = isset($row['c']) ? (int)$row['c'] : 0;
      $r->free();
      $data[count($data) - 1] = $c;
      return ['labels' => $labels, 'data' => $data];
    }
    return ['labels' => $labels, 'data' => $data];
  }

  // Count signups per month for last 6 months.
  $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
          FROM users
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY ym";
  $r = @$db->query($sql);
  if (!$r) return ['labels' => $labels, 'data' => $data];
  $map = [];
  while ($row = $r->fetch_assoc()) {
    $map[(string)$row['ym']] = (int)$row['c'];
  }
  $r->free();

  // Convert to cumulative series to match “growth”.
  $cum = 0;
  for ($j = 0; $j < 6; $j++) {
    $dt = (new DateTimeImmutable('first day of this month'))->sub(new DateInterval('P' . (5 - $j) . 'M'));
    $ym = $dt->format('Y-m');
    $cum += $map[$ym] ?? 0;
    $data[$j] = $cum;
  }

  // Add baseline: users before the window.
  $base = 0;
  $stmt = @$db->prepare("SELECT COUNT(*) AS c FROM users WHERE created_at < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
  if ($stmt) {
    $stmt->execute();
    $stmt->store_result();
    $c = 0;
    $stmt->bind_result($c);
    $stmt->fetch();
    $stmt->free_result();
    $stmt->close();
    $base = (int)$c;
  }
  foreach ($data as $idx => $v) {
    $data[$idx] = $base + (int)$v;
  }

  return ['labels' => $labels, 'data' => $data];
}

/**
 * Revenue chart: last 7 days (rolling) from completed deposits.
 * @return array{labels:array<int,string>, data:array<int,float>}
 */
function admin_dash_chart_revenue_weekly(mysqli $db): array {
  $labels = [];
  $data = [];
  for ($i = 6; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $labels[] = $dt->format('M j');
    $data[] = 0.0;
  }
  if (!admin_dash_table_exists($db, 'transactions')) {
    return ['labels' => $labels, 'data' => $data];
  }
  $sql = "SELECT DATE(created_at) AS d, COALESCE(SUM(amount),0) AS s
          FROM transactions
          WHERE type='deposit' AND status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
          GROUP BY d";
  $r = @$db->query($sql);
  if (!$r) return ['labels' => $labels, 'data' => $data];
  $map = [];
  while ($row = $r->fetch_assoc()) {
    $d = (string)($row['d'] ?? '');
    if ($d !== '') {
      $map[$d] = (float)($row['s'] ?? 0);
    }
  }
  $r->free();
  for ($i = 6; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $key = $dt->format('Y-m-d');
    $data[6 - $i] = $map[$key] ?? 0.0;
  }
  return ['labels' => $labels, 'data' => $data];
}

/**
 * Trading volume chart: 6 buckets for today (00,04,...20) using trade transactions.
 * @return array{labels:array<int,string>, data:array<int,float>}
 */
function admin_dash_chart_volume_today(mysqli $db): array {
  $labels = ['00', '04', '08', '12', '16', '20'];
  $data = array_fill(0, count($labels), 0.0);
  if (!admin_dash_table_exists($db, 'transactions')) {
    return ['labels' => $labels, 'data' => $data];
  }
  $sql = "SELECT FLOOR(HOUR(created_at)/4) AS b, COALESCE(SUM(ABS(amount)),0) AS s
          FROM transactions
          WHERE type='trade' AND created_at >= CURDATE()
          GROUP BY b";
  $r = @$db->query($sql);
  if (!$r) return ['labels' => $labels, 'data' => $data];
  while ($row = $r->fetch_assoc()) {
    $b = (int)($row['b'] ?? -1);
    if ($b >= 0 && $b < 6) {
      $data[$b] = (float)($row['s'] ?? 0);
    }
  }
  $r->free();
  return ['labels' => $labels, 'data' => $data];
}

/**
 * Deposit vs withdrawal: last 7 days (rolling).
 * @return array{labels:array<int,string>, deposits:array<int,float>, withdrawals:array<int,float>}
 */
function admin_dash_chart_deposit_vs_withdrawal(mysqli $db): array {
  $labels = [];
  $deposits = [];
  $withdrawals = [];
  for ($i = 6; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $labels[] = $dt->format('M j');
    $deposits[] = 0.0;
    $withdrawals[] = 0.0;
  }
  if (!admin_dash_table_exists($db, 'transactions')) {
    return ['labels' => $labels, 'deposits' => $deposits, 'withdrawals' => $withdrawals];
  }
  $sql = "SELECT type, DATE(created_at) AS d, COALESCE(SUM(amount),0) AS s
          FROM transactions
          WHERE type IN ('deposit','withdrawal') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
          GROUP BY type, d";
  $r = @$db->query($sql);
  if (!$r) return ['labels' => $labels, 'deposits' => $deposits, 'withdrawals' => $withdrawals];
  $depMap = [];
  $wdMap = [];
  while ($row = $r->fetch_assoc()) {
    $type = (string)($row['type'] ?? '');
    $d = (string)($row['d'] ?? '');
    if ($d === '') continue;
    $val = (float)($row['s'] ?? 0);
    if ($type === 'deposit') $depMap[$d] = $val;
    if ($type === 'withdrawal') $wdMap[$d] = $val;
  }
  $r->free();
  for ($i = 6; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $key = $dt->format('Y-m-d');
    $idx = 6 - $i;
    $deposits[$idx] = $depMap[$key] ?? 0.0;
    $withdrawals[$idx] = $wdMap[$key] ?? 0.0;
  }
  return ['labels' => $labels, 'deposits' => $deposits, 'withdrawals' => $withdrawals];
}

