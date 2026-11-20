<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_tx_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_tx_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_tx_table_exists(mysqli $db): bool {
  $r = @$db->query("SHOW TABLES LIKE 'transactions'");
  if (!$r) {
    return false;
  }
  $ok = $r->num_rows > 0;
  $r->free();
  return $ok;
}

function admin_tx_schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
  $s = $db->real_escape_string($schema);
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($column);
  $r = @$db->query(
    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$s}' AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'"
  );
  if (!$r) {
    return false;
  }
  $row = $r->fetch_assoc();
  $r->free();
  return isset($row['n']) && (int)$row['n'] > 0;
}

/**
 * Optional second user on a transaction (internal transfer / explicit counterparty).
 */
function admin_tx_ensure_counterparty_column(mysqli $db, string $schema): void {
  if (!admin_tx_table_exists($db)) {
    return;
  }
  if (admin_tx_schema_column_exists($db, $schema, 'transactions', 'counterparty_user_id')) {
    return;
  }
  @$db->query('ALTER TABLE `transactions` ADD COLUMN `counterparty_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
}

function admin_tx_format_wallet_short(string $w): string {
  $w = trim($w);
  if ($w === '' || $w === '—') {
    return '—';
  }
  if (strlen($w) <= 18) {
    return $w;
  }
  return substr($w, 0, 10) . '…' . substr($w, -6);
}

/**
 * Build display names for who sent vs who received (platform user vs external / market / counterparty user).
 *
 * @return array{sender_label:string, receiver_label:string, counterparty_user_id:int|null}
 */
function admin_tx_party_labels(
  string $type,
  string $primaryUserLabel,
  int $primaryUserId,
  array $meta,
  ?string $counterpartyDisplay,
  ?int $counterpartyUserId,
  string $walletRef
): array {
  $type = strtolower(trim($type));
  $sl = isset($meta['sender_label']) && is_string($meta['sender_label']) ? trim($meta['sender_label']) : '';
  $rl = isset($meta['receiver_label']) && is_string($meta['receiver_label']) ? trim($meta['receiver_label']) : '';
  if ($sl !== '' && $rl !== '') {
    return [
      'sender_label' => $sl,
      'receiver_label' => $rl,
      'counterparty_user_id' => $counterpartyUserId,
    ];
  }

  $cp = $counterpartyDisplay !== null ? trim($counterpartyDisplay) : '';
  $fromAddr = isset($meta['from_address']) ? (string)$meta['from_address'] : (isset($meta['sender_wallet']) ? (string)$meta['sender_wallet'] : '');
  $toAddr = isset($meta['to_address']) ? (string)$meta['to_address'] : (isset($meta['receiver_wallet']) ? (string)$meta['receiver_wallet'] : '');

  if ($type === 'deposit') {
    $sender = $cp !== '' ? $cp : ($fromAddr !== '' ? admin_tx_format_wallet_short($fromAddr) : 'External');
    $receiver = $primaryUserLabel;
    return ['sender_label' => $sender, 'receiver_label' => $receiver, 'counterparty_user_id' => $counterpartyUserId];
  }
  if ($type === 'withdrawal') {
    $sender = $primaryUserLabel;
    $receiver = $cp !== '' ? $cp : ($toAddr !== '' ? admin_tx_format_wallet_short($toAddr) : 'External');
    return ['sender_label' => $sender, 'receiver_label' => $receiver, 'counterparty_user_id' => $counterpartyUserId];
  }
  if ($type === 'trade') {
    $sender = $primaryUserLabel;
    $receiver = $cp !== '' ? $cp : 'Market / liquidity';
    return ['sender_label' => $sender, 'receiver_label' => $receiver, 'counterparty_user_id' => $counterpartyUserId];
  }

  return [
    'sender_label' => $primaryUserLabel,
    'receiver_label' => $walletRef !== '' && $walletRef !== '—' ? admin_tx_format_wallet_short($walletRef) : '—',
    'counterparty_user_id' => $counterpartyUserId,
  ];
}

function admin_tx_primary_user_label(array $row): string {
  $name = trim((string)($row['full_name'] ?? ''));
  if ($name === '') {
    $name = trim((string)($row['email'] ?? ''));
  }
  if ($name === '') {
    $wid = (string)($row['wallet_id'] ?? '');
    $name = $wid !== '' ? $wid : ('User #' . (int)($row['user_id'] ?? 0));
  }
  return $name;
}

/**
 * @return array{display:string, user_id:int|null}
 */
function admin_tx_counterparty_from_row(array $row): array {
  $cpUid = isset($row['counterparty_user_id']) ? (int)$row['counterparty_user_id'] : 0;
  if ($cpUid <= 0) {
    return ['display' => '', 'user_id' => null];
  }
  $cpDisplay = trim((string)($row['cp_full_name'] ?? ''));
  if ($cpDisplay === '') {
    $cpDisplay = trim((string)($row['cp_email'] ?? ''));
  }
  if ($cpDisplay === '') {
    $cpw = (string)($row['cp_wallet_id'] ?? '');
    $cpDisplay = $cpw !== '' ? $cpw : ('User #' . $cpUid);
  }
  return ['display' => $cpDisplay, 'user_id' => $cpUid];
}

/**
 * @param array<string, mixed> $meta
 * @return array{sender_label:string, receiver_label:string, counterparty_user_id:int|null}
 */
function admin_tx_attach_party_labels(array $row, array $meta): array {
  $name = admin_tx_primary_user_label($row);
  $cp = admin_tx_counterparty_from_row($row);
  $wallet = isset($meta['wallet_address']) && is_string($meta['wallet_address'])
    ? $meta['wallet_address']
    : ((string)($row['wallet_id'] ?? '') !== '' ? (string)$row['wallet_id'] : '—');
  $party = admin_tx_party_labels(
    strtolower((string)($row['type'] ?? '')),
    $name,
    (int)($row['user_id'] ?? 0),
    $meta,
    $cp['display'] !== '' ? $cp['display'] : null,
    $cp['user_id'],
    $wallet
  );
  return $party;
}

/**
 * @return array<string, mixed>
 */
function admin_tx_parse_meta(?string $json): array {
  if ($json === null || $json === '') {
    return [];
  }
  $d = json_decode($json, true);
  return is_array($d) ? $d : [];
}

function admin_tx_display_status(string $status, array $meta): string {
  $s = strtolower(trim($status));
  if (!empty($meta['admin_flagged']) || !empty($meta['flagged'])) {
    return 'Flagged';
  }
  return match ($s) {
    'completed' => 'Completed',
    'pending' => 'Pending',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled',
    default => ucfirst($s),
  };
}

function admin_tx_status_class(string $display): string {
  $d = strtolower($display);
  return match ($d) {
    'completed' => 'completed',
    'pending' => 'pending',
    'failed' => 'failed',
    'flagged' => 'flagged',
    'cancelled' => 'failed',
    default => 'pending',
  };
}

/**
 * @return array{
 *   total_count:int,
 *   deposits_usd:float,
 *   withdrawals_usd:float,
 *   pending_count:int,
 *   failed_count:int,
 *   sparklines: array{
 *     total: array<int,int>,
 *     deposits: array<int,int>,
 *     withdrawals: array<int,int>,
 *     pending: array<int,int>,
 *     failed: array<int,int>
 *   }
 * }
 */
function admin_tx_summary(mysqli $db): array {
  $empty = [
    'total_count' => 0,
    'deposits_usd' => 0.0,
    'withdrawals_usd' => 0.0,
    'pending_count' => 0,
    'failed_count' => 0,
    'sparklines' => [
      'total' => array_fill(0, 7, 0),
      'deposits' => array_fill(0, 7, 0),
      'withdrawals' => array_fill(0, 7, 0),
      'pending' => array_fill(0, 7, 0),
      'failed' => array_fill(0, 7, 0),
    ],
  ];

  if (!admin_tx_table_exists($db)) {
    return $empty;
  }

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM transactions')) {
    $row = $r->fetch_assoc();
    $empty['total_count'] = (int)($row['c'] ?? 0);
    $r->free();
  }

  $stmt = @$db->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM transactions WHERE type = 'deposit' AND status = 'completed'");
  if ($stmt) {
    $stmt->execute();
    $stmt->store_result();
    $s = '0';
    $stmt->bind_result($s);
    $stmt->fetch();
    $stmt->free_result();
    $stmt->close();
    $empty['deposits_usd'] = (float)$s;
  }

  $stmt = @$db->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM transactions WHERE type = 'withdrawal' AND status = 'completed'");
  if ($stmt) {
    $stmt->execute();
    $stmt->store_result();
    $s = '0';
    $stmt->bind_result($s);
    $stmt->fetch();
    $stmt->free_result();
    $stmt->close();
    $empty['withdrawals_usd'] = (float)$s;
  }

  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE status = 'pending'")) {
    $row = $r->fetch_assoc();
    $empty['pending_count'] = (int)($row['c'] ?? 0);
    $r->free();
  }

  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE status IN ('failed','cancelled')")) {
    $row = $r->fetch_assoc();
    $empty['failed_count'] = (int)($row['c'] ?? 0);
    $r->free();
  }

  $days = [];
  for ($i = 6; $i >= 0; $i--) {
    $days[] = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'))->format('Y-m-d');
  }

  $fill = static function (mysqli $db, string $whereSql) use ($days): array {
    $out = array_fill(0, 7, 0);
    $sql = "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    if ($whereSql !== '') {
      $sql .= ' AND (' . $whereSql . ')';
    }
    $sql .= ' GROUP BY d';
    if ($r = @$db->query($sql)) {
      $map = [];
      while ($row = $r->fetch_assoc()) {
        $map[(string)($row['d'] ?? '')] = (int)($row['c'] ?? 0);
      }
      $r->free();
      foreach ($days as $idx => $ymd) {
        $out[$idx] = $map[$ymd] ?? 0;
      }
    }
    return $out;
  };

  $empty['sparklines']['total'] = $fill($db, '');
  $empty['sparklines']['deposits'] = $fill($db, "type = 'deposit'");
  $empty['sparklines']['withdrawals'] = $fill($db, "type = 'withdrawal'");
  $empty['sparklines']['pending'] = $fill($db, "status = 'pending'");
  $empty['sparklines']['failed'] = $fill($db, "status IN ('failed','cancelled')");

  return $empty;
}

