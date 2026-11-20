<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_dashboard_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_dashboard_data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function aa_int(mixed $v, int $default): int {
  if (is_numeric($v)) return (int)$v;
  return $default;
}

function aa_period_days(string $period): int {
  $p = strtolower(trim($period));
  return match ($p) {
    'today' => 1,
    '7d' => 7,
    '30d' => 30,
    default => 1,
  };
}

function aa_table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = @$db->query("SHOW TABLES LIKE '{$t}'");
  if (!$r) return false;
  $ok = $r->num_rows > 0;
  $r->free();
  return $ok;
}

function aa_initials(string $nameOrEmail): string {
  $s = trim($nameOrEmail);
  if ($s === '') return 'NA';
  if (str_contains($s, '@')) {
    $s = explode('@', $s)[0] ?? $s;
  }
  $parts = preg_split('/[^a-zA-Z0-9]+/', $s) ?: [$s];
  $parts = array_values(array_filter($parts, fn($x) => $x !== ''));
  if (count($parts) === 1) {
    $w = $parts[0];
    return strtoupper(substr($w, 0, 2));
  }
  return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
}

function aa_platform_balance(mysqli $db): float {
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;

  $cols = [];
  foreach (['available_balance_usd', 'spot_balance_usd', 'futures_balance_usd', 'portfolio_value_usd'] as $c) {
    if (admin_schema_column_exists($db, $schema, 'users', $c)) {
      $cols[] = "`{$c}`";
    }
  }
  if (!$cols) return 0.0;
  $expr = implode(' + ', $cols);
  $sql = "SELECT COALESCE(SUM({$expr}),0) AS s FROM users";
  $r = @$db->query($sql);
  if (!$r) return 0.0;
  $row = $r->fetch_assoc();
  $r->free();
  return (float)($row['s'] ?? 0);
}

function aa_users_has_created_at(mysqli $db): bool {
  /** @var string $DB_NAME */
  global $DB_NAME;
  return admin_schema_column_exists($db, (string)$DB_NAME, 'users', 'created_at');
}

function aa_fetch_engagement(mysqli $db, int $days, int $limit = 4): array {
  $limit = max(1, min(10, $limit));
  $out = [
    'most_active' => [],
    'highest_balances' => [],
    'frequent_traders' => [],
  ];

  // Highest balances (from users table)
  $balCol = null;
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  foreach (['available_balance_usd', 'portfolio_value_usd', 'spot_balance_usd', 'futures_balance_usd'] as $c) {
    if (admin_schema_column_exists($db, $schema, 'users', $c)) {
      $balCol = $c;
      break;
    }
  }

  if ($balCol !== null) {
    $sql = "SELECT id, full_name, email, COALESCE({$balCol},0) AS bal
            FROM users
            ORDER BY bal DESC, id DESC
            LIMIT " . (int)$limit;
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $name = (string)($row['full_name'] ?? '');
        $email = (string)($row['email'] ?? '');
        $label = $name !== '' ? $name : ($email !== '' ? $email : ('User #' . (int)($row['id'] ?? 0)));
        $out['highest_balances'][] = [
          'initials' => aa_initials($label),
          'label' => $label,
          'value' => (float)($row['bal'] ?? 0),
        ];
      }
      $r->free();
    }
  }

  if (!aa_table_exists($db, 'transactions')) {
    return $out;
  }

  $daysBack = max(1, min(90, $days));

  // Most active users (trade count)
  $sql = "SELECT t.user_id, COUNT(*) AS n, MAX(t.created_at) AS last_at, u.full_name, u.email
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id
          WHERE t.type='trade'
            AND t.created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY)
          GROUP BY t.user_id
          ORDER BY n DESC, last_at DESC
          LIMIT " . (int)$limit;
  if ($r = @$db->query($sql)) {
    while ($row = $r->fetch_assoc()) {
      $name = (string)($row['full_name'] ?? '');
      $email = (string)($row['email'] ?? '');
      $label = $name !== '' ? $name : ($email !== '' ? $email : ('User #' . (int)($row['user_id'] ?? 0)));
      $out['most_active'][] = [
        'initials' => aa_initials($label),
        'label' => $label,
        'trades' => (int)($row['n'] ?? 0),
      ];
    }
    $r->free();
  }

  // Frequent traders (avg trades/day)
  $sql = "SELECT t.user_id, COUNT(*) AS n, u.full_name, u.email
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id
          WHERE t.type='trade'
            AND t.created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY)
          GROUP BY t.user_id
          ORDER BY (n / " . (int)$daysBack . ") DESC, n DESC
          LIMIT " . (int)$limit;
  if ($r = @$db->query($sql)) {
    while ($row = $r->fetch_assoc()) {
      $name = (string)($row['full_name'] ?? '');
      $email = (string)($row['email'] ?? '');
      $label = $name !== '' ? $name : ($email !== '' ? $email : ('User #' . (int)($row['user_id'] ?? 0)));
      $n = (int)($row['n'] ?? 0);
      $out['frequent_traders'][] = [
        'initials' => aa_initials($label),
        'label' => $label,
        'per_day' => $daysBack > 0 ? ($n / $daysBack) : 0.0,
      ];
    }
    $r->free();
  }

  return $out;
}

