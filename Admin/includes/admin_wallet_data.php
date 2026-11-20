<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_wallet_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

/**
 * mysqli_stmt::bind_param with a dynamic value list (avoids PHP spread/reference quirks).
 *
 * @param list<mixed> $params
 */
function admin_wallet_stmt_bind_array(mysqli_stmt $stmt, string $types, array $params): bool {
  $bind = array_merge([$types], $params);
  $refs = [];
  foreach ($bind as $key => $_) {
    $refs[$key] = &$bind[$key];
  }
  return call_user_func_array([$stmt, 'bind_param'], $refs);
}

/**
 * @param array<string, mixed> $row
 * @return array{id:string,userId:int,user:string,email:string,asset:string,balance:float,balanceUSD:float,status:string,lastActivity:string}
 */
function admin_wallet_format_user_row(array $row): array {
  $uid = (int)($row['id'] ?? 0);
  $wid = $row['wallet_id'] ?? null;
  $w = trim((string)$wid);
  $displayId = $w !== '' ? $w : ('UID-' . (string)$uid);
  $avail = (float)($row['available_balance_usd'] ?? 0);
  $adminFund = (float)($row['admin_manual_fund_usd'] ?? 0);
  $adminLedger = (float)($row['admin_wallet_ledger_usd'] ?? 0);
  $spot = (float)($row['spot_balance_usd'] ?? 0);
  $bal = $avail + $adminFund + $adminLedger + $spot;
  $locked = (int)($row['is_locked'] ?? 0);
  $la = $row['last_activity'] ?? null;
  return [
    'id' => $displayId,
    'userId' => $uid,
    'user' => (string)($row['full_name'] ?? ''),
    'email' => (string)($row['email'] ?? ''),
    'asset' => 'USDT',
    'balance' => $bal,
    'balanceUSD' => $bal,
    'status' => $locked === 1 ? 'Frozen' : 'Active',
    'lastActivity' => $la !== null && $la !== '' ? (string)$la : '—',
  ];
}

function admin_wallet_transactions_table_exists(mysqli $db): bool {
  $r = @$db->query("SHOW TABLES LIKE 'transactions'");
  if (!$r) {
    return false;
  }
  $ok = $r->num_rows > 0;
  $r->free();
  return $ok;
}

/**
 * @return array{
 *   total_balance_usd: float,
 *   wallet_count: int,
 *   active_wallets: int,
 *   frozen_wallets: int,
 *   spark_total: list<float>,
 *   spark_wallets: list<float>,
 *   spark_active: list<float>,
 *   spark_frozen: list<float>
 * }
 */
function admin_wallet_aggregate_stats(mysqli $db, string $schema): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_admin_security_columns($db, $schema);

  $total = 0.0;
  $wc = 0;
  $active = 0;
  $frozen = 0;

  $r = @$db->query(
    'SELECT COALESCE(SUM(available_balance_usd + COALESCE(spot_balance_usd, 0) + COALESCE(admin_manual_fund_usd, 0) + COALESCE(admin_wallet_ledger_usd, 0)), 0) AS s, COUNT(*) AS c,
            SUM(CASE WHEN COALESCE(is_locked, 0) = 0 THEN 1 ELSE 0 END) AS a,
            SUM(CASE WHEN COALESCE(is_locked, 0) = 1 THEN 1 ELSE 0 END) AS f
     FROM users'
  );
  if ($r && ($row = $r->fetch_assoc())) {
    $total = (float)($row['s'] ?? 0);
    $wc = (int)($row['c'] ?? 0);
    $active = (int)($row['a'] ?? 0);
    $frozen = (int)($row['f'] ?? 0);
    $r->free();
  }

  $spark = static function (float $base): array {
    $out = [];
    for ($i = 0; $i < 7; $i++) {
      $out[] = round($base * (1 + ($i - 3) * 0.002), 2);
    }
    return $out;
  };

  return [
    'total_balance_usd' => $total,
    'wallet_count' => $wc,
    'active_wallets' => $active,
    'frozen_wallets' => $frozen,
    'spark_total' => $spark(max(0.0, $total)),
    'spark_wallets' => $spark((float)$wc),
    'spark_active' => $spark((float)$active),
    'spark_frozen' => $spark((float)$frozen),
  ];
}