/**
 * @return array<int, array{id:int, type:string, asset:string, amount:float, status:string, display_status:string, created_at:string, label:string}>
 */
function admin_tx_recent_feed(mysqli $db, int $limit = 8): array {
  $limit = max(1, min(30, $limit));
  if (!admin_tx_table_exists($db)) {
    return [];
  }

  global $DB_NAME;
  ensure_transactions_table($db);
  admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);
  $hasCp = admin_tx_schema_column_exists($db, (string)$DB_NAME, 'transactions', 'counterparty_user_id');
  $joinCp = $hasCp ? ' LEFT JOIN users cp ON cp.id = t.counterparty_user_id' : '';
  $selCp = $hasCp
    ? ', t.counterparty_user_id, cp.full_name AS cp_full_name, cp.email AS cp_email, cp.wallet_id AS cp_wallet_id'
    : '';

  $sql = "SELECT t.id, t.user_id, t.type, t.asset, t.amount, t.status, t.meta, t.created_at, u.full_name, u.email, u.wallet_id
          {$selCp}
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id{$joinCp}
          ORDER BY t.created_at DESC, t.id DESC
          LIMIT " . (int)$limit;
  $r = @$db->query($sql);
  if (!$r) {
    return [];
  }
  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
    $display = admin_tx_display_status((string)($row['status'] ?? ''), $meta);
    $type = strtolower((string)($row['type'] ?? ''));
    $asset = strtoupper((string)($row['asset'] ?? ''));
    $amt = (float)($row['amount'] ?? 0);
    $party = admin_tx_attach_party_labels($row, $meta);
    $label = '';
    if ($type === 'trade') {
      $label = $asset . ' trade · ' . $party['sender_label'] . ' → ' . $party['receiver_label'];
    } else {
      $label = ucfirst($type !== '' ? $type : 'tx') . ' ' . number_format($amt, 2) . ($asset !== '' ? ' ' . $asset : '')
        . ' · ' . $party['sender_label'] . ' → ' . $party['receiver_label'];
    }
    $rows[] = [
      'id' => (int)($row['id'] ?? 0),
      'type' => $type,
      'asset' => $asset,
      'amount' => $amt,
      'status' => (string)($row['status'] ?? ''),
      'display_status' => $display,
      'created_at' => (string)($row['created_at'] ?? ''),
      'label' => $label,
      'sender_label' => $party['sender_label'],
      'receiver_label' => $party['receiver_label'],
    ];
  }
  $r->free();
  return $rows;
}

/**
 * @return array{
 *   page:int, per_page:int, total:int,
 *   rows: array<int, array{
 *     id:int, user_id:int, user_label:string, sender_label:string, receiver_label:string, counterparty_user_id:int|null,
 *     type:string, amount:float, asset:string,
 *     network:string, wallet:string, status:string, display_status:string, created_at:string, meta:array
 *   }>
 * }
 */