function aa_fetch_pairs(mysqli $db, int $days, int $limit = 5): array {
  $limit = max(1, min(20, $limit));
  if (!aa_table_exists($db, 'transactions')) return [];
  $daysBack = max(1, min(90, $days));

  // Current window
  $sql = "SELECT COALESCE(NULLIF(UPPER(asset),''),'—') AS asset,
                 COUNT(*) AS trades,
                 COALESCE(SUM(ABS(amount)),0) AS volume
          FROM transactions
          WHERE type='trade'
            AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY)
          GROUP BY asset
          ORDER BY volume DESC, trades DESC
          LIMIT " . (int)$limit;
  $cur = [];
  if ($r = @$db->query($sql)) {
    while ($row = $r->fetch_assoc()) {
      $asset = (string)($row['asset'] ?? '—');
      $cur[$asset] = [
        'asset' => $asset,
        'pair' => $asset !== '—' ? ($asset . '/USDT') : '—',
        'trades' => (int)($row['trades'] ?? 0),
        'volume' => (float)($row['volume'] ?? 0),
      ];
    }
    $r->free();
  }

  // Previous window for change %
  $sqlPrev = "SELECT COALESCE(NULLIF(UPPER(asset),''),'—') AS asset,
                     COALESCE(SUM(ABS(amount)),0) AS volume
              FROM transactions
              WHERE type='trade'
                AND created_at < DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY)
                AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)($daysBack * 2) . " DAY)
              GROUP BY asset";
  $prevMap = [];
  if ($r = @$db->query($sqlPrev)) {
    while ($row = $r->fetch_assoc()) {
      $asset = (string)($row['asset'] ?? '—');
      $prevMap[$asset] = (float)($row['volume'] ?? 0);
    }
    $r->free();
  }

  $rows = [];
  foreach ($cur as $asset => $row) {
    $prev = $prevMap[$asset] ?? 0.0;
    $change = 0.0;
    if ($prev > 0) {
      $change = (($row['volume'] - $prev) / $prev) * 100.0;
    } elseif ($row['volume'] > 0) {
      $change = 100.0;
    }
    $rows[] = $row + ['change_pct' => $change];
  }
  return $rows;
}

function aa_fetch_assets_distribution(mysqli $db, int $days, int $limit = 4): array {
  $limit = max(1, min(10, $limit));
  if (!aa_table_exists($db, 'transactions')) return [];
  $daysBack = max(1, min(90, $days));

  $sql = "SELECT COALESCE(NULLIF(UPPER(asset),''),'—') AS asset,
                 COALESCE(SUM(ABS(amount)),0) AS volume
          FROM transactions
          WHERE type='trade'
            AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY)
          GROUP BY asset
          ORDER BY volume DESC
          LIMIT " . (int)$limit;
  $rows = [];
  $total = 0.0;
  if ($r = @$db->query($sql)) {
    while ($row = $r->fetch_assoc()) {
      $vol = (float)($row['volume'] ?? 0);
      $rows[] = ['asset' => (string)($row['asset'] ?? '—'), 'volume' => $vol];
      $total += $vol;
    }
    $r->free();
  }
  foreach ($rows as &$row) {
    $row['pct'] = $total > 0 ? (($row['volume'] / $total) * 100.0) : 0.0;
  }
  unset($row);
  return $rows;
}

