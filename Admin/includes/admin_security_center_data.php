<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';
require_once __DIR__ . '/admin_dashboard_data.php';

function admin_sec_time_ago(?string $dt): string {
  if (!is_string($dt) || $dt === '') {
    return '—';
  }
  try {
    $t = new DateTimeImmutable($dt);
  } catch (Throwable) {
    return '—';
  }
  $now = new DateTimeImmutable('now');
  $sec = $now->getTimestamp() - $t->getTimestamp();
  if ($sec < 60) {
    return 'just now';
  }
  if ($sec < 3600) {
    return (int)floor($sec / 60) . ' min ago';
  }
  if ($sec < 86400) {
    return (int)floor($sec / 3600) . ' hour ago';
  }
  if ($sec < 86400 * 7) {
    return (int)floor($sec / 86400) . ' days ago';
  }
  return $t->format('M j, Y');
}

function admin_sec_clamp_int(int $v, int $min, int $max): int {
  return max($min, min($max, $v));
}

/**
 * @return array{
 *   active_threats:int,
 *   blocked_attacks:int,
 *   suspicious:int,
 *   security_score:int,
 *   risk_score:int,
 *   threat_level:string,
 *   anomalies:int
 * }
 */
function admin_security_center_stats(mysqli $db, string $schema): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_admin_security_columns($db, $schema);
  ensure_kyc_requests_table($db);
  ensure_transactions_table($db);

  $locked = 0;
  $inactive = 0;
  $forceReset = 0;
  $r = @$db->query(
    'SELECT SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) AS l,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS i,
            SUM(CASE WHEN force_password_reset = 1 THEN 1 ELSE 0 END) AS f
     FROM users'
  );
  if ($r && ($row = $r->fetch_assoc())) {
    $locked = (int)($row['l'] ?? 0);
    $inactive = (int)($row['i'] ?? 0);
    $forceReset = (int)($row['f'] ?? 0);
    $r->free();
  }

  $pendingKyc = 0;
  $rejectedKyc7d = 0;
  $rk = @$db->query(
    "SELECT SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS p,
            SUM(CASE WHEN status = 'rejected' AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS r
     FROM kyc_requests"
  );
  if ($rk && ($row = $rk->fetch_assoc())) {
    $pendingKyc = (int)($row['p'] ?? 0);
    $rejectedKyc7d = (int)($row['r'] ?? 0);
    $rk->free();
  }

  $pendingWithdrawals = 0;
  if (admin_dash_table_exists($db, 'transactions')) {
    $rw = @$db->query(
      "SELECT COUNT(*) AS c FROM transactions WHERE LOWER(type) LIKE '%withdraw%' AND LOWER(status) IN ('pending','processing')"
    );
    if ($rw && ($row = $rw->fetch_assoc())) {
      $pendingWithdrawals = (int)($row['c'] ?? 0);
      $rw->free();
    }
  }

  $failedSum = 0;
  $lockedAdminAttempts = 0;
  if (admin_dash_table_exists($db, 'admin_login_attempts')) {
    $fa = @$db->query('SELECT COALESCE(SUM(failed_count), 0) AS s FROM admin_login_attempts');
    if ($fa && ($row = $fa->fetch_assoc())) {
      $failedSum = (int)($row['s'] ?? 0);
      $fa->free();
    }
    $lk = @$db->query('SELECT COUNT(*) AS c FROM admin_login_attempts WHERE locked_until IS NOT NULL AND locked_until > NOW()');
    if ($lk && ($row = $lk->fetch_assoc())) {
      $lockedAdminAttempts = (int)($row['c'] ?? 0);
      $lk->free();
    }
  }

  $activeThreats = admin_sec_clamp_int($locked + $forceReset + (int)ceil($pendingKyc / 2) + $lockedAdminAttempts, 0, 99999);
  $blockedAttacks = admin_sec_clamp_int($failedSum + $lockedAdminAttempts * 3, 0, 999999);
  $suspicious = admin_sec_clamp_int($pendingWithdrawals + $rejectedKyc7d + $inactive, 0, 999999);

  $penalty = min(55, $locked * 4 + $forceReset * 3 + $inactive * 3 + (int)ceil($pendingKyc / 2));
  $securityScore = admin_sec_clamp_int(100 - $penalty, 45, 100);

  $anomalies = admin_sec_clamp_int($locked + $forceReset + $rejectedKyc7d + $pendingWithdrawals, 0, 99999);
  $riskScore = admin_sec_clamp_int(55 + (int)round($anomalies * 0.35) + ($pendingKyc > 0 ? 8 : 0), 0, 100);

  $threatLevel = 'Low';
  $threatClass = 'risk-low';
  if ($riskScore >= 70) {
    $threatLevel = 'High';
    $threatClass = 'risk-high';
  } elseif ($riskScore >= 45) {
    $threatLevel = 'Medium';
    $threatClass = 'risk-medium';
  }

  return [
    'active_threats' => $activeThreats,
    'blocked_attacks' => $blockedAttacks,
    'suspicious' => $suspicious,
    'security_score' => $securityScore,
    'risk_score' => $riskScore,
    'threat_level' => $threatLevel,
    'threat_level_class' => $threatClass,
    'anomalies' => $anomalies,
  ];
}