/**
 * @return list<array{id:string,userId:int,user:string,email:string,asset:string,balance:float,balanceUSD:float,status:string,lastActivity:string}>
 */
function admin_wallet_list_rows(mysqli $db, string $schema, int $limit, ?string $q): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_admin_security_columns($db, $schema);

  $limit = max(1, min(500, $limit));
  $hasTx = admin_wallet_transactions_table_exists($db);

  $where = '';
  $params = [];
  $types = '';
  if ($q !== null && trim($q) !== '') {
    $qtrim = trim($q);
    $like = '%' . $qtrim . '%';
    $idNum = ctype_digit($qtrim) ? (int)$qtrim : 0;
    if ($idNum > 0) {
      $where = ' WHERE ((? > 0 AND u.id = ?) OR u.email LIKE ? OR u.full_name LIKE ? OR COALESCE(u.wallet_id, \'\') LIKE ?) ';
      $types = 'iisss';
      $idA = $idNum;
      $idB = $idNum;
      $l1 = $like;
      $l2 = $like;
      $l3 = $like;
      $params = [$idA, $idB, $l1, $l2, $l3];
    } else {
      $where = ' WHERE (u.email LIKE ? OR u.full_name LIKE ? OR COALESCE(u.wallet_id, \'\') LIKE ?) ';
      $types = 'sss';
      $l1 = $like;
      $l2 = $like;
      $l3 = $like;
      $params = [$l1, $l2, $l3];
    }
  }

  $lastSub = $hasTx
    ? '(SELECT MAX(t.created_at) FROM transactions t WHERE t.user_id = u.id)'
    : 'NULL';

  $sql = "SELECT u.id, u.full_name, u.email, u.wallet_id, u.available_balance_usd, u.spot_balance_usd, COALESCE(u.admin_manual_fund_usd, 0) AS admin_manual_fund_usd, COALESCE(u.admin_wallet_ledger_usd, 0) AS admin_wallet_ledger_usd, COALESCE(u.is_locked, 0) AS is_locked,
          {$lastSub} AS last_activity
          FROM users u {$where}
          ORDER BY (u.available_balance_usd + COALESCE(u.spot_balance_usd, 0) + COALESCE(u.admin_manual_fund_usd, 0) + COALESCE(u.admin_wallet_ledger_usd, 0)) DESC, u.id DESC
          LIMIT " . (int)$limit;

  $rows = [];

  // No filter: plain query + fetch_assoc (avoids mysqli bind_param quirks; works on all drivers).
  if ($where === '') {
    $r = @$db->query($sql);
    if (!$r) {
      error_log('[admin_wallet_list_rows] query failed: ' . $db->error);
      return [];
    }
    while ($row = $r->fetch_assoc()) {
      $rows[] = admin_wallet_format_user_row($row);
    }
    $r->free();
    return $rows;
  }

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log('[admin_wallet_list_rows] prepare failed: ' . $db->error);
    return [];
  }
  if (!admin_wallet_stmt_bind_array($stmt, $types, $params)) {
    $stmt->close();
    return [];
  }
  if (!$stmt->execute()) {
    error_log('[admin_wallet_list_rows] execute failed: ' . $stmt->error);
    $stmt->close();
    return [];
  }

  $res = $stmt->get_result();
  if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
      $rows[] = admin_wallet_format_user_row($row);
    }
    $res->free();
    $stmt->close();
    return $rows;
  }

  $stmt->store_result();
  $id = 0;
  $fn = $em = $wid = $avail = $spot = $adminFund = $adminLedger = $locked = $lastAct = '';
  $stmt->bind_result($id, $fn, $em, $wid, $avail, $spot, $adminFund, $adminLedger, $locked, $lastAct);
  while ($stmt->fetch()) {
    $rows[] = admin_wallet_format_user_row([
      'id' => $id,
      'full_name' => $fn,
      'email' => $em,
      'wallet_id' => $wid,
      'available_balance_usd' => $avail,
      'spot_balance_usd' => $spot,
      'admin_manual_fund_usd' => $adminFund,
      'admin_wallet_ledger_usd' => $adminLedger,
      'is_locked' => $locked,
      'last_activity' => $lastAct,
    ]);
  }
  $stmt->free_result();
  $stmt->close();
  return $rows;
}