function aa_fetch_insights(mysqli $db, int $days): array {
  $daysBack = max(1, min(90, $days));
  $insights = [];

  if (aa_table_exists($db, 'transactions')) {
    // Compare trade volume current vs previous window.
    $sql = "SELECT
              COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY) THEN ABS(amount) ELSE 0 END),0) AS cur,
              COALESCE(SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY) AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)($daysBack * 2) . " DAY) THEN ABS(amount) ELSE 0 END),0) AS prev
            FROM transactions
            WHERE type='trade'";
    $cur = 0.0;
    $prev = 0.0;
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $cur = (float)($row['cur'] ?? 0);
      $prev = (float)($row['prev'] ?? 0);
      $r->free();
    }
    $pct = $prev > 0 ? (($cur - $prev) / $prev) * 100.0 : ($cur > 0 ? 100.0 : 0.0);
    $insights[] = [
      'level' => 'insight',
      'icon' => 'fa-solid fa-chart-line',
      'title' => 'Trading volume ' . ($pct >= 0 ? 'increased' : 'decreased') . ' by ' . number_format(abs($pct), 1) . '%',
      'detail' => 'Compared to the previous period',
    ];

    // Withdrawal spike
    $sql = "SELECT
              COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY) THEN amount ELSE 0 END),0) AS cur,
              COALESCE(SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY) AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)($daysBack * 2) . " DAY) THEN amount ELSE 0 END),0) AS prev
            FROM transactions
            WHERE type='withdrawal'";
    $cur = 0.0;
    $prev = 0.0;
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $cur = (float)($row['cur'] ?? 0);
      $prev = (float)($row['prev'] ?? 0);
      $r->free();
    }
    $pct = $prev > 0 ? (($cur - $prev) / $prev) * 100.0 : ($cur > 0 ? 100.0 : 0.0);
    $insights[] = [
      'level' => $pct >= 25 ? 'warning' : 'insight',
      'icon' => 'fa-solid fa-exclamation-triangle',
      'title' => 'Withdrawals ' . ($pct >= 0 ? 'up' : 'down') . ' ' . number_format(abs($pct), 1) . '%',
      'detail' => 'Compared to the previous period',
    ];

    // Suspicious activity heuristic: users with lots of trades
    $sql = "SELECT COUNT(*) AS n FROM (
              SELECT user_id, COUNT(*) AS c
              FROM transactions
              WHERE type='trade'
                AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY)
              GROUP BY user_id
              HAVING c >= 200
            ) x";
    $n = 0;
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $n = (int)($row['n'] ?? 0);
      $r->free();
    }
    $insights[] = [
      'level' => $n > 0 ? 'critical' : 'insight',
      'icon' => $n > 0 ? 'fa-solid fa-skull' : 'fa-solid fa-shield',
      'title' => $n > 0 ? 'Suspicious activity detected' : 'No suspicious patterns detected',
      'detail' => $n > 0 ? "{$n} high-frequency trader(s) flagged by heuristic" : 'No high-frequency anomalies in this period',
    ];
  }

  // User growth accelerating (if users.created_at exists)
  if (aa_users_has_created_at($db)) {
    $sql = "SELECT
              COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY) THEN 1 ELSE 0 END),0) AS cur,
              COALESCE(SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL " . (int)$daysBack . " DAY) AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)($daysBack * 2) . " DAY) THEN 1 ELSE 0 END),0) AS prev
            FROM users";
    $cur = 0;
    $prev = 0;
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $cur = (int)($row['cur'] ?? 0);
      $prev = (int)($row['prev'] ?? 0);
      $r->free();
    }
    $pct = $prev > 0 ? (($cur - $prev) / $prev) * 100.0 : ($cur > 0 ? 100.0 : 0.0);
    $insights[] = [
      'level' => 'insight',
      'icon' => 'fa-solid fa-users',
      'title' => 'User growth ' . ($pct >= 0 ? 'up' : 'down') . ' ' . number_format(abs($pct), 1) . '%',
      'detail' => 'Compared to the previous period',
    ];
  } else {
    $insights[] = [
      'level' => 'insight',
      'icon' => 'fa-solid fa-users',
      'title' => 'User growth metric limited',
      'detail' => 'Add `users.created_at` for signup-based growth insights',
    ];
  }

  return array_slice($insights, 0, 6);
}