/**
 * @return list<array{type:string,title:string,description:string,time:string}>
 */
function admin_security_center_threat_feed(mysqli $db, string $schema, int $limit = 8): array {
  ensure_user_admin_security_columns($db, $schema);
  ensure_kyc_requests_table($db);
  ensure_transactions_table($db);
  $limit = max(1, min(20, $limit));

  $events = [];

  $lq = @$db->query(
    'SELECT email, full_name, locked_reason FROM users WHERE is_locked = 1 ORDER BY id DESC LIMIT 5'
  );
  if ($lq) {
    while ($row = $lq->fetch_assoc()) {
      $email = (string)($row['email'] ?? '');
      $name = (string)($row['full_name'] ?? '');
      $reason = trim((string)($row['locked_reason'] ?? ''));
      $events[] = [
        'ts' => time(),
        'type' => 'warning',
        'title' => 'Account locked',
        'description' => $name !== '' ? "{$name} ({$email})" . ($reason !== '' ? " · {$reason}" : '') : $email,
        'time' => 'Recently',
      ];
    }
    $lq->free();
  }

  $kq = @$db->query(
    "SELECT full_name, status, submitted_at FROM kyc_requests WHERE status IN ('pending','rejected') ORDER BY submitted_at DESC LIMIT 6"
  );
  if ($kq) {
    while ($row = $kq->fetch_assoc()) {
      $st = strtolower((string)($row['status'] ?? ''));
      $name = (string)($row['full_name'] ?? '');
      $sub = (string)($row['submitted_at'] ?? '');
      $type = $st === 'rejected' ? 'critical' : 'warning';
      $title = $st === 'rejected' ? 'KYC rejected' : 'KYC pending review';
      $events[] = [
        'ts' => $sub !== '' ? strtotime($sub) : time(),
        'type' => $type,
        'title' => $title,
        'description' => $name !== '' ? "Applicant: {$name}" : 'KYC queue',
        'time' => admin_sec_time_ago($sub),
      ];
    }
    $kq->free();
  }

  if (admin_dash_table_exists($db, 'transactions')) {
    $tq = @$db->query(
      "SELECT t.id, t.user_id, t.type, t.amount, t.status, t.created_at, u.email, u.full_name
       FROM transactions t
       LEFT JOIN users u ON u.id = t.user_id
       WHERE LOWER(t.type) LIKE '%withdraw%' AND LOWER(t.status) = 'pending'
       ORDER BY t.created_at DESC LIMIT 5"
    );
    if ($tq) {
      while ($row = $tq->fetch_assoc()) {
        $amt = (string)($row['amount'] ?? '0');
        $email = (string)($row['email'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $events[] = [
          'ts' => $created !== '' ? strtotime($created) : time(),
          'type' => 'critical',
          'title' => 'Pending withdrawal',
          'description' => 'Amount: ' . $amt . ($email !== '' ? " · {$email}" : ''),
          'time' => admin_sec_time_ago($created),
        ];
      }
      $tq->free();
    }
  }

  usort($events, static function (array $a, array $b): int {
    return (int)($b['ts'] ?? 0) <=> (int)($a['ts'] ?? 0);
  });

  $out = [];
  foreach ($events as $e) {
    unset($e['ts']);
    $out[] = [
      'type' => (string)($e['type'] ?? 'warning'),
      'title' => (string)($e['title'] ?? ''),
      'description' => (string)($e['description'] ?? ''),
      'time' => (string)($e['time'] ?? '—'),
    ];
    if (count($out) >= $limit) {
      break;
    }
  }

  return $out;
}

function admin_security_compute_user_risk(array $row): int {
  $kycUser = strtolower(trim((string)($row['kyc_status'] ?? '')));
  $kycReq = strtolower(trim((string)($row['kyc_req_status'] ?? '')));
  $score = 12;
  if ($kycReq === 'pending' || $kycUser === 'pending') {
    $score += 22;
  }
  if ($kycReq === 'rejected' || $kycUser === 'rejected') {
    $score += 38;
  }
  if (empty($row['twofa_enabled']) || (int)$row['twofa_enabled'] === 0) {
    $score += 14;
  }
  if (!empty($row['is_locked']) && (int)$row['is_locked'] === 1) {
    $score += 28;
  }
  if (isset($row['is_active']) && (int)$row['is_active'] === 0) {
    $score += 22;
  }
  if (!empty($row['force_password_reset']) && (int)$row['force_password_reset'] === 1) {
    $score += 12;
  }
  return admin_sec_clamp_int($score, 5, 100);
}

/**
 * @return list<array{user:string,email:string,risk:int,lastActivity:string,status:string}>
 */
function admin_security_center_user_risks(mysqli $db, string $schema, int $limit = 20): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_security_columns($db, $schema);
  ensure_user_admin_security_columns($db, $schema);
  ensure_kyc_requests_table($db);

  $limit = max(1, min(50, $limit));
  $hasKycCol = admin_dash_column_exists($db, $schema, 'users', 'kyc_status');

  $kycSel = $hasKycCol ? 'u.kyc_status' : "'' AS kyc_status";
  $sql = "SELECT u.id, u.full_name, u.email, u.is_active, u.is_locked, u.force_password_reset, u.twofa_enabled, u.last_login_at,
                 {$kycSel}, k.status AS kyc_req_status
          FROM users u
          LEFT JOIN kyc_requests k ON k.user_id = u.id
          ORDER BY u.id DESC
          LIMIT " . (int)$limit;

  $r = @$db->query($sql);
  if (!$r) {
    return [];
  }

  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $risk = admin_security_compute_user_risk($row);
    $isLocked = !empty($row['is_locked']) && (int)$row['is_locked'] === 1;
    $isActive = !isset($row['is_active']) || (int)$row['is_active'] === 1;
    if ($isLocked) {
      $status = 'Locked';
    } elseif (!$isActive) {
      $status = 'Frozen';
    } else {
      $status = 'Active';
    }
    $ll = (string)($row['last_login_at'] ?? '');
    $rows[] = [
      'user' => (string)($row['full_name'] ?? '') !== '' ? (string)$row['full_name'] : (string)($row['email'] ?? ''),
      'email' => (string)($row['email'] ?? ''),
      'risk' => $risk,
      'lastActivity' => $ll !== '' ? admin_sec_time_ago($ll) : '—',
      'status' => $status,
    ];
  }
  $r->free();
  return $rows;
}