function admin_tx_list(
  mysqli $db,
  int $page,
  int $perPage,
  string $q,
  string $typeFilter,
  string $statusFilter,
  string $sort
): array {
  $page = max(1, $page);
  $perPage = max(5, min(100, $perPage));
  $offset = ($page - 1) * $perPage;

  if (!admin_tx_table_exists($db)) {
    return ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'rows' => []];
  }

  global $DB_NAME;
  ensure_transactions_table($db);
  admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);
  $hasCp = admin_tx_schema_column_exists($db, (string)$DB_NAME, 'transactions', 'counterparty_user_id');
  $joinCp = $hasCp ? ' LEFT JOIN users cp ON cp.id = t.counterparty_user_id' : '';
  $selCp = $hasCp
    ? ', t.counterparty_user_id, cp.full_name AS cp_full_name, cp.email AS cp_email, cp.wallet_id AS cp_wallet_id'
    : '';

  $where = [];
  $types = '';
  $params = [];

  $qq = trim($q);
  if ($qq !== '') {
    $like = '%' . $qq . '%';
    if (ctype_digit($qq)) {
      if ($hasCp) {
        $where[] = '(t.id = ? OR t.counterparty_user_id = ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.wallet_id LIKE ? OR cp.full_name LIKE ? OR cp.email LIKE ? OR cp.wallet_id LIKE ?)';
        $types .= 'iisssssss';
        $params[] = (int)$qq;
        $params[] = (int)$qq;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
      } else {
        $where[] = '(t.id = ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.wallet_id LIKE ?)';
        $types .= 'isss';
        $params[] = (int)$qq;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
      }
    } elseif ($hasCp) {
      $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.wallet_id LIKE ? OR CAST(t.id AS CHAR) LIKE ? OR cp.full_name LIKE ? OR cp.email LIKE ? OR cp.wallet_id LIKE ?)';
      $types .= 'sssssss';
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    } else {
      $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.wallet_id LIKE ? OR CAST(t.id AS CHAR) LIKE ?)';
      $types .= 'ssss';
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    }
  }

  $tf = strtolower(trim($typeFilter));
  if (in_array($tf, ['deposit', 'withdrawal', 'trade'], true)) {
    $where[] = 't.type = ?';
    $types .= 's';
    $params[] = $tf;
  }

  $sf = strtolower(trim($statusFilter));
  if ($sf === 'pending') {
    $where[] = "t.status = 'pending'";
  } elseif ($sf === 'completed') {
    $where[] = "t.status = 'completed'";
  } elseif ($sf === 'failed') {
    $where[] = "t.status IN ('failed','cancelled')";
  } elseif ($sf === 'flagged') {
    $where[] = "(t.meta LIKE '%\"admin_flagged\":true%' OR t.meta LIKE '%\"flagged\":true%')";
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  $orderBy = 't.created_at DESC, t.id DESC';
  $sortNorm = strtolower(trim($sort));
  if ($sortNorm === 'oldest') {
    $orderBy = 't.created_at ASC, t.id ASC';
  } elseif ($sortNorm === 'highest_amount') {
    $orderBy = 't.amount DESC, t.id DESC';
  }

  $total = 0;
  $countSql = "SELECT COUNT(*) AS c FROM transactions t LEFT JOIN users u ON u.id = t.user_id{$joinCp} {$whereSql}";
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

  $sql = "SELECT t.id, t.user_id, t.type, t.asset, t.amount, t.status, t.meta, t.created_at,
                 u.full_name, u.email, u.wallet_id
                 {$selCp}
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id{$joinCp}
          {$whereSql}
          ORDER BY {$orderBy}
          LIMIT ? OFFSET ?";
  $stmt2 = $db->prepare($sql);
  $rows = [];
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
        $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
        $display = admin_tx_display_status((string)($row['status'] ?? ''), $meta);
        $name = admin_tx_primary_user_label($row);
        $network = isset($meta['network']) && is_string($meta['network']) ? $meta['network'] : '—';
        $wallet = isset($meta['wallet_address']) && is_string($meta['wallet_address'])
          ? $meta['wallet_address']
          : ((string)($row['wallet_id'] ?? '') !== '' ? (string)$row['wallet_id'] : '—');
        $party = admin_tx_attach_party_labels($row, $meta);
        $rows[] = [
          'id' => (int)($row['id'] ?? 0),
          'user_id' => (int)($row['user_id'] ?? 0),
          'user_label' => $name,
          'sender_label' => $party['sender_label'],
          'receiver_label' => $party['receiver_label'],
          'counterparty_user_id' => $party['counterparty_user_id'],
          'type' => strtolower((string)($row['type'] ?? '')),
          'amount' => (float)($row['amount'] ?? 0),
          'asset' => strtoupper((string)($row['asset'] ?? '')),
          'network' => $network,
          'wallet' => $wallet,
          'status' => (string)($row['status'] ?? ''),
          'display_status' => $display,
          'created_at' => (string)($row['created_at'] ?? ''),
          'meta' => $meta,
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
    'rows' => $rows,
  ];
}

/**
 * Aggregate counts for deposit-type transactions (admin deposit page).
 *
 * @return array{total_completed_usd:float, pending_count:int, completed_count:int, cancelled_count:int}
 */
function admin_deposit_stats(mysqli $db): array {
  $out = [
    'total_completed_usd' => 0.0,
    'pending_count' => 0,
    'completed_count' => 0,
    'cancelled_count' => 0,
  ];
  if (!admin_tx_table_exists($db)) {
    return $out;
  }
  if ($r = @$db->query("SELECT COALESCE(SUM(amount),0) AS s FROM transactions WHERE type = 'deposit' AND status = 'completed'")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['total_completed_usd'] = (float)($row['s'] ?? 0);
  }
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'deposit' AND status = 'pending'")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['pending_count'] = (int)($row['c'] ?? 0);
  }
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'deposit' AND status = 'completed'")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['completed_count'] = (int)($row['c'] ?? 0);
  }
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'deposit' AND status IN ('failed','cancelled')")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['cancelled_count'] = (int)($row['c'] ?? 0);
  }
  return $out;
}

/**
 * Aggregate counts for withdrawal-type transactions (admin withdrawal page).
 *
 * @return array{total_completed_usd:float, pending_count:int, completed_count:int, rejected_count:int, flagged_count:int}
 */
function admin_withdrawal_stats(mysqli $db): array {
  $out = [
    'total_completed_usd' => 0.0,
    'pending_count' => 0,
    'completed_count' => 0,
    'rejected_count' => 0,
    'flagged_count' => 0,
  ];
  if (!admin_tx_table_exists($db)) {
    return $out;
  }
  if ($r = @$db->query("SELECT COALESCE(SUM(amount),0) AS s FROM transactions WHERE type = 'withdrawal' AND status = 'completed'")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['total_completed_usd'] = (float)($row['s'] ?? 0);
  }
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'withdrawal' AND status = 'pending'")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['pending_count'] = (int)($row['c'] ?? 0);
  }
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'withdrawal' AND status = 'completed'")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['completed_count'] = (int)($row['c'] ?? 0);
  }
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'withdrawal' AND status IN ('failed','cancelled')")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['rejected_count'] = (int)($row['c'] ?? 0);
  }
  // flagged is stored in meta as admin_flagged=true (see admin_tx_display_status)
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'withdrawal' AND (meta LIKE '%\"admin_flagged\":true%' OR meta LIKE '%\"flagged\":true%')")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['flagged_count'] = (int)($row['c'] ?? 0);
  }
  return $out;
}

/**
 * Security panel insights for withdrawals page (best-effort).
 *
 * @return array{
 *   largest_pending: array{tx_id:int, user_label:string, amount_usd:float, asset:string, minutes_ago:int}|null,
 *   flagged_pending_count:int,
 *   rapid_user: array{user_label:string, count:int, total_usd:float, minutes:int}|null,
 *   new_address: array{tx_id:int, user_label:string, to_address_short:string, is_first_for_user:bool}|null
 * }
 */