/**
 * @return list<array{name:string,balance_usd:float}>
 */
function admin_wallet_top_traders(mysqli $db, string $schema, int $limit): array {
  ensure_user_dashboard_schema($db, $schema);
  $limit = max(1, min(20, $limit));
  $r = $db->query(
    'SELECT full_name, (available_balance_usd + COALESCE(spot_balance_usd, 0) + COALESCE(admin_manual_fund_usd, 0) + COALESCE(admin_wallet_ledger_usd, 0)) AS bal
     FROM users ORDER BY bal DESC, id DESC LIMIT ' . (int)$limit
  );
  if (!$r) {
    return [];
  }
  $out = [];
  while ($row = $r->fetch_assoc()) {
    $out[] = [
      'name' => (string)($row['full_name'] ?? ''),
      'balance_usd' => (float)($row['bal'] ?? 0),
    ];
  }
  $r->free();
  return $out;
}

/**
 * @return list<array{type:string,label:string,amount:string,created_at:string,user:string}>
 */
function admin_wallet_recent_feed(mysqli $db, int $limit): array {
  if (!admin_wallet_transactions_table_exists($db)) {
    return [];
  }
  $limit = max(1, min(30, $limit));
  $r = $db->query(
    'SELECT t.type, t.amount, t.status, t.created_at, t.meta, u.full_name
     FROM transactions t
     INNER JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT ' . (int)$limit
  );
  if (!$r) {
    return [];
  }
  $out = [];
  while ($row = $r->fetch_assoc()) {
    $type = strtolower((string)($row['type'] ?? ''));
    $meta = [];
    if (!empty($row['meta'])) {
      $decoded = json_decode((string)$row['meta'], true);
      $meta = is_array($decoded) ? $decoded : [];
    }
    $isAdmin = isset($meta['source']) && $meta['source'] === 'admin_wallet_control';
    $label = $type;
    if ($isAdmin) {
      $label = isset($meta['direction']) && $meta['direction'] === 'debit' ? 'Admin debit' : 'Admin credit';
    } elseif ($type === 'deposit') {
      $label = 'Deposit';
    } elseif ($type === 'withdrawal') {
      $label = 'Withdrawal';
    } elseif ($type === 'send') {
      $label = 'Send';
    } elseif ($type === 'trade') {
      $label = 'Trade';
    } elseif ($type === 'wallet_to_trade') {
      $label = 'Wallet → Trading transfer';
    } elseif ($type === 'admin_manual_fund') {
      $label = 'Admin manual fund';
    } elseif ($type === 'admin_credit') {
      $label = 'Admin credit (non-tradable)';
    } elseif ($type === 'admin_debit') {
      $label = 'Admin debit (non-tradable)';
    }
    $out[] = [
      'type' => $type,
      'label' => $label,
      'amount' => (string)($row['amount'] ?? '0'),
      'created_at' => (string)($row['created_at'] ?? ''),
      'user' => (string)($row['full_name'] ?? ''),
    ];
  }
  $r->free();
  return $out;
}

/**
 * @return list<array{id:int,type:string,asset:string,amount:string,status:string,created_at:string,meta:array<string,mixed>}>
 */