/**
 * @return list<array{user:string,location:string,device:string,ip:string,time:string}>
 */
function admin_security_center_login_activity(mysqli $db, int $limit = 12): array {
  require_once dirname(__DIR__, 2) . '/includes/geoip_local.php';

  $limit = max(1, min(30, $limit));
  $sql = 'SELECT full_name, email, last_login_at, last_login_ip FROM users WHERE last_login_at IS NOT NULL ORDER BY last_login_at DESC LIMIT ' . (int)$limit;
  $r = @$db->query($sql);
  if (!$r) {
    return [];
  }
  $out = [];
  while ($row = $r->fetch_assoc()) {
    $ip = (string)($row['last_login_ip'] ?? '');
    $g = geoip_local_country($ip);
    $loc = $g['name'] !== '' ? $g['name'] : ($ip !== '' ? 'Unknown region' : '—');
    $out[] = [
      'user' => (string)($row['full_name'] ?? '') !== '' ? (string)$row['full_name'] : (string)($row['email'] ?? ''),
      'location' => $loc,
      'device' => '—',
      'ip' => $ip !== '' ? $ip : '—',
      'time' => admin_sec_time_ago((string)($row['last_login_at'] ?? '')),
    ];
  }
  $r->free();
  return $out;
}

/**
 * @return list<array{time:string,action:string,result:string,result_class:string}>
 */