function admin_withdrawal_alerts(mysqli $db): array {
  $out = [
    'largest_pending' => null,
    'flagged_pending_count' => 0,
    'rapid_user' => null,
    'new_address' => null,
    'unusual_ip' => null,
    'unusual_country' => null,
  ];
  if (!admin_tx_table_exists($db)) {
    return $out;
  }

  // Largest pending withdrawal.
  $sql = "SELECT t.id, t.user_id, t.amount, t.asset, t.created_at, u.full_name, u.email, u.wallet_id
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id
          WHERE t.type = 'withdrawal' AND t.status = 'pending'
          ORDER BY t.amount DESC, t.created_at DESC
          LIMIT 1";
  if ($r = @$db->query($sql)) {
    $row = $r->fetch_assoc();
    $r->free();
    if (is_array($row)) {
      $createdAt = (string)($row['created_at'] ?? '');
      $mins = 0;
      if ($createdAt !== '') {
        $t = strtotime($createdAt);
        if ($t !== false) {
          $mins = max(0, (int)floor((time() - $t) / 60));
        }
      }
      $out['largest_pending'] = [
        'tx_id' => (int)($row['id'] ?? 0),
        'user_label' => admin_tx_primary_user_label($row),
        'amount_usd' => (float)($row['amount'] ?? 0),
        'asset' => strtoupper((string)($row['asset'] ?? '')),
        'minutes_ago' => $mins,
      ];
    }
  }

  // Flagged pending withdrawals count (meta flagged).
  if ($r = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'withdrawal' AND status = 'pending' AND (meta LIKE '%\"admin_flagged\":true%' OR meta LIKE '%\"flagged\":true%')")) {
    $row = $r->fetch_assoc();
    $r->free();
    $out['flagged_pending_count'] = (int)($row['c'] ?? 0);
  }

  // Rapid multiple withdrawals: top user by count within last 10 minutes (pending).
  if ($r = @$db->query("SELECT user_id, COUNT(*) AS c, COALESCE(SUM(amount),0) AS s
                        FROM transactions
                        WHERE type='withdrawal' AND status='pending' AND created_at >= (NOW() - INTERVAL 10 MINUTE)
                        GROUP BY user_id
                        ORDER BY c DESC, s DESC
                        LIMIT 1")) {
    $row = $r->fetch_assoc();
    $r->free();
    if (is_array($row) && (int)($row['c'] ?? 0) >= 3) {
      $uid = (int)($row['user_id'] ?? 0);
      $label = 'User #' . $uid;
      $u = @$db->prepare("SELECT id, full_name, email, wallet_id FROM users WHERE id = ? LIMIT 1");
      if ($u) {
        $u->bind_param('i', $uid);
        $u->execute();
        $resU = $u->get_result();
        $ur = $resU ? $resU->fetch_assoc() : null;
        $u->close();
        if (is_array($ur)) {
          $label = admin_tx_primary_user_label($ur + ['user_id' => $uid]);
        }
      }
      $out['rapid_user'] = [
        'user_label' => $label,
        'count' => (int)($row['c'] ?? 0),
        'total_usd' => (float)($row['s'] ?? 0),
        'minutes' => 10,
      ];
    }
  }

  // New wallet address (best-effort): latest pending withdrawal with to_address; mark "first for user" if no prior withdrawals contain that address for that user.
  $sql = "SELECT t.id, t.user_id, t.meta, t.created_at, u.full_name, u.email, u.wallet_id
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id
          WHERE t.type='withdrawal' AND t.status='pending'
          ORDER BY t.created_at DESC, t.id DESC
          LIMIT 10";
  if ($r = @$db->query($sql)) {
    while ($row = $r->fetch_assoc()) {
      $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
      $addr = isset($meta['to_address']) && is_string($meta['to_address']) ? trim($meta['to_address']) : '';
      if ($addr === '') continue;
      $uid = (int)($row['user_id'] ?? 0);
      $esc = $db->real_escape_string($addr);
      $isFirst = true;
      if ($uid > 0) {
        if ($rr = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type='withdrawal' AND user_id=" . (int)$uid . " AND id <> " . (int)($row['id'] ?? 0) . " AND meta LIKE '%" . $esc . "%'")) {
          $cr = $rr->fetch_assoc();
          $rr->free();
          $isFirst = ((int)($cr['c'] ?? 0)) === 0;
        }
      }
      $short = strlen($addr) > 18 ? (substr($addr, 0, 8) . '...' . substr($addr, -6)) : $addr;
      $out['new_address'] = [
        'tx_id' => (int)($row['id'] ?? 0),
        'user_label' => admin_tx_primary_user_label($row),
        'to_address_short' => $short,
        'is_first_for_user' => $isFirst,
      ];
      break;
    }
    $r->free();
  }

  // Unusual IP (best-effort): latest pending withdrawal with meta.ip that differs from user's most recent withdrawal ip.
  $sql = "SELECT t.id, t.user_id, t.meta, t.created_at, u.full_name, u.email, u.wallet_id
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id
          WHERE t.type='withdrawal' AND t.status='pending'
          ORDER BY t.created_at DESC, t.id DESC
          LIMIT 10";
  if ($r = @$db->query($sql)) {
    while ($row = $r->fetch_assoc()) {
      $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
      $ip = isset($meta['ip']) && is_string($meta['ip']) ? trim($meta['ip']) : '';
      if ($ip === '') continue;
      $uid = (int)($row['user_id'] ?? 0);
      if ($uid <= 0) continue;
      $prevIp = '';
      $prevCountry = '';
      $st = @$db->prepare("SELECT meta FROM transactions WHERE type='withdrawal' AND user_id = ? AND id <> ? ORDER BY created_at DESC, id DESC LIMIT 1");
      if ($st) {
        $txId = (int)($row['id'] ?? 0);
        $st->bind_param('ii', $uid, $txId);
        $st->execute();
        $st->store_result();
        $mjson = '';
        $st->bind_result($mjson);
        if ($st->fetch()) {
          $pm = admin_tx_parse_meta($mjson);
          $prevIp = isset($pm['ip']) && is_string($pm['ip']) ? trim($pm['ip']) : '';
          if (isset($pm['country_iso']) && is_string($pm['country_iso'])) {
            $prevCountry = strtoupper(trim($pm['country_iso']));
          } elseif (isset($pm['country_name']) && is_string($pm['country_name'])) {
            $prevCountry = trim($pm['country_name']);
          }
        }
        $st->free_result();
        $st->close();
      }
      if ($prevIp !== '' && $prevIp !== $ip) {
        $mins = 0;
        $createdAt = (string)($row['created_at'] ?? '');
        if ($createdAt !== '') {
          $t = strtotime($createdAt);
          if ($t !== false) $mins = max(0, (int)floor((time() - $t) / 60));
        }
        $out['unusual_ip'] = [
          'tx_id' => (int)($row['id'] ?? 0),
          'user_label' => admin_tx_primary_user_label($row),
          'ip' => $ip,
          'prev_ip' => $prevIp,
          'minutes_ago' => $mins,
        ];
        $countryNow = '';
        if (isset($meta['country_iso']) && is_string($meta['country_iso'])) {
          $countryNow = strtoupper(trim($meta['country_iso']));
        } elseif (isset($meta['country_name']) && is_string($meta['country_name'])) {
          $countryNow = trim($meta['country_name']);
        }
        if ($countryNow !== '' && $prevCountry !== '' && $countryNow !== $prevCountry) {
          $out['unusual_country'] = [
            'tx_id' => (int)($row['id'] ?? 0),
            'user_label' => admin_tx_primary_user_label($row),
            'country' => $countryNow,
            'prev_country' => $prevCountry,
            'minutes_ago' => $mins,
          ];
        }
        break;
      }
    }
    $r->free();
  }

  return $out;
}

function admin_tx_get(mysqli $db, int $id): ?array {
  if ($id <= 0 || !admin_tx_table_exists($db)) {
    return null;
  }
  global $DB_NAME;
  ensure_transactions_table($db);
  admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);
  $hasCp = admin_tx_schema_column_exists($db, (string)$DB_NAME, 'transactions', 'counterparty_user_id');
  $joinCp = $hasCp ? ' LEFT JOIN users cp ON cp.id = t.counterparty_user_id' : '';
  $selCp = $hasCp
    ? ', t.counterparty_user_id, cp.full_name AS cp_full_name, cp.email AS cp_email, cp.wallet_id AS cp_wallet_id'
    : '';
  $sql = "SELECT t.id, t.user_id, t.type, t.asset, t.amount, t.status, t.meta, t.created_at,
                 u.full_name, u.email, u.wallet_id
                 {$selCp}
          FROM transactions t
          LEFT JOIN users u ON u.id = t.user_id{$joinCp}
          WHERE t.id = ?
          LIMIT 1";
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return null;
  }
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!is_array($row)) {
    return null;
  }
  $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
  $name = admin_tx_primary_user_label($row);
  $network = isset($meta['network']) && is_string($meta['network']) ? $meta['network'] : '—';
  $wallet = isset($meta['wallet_address']) && is_string($meta['wallet_address'])
    ? $meta['wallet_address']
    : ((string)($row['wallet_id'] ?? '') !== '' ? (string)$row['wallet_id'] : '—');
  $party = admin_tx_attach_party_labels($row, $meta);

  return [
    'id' => (int)($row['id'] ?? 0),
    'user_id' => (int)($row['user_id'] ?? 0),
    'user_label' => $name,
    'sender_label' => $party['sender_label'],
    'receiver_label' => $party['receiver_label'],
    'counterparty_user_id' => $party['counterparty_user_id'],
    'type' => strtolower((string)($row['type'] ?? '')),
    'amount' => (float)($row['amount'] ?? 0),
    'asset' => strtoupper((string)($row['asset'] ?? '')),
    'network' => $network,
    'wallet' => $wallet,
    'status' => (string)($row['status'] ?? ''),
    'display_status' => admin_tx_display_status((string)($row['status'] ?? ''), $meta),
    'created_at' => (string)($row['created_at'] ?? ''),
    'meta' => $meta,
  ];
}

