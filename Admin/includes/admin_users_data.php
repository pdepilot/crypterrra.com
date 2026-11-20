<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_users_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_users_table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = @$db->query("SHOW TABLES LIKE '{$t}'");
  if (!$r) return false;
  $ok = $r->num_rows > 0;
  $r->free();
  return $ok;
}

function admin_users_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
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

/**
 * @return array{total:int,new_today:int,verified:int,suspended:int}
 */
function admin_users_summary(mysqli $db): array {
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;

  $summary = [
    'total' => 0,
    'new_today' => 0,
    'verified' => 0,
    'suspended' => 0,
  ];

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users')) {
    $row = $r->fetch_assoc();
    $summary['total'] = (int)($row['c'] ?? 0);
    $r->free();
  }

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users WHERE is_active = 0')) {
    $row = $r->fetch_assoc();
    $summary['suspended'] = (int)($row['c'] ?? 0);
    $r->free();
  }

  if (admin_users_column_exists($db, $schema, 'users', 'created_at')) {
    if ($r = @$db->query('SELECT COUNT(*) AS c FROM users WHERE created_at >= CURDATE()')) {
      $row = $r->fetch_assoc();
      $summary['new_today'] = (int)($row['c'] ?? 0);
      $r->free();
    }
  }

  if (admin_users_column_exists($db, $schema, 'users', 'kyc_status')) {
    if ($r = @$db->query("SELECT COUNT(*) AS c FROM users WHERE LOWER(kyc_status) = 'verified'")) {
      $row = $r->fetch_assoc();
      $summary['verified'] = (int)($row['c'] ?? 0);
      $r->free();
    }
  }

  return $summary;
}

/**
 * @return array{
 *   page:int, per_page:int, total:int, users:array<int,array{
 *     id:int,
 *     full_name:string,
 *     email:string,
 *     country:string,
 *     kyc_status:string,
 *     is_active:int,
 *     available_balance_usd:float,
 *     created_at:string,
 *     last_login_at:string
 *   }>
 * }
 */