function admin_wallet_user_history(mysqli $db, int $userId, int $limit): array {
  if (!admin_wallet_transactions_table_exists($db) || $userId <= 0) {
    return [];
  }
  $limit = max(1, min(100, $limit));
  $stmt = $db->prepare(
    'SELECT id, type, asset, amount, status, meta, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();
  $tid = 0;
  $type = $asset = $amount = $status = $metaRaw = $createdAt = '';
  $stmt->bind_result($tid, $type, $asset, $amount, $status, $metaRaw, $createdAt);
  $rows = [];
  while ($stmt->fetch()) {
    $meta = [];
    if ($metaRaw !== '') {
      $d = json_decode($metaRaw, true);
      $meta = is_array($d) ? $d : [];
    }
    $rows[] = [
      'id' => (int)$tid,
      'type' => (string)$type,
      'asset' => (string)$asset,
      'amount' => (string)$amount,
      'status' => (string)$status,
      'created_at' => (string)$createdAt,
      'meta' => $meta,
    ];
  }
  $stmt->free_result();
  $stmt->close();
  return $rows;
}

/**
 * @return list<array{id:int,label:string,email:string,wallet_id:string}>
 */
function admin_wallet_search_users(mysqli $db, string $schema, string $q, int $limit): array {
  ensure_user_dashboard_schema($db, $schema);
  $q = trim($q);
  if (strlen($q) < 1) {
    return [];
  }
  $limit = max(1, min(40, $limit));
  $like = '%' . $q . '%';
  $stmt = $db->prepare(
    'SELECT id, full_name, email, wallet_id FROM users
     WHERE (? > 0 AND id = ?)
        OR email LIKE ?
        OR full_name LIKE ?
        OR COALESCE(wallet_id, \'\') LIKE ?
     ORDER BY id DESC LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    error_log('[admin_wallet_search_users] prepare failed: ' . $db->error);
    return [];
  }
  $idNum = ctype_digit($q) ? (int)$q : 0;
  $idA = $idNum;
  $idB = $idNum;
  $l1 = $like;
  $l2 = $like;
  $l3 = $like;
  $params = [$idA, $idB, $l1, $l2, $l3];
  if (!admin_wallet_stmt_bind_array($stmt, 'iisss', $params)) {
    $stmt->close();
    return [];
  }
  if (!$stmt->execute()) {
    error_log('[admin_wallet_search_users] execute failed: ' . $stmt->error);
    $stmt->close();
    return [];
  }

  $rows = [];
  $res = $stmt->get_result();
  if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
      $rows[] = [
        'id' => (int)($row['id'] ?? 0),
        'label' => (string)($row['full_name'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'wallet_id' => (string)($row['wallet_id'] ?? ''),
      ];
    }
    $res->free();
    $stmt->close();
    return $rows;
  }

  $stmt->store_result();
  $id = 0;
  $fn = $em = $wid = '';
  $stmt->bind_result($id, $fn, $em, $wid);
  while ($stmt->fetch()) {
    $rows[] = [
      'id' => (int)$id,
      'label' => (string)$fn,
      'email' => (string)$em,
      'wallet_id' => (string)$wid,
    ];
  }
  $stmt->free_result();
  $stmt->close();
  return $rows;
}

/**
 * @param 'credit'|'debit' $direction
 * @return array{ok:bool, error?:string, available_balance_usd?:string}
 */
function admin_wallet_apply_balance_adjustment(
  mysqli $db,
  string $schema,
  int $userId,
  string $direction,
  float $amountUsd,
  string $reason,
  int $adminId,
  ?string $assetOverride = null
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);

  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'user'];
  }
  if (!is_finite($amountUsd) || $amountUsd <= 0) {
    return ['ok' => false, 'error' => 'amount'];
  }
  $direction = strtolower($direction);
  if ($direction !== 'credit' && $direction !== 'debit') {
    return ['ok' => false, 'error' => 'direction'];
  }

  $asset = $assetOverride !== null && $assetOverride !== ''
    ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', $assetOverride) ?? '')
    : 'USDT';
  if ($asset === '') {
    $asset = 'USDT';
  }

  $reason = trim($reason);
  $metaBase = [
    'source' => 'admin_wallet_control',
    'direction' => $direction,
    'reason' => $reason,
    'admin_id' => $adminId,
    'at' => date('c'),
    'target' => 'main_wallet',
  ];

  $db->begin_transaction();
  try {
    $stmt = $db->prepare(
      'SELECT available_balance_usd, spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE'
    );
    if (!$stmt) {
      throw new RuntimeException('prepare');
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->store_result();
    $aStr = '0.00';
    $sStr = '0.00';
    $stmt->bind_result($aStr, $sStr);
    if (!$stmt->fetch()) {
      $stmt->free_result();
      $stmt->close();
      $db->rollback();
      return ['ok' => false, 'error' => 'not_found'];
    }
    $stmt->free_result();
    $stmt->close();

    $avail = (float)$aStr;
    $spot = (float)$sStr;

    if ($direction === 'credit') {
      $avail += $amountUsd;
    } else {
      if ($avail + 1e-9 < $amountUsd) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient'];
      }
      $avail -= $amountUsd;
    }

    $availOut = number_format($avail, 2, '.', '');
    $spotOut = number_format($spot, 2, '.', '');
    $pvOut = number_format($avail + $spot, 2, '.', '');
    $up = $db->prepare('UPDATE users SET available_balance_usd = ?, spot_balance_usd = ?, portfolio_value_usd = ? WHERE id = ?');
    if (!$up) {
      throw new RuntimeException('update');
    }
    $up->bind_param('sssi', $availOut, $spotOut, $pvOut, $userId);
    $up->execute();
    $up->close();

    $meta = $metaBase + ['asset' => $asset];
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $type = $direction === 'credit' ? 'admin_credit' : 'admin_debit';
    $status = 'completed';
    $amtDb = number_format($amountUsd, 8, '.', '');
    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
      throw new RuntimeException('insert');
    }
    $ins->bind_param('isssss', $userId, $type, $asset, $amtDb, $status, $metaJson);
    $ins->execute();
    $ins->close();

    $db->commit();
    return ['ok' => true];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[admin_wallet_apply_balance_adjustment] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * Credit or debit the user’s trading account only (`spot_balance_usd`). Main wallet is unchanged.
 *
 * @param 'credit'|'debit' $direction
 * @return array{ok:bool, error?:string, spot_balance_usd?:string, available_balance_usd?:string}
 */
