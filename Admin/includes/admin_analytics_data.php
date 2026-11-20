<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_dashboard_helpers.php';
require_once __DIR__ . '/admin_dashboard_data.php';

function admin_analytics_money_compact(float $n): string {
  return admin_format_usd_compact($n);
}

function admin_analytics_table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = @$db->query("SHOW TABLES LIKE '{$t}'");
  if (!$r) return false;
  $ok = $r->num_rows > 0;
  $r->free();
  return $ok;
}

/**
 * @return array{labels:array<int,string>, data:array<int,float>}
 */
function admin_analytics_chart_sum_by_day(mysqli $db, string $type, int $daysBack): array {
  $daysBack = max(1, min(90, $daysBack));
  $labels = [];
  $data = [];
  for ($i = $daysBack - 1; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $labels[] = $dt->format('M j');
    $data[] = 0.0;
  }

  if (!admin_analytics_table_exists($db, 'transactions')) {
    return ['labels' => $labels, 'data' => $data];
  }

  $type = strtolower(trim($type));
  if (!in_array($type, ['deposit', 'withdrawal', 'trade'], true)) {
    return ['labels' => $labels, 'data' => $data];
  }

  $sql = "SELECT DATE(created_at) AS d,
                 COALESCE(SUM(" . ($type === 'trade' ? 'ABS(amount)' : 'amount') . "),0) AS s
          FROM transactions
          WHERE type = '" . $db->real_escape_string($type) . "'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . (int)($daysBack - 1) . " DAY)
          GROUP BY d";
  $r = @$db->query($sql);
  if (!$r) return ['labels' => $labels, 'data' => $data];
  $map = [];
  while ($row = $r->fetch_assoc()) {
    $d = (string)($row['d'] ?? '');
    if ($d !== '') $map[$d] = (float)($row['s'] ?? 0);
  }
  $r->free();

  for ($i = $daysBack - 1; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $key = $dt->format('Y-m-d');
    $idx = ($daysBack - 1) - $i;
    $data[$idx] = $map[$key] ?? 0.0;
  }
  return ['labels' => $labels, 'data' => $data];
}

/**
 * @return array{labels:array<int,string>, deposits:array<int,float>, withdrawals:array<int,float>}
 */
function admin_analytics_chart_deposit_vs_withdrawal_days(mysqli $db, int $daysBack): array {
  $daysBack = max(1, min(90, $daysBack));
  $labels = [];
  $deposits = [];
  $withdrawals = [];
  for ($i = $daysBack - 1; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $labels[] = $dt->format('M j');
    $deposits[] = 0.0;
    $withdrawals[] = 0.0;
  }

  if (!admin_analytics_table_exists($db, 'transactions')) {
    return ['labels' => $labels, 'deposits' => $deposits, 'withdrawals' => $withdrawals];
  }

  $sql = "SELECT type, DATE(created_at) AS d, COALESCE(SUM(amount),0) AS s
          FROM transactions
          WHERE type IN ('deposit','withdrawal')
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . (int)($daysBack - 1) . " DAY)
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

  for ($i = $daysBack - 1; $i >= 0; $i--) {
    $dt = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
    $key = $dt->format('Y-m-d');
    $idx = ($daysBack - 1) - $i;
    $deposits[$idx] = $depMap[$key] ?? 0.0;
    $withdrawals[$idx] = $wdMap[$key] ?? 0.0;
  }

  return ['labels' => $labels, 'deposits' => $deposits, 'withdrawals' => $withdrawals];
}

/**
 * @return array{deposits:float, withdrawals:float, trades:float}
 */
function admin_analytics_tx_type_breakdown(mysqli $db, int $daysBack): array {
  $daysBack = max(1, min(90, $daysBack));
  $out = ['deposits' => 0.0, 'withdrawals' => 0.0, 'trades' => 0.0];
  if (!admin_analytics_table_exists($db, 'transactions')) return $out;

  $sql = "SELECT type, COALESCE(SUM(" .
    "CASE WHEN type='trade' THEN ABS(amount) ELSE amount END" .
    "),0) AS s
    FROM transactions
    WHERE type IN ('deposit','withdrawal','trade')
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . (int)($daysBack - 1) . " DAY)
    GROUP BY type";
  $r = @$db->query($sql);
  if (!$r) return $out;
  while ($row = $r->fetch_assoc()) {
    $t = strtolower((string)($row['type'] ?? ''));
    $s = (float)($row['s'] ?? 0);
    if ($t === 'deposit') $out['deposits'] = $s;
    if ($t === 'withdrawal') $out['withdrawals'] = $s;
    if ($t === 'trade') $out['trades'] = $s;
  }
  $r->free();
  return $out;
}