function admin_users_list(
  mysqli $db,
  int $page,
  int $perPage,
  string $q,
  string $filter,
  string $sort
): array {
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;

  $page = max(1, $page);
  $perPage = max(5, min(100, $perPage));
  $offset = ($page - 1) * $perPage;

  $hasCountry = admin_users_column_exists($db, $schema, 'users', 'country');
  $hasKyc = admin_users_column_exists($db, $schema, 'users', 'kyc_status');
  $hasBal = admin_users_column_exists($db, $schema, 'users', 'available_balance_usd');
  $hasCreated = admin_users_column_exists($db, $schema, 'users', 'created_at');
  $hasLast = admin_users_column_exists($db, $schema, 'users', 'last_login_at');

  $where = [];
  $types = '';
  $params = [];

  $qq = trim($q);
  if ($qq !== '') {
    $where[] = '(full_name LIKE CONCAT(\'%\', ?, \'%\') OR email LIKE CONCAT(\'%\', ?, \'%\') OR CAST(id AS CHAR) LIKE CONCAT(\'%\', ?, \'%\'))';
    $types .= 'sss';
    $params[] = $qq;
    $params[] = $qq;
    $params[] = $qq;
  }

  $filterNorm = strtolower(trim($filter));
  if ($filterNorm === 'active') {
    $where[] = 'is_active = 1';
  } elseif ($filterNorm === 'suspended') {
    $where[] = 'is_active = 0';
  } elseif ($filterNorm === 'verified' && $hasKyc) {
    $where[] = "LOWER(kyc_status) = 'verified'";
  } elseif ($filterNorm === 'unverified' && $hasKyc) {
    $where[] = "LOWER(kyc_status) <> 'verified'";
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  $orderBy = 'id DESC';
  $sortNorm = strtolower(trim($sort));
  if ($sortNorm === 'oldest') {
    $orderBy = 'id ASC';
  } elseif ($sortNorm === 'highest_balance' && $hasBal) {
    $orderBy = 'available_balance_usd DESC, id DESC';
  } elseif ($sortNorm === 'newest' && $hasCreated) {
    $orderBy = 'created_at DESC, id DESC';
  }

  $total = 0;
  $countSql = "SELECT COUNT(*) AS c FROM users {$whereSql}";
  $stmt = $db->prepare($countSql);
  if ($stmt) {
    if ($types !== '') {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->store_result();
    $c = 0;
    $stmt->bind_result($c);
    $stmt->fetch();
    $stmt->free_result();
    $stmt->close();
    $total = (int)$c;
  }

  $cols = [
    'id',
    'full_name',
    'email',
    'is_active',
    $hasCountry ? 'country' : "'' AS country",
    $hasKyc ? 'kyc_status' : "'' AS kyc_status",
    $hasBal ? 'available_balance_usd' : "0.00 AS available_balance_usd",
    $hasCreated ? 'created_at' : "'' AS created_at",
    $hasLast ? 'last_login_at' : "'' AS last_login_at",
  ];

  $sql = "SELECT " . implode(', ', $cols) . " FROM users {$whereSql} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
  $stmt2 = $db->prepare($sql);
  $users = [];
  if ($stmt2) {
    $types2 = $types . 'ii';
    $params2 = $params;
    $params2[] = $perPage;
    $params2[] = $offset;
    $stmt2->bind_param($types2, ...$params2);
    $stmt2->execute();
    $res = $stmt2->get_result();
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $users[] = [
          'id' => (int)($row['id'] ?? 0),
          'full_name' => (string)($row['full_name'] ?? ''),
          'email' => (string)($row['email'] ?? ''),
          'country' => ((string)($row['country'] ?? '')) !== '' ? (string)$row['country'] : '—',
          'kyc_status' => ((string)($row['kyc_status'] ?? '')) !== '' ? (string)$row['kyc_status'] : 'N/A',
          'is_active' => (int)($row['is_active'] ?? 0),
          'available_balance_usd' => (float)($row['available_balance_usd'] ?? 0),
          'created_at' => (string)($row['created_at'] ?? ''),
          'last_login_at' => (string)($row['last_login_at'] ?? ''),
        ];
      }
      $res->free();
    }
    $stmt2->close();
  }

  return [
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'users' => $users,
  ];
}

function admin_users_get(mysqli $db, int $id): ?array {
  if ($id <= 0) return null;
  $stmt = $db->prepare('SELECT id, full_name, email, is_active, created_at, last_login_at FROM users WHERE id = ? LIMIT 1');
  if (!$stmt) return null;
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!is_array($row)) return null;
  return [
    'id' => (int)($row['id'] ?? 0),
    'full_name' => (string)($row['full_name'] ?? ''),
    'email' => (string)($row['email'] ?? ''),
    'is_active' => (int)($row['is_active'] ?? 0),
    'created_at' => (string)($row['created_at'] ?? ''),
    'last_login_at' => (string)($row['last_login_at'] ?? ''),
  ];
}