function admin_trade_balance_adjustment(
  mysqli $db,
  string $schema,
  int $userId,
  string $direction,
  float $amountUsd,
  string $reason,
  int $adminId
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);

  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'user'];
  }
  if (!is_finite($amountUsd) || $amountUsd <= 0) {
    return ['ok' => false, 'error' => 'amount'];
  }
  $direction = strtolower($direction);
  if ($direction !== 'credit' && $direction !== 'debit') {
    return ['ok' => false, 'error' => 'direction'];
  }

  $asset = 'USDT';
  $reason = trim($reason);
  $metaBase = [
    'source' => 'admin_market_control',
    'direction' => $direction,
    'reason' => $reason,
    'admin_id' => $adminId,
    'at' => date('c'),
    'target' => 'trade_account',
  ];

  $db->begin_transaction();
  try {
    $stmt = $db->prepare(
      'SELECT available_balance_usd, spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE'
    );
    if (!$stmt) {
      throw new RuntimeException('prepare');
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->store_result();
    $aStr = '0.00';
    $sStr = '0.00';
    $stmt->bind_result($aStr, $sStr);
    if (!$stmt->fetch()) {
      $stmt->free_result();
      $stmt->close();
      $db->rollback();
      return ['ok' => false, 'error' => 'not_found'];
    }
    $stmt->free_result();
    $stmt->close();

    $avail = (float)$aStr;
    $spot = (float)$sStr;

    if ($direction === 'credit') {
      $spot += $amountUsd;
    } else {
      if ($spot + 1e-9 < $amountUsd) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient'];
      }
      $spot -= $amountUsd;
    }

    $availOut = number_format($avail, 2, '.', '');
    $spotOut = number_format($spot, 2, '.', '');
    $pvOut = number_format($avail + $spot, 2, '.', '');
    $up = $db->prepare('UPDATE users SET available_balance_usd = ?, spot_balance_usd = ?, portfolio_value_usd = ? WHERE id = ?');
    if (!$up) {
      throw new RuntimeException('update');
    }
    $up->bind_param('sssi', $availOut, $spotOut, $pvOut, $userId);
    $up->execute();
    $up->close();

    $meta = $metaBase + ['asset' => $asset];
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $type = $direction === 'credit' ? 'admin_trade_credit' : 'admin_trade_debit';
    $status = 'completed';
    $amtDb = number_format($amountUsd, 8, '.', '');
    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
      throw new RuntimeException('insert');
    }
    $ins->bind_param('isssss', $userId, $type, $asset, $amtDb, $status, $metaJson);
    $ins->execute();
    $ins->close();

    $db->commit();
    return [
      'ok' => true,
      'spot_balance_usd' => $spotOut,
      'available_balance_usd' => $availOut,
    ];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[admin_trade_balance_adjustment] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * Manual fund: credits `user_asset_balances` (per-asset crypto) + ledger + transactions.
 * User-facing USD totals and portfolio use `getPortfolioValue()` / `assets_api.php`, not `available_balance_usd` alone.
 *
 * @return array{ok:bool, error?:string, available_balance_usd?:string}
 */