/**
 * Approve a pending deposit: credits user USD balances and marks the row completed.
 *
 * @return array{ok:bool, error?:string}
 */
function admin_tx_deposit_approve(mysqli $db, int $txId): array {
  global $DB_NAME;
  ensure_user_dashboard_schema($db, (string)$DB_NAME);

  $db->begin_transaction();
  try {
    $stmt = $db->prepare('SELECT user_id, type, status, amount, meta FROM transactions WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      $db->rollback();
      return ['ok' => false, 'error' => 'db'];
    }
    $stmt->bind_param('i', $txId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($row)) {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_found'];
    }
    if (strtolower((string)($row['type'] ?? '')) !== 'deposit') {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_deposit'];
    }
    if (strtolower((string)($row['status'] ?? '')) !== 'pending') {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid_state'];
    }
    $userId = (int)($row['user_id'] ?? 0);
    $amount = (float)($row['amount'] ?? 0);
    if ($userId <= 0 || !is_finite($amount) || $amount <= 0) {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid'];
    }

    $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
    $meta['admin_approved_at'] = date('c');

    $u = $db->prepare('SELECT available_balance_usd, spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$u) {
      throw new RuntimeException('prepare user');
    }
    $u->bind_param('i', $userId);
    $u->execute();
    $resU = $u->get_result();
    $ur = $resU ? $resU->fetch_assoc() : null;
    $u->close();
    if (!is_array($ur)) {
      $db->rollback();
      return ['ok' => false, 'error' => 'user_not_found'];
    }

    $avail = (float)($ur['available_balance_usd'] ?? 0);
    $spot = (float)($ur['spot_balance_usd'] ?? 0);
    $newAvail = $avail + $amount;
    $aStr = number_format($newAvail, 2, '.', '');
    $sStr = number_format($spot, 2, '.', '');
    $volAdd = number_format($amount, 2, '.', '');
    $pvStr = number_format($newAvail + $spot, 2, '.', '');

    $up = $db->prepare('UPDATE users SET available_balance_usd = ?, spot_balance_usd = ?, volume_24h_usd = volume_24h_usd + ?, portfolio_value_usd = ? WHERE id = ?');
    if (!$up) {
      throw new RuntimeException('prepare user update');
    }
    $up->bind_param('ssssi', $aStr, $sStr, $volAdd, $pvStr, $userId);
    $up->execute();
    $up->close();

    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $done = 'completed';
    $upTx = $db->prepare('UPDATE transactions SET status = ?, meta = ? WHERE id = ?');
    if (!$upTx) {
      throw new RuntimeException('prepare tx update');
    }
    $upTx->bind_param('ssi', $done, $metaJson, $txId);
    $upTx->execute();
    $upTx->close();

    $db->commit();
    return ['ok' => true];
  } catch (Throwable) {
    $db->rollback();
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * Approve a pending withdrawal: debits user USD balances and marks the row completed.
 *
 * @return array{ok:bool, error?:string}
 */
function admin_tx_withdrawal_approve(mysqli $db, int $txId): array {
  global $DB_NAME;
  ensure_user_dashboard_schema($db, (string)$DB_NAME);

  $db->begin_transaction();
  try {
    $stmt = $db->prepare('SELECT user_id, type, status, amount, meta FROM transactions WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      $db->rollback();
      return ['ok' => false, 'error' => 'db'];
    }
    $stmt->bind_param('i', $txId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($row)) {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_found'];
    }
    if (strtolower((string)($row['type'] ?? '')) !== 'withdrawal') {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_withdrawal'];
    }
    if (strtolower((string)($row['status'] ?? '')) !== 'pending') {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid_state'];
    }
    $userId = (int)($row['user_id'] ?? 0);
    $amount = (float)($row['amount'] ?? 0);
    if ($userId <= 0 || !is_finite($amount) || $amount <= 0) {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid'];
    }

    $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
    $meta['admin_approved_at'] = date('c');

    $reserved = !empty($meta['reserved']) || !empty($meta['reserved_total_usd']);
    if (!$reserved) {
      // Legacy mode: if user funds were not reserved at request time, debit now.
      $u = $db->prepare('SELECT available_balance_usd, spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
      if (!$u) {
        throw new RuntimeException('prepare user');
      }
      $u->bind_param('i', $userId);
      $u->execute();
      $resU = $u->get_result();
      $ur = $resU ? $resU->fetch_assoc() : null;
      $u->close();
      if (!is_array($ur)) {
        $db->rollback();
        return ['ok' => false, 'error' => 'user_not_found'];
      }
      $avail = (float)($ur['available_balance_usd'] ?? 0);
      $spot = (float)($ur['spot_balance_usd'] ?? 0);
      if ($avail + 1e-9 < $amount) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient_balance'];
      }
      $newAvail = $avail - $amount;
      $aStr = number_format($newAvail, 2, '.', '');
      $sStr = number_format($spot, 2, '.', '');
      $pvStr = number_format($newAvail + $spot, 2, '.', '');
      $up = $db->prepare('UPDATE users SET available_balance_usd = ?, spot_balance_usd = ?, portfolio_value_usd = ? WHERE id = ?');
      if (!$up) {
        throw new RuntimeException('prepare user update');
      }
      $up->bind_param('sssi', $aStr, $sStr, $pvStr, $userId);
      $up->execute();
      $up->close();
    }

    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $done = 'completed';
    $upTx = $db->prepare('UPDATE transactions SET status = ?, meta = ? WHERE id = ?');
    if (!$upTx) {
      throw new RuntimeException('prepare tx update');
    }
    $upTx->bind_param('ssi', $done, $metaJson, $txId);
    $upTx->execute();
    $upTx->close();

    $db->commit();
    return ['ok' => true];
  } catch (Throwable) {
    $db->rollback();
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * Sends created by `Userprofile/api/wallet_api.php` debit `user_asset_balances` immediately and insert a `transactions`
 * row with `type = send` + `status = pending`.
 *
 * If an admin rejects/cancels while still pending, we must refund those debits (asset + optional gas fee).
 *
 * @return array{ok:bool, error?:string}
 */
function admin_tx_send_reject_and_refund(mysqli $db, int $txId, string $reason, int $adminId): array {
  if ($txId <= 0) {
    return ['ok' => false, 'error' => 'invalid'];
  }
  $reason = trim($reason);

  ensure_asset_portfolio_schema($db);
  ensure_transactions_table($db);

  $db->begin_transaction();
  try {
    $stmt = $db->prepare('SELECT user_id, type, status, asset, amount, meta FROM transactions WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new RuntimeException('prepare');
    }
    $stmt->bind_param('i', $txId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($row)) {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_found'];
    }

    $type = strtolower(trim((string)($row['type'] ?? '')));
    if ($type !== 'send') {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_send'];
    }

    $status = strtolower(trim((string)($row['status'] ?? '')));
    if ($status !== 'pending') {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid_state'];
    }

    $userId = (int)($row['user_id'] ?? 0);
    if ($userId <= 0) {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid_user'];
    }

    $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
    $assetId = isset($meta['asset_id']) ? strtolower(trim((string)$meta['asset_id'])) : '';
    if ($assetId === '') {
      $assetId = strtolower(trim((string)($row['asset'] ?? '')));
    }

    $cryptoAmt = isset($meta['crypto_amount']) && is_numeric($meta['crypto_amount'])
      ? (float)$meta['crypto_amount']
      : (float)($row['amount'] ?? 0);
    $assetFee = isset($meta['asset_fee_amount']) && is_numeric($meta['asset_fee_amount']) ? (float)$meta['asset_fee_amount'] : 0.0;
    $feeAssetId = isset($meta['gas_asset_id']) ? strtolower(trim((string)$meta['gas_asset_id'])) : '';
    $gasFee = 0.0;
    if (isset($meta['gas_fee_amount']) && is_numeric($meta['gas_fee_amount'])) {
      $gasFee = (float)$meta['gas_fee_amount'];
    } elseif (isset($meta['gas_fee']) && is_numeric($meta['gas_fee'])) {
      $gasFee = (float)$meta['gas_fee'];
    }

    if ($assetId === '' || !is_finite($cryptoAmt) || $cryptoAmt <= 0) {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid_meta'];
    }

    $totalAssetDebit = $cryptoAmt + max(0.0, $assetFee);

    // Refund main asset (+asset fee if it was debited alongside amount).
    $b1 = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
    if (!$b1) {
      throw new RuntimeException('prepare bal');
    }
    $b1->bind_param('is', $userId, $assetId);
    $b1->execute();
    $b1->store_result();
    $balStr = '0';
    $b1->bind_result($balStr);
    $b1->fetch();
    $b1->free_result();
    $b1->close();

    $newBal = (float)$balStr + $totalAssetDebit;
    $newBalStr = number_format($newBal, 18, '.', '');
    $upB = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
    if (!$upB) {
      throw new RuntimeException('up bal');
    }
    $upB->bind_param('iss', $userId, $assetId, $newBalStr);
    $upB->execute();
    $upB->close();

    $refundMeta = [
      'kind' => 'send_refund',
      'tx_id' => $txId,
      'reason' => $reason !== '' ? $reason : 'declined',
      'admin_id' => $adminId,
      'at' => date('c'),
    ];
    $refundMetaJson = json_encode($refundMeta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $amtDb = number_format($totalAssetDebit, 18, '.', '');
    $afterDb = number_format($newBal, 18, '.', '');
    $led = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'credit\', ?, ?, ?)');
    if ($led) {
      $led->bind_param('issss', $userId, $assetId, $amtDb, $afterDb, $refundMetaJson);
      $led->execute();
      $led->close();
    }

    // Refund gas if debited separately.
    if ($feeAssetId !== '' && is_finite($gasFee) && $gasFee > 0 && $feeAssetId !== $assetId) {
      $b2 = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
      if (!$b2) {
        throw new RuntimeException('prepare gas');
      }
      $b2->bind_param('is', $userId, $feeAssetId);
      $b2->execute();
      $b2->store_result();
      $gStr = '0';
      $b2->bind_result($gStr);
      $b2->fetch();
      $b2->free_result();
      $b2->close();

      $gNew = (float)$gStr + $gasFee;
      $gNewStr = number_format($gNew, 18, '.', '');
      $upG = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
      if (!$upG) {
        throw new RuntimeException('up gas');
      }
      $upG->bind_param('iss', $userId, $feeAssetId, $gNewStr);
      $upG->execute();
      $upG->close();

      $gasRefundMeta = $refundMeta + ['kind' => 'gas_refund'];
      $gasRefundMetaJson = json_encode($gasRefundMeta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
      $feeAmtDb = number_format($gasFee, 18, '.', '');
      $feeAfterDb = number_format($gNew, 18, '.', '');
      $ledG = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'credit\', ?, ?, ?)');
      if ($ledG) {
        $ledG->bind_param('issss', $userId, $feeAssetId, $feeAmtDb, $feeAfterDb, $gasRefundMetaJson);
        $ledG->execute();
        $ledG->close();
      }
    }

    $meta['admin_review'] = 'rejected';
    $meta['admin_review_at'] = date('c');
    $meta['admin_review_by'] = $adminId;
    if ($reason !== '') {
      $meta['admin_review_note'] = $reason;
    }
    $metaJson2 = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    $newStatus = 'failed';
    $upTx = $db->prepare('UPDATE transactions SET status = ?, meta = ? WHERE id = ?');
    if (!$upTx) {
      throw new RuntimeException('up tx');
    }
    $upTx->bind_param('ssi', $newStatus, $metaJson2, $txId);
    $upTx->execute();
    $upTx->close();

    $db->commit();
    return ['ok' => true];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[admin_tx_send_reject_and_refund] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * Mark a pending send as approved/completed (funds already moved at request time).
 *
 * @return array{ok:bool, error?:string}
 */
function admin_tx_send_approve(mysqli $db, int $txId, int $adminId): array {
  if ($txId <= 0) {
    return ['ok' => false, 'error' => 'invalid'];
  }
  ensure_transactions_table($db);

  $db->begin_transaction();
  try {
    $stmt = $db->prepare('SELECT user_id, type, status, meta FROM transactions WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new RuntimeException('prepare');
    }
    $stmt->bind_param('i', $txId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($row)) {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_found'];
    }
    if (strtolower(trim((string)($row['type'] ?? ''))) !== 'send') {
      $db->rollback();
      return ['ok' => false, 'error' => 'not_send'];
    }
    if (strtolower(trim((string)($row['status'] ?? ''))) !== 'pending') {
      $db->rollback();
      return ['ok' => false, 'error' => 'invalid_state'];
    }

    $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
    $meta['admin_review'] = 'approved';
    $meta['admin_review_at'] = date('c');
    $meta['admin_review_by'] = $adminId;
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    $done = 'completed';
    $up = $db->prepare('UPDATE transactions SET status = ?, meta = ? WHERE id = ?');
    if (!$up) {
      throw new RuntimeException('up');
    }
    $up->bind_param('ssi', $done, $metaJson, $txId);
    $up->execute();
    $up->close();

    $db->commit();
    return ['ok' => true];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[admin_tx_send_approve] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * @return array{ok:bool, error?:string}
 */
function admin_tx_apply_action(mysqli $db, int $id, string $action, ?string $note = null): array {
  if ($id <= 0 || !admin_tx_table_exists($db)) {
    return ['ok' => false, 'error' => 'not_found'];
  }

  $stmt = $db->prepare('SELECT type, status, meta FROM transactions WHERE id = ? LIMIT 1');
  if (!$stmt) {
    return ['ok' => false, 'error' => 'db'];
  }
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->store_result();
  $typeCol = '';
  $st = '';
  $metaJson = '';
  $stmt->bind_result($typeCol, $st, $metaJson);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    return ['ok' => false, 'error' => 'not_found'];
  }

  $type = strtolower(trim((string)$typeCol));
  $statusLower = strtolower(trim((string)$st));
  $meta = admin_tx_parse_meta($metaJson);
  $act = strtolower(trim($action));

  if ($act === 'flag') {
    $meta['admin_flagged'] = true;
    $meta['admin_flagged_at'] = date('c');
    $newJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $up = $db->prepare('UPDATE transactions SET meta = ? WHERE id = ?');
    if (!$up) {
      return ['ok' => false, 'error' => 'db'];
    }
    $up->bind_param('si', $newJson, $id);
    $ok = @$up->execute();
    $up->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'update'];
  }

  if ($act === 'approve' && $type === 'deposit') {
    return admin_tx_deposit_approve($db, $id);
  }
  if ($act === 'approve' && $type === 'withdrawal') {
    return admin_tx_withdrawal_approve($db, $id);
  }

  // Sends: funds are debited at request time; approve finalizes, reject refunds debits.
  if ($type === 'send') {
    global $ADMIN_SESSION_ID;
    $adminId = isset($ADMIN_SESSION_ID) ? (int)$ADMIN_SESSION_ID : 0;

    if ($act === 'approve') {
      if ($statusLower !== 'pending') {
        return ['ok' => false, 'error' => 'invalid_state'];
      }
      return admin_tx_send_approve($db, $id, $adminId > 0 ? $adminId : 0);
    }

    if (in_array($act, ['reject', 'reverse', 'cancel'], true)) {
      if ($statusLower !== 'pending') {
        return ['ok' => false, 'error' => 'invalid_state'];
      }
      $n = is_string($note) ? trim($note) : '';
      return admin_tx_send_reject_and_refund($db, $id, $n, $adminId > 0 ? $adminId : 0);
    }
  }

  // If we reserved funds at withdrawal request time, rejecting/cancelling should refund.
  if ($type === 'withdrawal' && in_array($act, ['reject', 'reverse', 'cancel'], true)) {
    if ($statusLower !== 'pending') {
      return ['ok' => false, 'error' => 'invalid_state'];
    }
    $db->begin_transaction();
    try {
      $stmt = $db->prepare('SELECT user_id, amount, meta FROM transactions WHERE id = ? LIMIT 1 FOR UPDATE');
      if (!$stmt) {
        $db->rollback();
        return ['ok' => false, 'error' => 'db'];
      }
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      if (!is_array($row)) {
        $db->rollback();
        return ['ok' => false, 'error' => 'not_found'];
      }
      $userId = (int)($row['user_id'] ?? 0);
      $amount = (float)($row['amount'] ?? 0);
      $meta = admin_tx_parse_meta(isset($row['meta']) ? (string)$row['meta'] : null);
      $reserved = !empty($meta['reserved']) || !empty($meta['reserved_asset_amount']) || !empty($meta['reserved_total_usd']);

      // New wallet-assets model refund
      $assetId = isset($meta['asset_id']) ? strtolower((string)$meta['asset_id']) : '';
      $cryptoAmount = isset($meta['crypto_amount']) ? (float)$meta['crypto_amount'] : 0.0;
      $assetFee = isset($meta['asset_fee_amount']) ? (float)$meta['asset_fee_amount'] : 0.0;
      $gasAssetId = isset($meta['gas_asset_id']) ? strtolower((string)$meta['gas_asset_id']) : '';
      $gasFee = isset($meta['gas_fee_amount']) ? (float)$meta['gas_fee_amount'] : 0.0;

      if ($reserved && $userId > 0 && $assetId !== '' && is_finite($cryptoAmount) && $cryptoAmount > 0) {
        // Trade-withdrawal model: refund trade balance only (spot_balance_usd).
        if (isset($meta['reserved_trade_usd']) && is_numeric($meta['reserved_trade_usd'])) {
          $refundTrade = (float)$meta['reserved_trade_usd'];
          if ($refundTrade > 0) {
            global $DB_NAME;
            ensure_user_dashboard_schema($db, (string)$DB_NAME);
            $u = $db->prepare('SELECT spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
            if ($u) {
              $u->bind_param('i', $userId);
              $u->execute();
              $u->store_result();
              $spotStr = '0.00';
              $u->bind_result($spotStr);
              if ($u->fetch()) {
                $spot = (float)$spotStr;
                $newSpot = $spot + $refundTrade;
                $newSpotStr = number_format($newSpot, 2, '.', '');
                $up = $db->prepare('UPDATE users SET spot_balance_usd = ? WHERE id = ?');
                if ($up) {
                  $up->bind_param('si', $newSpotStr, $userId);
                  $up->execute();
                  $up->close();
                }
              }
              $u->free_result();
              $u->close();
            }
            $meta['refunded_at'] = date('c');
            $meta['refunded_reason'] = $act;
            $meta['refunded_trade_usd'] = $refundTrade;
            $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $upMeta = $db->prepare('UPDATE transactions SET meta = ? WHERE id = ?');
            if ($upMeta) {
              $upMeta->bind_param('si', $metaJson, $id);
              $upMeta->execute();
              $upMeta->close();
            }
          }
        } else
        // Ensure portfolio tables exist (defined in user_db.php).
        if (function_exists('ensure_asset_portfolio_schema')) {
          ensure_asset_portfolio_schema($db);
        }
        // Refund withdrawn asset (amount + any asset-level fee).
        $refundAsset = $cryptoAmount + max(0.0, $assetFee);
        $stmtB = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
        if ($stmtB) {
          $stmtB->bind_param('is', $userId, $assetId);
          $stmtB->execute();
          $stmtB->store_result();
          $bStr = '0';
          $stmtB->bind_result($bStr);
          $stmtB->fetch();
          $stmtB->free_result();
          $stmtB->close();
          $newBal = (float)$bStr + $refundAsset;
          $newStr = number_format($newBal, 18, '.', '');
          $upB = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
          if ($upB) {
            $upB->bind_param('iss', $userId, $assetId, $newStr);
            $upB->execute();
            $upB->close();
          }
          $metaLed = ['kind' => 'withdrawal_refund', 'tx_id' => $id, 'reason' => $act, 'at' => date('c')];
          $metaLedJson = json_encode($metaLed, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
          $amtDb = number_format($refundAsset, 18, '.', '');
          $afterDb = number_format($newBal, 18, '.', '');
          $led = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'credit\', ?, ?, ?)');
          if ($led) {
            $led->bind_param('issss', $userId, $assetId, $amtDb, $afterDb, $metaLedJson);
            $led->execute();
            $led->close();
          }
        }

        // Refund gas fee if it was deducted.
        if ($gasAssetId !== '' && is_finite($gasFee) && $gasFee > 0) {
          $stmtG = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
          if ($stmtG) {
            $stmtG->bind_param('is', $userId, $gasAssetId);
            $stmtG->execute();
            $stmtG->store_result();
            $gStr = '0';
            $stmtG->bind_result($gStr);
            $stmtG->fetch();
            $stmtG->free_result();
            $stmtG->close();
            $newG = (float)$gStr + $gasFee;
            $newGStr = number_format($newG, 18, '.', '');
            $upG = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
            if ($upG) {
              $upG->bind_param('iss', $userId, $gasAssetId, $newGStr);
              $upG->execute();
              $upG->close();
            }
            $metaLed = ['kind' => 'gas_refund', 'tx_id' => $id, 'reason' => $act, 'at' => date('c')];
            $metaLedJson = json_encode($metaLed, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $amtDb = number_format($gasFee, 18, '.', '');
            $afterDb = number_format($newG, 18, '.', '');
            $led = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'credit\', ?, ?, ?)');
            if ($led) {
              $led->bind_param('issss', $userId, $gasAssetId, $amtDb, $afterDb, $metaLedJson);
              $led->execute();
              $led->close();
            }
          }
        }

        $meta['refunded_at'] = date('c');
        $meta['refunded_reason'] = $act;
        $meta['refunded_asset_amount'] = $cryptoAmount + max(0.0, $assetFee);
        $meta['refunded_gas_amount'] = $gasFee;
        $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $upMeta = $db->prepare('UPDATE transactions SET meta = ? WHERE id = ?');
        if ($upMeta) {
          $upMeta->bind_param('si', $metaJson, $id);
          $upMeta->execute();
          $upMeta->close();
        }
      } else {
        // Legacy USD-wallet model refund (kept for compatibility).
        $refund = $reserved ? (float)($meta['reserved_total_usd'] ?? $amount) : 0.0;
        if ($userId > 0 && $refund > 0) {
          global $DB_NAME;
          ensure_user_dashboard_schema($db, (string)$DB_NAME);
          $u = $db->prepare('SELECT available_balance_usd, spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
          if ($u) {
            $u->bind_param('i', $userId);
            $u->execute();
            $resU = $u->get_result();
            $ur = $resU ? $resU->fetch_assoc() : null;
            $u->close();
            if (is_array($ur)) {
              $avail = (float)($ur['available_balance_usd'] ?? 0);
              $spot = (float)($ur['spot_balance_usd'] ?? 0);
              $newAvail = $avail + $refund;
              $aStr = number_format($newAvail, 2, '.', '');
              $sStr = number_format($spot, 2, '.', '');
              $pvStr = number_format($newAvail + $spot, 2, '.', '');
              $up = $db->prepare('UPDATE users SET available_balance_usd = ?, spot_balance_usd = ?, portfolio_value_usd = ? WHERE id = ?');
              if ($up) {
                $up->bind_param('sssi', $aStr, $sStr, $pvStr, $userId);
                $up->execute();
                $up->close();
              }
            }
          }
          $meta['refunded_at'] = date('c');
          $meta['refunded_total_usd'] = $refund;
          $meta['refunded_reason'] = $act;
          $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
          $upMeta = $db->prepare('UPDATE transactions SET meta = ? WHERE id = ?');
          if ($upMeta) {
            $upMeta->bind_param('si', $metaJson, $id);
            $upMeta->execute();
            $upMeta->close();
          }
        }
      }
      $newStatus = ($act === 'reverse' || $act === 'cancel') ? 'cancelled' : 'failed';
      $upSt = $db->prepare('UPDATE transactions SET status = ? WHERE id = ?');
      if (!$upSt) {
        $db->rollback();
        return ['ok' => false, 'error' => 'db'];
      }
      $upSt->bind_param('si', $newStatus, $id);
      $ok = @$upSt->execute();
      $upSt->close();
      if (!$ok) {
        $db->rollback();
        return ['ok' => false, 'error' => 'update'];
      }
      $db->commit();
      return ['ok' => true];
    } catch (Throwable) {
      $db->rollback();
      return ['ok' => false, 'error' => 'exception'];
    }
  }

  if ($type === 'deposit' && in_array($act, ['reject', 'reverse', 'cancel'], true) && $statusLower !== 'pending') {
    return ['ok' => false, 'error' => 'invalid_state'];
  }

  if ($act === 'approve') {
    $newStatus = 'completed';
  } elseif ($act === 'reject') {
    $newStatus = 'failed';
  } elseif ($act === 'reverse' || $act === 'cancel') {
    $newStatus = 'cancelled';
  } else {
    return ['ok' => false, 'error' => 'action'];
  }

  $up = $db->prepare('UPDATE transactions SET status = ? WHERE id = ?');
  if (!$up) {
    return ['ok' => false, 'error' => 'db'];
  }
  $up->bind_param('si', $newStatus, $id);
  $ok = @$up->execute();
  $up->close();
  return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'update'];
}