function admin_users_set_active(mysqli $db, int $id, bool $active): bool {
  if ($id <= 0) return false;
  $val = $active ? 1 : 0;
  $stmt = $db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
  if (!$stmt) return false;
  $stmt->bind_param('ii', $val, $id);
  $ok = @$stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

function admin_users_delete(mysqli $db, int $id): bool {
  if ($id <= 0) return false;
  $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
  if (!$stmt) return false;
  $stmt->bind_param('i', $id);
  $ok = @$stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

/**
 * @return array{ok:bool,temp_password?:string}
 */
function admin_users_reset_password(mysqli $db, int $id): array {
  if ($id <= 0) return ['ok' => false];
  $temp = bin2hex(random_bytes(4)) . 'A1!';
  $hash = password_hash($temp, PASSWORD_DEFAULT);
  if (!is_string($hash) || $hash === '') return ['ok' => false];
  $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
  if (!$stmt) return ['ok' => false];
  $stmt->bind_param('si', $hash, $id);
  $ok = @$stmt->execute();
  $stmt->close();
  return $ok ? ['ok' => true, 'temp_password' => $temp] : ['ok' => false];
}

function admin_users_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_users_time_ago(?string $datetime): string {
  if ($datetime === null || $datetime === '') {
    return '—';
  }
  $ts = strtotime($datetime);
  if ($ts === false) {
    return '—';
  }
  $diff = time() - $ts;
  if ($diff < 0) {
    return 'just now';
  }
  if ($diff < 60) {
    return $diff <= 1 ? 'just now' : $diff . ' sec ago';
  }
  if ($diff < 3600) {
    $m = (int)floor($diff / 60);
    return $m . ' min ago';
  }
  if ($diff < 86400) {
    $h = (int)floor($diff / 3600);
    return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
  }
  if ($diff < 86400 * 7) {
    $d = (int)floor($diff / 86400);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
  }
  return date('M j, Y', $ts);
}

/**
 * Recent user-related events: new signups + transaction rows (deposits, withdrawals, trades).
 *
 * @return array<int, array{ts:int, icon:string, name:string, detail:string, at:string}>
 */
function admin_users_activity_feed(mysqli $db, int $limit = 12): array {
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  $limit = max(1, min(50, $limit));
  $events = [];

  if (admin_users_column_exists($db, $schema, 'users', 'created_at')) {
    $sql = "SELECT full_name, created_at FROM users WHERE created_at IS NOT NULL ORDER BY created_at DESC LIMIT 25";
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $at = (string)($row['created_at'] ?? '');
        $ts = strtotime($at);
        if ($ts === false) {
          continue;
        }
        $name = trim((string)($row['full_name'] ?? ''));
        if ($name === '') {
          $name = 'User';
        }
        $events[] = [
          'ts' => $ts,
          'icon' => 'fa-solid fa-user-plus',
          'name' => $name,
          'detail' => 'signed up',
          'at' => $at,
        ];
      }
      $r->free();
    }
  }

  if (admin_users_table_exists($db, 'transactions')) {
    $sql = "SELECT t.type, t.amount, t.asset, t.created_at, u.full_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT 40";
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $at = (string)($row['created_at'] ?? '');
        $ts = strtotime($at);
        if ($ts === false) {
          continue;
        }
        $name = trim((string)($row['full_name'] ?? ''));
        if ($name === '') {
          $name = 'User';
        }
        $type = strtolower(trim((string)($row['type'] ?? '')));
        $amount = (float)($row['amount'] ?? 0);
        $asset = strtoupper(trim((string)($row['asset'] ?? '')));
        $detail = '';
        $icon = 'fa-solid fa-arrow-right-arrow-left';
        if ($type === 'deposit') {
          $icon = 'fa-solid fa-circle-dollar';
          $amtLabel = '$' . number_format($amount, 2);
          if ($asset !== '' && $asset !== 'USD' && $asset !== 'USDT') {
            $amtLabel .= ' ' . $asset;
          }
          $detail = 'made a deposit (' . $amtLabel . ')';
        } elseif ($type === 'withdrawal') {
          $icon = 'fa-solid fa-arrow-up';
          $detail = 'requested a withdrawal';
        } elseif ($type === 'trade') {
          $icon = 'fa-solid fa-chart-line';
          $detail = 'placed a trade' . ($asset !== '' ? ' (' . $asset . ')' : '');
        } else {
          $detail = $type !== '' ? $type : 'transaction';
        }
        $events[] = [
          'ts' => $ts,
          'icon' => $icon,
          'name' => $name,
          'detail' => $detail,
          'at' => $at,
        ];
      }
      $r->free();
    }
  }

  usort($events, static fn(array $a, array $b): int => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
  return array_slice($events, 0, $limit);
}