function admin_wallet_manual_fund(
  mysqli $db,
  string $schema,
  int $userId,
  float $amountUsd,
  string $asset,
  string $network,
  string $reference,
  string $description,
  int $adminId
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);
  ensure_asset_portfolio_schema($db);

  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'user'];
  }
  if (!is_finite($amountUsd) || $amountUsd <= 0) {
    return ['ok' => false, 'error' => 'amount'];
  }

  $asset = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $asset) ?? '');
  if ($asset === '') {
    $asset = 'USDT';
  }

  $net = strtoupper(trim($network));
  $assetId = strtolower($asset);
  if ($asset === 'USDT') {
    if (str_contains($net, 'TRC')) {
      $assetId = 'usdt_trc20';
    } elseif (str_contains($net, 'ERC')) {
      $assetId = 'usdt_erc20';
    } else {
      // Default stablecoin network if not specified.
      $assetId = 'usdt_erc20';
    }
  } elseif ($asset === 'BTC') {
    $assetId = 'btc';
  } elseif ($asset === 'ETH') {
    $assetId = 'eth';
  } elseif ($asset === 'TRX' || $asset === 'TRON') {
    $assetId = 'trx';
    $asset = 'TRX';
  } elseif ($asset === 'BNB') {
    $assetId = 'bnb';
  } elseif ($asset === 'SOL') {
    $assetId = 'sol';
  } elseif ($asset === 'MATIC') {
    $assetId = 'matic';
  } elseif ($asset === 'DOT') {
    $assetId = 'dot';
  }

  $reason = trim($description);
  if ($reason === '') {
    $reason = 'Manual fund';
  }
  $reason .= ' | Ref: ' . trim($reference);
  $reason .= ' | network:' . trim($network);

  $meta = [
    'source' => 'admin_wallet_control',
    'action' => 'manual_fund',
    'target' => 'wallet_assets',
    'reason' => $reason,
    'admin_id' => $adminId,
    'at' => date('c'),
    'asset' => $asset,
    'asset_id' => $assetId,
    'network' => trim($network),
    'reference' => trim($reference),
    'amount_crypto' => $amountUsd,
  ];
  $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  $type = 'admin_manual_fund';
  $status = 'completed';

  $db->begin_transaction();
  try {
    // Ensure user exists (lock row for consistency with other admin actions).
    $chk = $db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$chk) {
      throw new RuntimeException('prepare');
    }
    $chk->bind_param('i', $userId);
    $chk->execute();
    $chk->store_result();
    if (!$chk->fetch()) {
      $chk->close();
      $db->rollback();
      return ['ok' => false, 'error' => 'not_found'];
    }
    $chk->close();

    // Credit the exact selected asset (crypto units), never trade balance.
    $sel = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
    if (!$sel) {
      throw new RuntimeException('prepare_balance');
    }
    $sel->bind_param('is', $userId, $assetId);
    $sel->execute();
    $sel->store_result();
    $balStr = '0';
    $sel->bind_result($balStr);
    $found = $sel->fetch();
    $sel->free_result();
    $sel->close();

    $prev = $found ? (float)$balStr : 0.0;
    $new = $prev + $amountUsd;
    $newStr = number_format($new, 18, '.', '');
    if ($found) {
      $upB = $db->prepare('UPDATE user_asset_balances SET balance = ? WHERE user_id = ? AND asset_id = ?');
      if (!$upB) {
        throw new RuntimeException('update_balance');
      }
      $upB->bind_param('sis', $newStr, $userId, $assetId);
      $upB->execute();
      $upB->close();
    } else {
      $insB = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?)');
      if (!$insB) {
        throw new RuntimeException('insert_balance');
      }
      $insB->bind_param('iss', $userId, $assetId, $newStr);
      $insB->execute();
      $insB->close();
    }

    $amtCryptoDb = number_format($amountUsd, 18, '.', '');
    $afterDb = number_format($new, 18, '.', '');
    $led = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'credit\', ?, ?, ?)');
    if ($led) {
      $led->bind_param('issss', $userId, $assetId, $amtCryptoDb, $afterDb, $metaJson);
      $led->execute();
      $led->close();
    }

    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
      throw new RuntimeException('insert');
    }
    // Keep `transactions.amount` in crypto units for transparency; USD value is derived live in UI.
    $txAmount = number_format($amountUsd, 18, '.', '');
    $txAsset = strtoupper($assetId);
    $ins->bind_param('isssss', $userId, $type, $txAsset, $txAmount, $status, $metaJson);
    $ins->execute();
    $ins->close();

    $db->commit();
    return ['ok' => true];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[admin_wallet_manual_fund] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * @return array{ok:bool, error?:string}
 */