function aa_fetch_feed(mysqli $db, int $limit = 5): array {
  $limit = max(1, min(20, $limit));
  $events = [];

  // Recent users (if created_at exists)
  if (aa_users_has_created_at($db)) {
    $sql = "SELECT full_name, email, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT " . (int)$limit;
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $name = (string)($row['full_name'] ?? '');
        $email = (string)($row['email'] ?? '');
        $label = $name !== '' ? $name : ($email !== '' ? $email : 'New user');
        $events[] = [
          'kind' => 'user',
          'iconClass' => 'user',
          'text' => $label . ' registered',
          'ts' => (string)($row['created_at'] ?? ''),
        ];
      }
      $r->free();
    }
  }

  // Recent transactions
  if (aa_table_exists($db, 'transactions')) {
    $sql = "SELECT type, amount, asset, created_at, u.full_name, u.email
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT " . (int)$limit;
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $type = strtolower((string)($row['type'] ?? ''));
        $amt = (float)($row['amount'] ?? 0);
        $asset = strtoupper((string)($row['asset'] ?? ''));
        $who = (string)($row['full_name'] ?? '');
        if ($who === '') $who = (string)($row['email'] ?? '');
        if ($who === '') $who = 'A user';

        $iconClass = $type === 'deposit' ? 'deposit' : ($type === 'trade' ? 'trade' : ($type === 'withdrawal' ? 'withdrawal' : 'trade'));
        $labelAmt = ($type === 'trade')
          ? ('$' . number_format(abs($amt), 2))
          : ('$' . number_format($amt, 2) . ($asset !== '' ? " {$asset}" : ''));
        $verb = $type === 'deposit' ? 'deposited' : ($type === 'withdrawal' ? 'withdrew' : ($type === 'trade' ? 'traded' : 'transacted'));

        $events[] = [
          'kind' => 'tx',
          'iconClass' => $iconClass,
          'text' => "{$who} {$verb} {$labelAmt}",
          'ts' => (string)($row['created_at'] ?? ''),
        ];
      }
      $r->free();
    }
  }

  // Sort by timestamp desc (best-effort)
  usort($events, function ($a, $b) {
    $at = strtotime((string)($a['ts'] ?? '')) ?: 0;
    $bt = strtotime((string)($b['ts'] ?? '')) ?: 0;
    return $bt <=> $at;
  });

  return array_slice($events, 0, $limit);
}

$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'snapshot';
$period = isset($_GET['period']) && is_string($_GET['period']) ? $_GET['period'] : 'today';
$days = aa_period_days($period);

try {
  $db = admin_dash_db();
} catch (Throwable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

try {
  if ($action === 'snapshot') {
    $limit = aa_int($_GET['limit'] ?? 5, 5);
    $eng = aa_fetch_engagement($db, $days, 4);
    $pairs = aa_fetch_pairs($db, $days, 5);
    $assets = aa_fetch_assets_distribution($db, $days, 4);
    $platform = aa_platform_balance($db);
    $avg = 0.0;
    if ($r = @$db->query("SELECT COUNT(*) AS c FROM users")) {
      $row = $r->fetch_assoc();
      $c = (int)($row['c'] ?? 0);
      $r->free();
      $avg = $c > 0 ? ($platform / $c) : 0.0;
    }
    $insights = aa_fetch_insights($db, $days);
    $feed = aa_fetch_feed($db, $limit);

    $db->close();
    echo json_encode([
      'ok' => true,
      'period' => $period,
      'engagement' => $eng,
      'pairs' => $pairs,
      'financial' => [
        'platform_balance_usd' => $platform,
        'avg_user_balance_usd' => $avg,
        'top_assets' => $assets,
      ],
      'insights' => $insights,
      'feed' => $feed,
    ]);
    exit;
  }

  if ($action === 'feed') {
    $limit = aa_int($_GET['limit'] ?? 5, 5);
    $feed = aa_fetch_feed($db, $limit);
    $db->close();
    echo json_encode(['ok' => true, 'feed' => $feed]);
    exit;
  }

  $db->close();
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'unknown_action']);
} catch (Throwable) {
  $db->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server']);
}