function admin_security_center_security_logs(mysqli $db, int $limit = 10): array {
  $limit = max(1, min(30, $limit));
  $out = [];

  if (admin_dash_table_exists($db, 'admin_logs')) {
    $q = @$db->query(
      'SELECT created_at, action, ip FROM admin_logs ORDER BY created_at DESC LIMIT ' . (int)$limit
    );
    if ($q) {
      while ($row = $q->fetch_assoc()) {
        $action = (string)($row['action'] ?? '');
        $ip = (string)($row['ip'] ?? '');
        $ca = (string)($row['created_at'] ?? '');
        $timeLabel = '—';
        if ($ca !== '') {
          try {
            $timeLabel = (new DateTimeImmutable($ca))->format('H:i');
          } catch (Throwable) {
            $timeLabel = '—';
          }
        }
        $out[] = [
          'time' => $timeLabel,
          'action' => $action . ($ip !== '' ? " · {$ip}" : ''),
          'result' => 'Logged',
          'result_class' => 'success',
        ];
      }
      $q->free();
    }
  }

  if (count($out) >= 3) {
    return array_slice($out, 0, $limit);
  }

  if (admin_dash_table_exists($db, 'transactions')) {
    $need = $limit - count($out);
    $q = @$db->query(
      'SELECT created_at, type, status, amount, asset FROM transactions ORDER BY created_at DESC LIMIT ' . (int)max(1, $need)
    );
    if ($q) {
      while ($row = $q->fetch_assoc()) {
        $ca = (string)($row['created_at'] ?? '');
        $type = (string)($row['type'] ?? '');
        $st = (string)($row['status'] ?? '');
        $amt = (string)($row['amount'] ?? '');
        $asset = (string)($row['asset'] ?? '');
        $ok = strtolower($st) === 'completed' || strtolower($st) === 'approved';
        $timeLabel = '—';
        if ($ca !== '') {
          try {
            $timeLabel = (new DateTimeImmutable($ca))->format('H:i');
          } catch (Throwable) {
            $timeLabel = '—';
          }
        }
        $out[] = [
          'time' => $timeLabel,
          'action' => "{$type} {$asset} · {$amt} · {$st}",
          'result' => $ok ? 'OK' : 'Review',
          'result_class' => $ok ? 'success' : 'failed',
        ];
      }
      $q->free();
    }
  }

  return array_slice($out, 0, $limit);
}

/**
 * @return array{labels:list<string>, critical:list<int>, warning:list<int>}
 */
function admin_security_center_chart_series(mysqli $db): array {
  ensure_transactions_table($db);
  $labels = [];
  $critical = [];
  $warning = [];
  for ($i = 6; $i >= 0; $i--) {
    $d = new DateTimeImmutable('now');
    $d = $d->modify('-' . $i . ' days');
    $labels[] = $d->format('D');
    $day = $d->format('Y-m-d');

    $c = 0;
    $w = 0;
    if (admin_dash_table_exists($db, 'transactions')) {
      $sql = "SELECT
        SUM(CASE WHEN LOWER(status) IN ('failed','rejected','cancelled') THEN 1 ELSE 0 END) AS c,
        SUM(CASE WHEN LOWER(status) IN ('pending','processing') THEN 1 ELSE 0 END) AS w
        FROM transactions WHERE DATE(created_at) = '" . $db->real_escape_string($day) . "'";
      $r = @$db->query($sql);
      if ($r && ($row = $r->fetch_assoc())) {
        $c = (int)($row['c'] ?? 0);
        $w = (int)($row['w'] ?? 0);
        $r->free();
      }
    }
    $critical[] = $c;
    $warning[] = $w;
  }

  return ['labels' => $labels, 'critical' => $critical, 'warning' => $warning];
}

/**
 * @return array{
 *   stats: array,
 *   threat_feed: list,
 *   user_risks: list,
 *   login_activity: list,
 *   security_logs: list,
 *   chart: array
 * }
 */
function admin_security_center_payload(mysqli $db, string $schema): array {
  $stats = admin_security_center_stats($db, $schema);
  return [
    'stats' => $stats,
    'threat_feed' => admin_security_center_threat_feed($db, $schema, 8),
    'user_risks' => admin_security_center_user_risks($db, $schema, 20),
    'login_activity' => admin_security_center_login_activity($db, 12),
    'security_logs' => admin_security_center_security_logs($db, 10),
    'chart' => admin_security_center_chart_series($db),
  ];
}