/**
 * Builds all datasets needed by `admin-analytics.php`.
 *
 * @return array{
 *   kpis: array{total_users:int, active_users:int, total_deposits_usd:float, total_withdrawals_usd:float, trading_volume_usd:float, revenue_usd:float, platform_balance_usd:float, avg_user_balance_usd:float},
 *   series: array{
 *     today: array{revenue:array{labels:array<int,string>,data:array<int,float>}, users:array{labels:array<int,string>,data:array<int,int>}, volume:array{labels:array<int,string>,data:array<int,float>}, dvsw:array{labels:array<int,string>,deposits:array<int,float>,withdrawals:array<int,float>}, pie:array{deposits:float,withdrawals:float,trades:float}},
 *     d7: array{revenue:array{labels:array<int,string>,data:array<int,float>}, users:array{labels:array<int,string>,data:array<int,int>}, volume:array{labels:array<int,string>,data:array<int,float>}, dvsw:array{labels:array<int,string>,deposits:array<int,float>,withdrawals:array<int,float>}, pie:array{deposits:float,withdrawals:float,trades:float}},
 *     d30: array{revenue:array{labels:array<int,string>,data:array<int,float>}, users:array{labels:array<int,string>,data:array<int,int>}, volume:array{labels:array<int,string>,data:array<int,float>}, dvsw:array{labels:array<int,string>,deposits:array<int,float>,withdrawals:array<int,float>}, pie:array{deposits:float,withdrawals:float,trades:float}}
 *   }
 * }
 */
function admin_analytics_build_payload(mysqli $db): array {
  $metrics = admin_dashboard_metrics($db);

  $platformBalance = (float)($metrics['platform_balance_usd'] ?? 0.0);
  $totalUsers = (int)($metrics['total_users'] ?? 0);
  $avgBal = $totalUsers > 0 ? ($platformBalance / $totalUsers) : 0.0;

  // "Revenue" proxy: 0.1% of trade volume (best-effort).
  $tradeToday = admin_analytics_chart_sum_by_day($db, 'trade', 1);
  $tradeTodaySum = (float)array_sum($tradeToday['data'] ?? []);
  $revenue = $tradeTodaySum * 0.001;

  // Series
  $rev7 = admin_dash_chart_revenue_weekly($db); // 7 points
  $usersGrowth = admin_dash_chart_user_growth($db); // 6 months cumulative
  $volToday = admin_dash_chart_volume_today($db); // 6 buckets today
  $dvsw7 = admin_dash_chart_deposit_vs_withdrawal($db); // 7 points

  $rev30 = admin_analytics_chart_sum_by_day($db, 'deposit', 30);
  $vol7 = admin_analytics_chart_sum_by_day($db, 'trade', 7);
  $vol30 = admin_analytics_chart_sum_by_day($db, 'trade', 30);
  $dvsw30 = admin_analytics_chart_deposit_vs_withdrawal_days($db, 30);

  $pieToday = admin_analytics_tx_type_breakdown($db, 1);
  $pie7 = admin_analytics_tx_type_breakdown($db, 7);
  $pie30 = admin_analytics_tx_type_breakdown($db, 30);

  return [
    'kpis' => [
      'total_users' => $totalUsers,
      'active_users' => (int)($metrics['active_users'] ?? 0),
      'total_deposits_usd' => (float)($metrics['total_deposits_usd'] ?? 0.0),
      'total_withdrawals_usd' => (float)($metrics['total_withdrawals_usd'] ?? 0.0),
      'trading_volume_usd' => (float)($metrics['daily_volume_usd'] ?? 0.0),
      'revenue_usd' => $revenue,
      'platform_balance_usd' => $platformBalance,
      'avg_user_balance_usd' => $avgBal,
    ],
    'series' => [
      'today' => [
        'revenue' => $rev7,
        'users' => $usersGrowth,
        'volume' => $volToday,
        'dvsw' => $dvsw7,
        'pie' => $pieToday,
      ],
      'd7' => [
        'revenue' => $rev7,
        'users' => $usersGrowth,
        'volume' => $vol7,
        'dvsw' => $dvsw7,
        'pie' => $pie7,
      ],
      'd30' => [
        'revenue' => $rev30,
        'users' => $usersGrowth,
        'volume' => $vol30,
        'dvsw' => $dvsw30,
        'pie' => $pie30,
      ],
    ],
  ];
}