function admin_wallet_set_frozen(
  mysqli $db,
  string $schema,
  int $userId,
  bool $frozen,
  string $reason,
  int $adminId
): array {
  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'user'];
  }
  try {
    $r = $reason;
    if ($frozen && trim($reason) === '') {
      $r = 'Frozen from Admin Wallet Control (admin ' . $adminId . ')';
    }
    admin_user_set_lock($db, $schema, $userId, $frozen, $r);
    return ['ok' => true];
  } catch (Throwable $e) {
    error_log('[admin_wallet_set_frozen] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * Live security-style snapshots for Wallet Control (deposits, admin adjustments, withdrawal issues).
 *
 * @return list<array{severity:string,title:string,detail:string,sub:string}>
 */
function admin_wallet_security_alerts(mysqli $db, string $schema): array {
  ensure_user_dashboard_schema($db, $schema);

  $emptyOk = static function (string $title, string $detail, string $sub = ''): array {
    return ['severity' => 'ok', 'title' => $title, 'detail' => $detail, 'sub' => $sub];
  };

  if (!admin_wallet_transactions_table_exists($db)) {
    return [
      $emptyOk('Large Balance Change', 'No transactions table — nothing to monitor yet.', ''),
      $emptyOk('Frequent Adjustments', 'Enable transactions to track admin adjustments.', ''),
      $emptyOk('Suspicious Wallet Activity', 'No withdrawal history available yet.', ''),
    ];
  }

  $thresholdLargeUsd = 10000.0;
  $adjWindowMin = 10;
  $adjThreshold = 3;
  $failThreshold = 2;
  $pendingQueueWarn = 8;

  // 1) Largest total completed deposit inflow per user in the last hour (captures deposits + admin credits, both type deposit).
  $large = $emptyOk(
    'Large Balance Change',
    'No completed deposits in the last hour.',
    'Alert when a user’s total inflows exceed $' . number_format($thresholdLargeUsd, 0) . ' in 1 hour'
  );
  $r1 = @$db->query(
    'SELECT u.full_name, SUM(CAST(t.amount AS DECIMAL(24,8))) AS vol
     FROM transactions t
     INNER JOIN users u ON u.id = t.user_id
     WHERE t.created_at >= (NOW() - INTERVAL 1 HOUR)
       AND t.type = \'deposit\'
       AND t.status = \'completed\'
     GROUP BY u.id, u.full_name
     ORDER BY vol DESC
     LIMIT 1'
  );
  if ($r1 && ($row = $r1->fetch_assoc())) {
    $vol = (float)($row['vol'] ?? 0);
    $name = (string)($row['full_name'] ?? 'User');
    $r1->free();
    if ($vol >= $thresholdLargeUsd) {
      $large = [
        'severity' => 'critical',
        'title' => 'Large Balance Change Detected',
        'detail' => sprintf('%s: +$%s total inflows in the last hour', $name, number_format($vol, 2)),
        'sub' => 'Includes deposits and admin credits · Review Transactions',
      ];
    } else {
      $large = [
        'severity' => 'ok',
        'title' => 'Large Balance Change',
        'detail' => $vol > 0
          ? sprintf('Highest 1h inflow: %s (~$%s)', $name, number_format($vol, 2))
          : 'No completed deposits in the last hour.',
        'sub' => 'Critical alert above $' . number_format($thresholdLargeUsd, 0) . ' / user / hour',
      ];
    }
  } elseif ($r1) {
    $r1->free();
  }

  // 2) Admin wallet adjustments (meta tagged admin_wallet_control)
  $adjCount = 0;
  $r2 = @$db->query(
    'SELECT COUNT(*) AS c FROM transactions
     WHERE created_at >= (NOW() - INTERVAL ' . (int)$adjWindowMin . ' MINUTE)
       AND (meta LIKE \'%"source":"admin_wallet_control"%\' OR meta LIKE \'%admin_wallet_control%\')'
  );
  if ($r2 && ($row = $r2->fetch_assoc())) {
    $adjCount = (int)($row['c'] ?? 0);
    $r2->free();
  } elseif ($r2) {
    $r2->free();
  }

  if ($adjCount >= $adjThreshold) {
    $frequent = [
      'severity' => 'warning',
      'title' => 'Frequent Adjustments',
      'detail' => sprintf('%d admin wallet adjustment(s) in the last %d minutes', $adjCount, $adjWindowMin),
      'sub' => 'Matches meta source admin_wallet_control',
    ];
  } else {
    $frequent = [
      'severity' => 'ok',
      'title' => 'Frequent Adjustments',
      'detail' => sprintf('%d adjustment(s) in the last %d minutes', $adjCount, $adjWindowMin),
      'sub' => 'Warning at ≥' . (string)$adjThreshold . ' in that window',
    ];
  }

  // 3) Failed/cancelled withdrawals (24h) or large pending queue
  $susp = $emptyOk(
    'Suspicious Wallet Activity',
    'No failed/cancelled withdrawals in the last 24 hours.',
    'Alert when a user has ≥' . (string)$failThreshold . ' failures or pending queue is large'
  );

  $r3 = @$db->query(
    'SELECT u.full_name, COUNT(*) AS c
     FROM transactions t
     INNER JOIN users u ON u.id = t.user_id
     WHERE t.type = \'withdrawal\'
       AND t.status IN (\'failed\',\'cancelled\')
       AND t.created_at >= (NOW() - INTERVAL 24 HOUR)
     GROUP BY u.id, u.full_name
     ORDER BY c DESC
     LIMIT 1'
  );
  if ($r3 && ($row = $r3->fetch_assoc())) {
    $fc = (int)($row['c'] ?? 0);
    $fn = (string)($row['full_name'] ?? '');
    $r3->free();
    if ($fc >= $failThreshold) {
      $susp = [
        'severity' => 'warning',
        'title' => 'Suspicious Wallet Activity',
        'detail' => sprintf('%s: %d failed/cancelled withdrawal(s) in 24h', $fn, $fc),
        'sub' => 'Review Withdrawals / user history',
      ];
    } elseif ($fc >= 1) {
      $susp = [
        'severity' => 'ok',
        'title' => 'Suspicious Wallet Activity',
        'detail' => sprintf('%s: %d failed/cancelled withdrawal in 24h', $fn, $fc),
        'sub' => 'Warning if ≥' . (string)$failThreshold . ' failures per user',
      ];
    }
  } elseif ($r3) {
    $r3->free();
  }

  $pend = 0;
  $r4 = @$db->query('SELECT COUNT(*) AS c FROM transactions WHERE type = \'withdrawal\' AND status = \'pending\'');
  if ($r4 && ($row = $r4->fetch_assoc())) {
    $pend = (int)($row['c'] ?? 0);
    $r4->free();
  } elseif ($r4) {
    $r4->free();
  }

  if ($pend >= $pendingQueueWarn) {
    $susp = [
      'severity' => 'warning',
      'title' => 'Suspicious Wallet Activity',
      'detail' => sprintf('%d pending withdrawal(s) in queue', $pend),
      'sub' => 'Large backlog — review Withdrawals',
    ];
  }

  return [$large, $frequent, $susp];
}
