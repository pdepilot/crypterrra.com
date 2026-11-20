<?php
declare(strict_types=1);

// Default DB settings (XAMPP typical).
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'crypterra';

// Allow local override in project root: /db-config.php
if (is_file(__DIR__ . '/../../db-config.php')) {
  require __DIR__ . '/../../db-config.php';
}

require_once __DIR__ . '/../../includes/market_control_data.php';

function user_db_connect(string $host, string $user, string $pass, string $db): mysqli {
  mysqli_report(MYSQLI_REPORT_OFF);
  $mysqli = @new mysqli($host, $user, $pass);
  if ($mysqli->connect_errno) {
    throw new RuntimeException('Database connection failed: ' . $mysqli->connect_error);
  }
  $mysqli->set_charset('utf8mb4');
  if ($db !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
    $mysqli->close();
    throw new RuntimeException('Invalid database name.');
  }
  if ($db !== '') {
    if (!$mysqli->select_db($db)) {
      $err = $mysqli->error;
      $mysqli->close();
      throw new RuntimeException('Cannot open database: ' . $err);
    }
  }
  return $mysqli;
}

function user_schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
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
 * Ensures columns used by the user dashboard exist.
 * Best-effort: ALTER failures won't break page rendering.
 */
function ensure_user_dashboard_schema(mysqli $db, string $schema): void {
  $table = 'users';
  $cols = [
    ['wallet_id', "VARCHAR(32) NULL"],
    ['available_balance_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['portfolio_value_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['volume_24h_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['spot_balance_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['futures_balance_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['daily_pl_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['total_pl_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['open_positions', "INT UNSIGNED NOT NULL DEFAULT 0"],
    // Legacy: admin funding used separate columns; merged into available_balance_usd by patch wallet_trade_split_v1.
    ['admin_manual_fund_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
    ['admin_wallet_ledger_usd', "DECIMAL(18,2) NOT NULL DEFAULT 0.00"],
  ];

  foreach ($cols as [$name, $def]) {
    if (!user_schema_column_exists($db, $schema, $table, (string)$name)) {
      @$db->query("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$def}");
    }
  }

  ensure_wallet_balance_model_patch($db, $schema);
}

/**
 * One-time patches keyed in app_schema_patches (idempotent).
 */
function ensure_app_schema_patches_table(mysqli $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `app_schema_patches` (
  `patch_id` VARCHAR(64) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`patch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

/**
 * Merge legacy admin-only USD columns into main wallet (available_balance_usd) once.
 * After this, admin funds and credits use available_balance_usd only; spot_balance_usd is trading only.
 */
function ensure_wallet_balance_model_patch(mysqli $db, string $schema): void {
  ensure_app_schema_patches_table($db);
  $patchId = 'wallet_trade_split_v1';
  $chk = $db->prepare('SELECT patch_id FROM app_schema_patches WHERE patch_id = ? LIMIT 1');
  if (!$chk) {
    return;
  }
  $chk->bind_param('s', $patchId);
  $chk->execute();
  $chk->store_result();
  $found = $chk->fetch();
  $chk->free_result();
  $chk->close();
  if ($found) {
    return;
  }

  $db->begin_transaction();
  try {
    @$db->query(
      'UPDATE users SET available_balance_usd = available_balance_usd + COALESCE(admin_manual_fund_usd, 0) + COALESCE(admin_wallet_ledger_usd, 0), admin_manual_fund_usd = 0, admin_wallet_ledger_usd = 0'
    );
    $ins = $db->prepare('INSERT INTO app_schema_patches (patch_id) VALUES (?)');
    if ($ins) {
      $ins->bind_param('s', $patchId);
      @$ins->execute();
      $ins->close();
    }
    $db->commit();
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[wallet_trade_split_v1] ' . $e->getMessage());
  }
}

/**
 * Audit log for Wallet → Trading transfers.
 */
function ensure_wallet_transfer_log_table(mysqli $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `wallet_transfer_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amount_usd` DECIMAL(18,2) NOT NULL,
  `prev_wallet_usd` DECIMAL(18,2) NOT NULL,
  `new_wallet_usd` DECIMAL(18,2) NOT NULL,
  `prev_trade_usd` DECIMAL(18,2) NOT NULL,
  `new_trade_usd` DECIMAL(18,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wtl_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

/**
 * Move USD from main wallet (available_balance_usd) to trading account (spot_balance_usd).
 *
 * @return array{
 *   ok:bool,
 *   error?:string,
 *   available_balance_usd?:string,
 *   spot_balance_usd?:string,
 *   portfolio_value_usd?:string
 * }
 */
function user_transfer_wallet_to_trade(
  mysqli $db,
  string $schema,
  int $userId,
  float $amountUsd,
  ?string $clientIp = null
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);
  ensure_wallet_transfer_log_table($db);

  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'auth'];
  }
  if (!is_finite($amountUsd) || $amountUsd < 0.01) {
    return ['ok' => false, 'error' => 'amount'];
  }
  if ($amountUsd > 1e12) {
    return ['ok' => false, 'error' => 'amount'];
  }

  $ip = $clientIp !== null ? trim($clientIp) : '';
  if (strlen($ip) > 45) {
    $ip = substr($ip, 0, 45);
  }

  $db->begin_transaction();
  try {
    $stmt = $db->prepare('SELECT available_balance_usd, spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->store_result();
    $wStr = '0.00';
    $tStr = '0.00';
    $stmt->bind_result($wStr, $tStr);
    if (!$stmt->fetch()) {
      $stmt->free_result();
      $stmt->close();
      $db->rollback();
      return ['ok' => false, 'error' => 'user_not_found'];
    }
    $stmt->free_result();
    $stmt->close();

    $wallet = (float)$wStr;
    $trade = (float)$tStr;
    $prevW = $wallet;
    $prevT = $trade;

    if ($wallet + 1e-9 < $amountUsd) {
      $db->rollback();
      return [
        'ok' => false,
        'error' => 'insufficient_balance',
        'available_balance_usd' => number_format($wallet, 2, '.', ''),
        'spot_balance_usd' => number_format($trade, 2, '.', ''),
      ];
    }

    $newWallet = $wallet - $amountUsd;
    $newTrade = $trade + $amountUsd;
    if ($newWallet < -1e-9 || $newTrade < -1e-9) {
      $db->rollback();
      return ['ok' => false, 'error' => 'balance'];
    }

    $nwStr = number_format($newWallet, 2, '.', '');
    $ntStr = number_format($newTrade, 2, '.', '');
    $pvStr = number_format($newWallet + $newTrade, 2, '.', '');

    $up = $db->prepare('UPDATE users SET available_balance_usd = ?, spot_balance_usd = ?, portfolio_value_usd = ? WHERE id = ?');
    if (!$up) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $up->bind_param('sssi', $nwStr, $ntStr, $pvStr, $userId);
    $up->execute();
    $up->close();

    $meta = [
      'source' => 'wallet_transfer',
      'direction' => 'wallet_to_trade',
      'amount_usd' => $amountUsd,
      'prev_wallet_usd' => $prevW,
      'new_wallet_usd' => $newWallet,
      'prev_trade_usd' => $prevT,
      'new_trade_usd' => $newTrade,
      'at' => date('c'),
    ];
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $type = 'wallet_to_trade';
    $status = 'completed';
    $asset = 'USDT';
    $amtDb = number_format($amountUsd, 8, '.', '');
    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $ins->bind_param('isssss', $userId, $type, $asset, $amtDb, $status, $metaJson);
    $ins->execute();
    $ins->close();

    $log = $db->prepare(
      'INSERT INTO wallet_transfer_logs (user_id, amount_usd, prev_wallet_usd, new_wallet_usd, prev_trade_usd, new_trade_usd, ip)
       VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if ($log) {
      $pw = number_format($prevW, 2, '.', '');
      $pwn = number_format($newWallet, 2, '.', '');
      $pt = number_format($prevT, 2, '.', '');
      $ptn = number_format($newTrade, 2, '.', '');
      $amtL = number_format($amountUsd, 2, '.', '');
      $ipParam = $ip !== '' ? $ip : null;
      $log->bind_param('issssss', $userId, $amtL, $pw, $pwn, $pt, $ptn, $ipParam);
      $log->execute();
      $log->close();
    }

    $db->commit();
    return [
      'ok' => true,
      'available_balance_usd' => $nwStr,
      'spot_balance_usd' => $ntStr,
      'portfolio_value_usd' => $pvStr,
    ];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[user_transfer_wallet_to_trade] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

function user_fetch_by_id(mysqli $db, int $userId): ?array {
  $stmt = $db->prepare('SELECT id, full_name, email, wallet_id, available_balance_usd, portfolio_value_usd, volume_24h_usd FROM users WHERE id = ? LIMIT 1');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();

  $id = 0;
  $fullName = $email = '';
  $walletId = null;
  $available = $portfolio = $volume24h = '0.00';
  $spot = $futures = $dailyPl = $totalPl = '0.00';
  $openPositions = 0;
  // Backward-compatible select: try to fetch extended fields if they exist.
  // If some columns are missing (older schema), ensure_user_dashboard_schema() should have been run already.
  $stmt->bind_result($id, $fullName, $email, $walletId, $available, $portfolio, $volume24h);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();

  if (!$found) {
    return null;
  }

  return [
    'id' => (int)$id,
    'full_name' => (string)$fullName,
    'email' => (string)$email,
    'wallet_id' => is_string($walletId) ? $walletId : null,
    'available_balance_usd' => (string)$available,
    'portfolio_value_usd' => (string)$portfolio,
    'volume_24h_usd' => (string)$volume24h,
    // These may be filled by user_fetch_portfolio() below for trade.php.
  ];
}

/**
 * Main USD wallet (cash): `available_balance_usd` + legacy admin columns. Used by userprofile, wallet.php, send, withdraw, wallet_api GET.
 * Trading cash is `spot_balance_usd` (user_get_trade_balance_usd). Crypto position value is getPortfolioValue() / assets_api — not this.
 * Admin credit/debit (wallet_control_api) adjusts available_balance_usd; admin manual_fund credits user_asset_balances (crypto) — use getPortfolioValue / assets list for balances, not this sum alone.
 */
function user_get_wallet_balance_usd(mysqli $db, string $schema, int $userId): string {
  ensure_user_dashboard_schema($db, $schema);
  if ($userId <= 0) {
    return '0.00';
  }
  $stmt = $db->prepare(
    'SELECT COALESCE(available_balance_usd, 0) + COALESCE(admin_manual_fund_usd, 0) + COALESCE(admin_wallet_ledger_usd, 0) AS w
     FROM users WHERE id = ? LIMIT 1'
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();
  $w = '0.00';
  $stmt->bind_result($w);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    return '0.00';
  }
  $val = (float)$w;
  if (!is_finite($val) || $val < 0) {
    $val = 0.0;
  }
  return number_format($val, 2, '.', '');
}

/**
 * Cash (users table) + crypto holdings (user_asset_balances × prices) for UI totals.
 * Admin manual fund credits assets only — include crypto here so wallet/send match reality.
 *
 * @return array{cash_usd:string, crypto_portfolio_usd:string, total_balance_usd:string}
 */
function user_get_wallet_display_aggregates(mysqli $db, string $schema, int $userId): array {
  ensure_user_dashboard_schema($db, $schema);
  if ($userId <= 0) {
    return [
      'cash_usd' => '0.00',
      'crypto_portfolio_usd' => '0.00',
      'total_balance_usd' => '0.00',
    ];
  }
  $cash = user_get_wallet_balance_usd($db, $schema, $userId);
  $crypto = getPortfolioValue($userId);
  if (!is_finite($crypto) || $crypto < 0) {
    $crypto = 0.0;
  }
  $cryptoStr = number_format($crypto, 2, '.', '');
  $total = (float)$cash + $crypto;
  if (!is_finite($total) || $total < 0) {
    $total = 0.0;
  }
  return [
    'cash_usd' => $cash,
    'crypto_portfolio_usd' => $cryptoStr,
    'total_balance_usd' => number_format($total, 2, '.', ''),
  ];
}

/**
 * Trading account USD balance (spot) — separate from main wallet.
 */
function user_get_trade_balance_usd(mysqli $db, string $schema, int $userId): string {
  ensure_user_dashboard_schema($db, $schema);
  if ($userId <= 0) {
    return '0.00';
  }
  $stmt = $db->prepare('SELECT COALESCE(spot_balance_usd, 0) FROM users WHERE id = ? LIMIT 1');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();
  $s = '0.00';
  $stmt->bind_result($s);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    return '0.00';
  }
  $val = (float)$s;
  if (!is_finite($val) || $val < 0) {
    $val = 0.0;
  }
  return number_format($val, 2, '.', '');
}

/**
 * @return array{
 *   tradable_balance_usd:string,
 *   wallet_display_balance_usd:string,
 *   admin_manual_fund_usd:string,
 *   admin_wallet_ledger_usd:string
 * }|null
 */
function user_fetch_wallet_totals(mysqli $db, string $schema, int $userId): ?array {
  ensure_user_dashboard_schema($db, $schema);
  if ($userId <= 0) {
    return null;
  }
  $stmt = $db->prepare(
    'SELECT COALESCE(available_balance_usd, 0) + COALESCE(admin_manual_fund_usd, 0) + COALESCE(admin_wallet_ledger_usd, 0) AS w,
            COALESCE(admin_manual_fund_usd, 0), COALESCE(admin_wallet_ledger_usd, 0)
     FROM users WHERE id = ? LIMIT 1'
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();
  $w = '0.00';
  $m = '0.00';
  $l = '0.00';
  $stmt->bind_result($w, $m, $l);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    return null;
  }
  $manual = (float)$m;
  $ledger = (float)$l;
  $val = (float)$w;
  if (!is_finite($val) || $val < 0) {
    $val = 0.0;
  }
  $walletMain = number_format($val, 2, '.', '');
  return [
    'tradable_balance_usd' => $walletMain,
    'wallet_display_balance_usd' => $walletMain,
    'admin_manual_fund_usd' => number_format($manual, 2, '.', ''),
    'admin_wallet_ledger_usd' => number_format($ledger, 2, '.', ''),
  ];
}

function user_fetch_portfolio(mysqli $db, int $userId): ?array {
  $stmt = $db->prepare('
    SELECT available_balance_usd, spot_balance_usd, futures_balance_usd, open_positions
    FROM users
    WHERE id = ?
    LIMIT 1
  ');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();

  $available = $spot = $futures = '0.00';
  $openPositions = 0;
  $stmt->bind_result($available, $spot, $futures, $openPositions);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    return null;
  }

  // Compute P/L from positions + realized trades.
  $positions = user_fetch_positions($db, $userId);
  $unrealized = 0.0;
  foreach ($positions as $p) {
    $asset = (string)($p['asset'] ?? '');
    $qty = (float)($p['qty'] ?? 0);
    $avg = (float)($p['avg_entry_price_usd'] ?? 0);
    if ($asset === '' || $qty <= 0) continue;
    try {
      $price = user_price_usdt($asset, $db);
      $unrealized += ($price - $avg) * $qty;
    } catch (Throwable $e) {
      // ignore pricing error for this asset
    }
  }

  $realizedTotal = user_sum_realized_pl($db, $userId, null);
  $realized24h = user_sum_realized_pl($db, $userId, time() - 86400);
  $dailyPl = $realized24h + $unrealized;
  $totalPl = $realizedTotal + $unrealized;

  return [
    'available_balance_usd' => (string)$available,
    'spot_balance_usd' => (string)$spot,
    'futures_balance_usd' => (string)$futures,
    'daily_pl_usd' => number_format($dailyPl, 2, '.', ''),
    'total_pl_usd' => number_format($totalPl, 2, '.', ''),
    'open_positions' => (int)$openPositions,
  ];
}

function user_ensure_wallet_id(mysqli $db, int $userId): string {
  // Generate once and persist (simple numeric ID).
  $walletId = (string)random_int(10000000, 99999999);
  $stmt = $db->prepare('UPDATE users SET wallet_id = COALESCE(wallet_id, ?) WHERE id = ?');
  if ($stmt) {
    $stmt->bind_param('si', $walletId, $userId);
    @$stmt->execute();
    $stmt->close();
  }

  $stmt2 = $db->prepare('SELECT wallet_id FROM users WHERE id = ? LIMIT 1');
  if (!$stmt2) {
    return $walletId;
  }
  $stmt2->bind_param('i', $userId);
  $stmt2->execute();
  $stmt2->store_result();
  $current = '';
  $stmt2->bind_result($current);
  $stmt2->fetch();
  $stmt2->free_result();
  $stmt2->close();
  return $current !== '' ? $current : $walletId;
}

function ensure_transactions_table(mysqli $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `asset` VARCHAR(20) NOT NULL DEFAULT '',
  `amount` DECIMAL(24,8) NOT NULL DEFAULT 0.00000000,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `meta` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

function ensure_positions_table(mysqli $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `positions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `asset` VARCHAR(20) NOT NULL,
  `qty` DECIMAL(24,12) NOT NULL DEFAULT 0.000000000000,
  `avg_entry_price_usd` DECIMAL(24,12) NOT NULL DEFAULT 0.000000000000,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pos_user_asset` (`user_id`, `asset`),
  KEY `idx_pos_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

function user_price_usdt(string $asset, ?mysqli $db = null): float {
  $asset = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $asset) ?? '');
  if ($asset === '' || $asset === 'USDT') {
    return 1.0;
  }
  if ($db !== null) {
    try {
      market_control_ensure_schema($db);
      $admin = market_control_lookup_price($db, $asset);
      if ($admin !== null && $admin > 0) {
        return $admin;
      }
    } catch (Throwable $e) {
      // fall through to Binance
    }
  }

  $symbol = $asset . 'USDT';

  $ctx = stream_context_create([
    'http' => [
      'timeout' => 3,
      'header' => "User-Agent: Crypterra/1.0\r\nAccept: application/json\r\n",
    ],
  ]);
  $json = @file_get_contents('https://api.binance.com/api/v3/ticker/price?symbol=' . rawurlencode($symbol), false, $ctx);
  if (!is_string($json) || $json === '') {
    throw new RuntimeException('Price feed unavailable.');
  }
  $data = json_decode($json, true);
  if (!is_array($data) || !isset($data['price'])) {
    throw new RuntimeException('Invalid price response.');
  }
  $price = (float)$data['price'];
  if (!is_finite($price) || $price <= 0) {
    throw new RuntimeException('Invalid price.');
  }
  return $price;
}

function user_fetch_positions(mysqli $db, int $userId): array {
  $stmt = $db->prepare('SELECT asset, qty, avg_entry_price_usd FROM positions WHERE user_id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();
  $asset = '';
  $qty = $avg = '0';
  $stmt->bind_result($asset, $qty, $avg);
  $rows = [];
  while ($stmt->fetch()) {
    $rows[] = [
      'asset' => (string)$asset,
      'qty' => (string)$qty,
      'avg_entry_price_usd' => (string)$avg,
    ];
  }
  $stmt->free_result();
  $stmt->close();
  return $rows;
}

function user_sum_realized_pl(mysqli $db, int $userId, ?int $sinceTs = null): float {
  // Reads realized_pl_usd from transactions.meta (best-effort, 0 if missing).
  $where = 'user_id = ? AND type = ?';
  $params = [$userId, 'trade'];
  $types = 'is';

  if ($sinceTs !== null) {
    $where .= ' AND created_at >= FROM_UNIXTIME(?)';
    $params[] = $sinceTs;
    $types .= 'i';
  }

  $sql = "SELECT meta FROM transactions WHERE {$where}";
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return 0.0;
  }
  // bind dynamically
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $stmt->store_result();
  $meta = null;
  $stmt->bind_result($meta);
  $sum = 0.0;
  while ($stmt->fetch()) {
    if (!is_string($meta) || $meta === '') {
      continue;
    }
    $m = json_decode($meta, true);
    if (is_array($m) && isset($m['realized_pl_usd'])) {
      $val = (float)$m['realized_pl_usd'];
      if (is_finite($val)) {
        $sum += $val;
      }
    }
  }
  $stmt->free_result();
  $stmt->close();
  return $sum;
}

function user_fetch_recent_transactions(mysqli $db, int $userId, int $limit = 6): array {
  $limit = max(1, min(20, $limit));
  $stmt = $db->prepare(
    'SELECT id, type, asset, amount, status, meta, created_at
     FROM transactions
     WHERE user_id = ? AND type NOT LIKE \'admin_%\'
     ORDER BY created_at DESC, id DESC
     LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();

  $id = 0;
  $type = $asset = $status = $createdAt = '';
  $meta = '';
  $amount = '0.00000000';
  $stmt->bind_result($id, $type, $asset, $amount, $status, $meta, $createdAt);

  $rows = [];
  while ($stmt->fetch()) {
    $side = '';
    if (is_string($meta) && $meta !== '') {
      $m = json_decode($meta, true);
      if (is_array($m) && isset($m['side']) && is_string($m['side'])) {
        $side = $m['side'];
      }
    }
    $rows[] = [
      'id' => (int)$id,
      'type' => (string)$type,
      'asset' => (string)$asset,
      'amount' => (string)$amount,
      'status' => (string)$status,
      'side' => (string)$side,
      'created_at' => (string)$createdAt,
    ];
  }

  $stmt->free_result();
  $stmt->close();
  return $rows;
}

/**
 * Recent on-chain deposit rows plus admin credits to the trading account (from Admin → Market Control).
 *
 * `amount` is USD (matches transactions.amount). When present, `crypto_amount` is the on-chain amount from meta.
 *
 * @return list<array{id:int, type:string, asset:string, amount:string, crypto_amount:string, status:string, side:string, created_at:string}>
 */
function user_fetch_recent_deposits(mysqli $db, int $userId, int $limit = 10): array {
  $limit = max(1, min(50, $limit));
  $depositTypes = ['deposit', 'admin_trade_credit'];
  $placeholders = implode(',', array_fill(0, count($depositTypes), '?'));
  $stmt = $db->prepare(
    'SELECT id, type, asset, amount, status, meta, created_at
     FROM transactions
     WHERE user_id = ? AND type IN (' . $placeholders . ')
     ORDER BY created_at DESC, id DESC
     LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $bindTypes = 'i' . str_repeat('s', count($depositTypes));
  $params = array_merge([$userId], $depositTypes);
  if (!user_stmt_bind_array($stmt, $bindTypes, $params)) {
    $stmt->close();
    throw new RuntimeException('bind failed');
  }
  $stmt->execute();
  $stmt->store_result();

  $id = 0;
  $type = $asset = $status = $createdAt = '';
  $meta = '';
  $amount = '0.00000000';
  $stmt->bind_result($id, $type, $asset, $amount, $status, $meta, $createdAt);

  $rows = [];
  while ($stmt->fetch()) {
    $side = '';
    $cryptoOut = '';
    if (is_string($meta) && $meta !== '') {
      $m = json_decode($meta, true);
      if (is_array($m)) {
        if (isset($m['side']) && is_string($m['side'])) {
          $side = $m['side'];
        }
        if (isset($m['crypto_amount']) && is_numeric($m['crypto_amount'])) {
          $cryptoOut = (string)$m['crypto_amount'];
        }
      }
    }
    $rows[] = [
      'id' => (int)$id,
      'type' => (string)$type,
      'asset' => (string)$asset,
      'amount' => (string)$amount,
      'crypto_amount' => $cryptoOut,
      'status' => (string)$status,
      'side' => (string)$side,
      'created_at' => (string)$createdAt,
    ];
  }

  $stmt->free_result();
  $stmt->close();
  return $rows;
}

/**
 * Incoming / credited transactions for Receive page: deposits, admin funding, admin credit, receive-page credits.
 *
 * @return list<array{id:int,type:string,title:string,subtitle:string,amount_display:string,asset_label:string,status:string,status_class:string,icon:string,created_at:string}>
 */
function user_fetch_incoming_transactions(mysqli $db, int $userId, int $limit = 30): array {
  $limit = max(1, min(50, $limit));
  $types = ['deposit', 'admin_manual_fund', 'admin_credit', 'receive'];
  $placeholders = implode(',', array_fill(0, count($types), '?'));
  $sql =
    'SELECT id, type, asset, amount, status, meta, created_at
     FROM transactions
     WHERE user_id = ? AND type IN (' . $placeholders . ')
     ORDER BY created_at DESC, id DESC
     LIMIT ' . (int)$limit;
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $bindTypes = 'i' . str_repeat('s', count($types));
  $params = array_merge([$userId], $types);
  if (!user_stmt_bind_array($stmt, $bindTypes, $params)) {
    $stmt->close();
    return [];
  }
  $stmt->execute();
  $stmt->store_result();
  $id = 0;
  $type = $asset = $status = $createdAt = '';
  $metaRaw = '';
  $amount = '0';
  $stmt->bind_result($id, $type, $asset, $amount, $status, $metaRaw, $createdAt);

  $shortAddr = static function (string $addr): string {
    $a = trim($addr);
    if ($a === '') {
      return '';
    }
    if (strlen($a) <= 18) {
      return $a;
    }
    return substr($a, 0, 10) . '…' . substr($a, -6);
  };

  $assetLabel = static function (string $raw): string {
    $u = strtoupper(trim($raw));
    if ($u === '' || $u === 'USD') {
      return 'USD';
    }
    if ($u === 'BTC' || str_contains($u, 'BTC')) {
      return 'BTC';
    }
    if ($u === 'ETH' || ($u !== 'USDT' && str_contains($u, 'ETH'))) {
      return 'ETH';
    }
    if (str_contains($u, 'USDT')) {
      if (str_contains($u, 'TRC') || str_contains($u, 'TRON')) {
        return 'USDT (TRC20)';
      }
      return 'USDT (ERC20)';
    }
    if (str_contains($u, 'TRX') || $u === 'TRON') {
      return 'TRX';
    }
    if (str_contains($u, 'SOL')) {
      return 'SOL';
    }
    if (str_contains($u, 'MATIC') || str_contains($u, 'POL')) {
      return 'MATIC';
    }
    return $u;
  };

  $iconFor = static function (string $raw): string {
    $u = strtoupper($raw);
    if (str_contains($u, 'BTC')) {
      return 'btc';
    }
    if (str_contains($u, 'ETH') && !str_contains($u, 'USDT')) {
      return 'eth';
    }
    if (str_contains($u, 'USDT') || str_contains($u, 'TRC') || str_contains($u, 'DAI')) {
      return 'usdt';
    }
    if (str_contains($u, 'SOL')) {
      return 'sol';
    }
    if (str_contains($u, 'TRX') || $u === 'TRON') {
      return 'tron';
    }
    return 'generic';
  };

  $fmtAmt = static function (string $amtStr): string {
    $f = (float)$amtStr;
    if (!is_finite($f)) {
      return '0';
    }
    $s = rtrim(rtrim(number_format($f, 8, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
  };

  $statusClass = static function (string $st): string {
    $s = strtolower(trim($st));
    if ($s === 'completed' || $s === 'confirmed') {
      return 'status-completed';
    }
    if ($s === 'failed' || $s === 'rejected' || $s === 'cancelled') {
      return 'status-failed';
    }
    return 'status-pending';
  };

  $statusLabel = static function (string $st): string {
    $s = strtolower(trim($st));
    if ($s === 'completed' || $s === 'confirmed') {
      return 'Completed';
    }
    if ($s === 'failed') {
      return 'Failed';
    }
    if ($s === 'rejected' || $s === 'cancelled') {
      return 'Declined';
    }
    if ($s === 'pending') {
      return 'Pending';
    }
    return ucfirst($s !== '' ? $s : 'Pending');
  };

  $rows = [];
  while ($stmt->fetch()) {
    $meta = [];
    if (is_string($metaRaw) && $metaRaw !== '') {
      $d = json_decode($metaRaw, true);
      $meta = is_array($d) ? $d : [];
    }
    $typeLower = strtolower((string)$type);
    // Old Receive page demo simulator (removed) posted wallet_api receive with meta.source = receive_page.
    if (
      $typeLower === 'receive'
      && isset($meta['source'])
      && $meta['source'] === 'receive_page'
    ) {
      continue;
    }
    $title = 'Incoming';
    if ($typeLower === 'deposit') {
      $title = 'Deposit';
    } elseif ($typeLower === 'admin_manual_fund') {
      $title = 'Wallet funding';
    } elseif ($typeLower === 'admin_credit') {
      $title = 'Admin credit';
    } elseif ($typeLower === 'receive') {
      $title = 'Incoming transfer';
    }

    $subtitle = '—';
    if (!empty($meta['from_address']) && is_string($meta['from_address'])) {
      $subtitle = 'From: ' . $shortAddr($meta['from_address']);
    } elseif (!empty($meta['reason']) && is_string($meta['reason'])) {
      $r = trim($meta['reason']);
      if (strlen($r) > 90) {
        $r = substr($r, 0, 87) . '…';
      }
      $subtitle = $r !== '' ? $r : 'Platform';
    } elseif ($typeLower === 'admin_manual_fund' || $typeLower === 'admin_credit') {
      $subtitle = 'Platform';
    }

    $al = $assetLabel((string)$asset);
    $ad = $fmtAmt((string)$amount);

    $rows[] = [
      'id' => (int)$id,
      'type' => (string)$type,
      'title' => $title,
      'subtitle' => $subtitle,
      'amount_display' => '+' . $ad . ' ' . $al,
      'asset_label' => $al,
      'status' => $statusLabel((string)$status),
      'status_class' => $statusClass((string)$status),
      'icon' => $iconFor((string)$asset),
      'created_at' => (string)$createdAt,
    ];
  }

  $stmt->free_result();
  $stmt->close();
  return $rows;
}

/**
 * Recent withdrawal transactions only (type = withdrawal).
 *
 * `amount` is USD (matches transactions.amount). When present, `crypto_amount` is the on-chain amount from meta.
 *
 * @return list<array{id:int, type:string, asset:string, amount:string, crypto_amount:string, status:string, created_at:string, to_address:string, network:string, fee_usd:string}>
 */
function user_fetch_recent_withdrawals(mysqli $db, int $userId, int $limit = 10): array {
  $limit = max(1, min(50, $limit));
  $wtype = 'withdrawal';
  $stmt = $db->prepare(
    'SELECT id, type, asset, amount, status, meta, created_at
     FROM transactions
     WHERE user_id = ? AND type = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('is', $userId, $wtype);
  $stmt->execute();
  $stmt->store_result();

  $id = 0;
  $type = $asset = $status = $createdAt = '';
  $meta = '';
  $amount = '0.00000000';
  $stmt->bind_result($id, $type, $asset, $amount, $status, $meta, $createdAt);

  $rows = [];
  while ($stmt->fetch()) {
    $cryptoOut = '';
    $toAddr = '';
    $network = '';
    $feeUsd = '';
    if (is_string($meta) && $meta !== '') {
      $m = json_decode($meta, true);
      if (is_array($m)) {
        if (isset($m['crypto_amount']) && is_numeric($m['crypto_amount'])) {
          $cryptoOut = (string)$m['crypto_amount'];
        }
        if (isset($m['to_address']) && is_string($m['to_address'])) {
          $toAddr = $m['to_address'];
        }
        if (isset($m['network']) && is_string($m['network'])) {
          $network = $m['network'];
        }
        if (isset($m['fee_usd']) && is_numeric($m['fee_usd'])) {
          $feeUsd = (string)$m['fee_usd'];
        }
      }
    }
    $rows[] = [
      'id' => (int)$id,
      'type' => (string)$type,
      'asset' => (string)$asset,
      'amount' => (string)$amount,
      'crypto_amount' => $cryptoOut,
      'status' => (string)$status,
      'created_at' => (string)$createdAt,
      'to_address' => (string)$toAddr,
      'network' => (string)$network,
      'fee_usd' => (string)$feeUsd,
    ];
  }

  $stmt->free_result();
  $stmt->close();
  return $rows;
}

/**
 * Create a pending deposit request (shown in user dashboard / admin until approved).
 *
 * @return array{ok:bool, id?:int, error?:string}
 */
function user_create_pending_deposit(
  mysqli $db,
  int $userId,
  string $asset,
  float $cryptoAmount,
  float $amountUsd,
  string $network,
  ?string $depositAddress = null
): array {
  ensure_transactions_table($db);
  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'auth'];
  }
  $asset = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $asset) ?? '');
  if ($asset === '') {
    return ['ok' => false, 'error' => 'asset'];
  }
  if (!is_finite($cryptoAmount) || $cryptoAmount <= 0) {
    return ['ok' => false, 'error' => 'crypto_amount'];
  }
  if (!is_finite($amountUsd) || $amountUsd <= 0) {
    return ['ok' => false, 'error' => 'amount_usd'];
  }
  $network = trim($network);
  if ($network === '') {
    return ['ok' => false, 'error' => 'network'];
  }
  $addr = $depositAddress !== null ? trim($depositAddress) : '';

  $meta = [
    'network' => $network,
    'crypto_amount' => $cryptoAmount,
    'amount_usd' => $amountUsd,
    'wallet_address' => $addr,
    'source' => 'deposit_page',
  ];
  $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  $type = 'deposit';
  $status = 'pending';
  $amountDb = number_format($amountUsd, 8, '.', '');

  $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
  if (!$ins) {
    return ['ok' => false, 'error' => 'db'];
  }
  $ins->bind_param('isssss', $userId, $type, $asset, $amountDb, $status, $metaJson);
  $ok = @$ins->execute();
  $newId = (int)$ins->insert_id;
  $ins->close();
  if (!$ok || $newId <= 0) {
    return ['ok' => false, 'error' => 'insert'];
  }
  return ['ok' => true, 'id' => $newId];
}

/**
 * Create a pending withdrawal request (requires admin approval).
 *
 * @return array{ok:bool, id?:int, error?:string}
 */
function user_create_pending_withdrawal(
  mysqli $db,
  string $schema,
  int $userId,
  string $asset,
  float $cryptoAmount,
  float $amountUsd,
  string $network,
  string $toAddress,
  float $feeUsd = 0.0,
  ?string $clientIp = null,
  ?string $userAgent = null,
  ?string $countryIso = null,
  ?string $countryName = null
): array {
  ensure_transactions_table($db);
  ensure_asset_portfolio_schema($db);
  ensure_network_fee_rules_schema($db);

  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'auth'];
  }
  $assetId = strtolower(trim($asset));
  if ($assetId === '' || !preg_match('/^[a-z0-9_]+$/', $assetId)) {
    return ['ok' => false, 'error' => 'asset'];
  }
  $toAddress = trim($toAddress);
  if ($toAddress === '') {
    return ['ok' => false, 'error' => 'address'];
  }
  if (!is_finite($cryptoAmount) || $cryptoAmount <= 0) {
    return ['ok' => false, 'error' => 'crypto_amount'];
  }
  // Ignore client-sent USD/fee fields; compute server-side from fee rules + live price.

  $quote = user_quote_network_fees($db, $userId, $assetId, $network, $cryptoAmount);
  if (!($quote['ok'] ?? false)) {
    return ['ok' => false, 'error' => (string)($quote['error'] ?? 'fee')];
  }
  $netCode = (string)($quote['network_code'] ?? '');
  $gasAssetId = (string)($quote['gas_asset_id'] ?? '');
  $gasFeeAmt = (float)($quote['gas_fee_amount'] ?? 0.0);
  $assetFeeAmt = (float)($quote['asset_fee_amount'] ?? 0.0);
  $reserve = (float)($quote['min_gas_reserve'] ?? 0.0);

  // Estimate USD value (for admin dashboards) using cached market price.
  $usdPrice = 0.0;
  $stmtPx = $db->prepare('SELECT coingecko_id FROM asset_catalog WHERE asset_id = ? LIMIT 1');
  if ($stmtPx) {
    $stmtPx->bind_param('s', $assetId);
    $stmtPx->execute();
    $stmtPx->store_result();
    $cg = '';
    $stmtPx->bind_result($cg);
    $stmtPx->fetch();
    $stmtPx->free_result();
    $stmtPx->close();
    if ($cg !== '') {
      $quotes = asset_market_get_quotes($db, [$cg], 60);
      if (isset($quotes[$cg])) {
        $usdPrice = (float)($quotes[$cg]['price_usd'] ?? 0.0);
      }
    }
  }
  $usdValue = $usdPrice > 0 ? ($cryptoAmount * $usdPrice) : 0.0;

  // Reserve/debit balances immediately; withdrawal stays pending for admin approval.
  $db->begin_transaction();
  try {
    // Lock balances for withdrawal asset and gas asset.
    $sel = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
    if (!$sel) {
      throw new RuntimeException('db');
    }
    $sel->bind_param('is', $userId, $assetId);
    $sel->execute();
    $sel->store_result();
    $balStr = '0';
    $sel->bind_result($balStr);
    $sel->fetch();
    $sel->free_result();
    $sel->close();
    $assetBal = (float)$balStr;

    $gasBal = 0.0;
    if ($gasAssetId !== '' && $gasAssetId !== $assetId) {
      $selG = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
      if (!$selG) throw new RuntimeException('db');
      $selG->bind_param('is', $userId, $gasAssetId);
      $selG->execute();
      $selG->store_result();
      $gStr = '0';
      $selG->bind_result($gStr);
      $selG->fetch();
      $selG->free_result();
      $selG->close();
      $gasBal = (float)$gStr;
    } else {
      $gasBal = $assetBal;
    }

    $totalAssetDebit = $cryptoAmount + $assetFeeAmt;
    $requiredGas = $gasFeeAmt + $reserve;

    if ($gasAssetId === $assetId) {
      if ($assetBal + 1e-12 < ($totalAssetDebit + $requiredGas)) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient_gas'];
      }
      $newAssetBal = $assetBal - $totalAssetDebit - $gasFeeAmt;
      if ($newAssetBal + 1e-12 < $reserve) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient_gas'];
      }
      $newAssetStr = number_format($newAssetBal, 18, '.', '');
      $upA = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
      if (!$upA) throw new RuntimeException('db');
      $upA->bind_param('iss', $userId, $assetId, $newAssetStr);
      $upA->execute();
      $upA->close();
    } else {
      if ($assetBal + 1e-12 < $totalAssetDebit) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient_balance'];
      }
      if ($gasBal + 1e-12 < $requiredGas) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient_gas'];
      }
      $newAssetBal = $assetBal - $totalAssetDebit;
      $newGasBal = $gasBal - $gasFeeAmt;
      if ($newGasBal + 1e-12 < $reserve) {
        $db->rollback();
        return ['ok' => false, 'error' => 'insufficient_gas'];
      }
      $newAssetStr = number_format($newAssetBal, 18, '.', '');
      $newGasStr = number_format($newGasBal, 18, '.', '');
      $upA = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
      if (!$upA) throw new RuntimeException('db');
      $upA->bind_param('iss', $userId, $assetId, $newAssetStr);
      $upA->execute();
      $upA->close();
      $upG = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
      if (!$upG) throw new RuntimeException('db');
      $upG->bind_param('iss', $userId, $gasAssetId, $newGasStr);
      $upG->execute();
      $upG->close();
    }

    // Ledger entries for reserved deductions.
    $metaBase = [
      'kind' => 'withdrawal_request',
      'network' => $netCode,
      'to_address' => $toAddress,
      'gas_asset_id' => $gasAssetId,
      'gas_fee_amount' => $gasFeeAmt,
      'asset_fee_amount' => $assetFeeAmt,
      'min_gas_reserve' => $reserve,
      'at' => date('c'),
    ];
    $metaJsonLed = json_encode($metaBase, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $amtDb = number_format($totalAssetDebit, 18, '.', '');

    // Asset debit (amount + platform fee pct if set).
    $afterAsset = ($gasAssetId === $assetId)
      ? ($assetBal - $totalAssetDebit - $gasFeeAmt)
      : ($assetBal - $totalAssetDebit);
    $afterAssetDb = number_format($afterAsset, 18, '.', '');
    $ledA = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'debit\', ?, ?, ?)');
    if ($ledA) {
      $ledA->bind_param('issss', $userId, $assetId, $amtDb, $afterAssetDb, $metaJsonLed);
      $ledA->execute();
      $ledA->close();
    }
    // Gas debit.
    if ($gasFeeAmt > 0 && $gasAssetId !== '') {
      $afterGas = ($gasAssetId === $assetId) ? $afterAsset : ($gasBal - $gasFeeAmt);
      $afterGasDb = number_format($afterGas, 18, '.', '');
      $gasAmtDb = number_format($gasFeeAmt, 18, '.', '');
      $metaGas = $metaBase + ['kind' => 'gas_fee'];
      $metaGasJson = json_encode($metaGas, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
      $ledG = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'debit\', ?, ?, ?)');
      if ($ledG) {
        $ledG->bind_param('issss', $userId, $gasAssetId, $gasAmtDb, $afterGasDb, $metaGasJson);
        $ledG->execute();
        $ledG->close();
      }
    }

    $meta = [
      'network' => $netCode,
      'to_address' => $toAddress,
      'asset_id' => $assetId,
      'crypto_amount' => $cryptoAmount,
      'amount_usd' => $usdValue,
      'gas_asset_id' => $gasAssetId,
      'gas_fee_amount' => $gasFeeAmt,
      'asset_fee_amount' => $assetFeeAmt,
      'min_gas_reserve' => $reserve,
      'source' => 'withdraw_page',
      'reserved' => true,
      'reserved_asset_amount' => $totalAssetDebit,
      'reserved_gas_amount' => $gasFeeAmt,
    ];
  $ip = $clientIp !== null ? trim($clientIp) : '';
  $ua = $userAgent !== null ? trim($userAgent) : '';
  if ($ip !== '') {
    $meta['ip'] = $ip;
  }
  if ($ua !== '') {
    $meta['ua'] = $ua;
  }
  $cIso = $countryIso !== null ? strtoupper(trim($countryIso)) : '';
  $cName = $countryName !== null ? trim($countryName) : '';
  if ($cIso !== '') {
    $meta['country_iso'] = $cIso;
  }
  if ($cName !== '') {
    $meta['country_name'] = $cName;
  }
  $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  $type = 'withdrawal';
  $status = 'pending';
  $amountDb = number_format($usdValue, 8, '.', '');

    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
      $db->rollback();
      return ['ok' => false, 'error' => 'db'];
    }
    $assetCol = strtoupper($assetId);
    $ins->bind_param('isssss', $userId, $type, $assetCol, $amountDb, $status, $metaJson);
    $ok = @$ins->execute();
    $newId = (int)$ins->insert_id;
    $ins->close();
    if (!$ok || $newId <= 0) {
      $db->rollback();
      return ['ok' => false, 'error' => 'insert'];
    }
    $db->commit();
    return ['ok' => true, 'id' => $newId];
  } catch (Throwable) {
    $db->rollback();
    return ['ok' => false, 'error' => 'exception'];
  }
}

function user_send_fee_crypto(string $asset): float {
  return match (strtoupper($asset)) {
    'BTC' => 0.0002,
    'ETH' => 0.002,
    'USDT' => 5.0,
    'SOL' => 0.0005,
    default => 0.0,
  };
}

function user_send_min_crypto(string $asset): float {
  return match (strtoupper($asset)) {
    'BTC' => 0.001,
    'ETH' => 0.01,
    'USDT' => 10.0,
    'SOL' => 0.1,
    default => 0.0,
  };
}

function user_send_default_network(string $asset): string {
  return match (strtoupper($asset)) {
    'BTC' => 'BTC',
    'ETH' => 'ERC20',
    'USDT' => 'ERC20',
    'SOL' => 'SOL',
    default => strtoupper($asset),
  };
}

/**
 * Outbound crypto send from Send page: debits available_balance_usd (same pool as Wallet / Withdraw).
 *
 * @return array{ok:bool, id?:int, error?:string, available_balance_usd?:string}
 */
function user_create_send_transfer(
  mysqli $db,
  string $schema,
  int $userId,
  string $asset,
  float $cryptoAmount,
  string $toAddress,
  ?string $network = null
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);

  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'auth'];
  }
  $asset = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $asset) ?? '');
  if ($asset === '' || !in_array($asset, ['BTC', 'ETH', 'USDT', 'SOL'], true)) {
    return ['ok' => false, 'error' => 'asset'];
  }
  $toAddress = trim($toAddress);
  if ($toAddress === '') {
    return ['ok' => false, 'error' => 'address'];
  }
  if (!is_finite($cryptoAmount) || $cryptoAmount <= 0) {
    return ['ok' => false, 'error' => 'crypto_amount'];
  }
  $min = user_send_min_crypto($asset);
  if ($cryptoAmount + 1e-12 < $min) {
    return ['ok' => false, 'error' => 'below_minimum'];
  }

  $feeCrypto = user_send_fee_crypto($asset);
  if ($feeCrypto <= 0) {
    return ['ok' => false, 'error' => 'fee'];
  }

  if ($asset === 'USDT') {
    $amountUsd = $cryptoAmount;
    $feeUsd = $feeCrypto;
  } else {
    try {
      $price = user_price_usdt($asset, $db);
    } catch (Throwable) {
      return ['ok' => false, 'error' => 'price_unavailable'];
    }
    $amountUsd = $cryptoAmount * $price;
    $feeUsd = $feeCrypto * $price;
  }

  if (!is_finite($amountUsd) || !is_finite($feeUsd) || $amountUsd <= 0 || $feeUsd < 0) {
    return ['ok' => false, 'error' => 'amount'];
  }

  $totalDebit = $amountUsd + $feeUsd;
  $net = trim($network ?? '');
  if ($net === '') {
    $net = user_send_default_network($asset);
  }

  $db->begin_transaction();
  try {
    $balStmt = $db->prepare('SELECT available_balance_usd, spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$balStmt) {
      $db->rollback();
      return ['ok' => false, 'error' => 'db'];
    }
    $balStmt->bind_param('i', $userId);
    $balStmt->execute();
    $balStmt->store_result();
    $bal = '0.00';
    $spotBal = '0.00';
    $balStmt->bind_result($bal, $spotBal);
    $found = $balStmt->fetch();
    $balStmt->free_result();
    $balStmt->close();
    if (!$found) {
      $db->rollback();
      return ['ok' => false, 'error' => 'user_not_found'];
    }
    $avail = (float)$bal;
    $spot = (float)$spotBal;
    if ($avail + 1e-9 < $totalDebit) {
      $db->rollback();
      return ['ok' => false, 'error' => 'insufficient_balance'];
    }
    $newAvail = $avail - $totalDebit;
    $sStr = number_format($spot, 2, '.', '');
    $aStr = number_format($newAvail, 2, '.', '');
    $pvStr = number_format($newAvail + $spot, 2, '.', '');
    $up = $db->prepare('UPDATE users SET available_balance_usd = ?, spot_balance_usd = ?, portfolio_value_usd = ? WHERE id = ?');
    if (!$up) {
      $db->rollback();
      return ['ok' => false, 'error' => 'db'];
    }
    $up->bind_param('sssi', $aStr, $sStr, $pvStr, $userId);
    $up->execute();
    $up->close();

    $meta = [
      'network' => $net,
      'to_address' => $toAddress,
      'crypto_amount' => $cryptoAmount,
      'amount_usd' => $amountUsd,
      'fee_usd' => $feeUsd,
      'fee_crypto' => $feeCrypto,
      'source' => 'send_page',
      'reserved' => true,
      'reserved_total_usd' => $totalDebit,
    ];
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $type = 'send';
    $status = 'pending';
    $amountDb = number_format($amountUsd, 8, '.', '');

    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
      $db->rollback();
      return ['ok' => false, 'error' => 'db'];
    }
    $ins->bind_param('isssss', $userId, $type, $asset, $amountDb, $status, $metaJson);
    $ok = @$ins->execute();
    $newId = (int)$ins->insert_id;
    $ins->close();
    if (!$ok || $newId <= 0) {
      $db->rollback();
      return ['ok' => false, 'error' => 'insert'];
    }
    $db->commit();
    return ['ok' => true, 'id' => $newId, 'available_balance_usd' => $aStr];
  } catch (Throwable) {
    $db->rollback();
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * Recent send transactions (type = send).
 *
 * @return list<array{id:int, type:string, asset:string, amount:string, crypto_amount:string, status:string, created_at:string, to_address:string, network:string, fee_usd:string}>
 */
function user_fetch_recent_sends(mysqli $db, int $userId, int $limit = 15): array {
  $limit = max(1, min(50, $limit));
  $stype = 'send';
  $stmt = $db->prepare(
    'SELECT id, type, asset, amount, status, meta, created_at
     FROM transactions
     WHERE user_id = ? AND type = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('is', $userId, $stype);
  $stmt->execute();
  $stmt->store_result();

  $id = 0;
  $type = $asset = $status = $createdAt = '';
  $meta = '';
  $amount = '0.00000000';
  $stmt->bind_result($id, $type, $asset, $amount, $status, $meta, $createdAt);

  $rows = [];
  while ($stmt->fetch()) {
    $cryptoOut = '';
    $toAddr = '';
    $network = '';
    $feeUsd = '';
    if (is_string($meta) && $meta !== '') {
      $m = json_decode($meta, true);
      if (is_array($m)) {
        if (isset($m['crypto_amount']) && is_numeric($m['crypto_amount'])) {
          $cryptoOut = (string)$m['crypto_amount'];
        }
        if (isset($m['to_address']) && is_string($m['to_address'])) {
          $toAddr = $m['to_address'];
        }
        if (isset($m['network']) && is_string($m['network'])) {
          $network = $m['network'];
        }
        if (isset($m['fee_usd']) && is_numeric($m['fee_usd'])) {
          $feeUsd = (string)$m['fee_usd'];
        }
      }
    }
    $rows[] = [
      'id' => (int)$id,
      'type' => (string)$type,
      'asset' => (string)$asset,
      'amount' => (string)$amount,
      'crypto_amount' => $cryptoOut,
      'status' => (string)$status,
      'created_at' => (string)$createdAt,
      'to_address' => (string)$toAddr,
      'network' => (string)$network,
      'fee_usd' => (string)$feeUsd,
    ];
  }

  $stmt->free_result();
  $stmt->close();
  return $rows;
}

function user_fetch_trade_orders(mysqli $db, int $userId, int $limit = 50): array {
  $limit = max(1, min(200, $limit));
  $stmt = $db->prepare(
    'SELECT id, asset, amount, status, meta, created_at
     FROM transactions
     WHERE user_id = ? AND type = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $type = 'trade';
  $stmt->bind_param('is', $userId, $type);
  $stmt->execute();
  $stmt->store_result();

  $id = 0;
  $asset = $status = $meta = $createdAt = '';
  $amount = '0.00000000';
  $stmt->bind_result($id, $asset, $amount, $status, $meta, $createdAt);

  $rows = [];
  while ($stmt->fetch()) {
    $m = is_string($meta) && $meta !== '' ? json_decode($meta, true) : null;
    $side = (is_array($m) && isset($m['side']) && is_string($m['side'])) ? $m['side'] : '';
    $price = (is_array($m) && isset($m['price_usd'])) ? (float)$m['price_usd'] : 0.0;
    $qty = (is_array($m) && isset($m['qty'])) ? (float)$m['qty'] : 0.0;

    // Map status into "open"/"closed" for UI filters.
    $uiStatus = (strtolower((string)$status) === 'pending') ? 'open' : 'closed';

    $rows[] = [
      'id' => (int)$id,
      'pair' => strtoupper((string)$asset) . '/USDT',
      'side' => (string)$side,
      'qty' => number_format($qty, 8, '.', ''),
      'price_usd' => number_format($price, 2, '.', ''),
      'status' => $uiStatus,
      'created_at' => (string)$createdAt,
    ];
  }

  $stmt->free_result();
  $stmt->close();
  return $rows;
}

function user_csrf_token(): string {
  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function user_verify_csrf(?string $token): bool {
  if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    return false;
  }
  if (!is_string($token) || $token === '') {
    return false;
  }
  return hash_equals($_SESSION['csrf_token'], $token);
}

function user_execute_trade(
  mysqli $db,
  string $schema,
  int $userId,
  string $side,
  string $asset,
  string $network,
  float $amountUsd
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);
  ensure_positions_table($db);
  market_control_ensure_schema($db);

  $side = strtolower(trim($side));
  if ($side !== 'buy' && $side !== 'sell') {
    throw new InvalidArgumentException('Invalid side.');
  }
  $asset = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $asset) ?? '');
  if ($asset === '') {
    throw new InvalidArgumentException('Invalid asset.');
  }
  $network = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $network) ?? '');
  if ($network === '') {
    throw new InvalidArgumentException('Invalid network.');
  }
  if (!is_finite($amountUsd) || $amountUsd <= 0) {
    throw new InvalidArgumentException('Invalid amount.');
  }

  if (!market_control_trading_enabled($db)) {
    return ['ok' => false, 'error' => 'Trading is temporarily paused.'];
  }
  if (!market_control_asset_tradable($db, $asset)) {
    return ['ok' => false, 'error' => 'This asset is not available for trading.'];
  }

  $catalogId = trade_infer_catalog_asset_id($asset, $network);
  $networkFee = user_trade_network_fee_usd($db, $userId, $catalogId, $network);
  $feePct = market_control_get_fee_pct($db, $asset);
  $tradingFee = $amountUsd * ($feePct / 100.0);
  $totalFee = $networkFee + $tradingFee;

  $price = user_price_usdt($asset, $db);
  $qty = $amountUsd / $price;

  $db->begin_transaction();
  try {
    // Lock user row
    $stmt = $db->prepare('SELECT available_balance_usd, spot_balance_usd, open_positions FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->store_result();
    $bal = '0.00';
    $spotBal = '0.00';
    $openPos = 0;
    $stmt->bind_result($bal, $spotBal, $openPos);
    $found = $stmt->fetch();
    $stmt->free_result();
    $stmt->close();
    if (!$found) {
      throw new RuntimeException('User not found.');
    }
    $balance = (float)$bal;
    $spotBalance = (float)$spotBal;
    $openPos = (int)$openPos;

    // Lock or create position row.
    $posQty = 0.0;
    $posAvg = 0.0;
    $posStmt = $db->prepare('SELECT qty, avg_entry_price_usd FROM positions WHERE user_id = ? AND asset = ? LIMIT 1 FOR UPDATE');
    if ($posStmt) {
      $posStmt->bind_param('is', $userId, $asset);
      $posStmt->execute();
      $posStmt->store_result();
      $q = $a = '0';
      $posStmt->bind_result($q, $a);
      if ($posStmt->fetch()) {
        $posQty = (float)$q;
        $posAvg = (float)$a;
      }
      $posStmt->free_result();
      $posStmt->close();
    }

    $realizedPl = 0.0;

    if ($side === 'buy') {
      $debit = $amountUsd + $totalFee;
      if ($debit > $spotBalance + 1e-9) {
        $db->rollback();
        return [
          'ok' => false,
          'error' => 'Insufficient trading balance.',
          'spot_balance_usd' => number_format($spotBalance, 2, '.', ''),
          'available_balance_usd' => number_format($balance, 2, '.', ''),
        ];
      }
      $newSpot = $spotBalance - $debit;
      $newOpenPos = $openPos + 1;

      // Update avg entry: weighted average.
      $newPosQty = $posQty + $qty;
      $newPosAvg = ($newPosQty > 0) ? (($posQty * $posAvg) + ($qty * $price)) / $newPosQty : $price;

      $posUp = $db->prepare(
        'INSERT INTO positions (user_id, asset, qty, avg_entry_price_usd, created_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE qty = VALUES(qty), avg_entry_price_usd = VALUES(avg_entry_price_usd)'
      );
      if ($posUp) {
        $qtyDb = number_format($newPosQty, 12, '.', '');
        $avgDb = number_format($newPosAvg, 12, '.', '');
        $posUp->bind_param('isss', $userId, $asset, $qtyDb, $avgDb);
        $posUp->execute();
        $posUp->close();
      }
    } else {
      $credit = max(0.0, $amountUsd - $totalFee);
      $newSpot = $spotBalance + $credit;
      $newOpenPos = max(0, $openPos - 1);

      if ($posQty + 1e-12 < $qty) {
        $db->rollback();
        return ['ok' => false, 'error' => 'Insufficient asset position to sell.', 'spot_balance_usd' => number_format($spotBalance, 2, '.', '')];
      }
      $realizedPl = ($price - $posAvg) * $qty;
      $newPosQty = $posQty - $qty;

      if ($newPosQty <= 1e-12) {
        $del = $db->prepare('DELETE FROM positions WHERE user_id = ? AND asset = ?');
        if ($del) {
          $del->bind_param('is', $userId, $asset);
          $del->execute();
          $del->close();
        }
      } else {
        $posUp = $db->prepare('UPDATE positions SET qty = ? WHERE user_id = ? AND asset = ?');
        if ($posUp) {
          $qtyDb = number_format($newPosQty, 12, '.', '');
          $posUp->bind_param('sis', $qtyDb, $userId, $asset);
          $posUp->execute();
          $posUp->close();
        }
      }
    }

    $amountStr = number_format($amountUsd, 2, '.', '');
    $newSpotStr = number_format($newSpot, 2, '.', '');
    $walletStr = number_format($balance, 2, '.', '');
    $portfolioStr = number_format($balance + $newSpot, 2, '.', '');

    // Trades debit/credit spot_balance_usd (trading account) only; main wallet stays unchanged.
    $up = $db->prepare('UPDATE users SET spot_balance_usd = ?, volume_24h_usd = volume_24h_usd + ?, portfolio_value_usd = ?, open_positions = ? WHERE id = ?');
    if (!$up) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $up->bind_param('sssii', $newSpotStr, $amountStr, $portfolioStr, $newOpenPos, $userId);
    $up->execute();
    $up->close();

    $meta = json_encode([
      'side' => $side,
      'network' => $network,
      'amount_usd' => $amountUsd,
      'price_usd' => $price,
      'qty' => $qty,
      'trading_fee_usd' => $tradingFee,
      'network_fee_usd' => $networkFee,
      'total_fee_usd' => $totalFee,
      'realized_pl_usd' => $realizedPl,
    ], JSON_UNESCAPED_SLASHES);

    $type = 'trade';
    $status = 'completed';
    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $amountDb = number_format($amountUsd, 8, '.', '');
    $ins->bind_param('isssss', $userId, $type, $asset, $amountDb, $status, $meta);
    $ins->execute();
    $txId = (int)$ins->insert_id;
    $ins->close();

    $db->commit();
    return [
      'ok' => true,
      'tx_id' => $txId,
      'available_balance_usd' => $walletStr,
      'spot_balance_usd' => $newSpotStr,
      'open_positions' => $newOpenPos,
      'fees_usd' => number_format($totalFee, 2, '.', ''),
      'price_usd' => number_format($price, 8, '.', ''),
      'qty' => number_format($qty, 12, '.', ''),
      'realized_pl_usd' => number_format($realizedPl, 2, '.', ''),
    ];
  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }
}

/**
 * Spot trading uses `spot_balance_usd` only. Fund it from main USD via Userprofile wallet_api transfer_to_trade
 * after deposit admin approval credits `available_balance_usd` (Admin deposit approve). Pairs/fees gated by
 * market_control_* (Admin Market Control). Withdraw.php debits the same spot pool (pending trade withdrawals).
 */
function user_place_trade_order(
  mysqli $db,
  string $schema,
  int $userId,
  string $side,
  string $asset,
  string $network,
  float $amountUsd,
  ?string $catalogAssetId = null
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);
  ensure_positions_table($db);
  market_control_ensure_schema($db);

  $side = strtolower(trim($side));
  if ($side !== 'buy' && $side !== 'sell') {
    throw new InvalidArgumentException('Invalid side.');
  }
  $asset = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $asset) ?? '');
  if ($asset === '') {
    throw new InvalidArgumentException('Invalid asset.');
  }
  $network = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $network) ?? '');
  if ($network === '') {
    throw new InvalidArgumentException('Invalid network.');
  }
  if (!is_finite($amountUsd) || $amountUsd <= 0) {
    throw new InvalidArgumentException('Invalid amount.');
  }

  if (!market_control_trading_enabled($db)) {
    return ['ok' => false, 'error' => 'Trading is temporarily paused.'];
  }
  if (!market_control_asset_tradable($db, $asset)) {
    return ['ok' => false, 'error' => 'This asset is not available for trading.'];
  }

  $catalogId = ($catalogAssetId !== null && trim($catalogAssetId) !== '')
    ? strtolower(trim($catalogAssetId))
    : trade_infer_catalog_asset_id($asset, $network);
  // Basic pre-check: for buys, ensure user has enough to cover fees at placement time.
  $networkFee = user_trade_network_fee_usd($db, $userId, $catalogId, $network);
  $feePct = market_control_get_fee_pct($db, $asset);
  $tradingFee = $amountUsd * ($feePct / 100.0);
  $totalFee = $networkFee + $tradingFee;

  if ($side === 'buy') {
    $stmt = $db->prepare('SELECT spot_balance_usd FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $stmt->store_result();
      $bal = '0.00';
      $stmt->bind_result($bal);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      if (((float)$bal) + 1e-9 < ($amountUsd + $totalFee)) {
        return ['ok' => false, 'error' => 'Insufficient trading balance to cover this buy and the network fee.'];
      }
    }
  } else {
    // Sell: need enough of the asset in open positions; fees are taken from notional (same as fill).
    try {
      $price = user_price_usdt($asset, $db);
    } catch (Throwable $e) {
      return ['ok' => false, 'error' => 'Price unavailable for this asset. Try again shortly.'];
    }
    if (!is_finite($price) || $price <= 0) {
      return ['ok' => false, 'error' => 'Price unavailable for this asset.'];
    }
    $qtyNeeded = $amountUsd / $price;
    $posQty = 0.0;
    $ps = $db->prepare('SELECT qty FROM positions WHERE user_id = ? AND asset = ? LIMIT 1');
    if ($ps) {
      $ps->bind_param('is', $userId, $asset);
      $ps->execute();
      $ps->store_result();
      $qdb = '0';
      $ps->bind_result($qdb);
      if ($ps->fetch()) {
        $posQty = (float)$qdb;
      }
      $ps->free_result();
      $ps->close();
    }
    if ($posQty + 1e-12 < $qtyNeeded) {
      return [
        'ok' => false,
        'error' => 'Insufficient position in your trading account for this sell. Reduce the USD amount or buy more of this asset first.',
        'code' => 'insufficient_position',
      ];
    }
    $credit = $amountUsd - $totalFee;
    if ($credit < -1e-9) {
      return [
        'ok' => false,
        'error' => 'Trading and network fees exceed this sell amount. Increase the amount or choose a lower-fee network (e.g. BEP20).',
        'code' => 'fee_exceeds_amount',
      ];
    }
  }

  $meta = json_encode([
    'side' => $side,
    'network' => $network,
    'catalog_asset_id' => $catalogId,
    'amount_usd' => $amountUsd,
    'trading_fee_usd' => $tradingFee,
    'network_fee_usd' => $networkFee,
    'total_fee_usd' => $totalFee,
  ], JSON_UNESCAPED_SLASHES);

  $type = 'trade';
  $status = 'pending';
  $amountDb = number_format($amountUsd, 8, '.', ''); // store USD amount

  $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
  if (!$ins) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $ins->bind_param('isssss', $userId, $type, $asset, $amountDb, $status, $meta);
  $ins->execute();
  $orderId = (int)$ins->insert_id;
  $ins->close();

  return ['ok' => true, 'order_id' => $orderId];
}

function user_cancel_trade_order(mysqli $db, int $userId, int $orderId): array {
  ensure_transactions_table($db);
  $stmt = $db->prepare('UPDATE transactions SET status = ? WHERE id = ? AND user_id = ? AND type = ? AND status = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $newStatus = 'cancelled';
  $type = 'trade';
  $oldStatus = 'pending';
  $stmt->bind_param('siiss', $newStatus, $orderId, $userId, $type, $oldStatus);
  $stmt->execute();
  $affected = $stmt->affected_rows;
  $stmt->close();
  return $affected > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'Order not cancellable.'];
}

function user_fill_trade_order(mysqli $db, string $schema, int $userId, int $orderId): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);
  ensure_positions_table($db);
  market_control_ensure_schema($db);

  if (!market_control_trading_enabled($db)) {
    return ['ok' => false, 'error' => 'Trading is temporarily paused.'];
  }

  $db->begin_transaction();
  try {
    // Lock order row.
    $stmt = $db->prepare('SELECT asset, amount, status, meta FROM transactions WHERE id = ? AND user_id = ? AND type = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $type = 'trade';
    $stmt->bind_param('iis', $orderId, $userId, $type);
    $stmt->execute();
    $stmt->store_result();
    $asset = $status = $meta = '';
    $amount = '0.00000000';
    $stmt->bind_result($asset, $amount, $status, $meta);
    $found = $stmt->fetch();
    $stmt->free_result();
    $stmt->close();

    if (!$found) {
      $db->rollback();
      return ['ok' => false, 'error' => 'Order not found.'];
    }
    if (strtolower($status) !== 'pending') {
      $db->rollback();
      return ['ok' => false, 'error' => 'Order already processed.'];
    }

    $m = is_string($meta) && $meta !== '' ? json_decode($meta, true) : null;
    $side = (is_array($m) && isset($m['side']) && is_string($m['side'])) ? $m['side'] : '';
    $network = (is_array($m) && isset($m['network']) && is_string($m['network'])) ? $m['network'] : '';
    $amountUsd = is_array($m) && isset($m['amount_usd']) ? (float)$m['amount_usd'] : (float)$amount;
    if ($side === '' || $network === '' || $amountUsd <= 0) {
      $db->rollback();
      return ['ok' => false, 'error' => 'Order data invalid.'];
    }

    if (!market_control_asset_tradable($db, $asset)) {
      $db->rollback();
      return ['ok' => false, 'error' => 'This asset is not available for trading.'];
    }

    // Execute using live price at fill time.
    $price = user_price_usdt($asset, $db);
    $qty = $amountUsd / $price;

    // Reuse execution logic (but without inserting another transaction).
    $catalogFromMeta = (is_array($m) && isset($m['catalog_asset_id']) && is_string($m['catalog_asset_id']))
      ? strtolower(trim($m['catalog_asset_id']))
      : '';
    $catalogId = $catalogFromMeta !== '' ? $catalogFromMeta : trade_infer_catalog_asset_id($asset, $network);
    $networkFee = user_trade_network_fee_usd($db, $userId, $catalogId, $network);
    $feePct = market_control_get_fee_pct($db, $asset);
    $tradingFee = $amountUsd * ($feePct / 100.0);
    $totalFee = $networkFee + $tradingFee;

    // Lock user row
    $u = $db->prepare('SELECT available_balance_usd, spot_balance_usd, open_positions FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$u) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $u->bind_param('i', $userId);
    $u->execute();
    $u->store_result();
    $bal = $spotBal = '0.00';
    $openPos = 0;
    $u->bind_result($bal, $spotBal, $openPos);
    $u->fetch();
    $u->free_result();
    $u->close();

    $balance = (float)$bal;
    $spotBalance = (float)$spotBal;
    $openPos = (int)$openPos;

    // Lock position row
    $posQty = 0.0;
    $posAvg = 0.0;
    $posStmt = $db->prepare('SELECT qty, avg_entry_price_usd FROM positions WHERE user_id = ? AND asset = ? LIMIT 1 FOR UPDATE');
    if ($posStmt) {
      $posStmt->bind_param('is', $userId, $asset);
      $posStmt->execute();
      $posStmt->store_result();
      $q = $a = '0';
      $posStmt->bind_result($q, $a);
      if ($posStmt->fetch()) {
        $posQty = (float)$q;
        $posAvg = (float)$a;
      }
      $posStmt->free_result();
      $posStmt->close();
    }

    $realizedPl = 0.0;
    $side = strtolower($side);
    if ($side === 'buy') {
      $debit = $amountUsd + $totalFee;
      if ($debit > $spotBalance + 1e-9) {
        // Mark order as failed (insufficient at fill time).
        $fail = $db->prepare('UPDATE transactions SET status = ? WHERE id = ? AND user_id = ?');
        if ($fail) {
          $st = 'failed';
          $fail->bind_param('sii', $st, $orderId, $userId);
          $fail->execute();
          $fail->close();
        }
        $db->commit();
        return ['ok' => false, 'error' => 'Insufficient trading balance at execution time.'];
      }
      $newSpot = $spotBalance - $debit;
      $newOpenPos = $openPos + 1;

      $newPosQty = $posQty + $qty;
      $newPosAvg = ($newPosQty > 0) ? (($posQty * $posAvg) + ($qty * $price)) / $newPosQty : $price;
      $posUp = $db->prepare(
        'INSERT INTO positions (user_id, asset, qty, avg_entry_price_usd, created_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE qty = VALUES(qty), avg_entry_price_usd = VALUES(avg_entry_price_usd)'
      );
      if ($posUp) {
        $qtyDb = number_format($newPosQty, 12, '.', '');
        $avgDb = number_format($newPosAvg, 12, '.', '');
        $posUp->bind_param('isss', $userId, $asset, $qtyDb, $avgDb);
        $posUp->execute();
        $posUp->close();
      }
    } else {
      $credit = max(0.0, $amountUsd - $totalFee);
      $newSpot = $spotBalance + $credit;
      $newOpenPos = max(0, $openPos - 1);

      if ($posQty + 1e-12 < $qty) {
        $fail = $db->prepare('UPDATE transactions SET status = ? WHERE id = ? AND user_id = ?');
        if ($fail) {
          $st = 'failed';
          $fail->bind_param('sii', $st, $orderId, $userId);
          $fail->execute();
          $fail->close();
        }
        $db->commit();
        return ['ok' => false, 'error' => 'Insufficient position at execution time.'];
      }

      $realizedPl = ($price - $posAvg) * $qty;
      $newPosQty = $posQty - $qty;
      if ($newPosQty <= 1e-12) {
        $del = $db->prepare('DELETE FROM positions WHERE user_id = ? AND asset = ?');
        if ($del) {
          $del->bind_param('is', $userId, $asset);
          $del->execute();
          $del->close();
        }
      } else {
        $posUp = $db->prepare('UPDATE positions SET qty = ? WHERE user_id = ? AND asset = ?');
        if ($posUp) {
          $qtyDb = number_format($newPosQty, 12, '.', '');
          $posUp->bind_param('sis', $qtyDb, $userId, $asset);
          $posUp->execute();
          $posUp->close();
        }
      }
    }

    $newSpotStr = number_format($newSpot, 2, '.', '');
    $amountStr = number_format($amountUsd, 2, '.', '');
    $walletStr = number_format($balance, 2, '.', '');
    $portfolioStr = number_format($balance + $newSpot, 2, '.', '');

    $up = $db->prepare('UPDATE users SET spot_balance_usd = ?, volume_24h_usd = volume_24h_usd + ?, portfolio_value_usd = ?, open_positions = ? WHERE id = ?');
    if (!$up) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $up->bind_param('sssii', $newSpotStr, $amountStr, $portfolioStr, $newOpenPos, $userId);
    $up->execute();
    $up->close();

    // Update meta with fill details and mark completed.
    $m = is_array($m) ? $m : [];
    $m['price_usd'] = $price;
    $m['qty'] = $qty;
    $m['realized_pl_usd'] = $realizedPl;
    $m['filled_at'] = (new DateTimeImmutable('now'))->format('c');
    $newMeta = json_encode($m, JSON_UNESCAPED_SLASHES);

    $done = $db->prepare('UPDATE transactions SET status = ?, meta = ? WHERE id = ? AND user_id = ?');
    if (!$done) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $st = 'completed';
    $done->bind_param('ssii', $st, $newMeta, $orderId, $userId);
    $done->execute();
    $done->close();

    $db->commit();
    return [
      'ok' => true,
      'order_id' => $orderId,
      'available_balance_usd' => $walletStr,
      'spot_balance_usd' => $newSpotStr,
      'open_positions' => $newOpenPos,
      'realized_pl_usd' => number_format($realizedPl, 2, '.', ''),
    ];
  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }
}

// ---------------------------------------------------------------------------
// KYC (Know Your Customer) functions
// ---------------------------------------------------------------------------

function ensure_users_kyc_columns(mysqli $db, string $schema): void {
  // Best-effort: keep backward compatible with existing schemas.
  $table = 'users';
  $cols = [
    ['kyc_status', "VARCHAR(20) NOT NULL DEFAULT 'not_submitted'"],
    ['kyc_updated_at', "DATETIME NULL"],
  ];
  foreach ($cols as [$name, $def]) {
    if (!user_schema_column_exists($db, $schema, $table, (string)$name)) {
      @$db->query("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$def}");
    }
  }
}

function ensure_kyc_requests_table(mysqli $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `kyc_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `full_name` VARCHAR(180) NOT NULL DEFAULT '',
  `dob` DATE NULL,
  `country` VARCHAR(80) NOT NULL DEFAULT '',
  `nationality` VARCHAR(80) NOT NULL DEFAULT '',
  `address_line` VARCHAR(255) NOT NULL DEFAULT '',
  `city_state_postal` VARCHAR(255) NOT NULL DEFAULT '',
  `id_number` VARCHAR(80) NOT NULL DEFAULT '',
  `source_of_funds` VARCHAR(60) NOT NULL DEFAULT '',
  `transaction_volume` VARCHAR(30) NOT NULL DEFAULT '',
  `purpose` TEXT NULL,
  `doc_id_path` VARCHAR(400) NOT NULL DEFAULT '',
  `proof_address_path` VARCHAR(400) NOT NULL DEFAULT '',
  `selfie_path` VARCHAR(400) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `rejection_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL,
  `reviewed_by_admin_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kyc_user` (`user_id`),
  KEY `idx_kyc_status` (`status`),
  KEY `idx_kyc_submitted` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

function user_fetch_kyc_request(mysqli $db, int $userId): ?array {
  ensure_kyc_requests_table($db);
  $stmt = $db->prepare('SELECT id, user_id, full_name, dob, country, nationality, address_line, city_state_postal, id_number, source_of_funds, transaction_volume, purpose, doc_id_path, proof_address_path, selfie_path, status, rejection_reason, submitted_at, reviewed_at, reviewed_by_admin_id FROM kyc_requests WHERE user_id = ? LIMIT 1');
  if (!$stmt) {
    return null;
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();

  $id = 0;
  $uid = 0;
  $fullName = $dob = $country = $nationality = $addr = $city = $idNum = $sof = $tv = $purpose = '';
  $docId = $proof = $selfie = $status = $reject = $submittedAt = $reviewedAt = '';
  $reviewedBy = null;
  $stmt->bind_result($id, $uid, $fullName, $dob, $country, $nationality, $addr, $city, $idNum, $sof, $tv, $purpose, $docId, $proof, $selfie, $status, $reject, $submittedAt, $reviewedAt, $reviewedBy);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    return null;
  }
  return [
    'id' => (int)$id,
    'user_id' => (int)$uid,
    'full_name' => (string)$fullName,
    'dob' => (string)$dob,
    'country' => (string)$country,
    'nationality' => (string)$nationality,
    'address_line' => (string)$addr,
    'city_state_postal' => (string)$city,
    'id_number' => (string)$idNum,
    'source_of_funds' => (string)$sof,
    'transaction_volume' => (string)$tv,
    'purpose' => (string)$purpose,
    'doc_id_path' => (string)$docId,
    'proof_address_path' => (string)$proof,
    'selfie_path' => (string)$selfie,
    'status' => (string)$status,
    'rejection_reason' => (string)$reject,
    'submitted_at' => (string)$submittedAt,
    'reviewed_at' => (string)$reviewedAt,
    'reviewed_by_admin_id' => is_numeric($reviewedBy) ? (int)$reviewedBy : null,
  ];
}

function user_update_kyc_status(mysqli $db, string $schema, int $userId, string $status): void {
  ensure_users_kyc_columns($db, $schema);
  $status = strtolower(trim($status));
  $allowed = ['not_submitted', 'pending', 'approved', 'rejected'];
  if (!in_array($status, $allowed, true)) {
    $status = 'pending';
  }
  $stmt = $db->prepare('UPDATE users SET kyc_status = ?, kyc_updated_at = NOW() WHERE id = ?');
  if ($stmt) {
    $stmt->bind_param('si', $status, $userId);
    @$stmt->execute();
    $stmt->close();
  }
}

/**
 * Effective KYC status: kyc_requests row wins, else users.kyc_status.
 *
 * @return array{status:string,rejection_reason:string}
 */
function user_kyc_resolve_status(mysqli $db, string $schema, int $userId): array {
  ensure_users_kyc_columns($db, $schema);
  ensure_kyc_requests_table($db);
  $status = 'not_submitted';
  $reject = '';
  $kr = user_fetch_kyc_request($db, $userId);
  if ($kr !== null && isset($kr['status']) && (string)$kr['status'] !== '') {
    $status = strtolower((string)$kr['status']);
    $reject = trim((string)($kr['rejection_reason'] ?? ''));
  } else {
    $stmt = $db->prepare('SELECT kyc_status FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $stmt->store_result();
      $s = '';
      $stmt->bind_result($s);
      if ($stmt->fetch() && is_string($s) && $s !== '') {
        $status = strtolower($s);
      }
      $stmt->free_result();
      $stmt->close();
    }
  }
  return ['status' => $status, 'rejection_reason' => $reject];
}

/**
 * Sidebar badge for user area: reflects kyc_requests.status, else users.kyc_status.
 *
 * @return array{text:string,bg:string}
 */
function user_kyc_sidebar_badge(mysqli $db, string $schema, int $userId): array {
  $r = user_kyc_resolve_status($db, $schema, $userId);
  $status = $r['status'];
  return match ($status) {
    'approved' => ['text' => 'VERIFIED', 'bg' => '#22c55e'],
    'pending' => ['text' => 'PENDING', 'bg' => '#ffa726'],
    'rejected' => ['text' => 'REJECTED', 'bg' => '#ef5350'],
    default => ['text' => 'NOT SUBMITTED', 'bg' => '#64748b'],
  };
}

/**
 * Dashboard / profile card copy for Security & KYC block.
 *
 * @return array{status:string,label:string,message:string,color:string,badge_bg:string,cta_bg:string,rejection_reason:string,cta:string}
 */
function user_kyc_profile_card(mysqli $db, string $schema, int $userId): array {
  $r = user_kyc_resolve_status($db, $schema, $userId);
  $status = $r['status'];
  $reject = $r['rejection_reason'];
  return match ($status) {
    'approved' => [
      'status' => 'approved',
      'label' => 'Verified',
      'message' => 'Your identity has been verified. Full account features are available.',
      'color' => 'var(--positive, #22c55e)',
      'badge_bg' => '#22c55e',
      'cta_bg' => 'rgba(34, 197, 94, 0.18)',
      'rejection_reason' => '',
      'cta' => 'View details',
    ],
    'pending' => [
      'status' => 'pending',
      'label' => 'Pending review',
      'message' => 'Your documents are under review. We will notify you when the review is complete.',
      'color' => 'var(--warning-color)',
      'badge_bg' => '#ffa726',
      'cta_bg' => 'rgba(255, 167, 38, 0.2)',
      'rejection_reason' => '',
      'cta' => 'View status',
    ],
    'rejected' => [
      'status' => 'rejected',
      'label' => 'Rejected',
      'message' => 'Your last submission was not approved. You can update your documents and resubmit.',
      'color' => 'var(--danger-color, #ef5350)',
      'badge_bg' => '#ef5350',
      'cta_bg' => 'rgba(239, 83, 80, 0.18)',
      'rejection_reason' => $reject,
      'cta' => 'Resubmit KYC',
    ],
    default => [
      'status' => 'not_submitted',
      'label' => 'Not submitted',
      'message' => 'Complete identity verification to protect your account and unlock full features.',
      'color' => 'var(--text-muted)',
      'badge_bg' => '#64748b',
      'cta_bg' => 'rgba(100, 116, 139, 0.2)',
      'rejection_reason' => '',
      'cta' => 'Start verification',
    ],
  };
}

/**
 * @param array{full_name:string,dob?:string,country:string,nationality:string,address_line:string,city_state_postal:string,id_number:string,source_of_funds:string,transaction_volume:string,purpose:string} $data
 * @param array{doc_id_path:string,proof_address_path:string,selfie_path?:string} $files
 * @return array{ok:bool,error?:string}
 */
function user_submit_kyc_request(mysqli $db, string $schema, int $userId, array $data, array $files): array {
  ensure_kyc_requests_table($db);
  ensure_users_kyc_columns($db, $schema);

  if ($userId <= 0) return ['ok' => false, 'error' => 'auth'];
  $fullName = trim((string)($data['full_name'] ?? ''));
  $country = trim((string)($data['country'] ?? ''));
  $nationality = trim((string)($data['nationality'] ?? ''));
  $addr = trim((string)($data['address_line'] ?? ''));
  $city = trim((string)($data['city_state_postal'] ?? ''));
  $idNum = trim((string)($data['id_number'] ?? ''));
  $sof = trim((string)($data['source_of_funds'] ?? ''));
  $tv = trim((string)($data['transaction_volume'] ?? ''));
  $purpose = trim((string)($data['purpose'] ?? ''));
  $dob = isset($data['dob']) ? trim((string)$data['dob']) : '';

  if ($fullName === '' || $country === '' || $nationality === '' || $addr === '' || $city === '' || $idNum === '') {
    return ['ok' => false, 'error' => 'missing_fields'];
  }
  $docIdPath = trim((string)($files['doc_id_path'] ?? ''));
  $proofPath = trim((string)($files['proof_address_path'] ?? ''));
  $selfiePath = isset($files['selfie_path']) ? trim((string)$files['selfie_path']) : '';
  if ($docIdPath === '' || $proofPath === '') {
    return ['ok' => false, 'error' => 'missing_files'];
  }

  // Normalize dob: accept YYYY-MM-DD only.
  $dobDb = null;
  if ($dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    $dobDb = $dob;
  }

  $status = 'pending';
  $stmt = $db->prepare('
    INSERT INTO kyc_requests
      (user_id, full_name, dob, country, nationality, address_line, city_state_postal, id_number, source_of_funds, transaction_volume, purpose, doc_id_path, proof_address_path, selfie_path, status, rejection_reason, submitted_at, reviewed_at, reviewed_by_admin_id)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "", NOW(), NULL, NULL)
    ON DUPLICATE KEY UPDATE
      full_name = VALUES(full_name),
      dob = VALUES(dob),
      country = VALUES(country),
      nationality = VALUES(nationality),
      address_line = VALUES(address_line),
      city_state_postal = VALUES(city_state_postal),
      id_number = VALUES(id_number),
      source_of_funds = VALUES(source_of_funds),
      transaction_volume = VALUES(transaction_volume),
      purpose = VALUES(purpose),
      doc_id_path = VALUES(doc_id_path),
      proof_address_path = VALUES(proof_address_path),
      selfie_path = VALUES(selfie_path),
      status = VALUES(status),
      rejection_reason = "",
      submitted_at = NOW(),
      reviewed_at = NULL,
      reviewed_by_admin_id = NULL
  ');
  if (!$stmt) {
    return ['ok' => false, 'error' => 'db'];
  }
  $stmt->bind_param(
    'issssssssssssss',
    $userId,
    $fullName,
    $dobDb,
    $country,
    $nationality,
    $addr,
    $city,
    $idNum,
    $sof,
    $tv,
    $purpose,
    $docIdPath,
    $proofPath,
    $selfiePath,
    $status
  );
  $ok = @$stmt->execute();
  $stmt->close();
  if (!$ok) {
    return ['ok' => false, 'error' => 'insert'];
  }

  user_update_kyc_status($db, $schema, $userId, 'pending');
  return ['ok' => true];
}

// ---------------------------------------------------------------------------
// User security settings (used by Userprofile/security.php)
// ---------------------------------------------------------------------------

function ensure_user_security_columns(mysqli $db, string $schema): void {
  $table = 'users';

  if (!user_schema_column_exists($db, $schema, $table, 'twofa_enabled')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `twofa_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
  }
  if (!user_schema_column_exists($db, $schema, $table, 'login_alerts_enabled')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `login_alerts_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");
  }
  if (!user_schema_column_exists($db, $schema, $table, 'password_changed_at')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `password_changed_at` DATETIME NULL");
  }
}

function user_fetch_security_state(mysqli $db, string $schema, int $userId): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_security_columns($db, $schema);

  $stmt = $db->prepare('SELECT twofa_enabled, login_alerts_enabled, password_changed_at FROM users WHERE id = ? LIMIT 1');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();

  $twofa = 0;
  $alerts = 1;
  $pwdChangedAt = null;
  $stmt->bind_result($twofa, $alerts, $pwdChangedAt);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();

  if (!$found) {
    throw new RuntimeException('User not found.');
  }

  return [
    'twofa_enabled' => ((int)$twofa) === 1,
    'login_alerts_enabled' => ((int)$alerts) === 1,
    'password_changed_at' => is_string($pwdChangedAt) ? $pwdChangedAt : null,
  ];
}

function user_set_twofa_enabled(mysqli $db, string $schema, int $userId, bool $enabled): void {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_security_columns($db, $schema);
  $val = $enabled ? 1 : 0;
  $stmt = $db->prepare('UPDATE users SET twofa_enabled = ? WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('ii', $val, $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

function user_set_login_alerts_enabled(mysqli $db, string $schema, int $userId, bool $enabled): void {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_security_columns($db, $schema);
  $val = $enabled ? 1 : 0;
  $stmt = $db->prepare('UPDATE users SET login_alerts_enabled = ? WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('ii', $val, $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

function user_change_password(mysqli $db, string $schema, int $userId, string $currentPassword, string $newPassword): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_security_columns($db, $schema);

  if (strlen($newPassword) < 8) {
    return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
  }

  $stmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();
  $hash = '';
  $stmt->bind_result($hash);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();

  if (!$found || !is_string($hash) || $hash === '') {
    return ['ok' => false, 'error' => 'Account password is not set.'];
  }
  if (!password_verify($currentPassword, $hash)) {
    return ['ok' => false, 'error' => 'Current password is incorrect.'];
  }

  $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
  if (!is_string($newHash) || $newHash === '') {
    throw new RuntimeException('password_hash failed');
  }
  $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

  $up = $db->prepare('UPDATE users SET password = ?, password_changed_at = ? WHERE id = ?');
  if (!$up) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $up->bind_param('ssi', $newHash, $now, $userId);
  if (!$up->execute()) {
    $up->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $up->close();

  return ['ok' => true, 'password_changed_at' => $now];
}

// ---------------------------------------------------------------------------
// User security activity feed (Recent Security Activity on security.php)
// ---------------------------------------------------------------------------

function ensure_user_security_activity_table(mysqli $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `user_security_activity` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(48) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `detail` VARCHAR(512) NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

function user_security_activity_icon_class(string $eventType): string {
  return match ($eventType) {
    'login_success' => 'fa-solid fa-sign-in-alt',
    'password_changed' => 'fa-solid fa-key',
    'twofa_enabled', 'twofa_disabled' => 'fa-solid fa-mobile-screen',
    'login_alerts' => 'fa-solid fa-bell',
    'session_terminated' => 'fa-solid fa-right-from-bracket',
    default => 'fa-solid fa-shield-halved',
  };
}

/**
 * Best-effort: never throws; failures are logged only.
 */
function user_log_security_activity(
  mysqli $db,
  int $userId,
  string $eventType,
  string $title,
  string $detail = '',
  ?string $ip = null
): void {
  try {
    ensure_user_security_activity_table($db);
    if ($userId <= 0) {
      return;
    }
    $eventType = substr($eventType, 0, 48);
    $title = substr($title, 0, 255);
    $detail = substr($detail, 0, 512);
    $ipStr = '';
    if ($ip !== null && $ip !== '') {
      $ipStr = substr(trim($ip), 0, 45);
    }

    $stmt = $db->prepare(
      'INSERT INTO user_security_activity (user_id, event_type, title, detail, ip) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
      return;
    }
    $stmt->bind_param('issss', $userId, $eventType, $title, $detail, $ipStr);
    @$stmt->execute();
    $stmt->close();
  } catch (Throwable $e) {
    error_log('[user_log_security_activity] ' . $e->getMessage());
  }
}

/**
 * @return list<array{id:int,event_type:string,title:string,detail:string,ip:string,created_at:string,icon_class:string}>
 */
function user_list_security_activity(mysqli $db, int $userId, int $limit = 25): array {
  ensure_user_security_activity_table($db);
  $limit = max(1, min(100, $limit));
  $stmt = $db->prepare(
    'SELECT id, event_type, title, detail, ip, created_at FROM user_security_activity WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT '
      . (int)$limit
  );
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $et = (string)($row['event_type'] ?? '');
      $out[] = [
        'id' => (int)($row['id'] ?? 0),
        'event_type' => $et,
        'title' => (string)($row['title'] ?? ''),
        'detail' => (string)($row['detail'] ?? ''),
        'ip' => (string)($row['ip'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'icon_class' => user_security_activity_icon_class($et),
      ];
    }
    $res->free();
  }
  $stmt->close();
  return $out;
}

function user_clear_security_activity(mysqli $db, int $userId): bool {
  ensure_user_security_activity_table($db);
  if ($userId <= 0) {
    return false;
  }
  $stmt = $db->prepare('DELETE FROM user_security_activity WHERE user_id = ?');
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('i', $userId);
  $ok = @$stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

// ---------------------------------------------------------------------------
// User sessions (used by Userprofile/security.php Active Sessions)
// ---------------------------------------------------------------------------

function ensure_user_sessions_table(mysqli $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `session_id_hash` CHAR(64) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_session` (`user_id`, `session_id_hash`),
  KEY `idx_user_last_seen` (`user_id`, `last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

function user_session_hash(string $sid): string {
  return hash('sha256', $sid);
}

function user_agent_friendly_label(string $ua): string {
  $ua = trim($ua);
  if ($ua === '') {
    return 'Unknown device';
  }
  $u = strtolower($ua);

  $os = 'Desktop';
  if (str_contains($u, 'iphone')) {
    $os = 'iPhone';
  } elseif (str_contains($u, 'ipad')) {
    $os = 'iPad';
  } elseif (str_contains($u, 'android')) {
    $os = 'Android';
  } elseif (str_contains($u, 'windows')) {
    $os = 'Windows';
  } elseif (str_contains($u, 'mac os') || str_contains($u, 'macintosh')) {
    $os = 'macOS';
  } elseif (str_contains($u, 'linux')) {
    $os = 'Linux';
  }

  $br = 'Browser';
  if (str_contains($u, 'edg/') || str_contains($u, 'edgios') || str_contains($u, 'edga')) {
    $br = 'Edge';
  } elseif (str_contains($u, 'opr/') || str_contains($u, 'opera')) {
    $br = 'Opera';
  } elseif (str_contains($u, 'firefox/') || str_contains($u, 'fxios')) {
    $br = 'Firefox';
  } elseif (str_contains($u, 'safari/') && !str_contains($u, 'chrome') && !str_contains($u, 'crios')) {
    $br = 'Safari';
  } elseif (str_contains($u, 'chrome/') || str_contains($u, 'crios') || str_contains($u, 'chromium')) {
    $br = 'Chrome';
  }

  return $br . ' on ' . $os;
}

function user_record_session(mysqli $db, int $userId, string $sessionId, string $ip, string $userAgent): void {
  ensure_user_sessions_table($db);
  $h = user_session_hash($sessionId);
  $ip = trim($ip);
  $ua = substr(trim($userAgent), 0, 255);

  $stmt = $db->prepare(
    'INSERT INTO user_sessions (user_id, session_id_hash, ip, user_agent, created_at, last_seen_at)
     VALUES (?, ?, ?, ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE ip = VALUES(ip), user_agent = VALUES(user_agent), last_seen_at = NOW()'
  );
  if (!$stmt) return;
  $stmt->bind_param('isss', $userId, $h, $ip, $ua);
  @$stmt->execute();
  $stmt->close();
}

function user_session_exists(mysqli $db, int $userId, string $sessionId): bool {
  ensure_user_sessions_table($db);
  $h = user_session_hash($sessionId);
  $stmt = $db->prepare('SELECT id FROM user_sessions WHERE user_id = ? AND session_id_hash = ? LIMIT 1');
  if (!$stmt) return true; // fail-open
  $stmt->bind_param('is', $userId, $h);
  $stmt->execute();
  $stmt->store_result();
  $ok = $stmt->num_rows > 0;
  $stmt->free_result();
  $stmt->close();
  return $ok;
}

/**
 * @return list<array{id:int,ip:string,user_agent:string,device_label:string,created_at:string,last_seen_at:string,is_current:bool}>
 */
function user_list_sessions(mysqli $db, int $userId, string $currentSessionId, int $limit = 10): array {
  ensure_user_sessions_table($db);
  $limit = max(1, min(30, $limit));
  $currentHash = user_session_hash($currentSessionId);

  $stmt = $db->prepare('SELECT id, ip, user_agent, created_at, last_seen_at, session_id_hash FROM user_sessions WHERE user_id = ? ORDER BY last_seen_at DESC LIMIT ' . (int)$limit);
  if (!$stmt) return [];
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $hash = (string)($row['session_id_hash'] ?? '');
      $uaRaw = (string)($row['user_agent'] ?? '');
      $out[] = [
        'id' => (int)($row['id'] ?? 0),
        'ip' => (string)($row['ip'] ?? ''),
        'user_agent' => $uaRaw,
        'device_label' => user_agent_friendly_label($uaRaw),
        'created_at' => (string)($row['created_at'] ?? ''),
        'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
        'is_current' => $hash !== '' && hash_equals($currentHash, $hash),
      ];
    }
    $res->free();
  }
  $stmt->close();
  return $out;
}

function user_terminate_session(mysqli $db, int $userId, int $sessionRowId): bool {
  ensure_user_sessions_table($db);
  $stmt = $db->prepare('DELETE FROM user_sessions WHERE id = ? AND user_id = ?');
  if (!$stmt) return false;
  $stmt->bind_param('ii', $sessionRowId, $userId);
  $ok = @$stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

// ---------------------------------------------------------------------------
// Admin-controlled user security (used by Admin Security Center)
// ---------------------------------------------------------------------------

function ensure_user_admin_security_columns(mysqli $db, string $schema): void {
  $table = 'users';

  if (!user_schema_column_exists($db, $schema, $table, 'is_locked')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `is_locked` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
  }
  if (!user_schema_column_exists($db, $schema, $table, 'locked_reason')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `locked_reason` VARCHAR(255) NULL");
  }
  if (!user_schema_column_exists($db, $schema, $table, 'force_password_reset')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `force_password_reset` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
  }
  if (!user_schema_column_exists($db, $schema, $table, 'session_version')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 0");
  }
}

function user_fetch_account_flags(mysqli $db, string $schema, int $userId): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_security_columns($db, $schema);
  ensure_user_admin_security_columns($db, $schema);

  // `is_active` exists in schema used by login.php; fall back to 1 if missing.
  $sql = 'SELECT is_active, is_locked, force_password_reset, session_version FROM users WHERE id = ? LIMIT 1';
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();

  $isActive = 1;
  $isLocked = 0;
  $forceReset = 0;
  $sessVer = 0;
  $stmt->bind_result($isActive, $isLocked, $forceReset, $sessVer);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    throw new RuntimeException('User not found.');
  }

  return [
    'is_active' => ((int)$isActive) === 1,
    'is_locked' => ((int)$isLocked) === 1,
    'force_password_reset' => ((int)$forceReset) === 1,
    'session_version' => (int)$sessVer,
  ];
}

function admin_user_find_by_identifier(mysqli $db, string $identifier): ?array {
  $id = 0;
  $email = $fullName = '';
  $stmt = $db->prepare('SELECT id, email, full_name FROM users WHERE email = ? LIMIT 1');
  if (!$stmt) return null;
  $stmt->bind_param('s', $identifier);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($id, $email, $fullName);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) return null;
  return ['id' => (int)$id, 'email' => (string)$email, 'full_name' => (string)$fullName];
}

function admin_user_set_lock(mysqli $db, string $schema, int $userId, bool $locked, string $reason = ''): void {
  ensure_user_admin_security_columns($db, $schema);
  ensure_user_dashboard_schema($db, $schema);
  $reason = trim($reason);
  if (!$locked) {
    // Unlock = full restore: clear lock and re-enable login (freeze sets is_active=0; unlock must undo that).
    $stmt = $db->prepare('UPDATE users SET is_locked = 0, locked_reason = NULL, is_active = 1 WHERE id = ?');
    if (!$stmt) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
      $stmt->close();
      throw new RuntimeException('update failed: ' . $db->error);
    }
    $stmt->close();
    return;
  }
  $reasonDb = $reason !== '' ? substr($reason, 0, 255) : null;
  $stmt = $db->prepare('UPDATE users SET is_locked = 1, locked_reason = ? WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('si', $reasonDb, $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

function admin_user_set_active(mysqli $db, string $schema, int $userId, bool $active): void {
  ensure_user_dashboard_schema($db, $schema);
  if ($active) {
    ensure_user_admin_security_columns($db, $schema);
    // Unfreeze = restore access; also clear lock so login is not blocked.
    $stmt = $db->prepare('UPDATE users SET is_active = 1, is_locked = 0, locked_reason = NULL WHERE id = ?');
    if (!$stmt) {
      throw new RuntimeException('prepare failed: ' . $db->error);
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
      $stmt->close();
      throw new RuntimeException('update failed: ' . $db->error);
    }
    $stmt->close();
    return;
  }
  $stmt = $db->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

function admin_user_set_force_password_reset(mysqli $db, string $schema, int $userId, bool $forced): void {
  ensure_user_admin_security_columns($db, $schema);
  $v = $forced ? 1 : 0;
  $stmt = $db->prepare('UPDATE users SET force_password_reset = ? WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('ii', $v, $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

function admin_user_force_logout(mysqli $db, string $schema, int $userId): void {
  ensure_user_admin_security_columns($db, $schema);
  // Bump version so user_auth.php can invalidate existing sessions.
  $stmt = $db->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

// ---------------------------------------------------------------------------
// Account settings (settings.php + settings_api.php)
// ---------------------------------------------------------------------------

function ensure_user_account_settings_columns(mysqli $db, string $schema): void {
  $table = 'users';
  if (!user_schema_column_exists($db, $schema, $table, 'phone')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `phone` VARCHAR(64) NULL DEFAULT NULL");
  }
  if (!user_schema_column_exists($db, $schema, $table, 'settings_json')) {
    @$db->query("ALTER TABLE `{$table}` ADD COLUMN `settings_json` JSON NULL");
  }
}

/**
 * @return array<string, mixed>
 */
function user_settings_default_payload(): array {
  return [
    'emailNotifications' => true,
    'pushNotifications' => true,
    'transactionAlerts' => true,
    'language' => 'en',
    'timezone' => 'UTC',
    'currency' => 'USD',
    'compactView' => false,
    'animatedCharts' => true,
    'updateFrequency' => 'realtime',
  ];
}

/**
 * @param mixed $decoded
 * @return array<string, mixed>
 */
function user_normalize_settings_payload($decoded): array {
  $def = user_settings_default_payload();
  if (!is_array($decoded)) {
    return $def;
  }
  $allowedTz = ['UTC', 'EST', 'CST', 'PST', 'GMT'];
  $allowedFreq = ['realtime', '5s', '10s', '30s'];
  $out = $def;
  foreach ($def as $key => $defaultVal) {
    if (!array_key_exists($key, $decoded)) {
      continue;
    }
    $val = $decoded[$key];
    if (is_bool($defaultVal)) {
      $out[$key] = $val === true || $val === 1 || $val === '1';
      continue;
    }
    if (!is_string($val) && !is_numeric($val)) {
      continue;
    }
    $s = trim((string)$val);
    if ($key === 'language' && preg_match('/^[a-z]{2}$/', $s)) {
      $out[$key] = $s;
    } elseif ($key === 'timezone' && in_array($s, $allowedTz, true)) {
      $out[$key] = $s;
    } elseif ($key === 'currency' && preg_match('/^[A-Za-z]{3}$/', $s)) {
      $out[$key] = strtoupper($s);
    } elseif ($key === 'updateFrequency' && in_array($s, $allowedFreq, true)) {
      $out[$key] = $s;
    }
  }
  return $out;
}

/**
 * @return array{full_name: string, email: string, phone: string, wallet_id: string, settings: array<string, mixed>}|null
 */
function user_fetch_settings_bundle(mysqli $db, string $schema, int $userId): ?array {
  ensure_user_account_settings_columns($db, $schema);
  ensure_user_dashboard_schema($db, $schema);

  $stmt = $db->prepare(
    'SELECT full_name, email, phone, wallet_id, settings_json FROM users WHERE id = ? LIMIT 1'
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!is_array($row)) {
    return null;
  }

  $rawJson = $row['settings_json'] ?? null;
  $decoded = null;
  if (is_array($rawJson)) {
    $decoded = $rawJson;
  } elseif (is_string($rawJson) && $rawJson !== '') {
    $decoded = json_decode($rawJson, true);
  }
  $settings = user_normalize_settings_payload($decoded);

  $phone = isset($row['phone']) && is_string($row['phone']) ? $row['phone'] : '';
  $wid = isset($row['wallet_id']) && is_string($row['wallet_id']) ? $row['wallet_id'] : '';

  return [
    'full_name' => (string)($row['full_name'] ?? ''),
    'email' => (string)($row['email'] ?? ''),
    'phone' => $phone,
    'wallet_id' => $wid,
    'settings' => $settings,
  ];
}

/**
 * @param array<string, mixed> $patch
 * @return array<string, mixed>
 */
function user_merge_and_save_settings_json(mysqli $db, string $schema, int $userId, array $patch): array {
  ensure_user_account_settings_columns($db, $schema);
  $bundle = user_fetch_settings_bundle($db, $schema, $userId);
  $current = is_array($bundle) && isset($bundle['settings']) ? $bundle['settings'] : user_settings_default_payload();
  $merged = array_merge($current, $patch);
  $normalized = user_normalize_settings_payload($merged);
  $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if (!is_string($json)) {
    throw new RuntimeException('Could not encode settings.');
  }
  $stmt = $db->prepare('UPDATE users SET settings_json = ? WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('si', $json, $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
  return $normalized;
}

/**
 * @return array{ok: bool, error?: string}
 */
function user_update_profile_fields(
  mysqli $db,
  string $schema,
  int $userId,
  string $fullName,
  string $email,
  string $phone
): array {
  ensure_user_account_settings_columns($db, $schema);

  $fullName = trim($fullName);
  $email = trim($email);
  $phone = trim($phone);

  $fnLen = function_exists('mb_strlen') ? mb_strlen($fullName, 'UTF-8') : strlen($fullName);
  if ($fullName === '' || $fnLen > 150) {
    return ['ok' => false, 'error' => 'Please enter a valid display name (max 150 characters).'];
  }
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'error' => 'Please enter a valid email address.'];
  }
  $phLen = function_exists('mb_strlen') ? mb_strlen($phone, 'UTF-8') : strlen($phone);
  if ($phLen > 64) {
    return ['ok' => false, 'error' => 'Phone number is too long.'];
  }

  $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('si', $email, $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $dup = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if ($dup) {
    return ['ok' => false, 'error' => 'That email address is already in use.'];
  }

  $up = $db->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
  if (!$up) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $up->bind_param('sssi', $fullName, $email, $phone, $userId);
  if (!$up->execute()) {
    $up->close();
    return ['ok' => false, 'error' => 'Could not update profile.'];
  }
  $up->close();

  return ['ok' => true];
}

function user_verify_password(mysqli $db, int $userId, string $password): bool {
  $stmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->store_result();
  $hash = '';
  $stmt->bind_result($hash);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found || !is_string($hash) || $hash === '') {
    return false;
  }
  return password_verify($password, $hash);
}

function user_deactivate_own_account(mysqli $db, string $schema, int $userId): void {
  ensure_user_admin_security_columns($db, $schema);
  $stmt = $db->prepare('UPDATE users SET is_active = 0, session_version = session_version + 1 WHERE id = ?');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $stmt->bind_param('i', $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

function user_delete_own_account(mysqli $db, string $schema, int $userId): void {
  ensure_user_admin_security_columns($db, $schema);
  ensure_user_account_settings_columns($db, $schema);

  $token = bin2hex(random_bytes(8));
  $newEmail = 'deleted.' . $userId . '.' . $token . '@invalid.local';
  $stmt = $db->prepare(
    'UPDATE users SET is_active = 0, full_name = ?, email = ?, phone = NULL, settings_json = NULL, session_version = session_version + 1 WHERE id = ?'
  );
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $name = 'Deleted user';
  $stmt->bind_param('ssi', $name, $newEmail, $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new RuntimeException('update failed: ' . $db->error);
  }
  $stmt->close();
}

// ---------------------------------------------------------------------------
// Support tickets (support.php + admin-help-center.php)
// ---------------------------------------------------------------------------

function ensure_support_tickets_table(mysqli $db): void {
  // Run on the active connection schema (must match $DB_NAME from db-config.php).
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `ticket_ref` VARCHAR(32) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `issue_type` VARCHAR(32) NOT NULL,
  `priority` VARCHAR(16) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_ticket_ref` (`ticket_ref`),
  KEY `idx_support_user` (`user_id`),
  KEY `idx_support_status` (`status`),
  KEY `idx_support_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  if (!@$db->query($sql) && $db->errno) {
    error_log('[support_tickets DDL] ' . $db->errno . ': ' . $db->error);
  }
}

function user_support_generate_ticket_ref(mysqli $db): string {
  ensure_support_tickets_table($db);
  for ($i = 0; $i < 20; $i++) {
    $ref = 'CRY-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $stmt = $db->prepare('SELECT id FROM support_tickets WHERE ticket_ref = ? LIMIT 1');
    if (!$stmt) {
      break;
    }
    $stmt->bind_param('s', $ref);
    $stmt->execute();
    $stmt->store_result();
    $taken = $stmt->num_rows > 0;
    $stmt->free_result();
    $stmt->close();
    if (!$taken) {
      return $ref;
    }
  }
  return 'CRY-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * @return array{ok: bool, id?: int, ticket_ref?: string, error?: string}
 */
function user_support_create_ticket(
  mysqli $db,
  int $userId,
  string $fullName,
  string $email,
  string $issueType,
  string $priority,
  string $subject,
  string $message
): array {
  ensure_support_tickets_table($db);
  if ($userId <= 0) {
    return ['ok' => false, 'error' => 'auth'];
  }
  $fullName = trim($fullName);
  $email = trim($email);
  $issueType = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '', $issueType) ?? ''));
  $priority = strtolower(trim($priority));
  $subject = trim($subject);
  $message = trim($message);

  $allowedTypes = ['account', 'deposit', 'withdrawal', 'kyc', 'security', 'other'];
  if (!in_array($issueType, $allowedTypes, true)) {
    $issueType = 'other';
  }
  $allowedPri = ['low', 'medium', 'high'];
  if (!in_array($priority, $allowedPri, true)) {
    $priority = 'medium';
  }
  $fnLen = function_exists('mb_strlen') ? mb_strlen($fullName, 'UTF-8') : strlen($fullName);
  if ($fullName === '' || $fnLen > 150) {
    return ['ok' => false, 'error' => 'name'];
  }
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'error' => 'email'];
  }
  $subLen = function_exists('mb_strlen') ? mb_strlen($subject, 'UTF-8') : strlen($subject);
  $msgLen = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
  if ($subject === '' || $subLen > 255) {
    return ['ok' => false, 'error' => 'subject'];
  }
  if ($message === '' || $msgLen > 20000) {
    return ['ok' => false, 'error' => 'message'];
  }

  $ref = user_support_generate_ticket_ref($db);
  $status = 'open';

  $stmt = $db->prepare(
    'INSERT INTO support_tickets (user_id, ticket_ref, full_name, email, issue_type, priority, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
  );
  if (!$stmt) {
    return ['ok' => false, 'error' => 'db'];
  }
  $stmt->bind_param(
    'issssssss',
    $userId,
    $ref,
    $fullName,
    $email,
    $issueType,
    $priority,
    $subject,
    $message,
    $status
  );
  if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    error_log('[support_tickets INSERT] ' . $db->errno . ': ' . $db->error . ' stmt: ' . $err);
    return ['ok' => false, 'error' => 'insert'];
  }
  $newId = (int)$stmt->insert_id;
  $stmt->close();
  return ['ok' => true, 'id' => $newId, 'ticket_ref' => $ref];
}

/**
 * @return list<array{id:int,ticket_ref:string,subject:string,status:string,created_at:string,priority:string,issue_type:string}>
 */
function user_support_list_for_user(mysqli $db, int $userId, int $limit = 25): array {
  ensure_support_tickets_table($db);
  $limit = max(1, min(100, $limit));
  $stmt = $db->prepare(
    'SELECT id, ticket_ref, subject, status, created_at, priority, issue_type FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit
  );
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('i', $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    return [];
  }
  $rows = [];
  $res = $stmt->get_result();
  if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
      $rows[] = [
        'id' => (int)$row['id'],
        'ticket_ref' => (string)$row['ticket_ref'],
        'subject' => (string)$row['subject'],
        'status' => (string)$row['status'],
        'created_at' => (string)$row['created_at'],
        'priority' => (string)$row['priority'],
        'issue_type' => (string)$row['issue_type'],
      ];
    }
  } else {
    // mysqlnd missing: get_result() unavailable — use bind_result.
    $stmt->store_result();
    $id = 0;
    $ticketRef = $subject = $status = $createdAt = $priority = $issueType = '';
    $stmt->bind_result($id, $ticketRef, $subject, $status, $createdAt, $priority, $issueType);
    while ($stmt->fetch()) {
      $rows[] = [
        'id' => (int)$id,
        'ticket_ref' => (string)$ticketRef,
        'subject' => (string)$subject,
        'status' => (string)$status,
        'created_at' => (string)$createdAt,
        'priority' => (string)$priority,
        'issue_type' => (string)$issueType,
      ];
    }
    $stmt->free_result();
  }
  $stmt->close();
  return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function admin_support_list_all(mysqli $db, int $limit = 300): array {
  ensure_support_tickets_table($db);
  $limit = max(1, min(500, $limit));
  $r = $db->query(
    'SELECT t.id, t.user_id, t.ticket_ref, t.full_name, t.email, t.issue_type, t.priority, t.subject, t.message, t.status, t.created_at, t.updated_at
     FROM support_tickets t
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT ' . (int)$limit
  );
  if (!$r) {
    error_log('[admin_support_list_all] ' . $db->errno . ': ' . $db->error);
    return [];
  }
  $rows = [];
  while ($row = $r->fetch_assoc()) {
    $rows[] = [
      'id' => (int)$row['id'],
      'user_id' => (int)$row['user_id'],
      'ticket_ref' => (string)$row['ticket_ref'],
      'full_name' => (string)$row['full_name'],
      'email' => (string)$row['email'],
      'issue_type' => (string)$row['issue_type'],
      'priority' => (string)$row['priority'],
      'subject' => (string)$row['subject'],
      'message' => (string)$row['message'],
      'status' => (string)$row['status'],
      'created_at' => (string)$row['created_at'],
      'updated_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : '',
    ];
  }
  $r->free();
  return $rows;
}

/**
 * @return array<string, int>
 */
function admin_support_status_counts(mysqli $db): array {
  ensure_support_tickets_table($db);
  $out = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
  $r = @$db->query('SELECT status, COUNT(*) AS c FROM support_tickets GROUP BY status');
  if ($r) {
    while ($row = $r->fetch_assoc()) {
      $st = (string)($row['status'] ?? '');
      $c = (int)($row['c'] ?? 0);
      if (isset($out[$st])) {
        $out[$st] = $c;
      }
    }
    $r->free();
  }
  $r2 = @$db->query('SELECT COUNT(*) AS n FROM support_tickets');
  if ($r2 && ($row = $r2->fetch_assoc())) {
    $out['total'] = (int)($row['n'] ?? 0);
    $r2->free();
  }
  return $out;
}

function admin_support_update_status(mysqli $db, int $ticketId, string $status): bool {
  ensure_support_tickets_table($db);
  $status = strtolower(trim($status));
  $allowed = ['open', 'in_progress', 'resolved', 'closed'];
  if (!in_array($status, $allowed, true)) {
    return false;
  }
  $stmt = $db->prepare('UPDATE support_tickets SET status = ? WHERE id = ?');
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('si', $status, $ticketId);
  $ok = $stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

// =============================================================================
// Asset portfolio (wallet assets) – DB-driven balances + live market data
// =============================================================================

function ensure_asset_portfolio_schema(mysqli $db): void {
  $sqlCatalog = <<<SQL
CREATE TABLE IF NOT EXISTS `asset_catalog` (
  `asset_id` VARCHAR(64) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `symbol` VARCHAR(16) NOT NULL,
  `network` VARCHAR(24) NOT NULL DEFAULT '',
  `coingecko_id` VARCHAR(64) NOT NULL,
  `icon_url` VARCHAR(255) NULL,
  `gas_asset_id` VARCHAR(64) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`asset_id`),
  KEY `idx_symbol` (`symbol`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sqlCatalog);

  $sqlBalances = <<<SQL
CREATE TABLE IF NOT EXISTS `user_asset_balances` (
  `user_id` INT UNSIGNED NOT NULL,
  `asset_id` VARCHAR(64) NOT NULL,
  `balance` DECIMAL(36,18) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `asset_id`),
  KEY `idx_asset` (`asset_id`),
  CONSTRAINT `fk_uab_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_catalog` (`asset_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sqlBalances);

  $sqlLedger = <<<SQL
CREATE TABLE IF NOT EXISTS `user_asset_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `asset_id` VARCHAR(64) NOT NULL,
  `direction` ENUM('credit','debit') NOT NULL,
  `amount` DECIMAL(36,18) NOT NULL,
  `balance_after` DECIMAL(36,18) NOT NULL,
  `meta` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_asset_created` (`asset_id`, `created_at`),
  CONSTRAINT `fk_ual_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_catalog` (`asset_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sqlLedger);

  $sqlPrices = <<<SQL
CREATE TABLE IF NOT EXISTS `asset_market_cache` (
  `coingecko_id` VARCHAR(64) NOT NULL,
  `price_usd` DECIMAL(18,8) NOT NULL,
  `change_24h_pct` DECIMAL(9,4) NOT NULL DEFAULT 0,
  `icon_url` VARCHAR(255) NULL,
  `fetched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`coingecko_id`),
  KEY `idx_fetched` (`fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sqlPrices);

  /** @var string $DB_NAME */
  global $DB_NAME;
  if (isset($DB_NAME) && is_string($DB_NAME) && $DB_NAME !== '') {
    ensure_asset_catalog_is_nft_column($db, (string)$DB_NAME);
  }

  asset_catalog_seed_defaults($db);
  asset_catalog_merge_extra_catalog_rows($db);
  asset_catalog_merge_default_nft_assets($db);
}

/**
 * Adds wallet assets (e.g. USDT BEP20, XRP) that were added after first deploy.
 */
function asset_catalog_merge_extra_catalog_rows(mysqli $db): void {
  $chk = $db->prepare('SELECT 1 FROM asset_catalog WHERE asset_id = ? LIMIT 1');
  if (!$chk) {
    return;
  }
  $merge = static function (mysqli $db, mysqli_stmt $chk, string $assetId, string $name, string $symbol, string $network, string $cg, string $icon, ?string $gas, int $active, int $sort): void {
    $chk->bind_param('s', $assetId);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->free_result();
    if ($exists) {
      return;
    }
    if ($gas === null) {
      $ins = $db->prepare(
        'INSERT INTO asset_catalog (asset_id, name, symbol, network, coingecko_id, icon_url, gas_asset_id, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)'
      );
      if ($ins) {
        $ins->bind_param('ssssssii', $assetId, $name, $symbol, $network, $cg, $icon, $active, $sort);
        @$ins->execute();
        $ins->close();
      }
    } else {
      $ins = $db->prepare(
        'INSERT INTO asset_catalog (asset_id, name, symbol, network, coingecko_id, icon_url, gas_asset_id, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      if ($ins) {
        $ins->bind_param('sssssssii', $assetId, $name, $symbol, $network, $cg, $icon, $gas, $active, $sort);
        @$ins->execute();
        $ins->close();
      }
    }
  };

  $merge($db, $chk, 'usdt_bep20', 'USDT', 'USDT', 'BEP20', 'tether', 'https://assets.coincap.io/assets/icons/usdt@2x.png', 'bnb', 1, 25);
  $merge($db, $chk, 'xrp', 'XRP', 'XRP', '', 'ripple', 'https://assets.coincap.io/assets/icons/xrp@2x.png', null, 1, 95);
  $chk->close();
}

/**
 * Ensures a few catalog rows exist with is_nft=1 so the NFT tab is not empty on fresh installs.
 * Uses UPSERT so existing databases pick up the flag if the row was added earlier without is_nft.
 */
function asset_catalog_merge_default_nft_assets(mysqli $db): void {
  /** @var string $DB_NAME */
  global $DB_NAME;
  if (!isset($DB_NAME) || !is_string($DB_NAME) || $DB_NAME === '') {
    return;
  }
  if (!user_schema_column_exists($db, (string)$DB_NAME, 'asset_catalog', 'is_nft')) {
    return;
  }

  $rows = [
    [
      'nft_blur',
      'BLUR (NFT marketplace)',
      'BLUR',
      'Ethereum',
      'blur',
      'https://assets.coingecko.com/coins/images/28453/large/blur.png',
      'eth',
      1,
      25,
      1,
    ],
    [
      'nft_looksrare',
      'LooksRare (NFT)',
      'LOOKS',
      'Ethereum',
      'looksrare',
      'https://assets.coingecko.com/coins/images/22173/large/circle-black.png',
      'eth',
      1,
      26,
      1,
    ],
  ];

  $sql = <<<SQL
INSERT INTO asset_catalog
  (asset_id, name, symbol, network, coingecko_id, icon_url, gas_asset_id, is_active, sort_order, is_nft)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
  is_nft = 1
SQL;

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return;
  }
  foreach ($rows as $d) {
    [$assetId, $name, $symbol, $network, $cg, $icon, $gas, $active, $sort, $isNft] = $d;
    $stmt->bind_param(
      'sssssssiii',
      $assetId,
      $name,
      $symbol,
      $network,
      $cg,
      $icon,
      $gas,
      $active,
      $sort,
      $isNft,
    );
    @$stmt->execute();
  }
  $stmt->close();
}

function ensure_asset_catalog_is_nft_column(mysqli $db, string $schema): void {
  if (user_schema_column_exists($db, $schema, 'asset_catalog', 'is_nft')) {
    return;
  }
  @$db->query('ALTER TABLE `asset_catalog` ADD COLUMN `is_nft` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sort_order`');
}

function ensure_network_fee_rules_schema(mysqli $db): void {
  ensure_asset_portfolio_schema($db);

  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `network_fee_rules` (
  `network_code` VARCHAR(24) NOT NULL,
  `gas_asset_id` VARCHAR(64) NOT NULL,
  `gas_fee_flat` DECIMAL(36,18) NOT NULL DEFAULT 0,
  `asset_fee_pct` DECIMAL(10,6) NOT NULL DEFAULT 0,
  `min_gas_reserve` DECIMAL(36,18) NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`network_code`),
  KEY `idx_enabled` (`enabled`),
  CONSTRAINT `fk_nfr_gas_asset` FOREIGN KEY (`gas_asset_id`) REFERENCES `asset_catalog` (`asset_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);

  $sql2 = <<<SQL
CREATE TABLE IF NOT EXISTS `asset_network_fee_overrides` (
  `asset_id` VARCHAR(64) NOT NULL,
  `network_code` VARCHAR(24) NOT NULL,
  `gas_fee_flat` DECIMAL(36,18) NULL,
  `asset_fee_pct` DECIMAL(10,6) NULL,
  `min_gas_reserve` DECIMAL(36,18) NULL,
  `enabled` TINYINT(1) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`asset_id`, `network_code`),
  CONSTRAINT `fk_anfo_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_catalog` (`asset_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql2);

  // Seed defaults if missing (idempotent).
  $seed = [
    ['TRC20', 'trx', 2.0, 0.0, 1.0, 1],
    ['ERC20', 'eth', 0.002, 0.0, 0.001, 1],
    ['BTC', 'btc', 0.0002, 0.0, 0.0001, 1],
    ['BEP20', 'bnb', 0.0005, 0.0, 0.0002, 1],
    ['POLYGON', 'matic', 0.1, 0.0, 0.05, 1],
    ['SOL', 'sol', 0.0005, 0.0, 0.0001, 1],
    ['DOT', 'dot', 0.02, 0.0, 0.01, 1],
    ['XRP', 'xrp', 0.00002, 0.0, 0.0, 1],
  ];
  $stmt = $db->prepare('INSERT IGNORE INTO network_fee_rules (network_code, gas_asset_id, gas_fee_flat, asset_fee_pct, min_gas_reserve, enabled) VALUES (?, ?, ?, ?, ?, ?)');
  if ($stmt) {
    foreach ($seed as $r) {
      [$net, $gasId, $flat, $pct, $reserve, $en] = $r;
      $stmt->bind_param('ssdddi', $net, $gasId, $flat, $pct, $reserve, $en);
      @$stmt->execute();
    }
    $stmt->close();
  }
}

function normalize_network_code(string $network): string {
  $n = strtoupper(trim($network));
  if ($n === '') return '';
  if (str_contains($n, 'TRC')) return 'TRC20';
  if (str_contains($n, 'ERC')) return 'ERC20';
  if (str_contains($n, 'BEP')) return 'BEP20';
  if (str_contains($n, 'POLY')) return 'POLYGON';
  if ($n === 'SOL' || str_contains($n, 'SOLANA')) return 'SOL';
  if ($n === 'BTC' || str_contains($n, 'BITCOIN')) return 'BTC';
  if ($n === 'DOT') return 'DOT';
  if ($n === 'XRP') return 'XRP';
  return preg_replace('/[^A-Z0-9_]/', '', $n) ?? '';
}

/**
 * Map trading pair base + network to wallet catalog asset_id (for fee rules).
 */
function trade_infer_catalog_asset_id(string $tradeAssetUpper, string $networkRaw): string {
  $a = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $tradeAssetUpper) ?? '');
  $n = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $networkRaw) ?? '');
  if ($a === 'USDT') {
    if (str_contains($n, 'ERC')) {
      return 'usdt_erc20';
    }
    if (str_contains($n, 'TRC')) {
      return 'usdt_trc20';
    }
    if (str_contains($n, 'BEP')) {
      return 'usdt_bep20';
    }

    return 'usdt_bep20';
  }
  $map = [
    'BTC' => 'btc',
    'ETH' => 'eth',
    'SOL' => 'sol',
    'BNB' => 'bnb',
    'MATIC' => 'matic',
    'DOT' => 'dot',
    'TRX' => 'trx',
    'XRP' => 'xrp',
  ];

  return $map[$a] ?? strtolower($a);
}

/**
 * @return array{
 *   ok:bool,
 *   error?:string,
 *   network_code?:string,
 *   asset_id?:string,
 *   asset_symbol?:string,
 *   gas_asset_id?:string,
 *   gas_symbol?:string,
 *   gas_fee_flat?:float,
 *   asset_fee_pct?:float,
 *   asset_fee_amount?:float,
 *   gas_fee_amount?:float,
 *   min_gas_reserve?:float
 * }
 */
function user_quote_network_fees(mysqli $db, int $userId, string $assetId, string $network, float $amount): array {
  ensure_network_fee_rules_schema($db);
  $assetId = strtolower(trim($assetId));
  if ($assetId === '' || $userId <= 0 || !is_finite($amount) || $amount <= 0) {
    return ['ok' => false, 'error' => 'input'];
  }
  $net = normalize_network_code($network);

  // Load asset catalog (fallback network from catalog if client omitted).
  $stmtA = $db->prepare('SELECT symbol, network, gas_asset_id FROM asset_catalog WHERE asset_id = ? AND is_active = 1 LIMIT 1');
  if (!$stmtA) return ['ok' => false, 'error' => 'db'];
  $stmtA->bind_param('s', $assetId);
  $stmtA->execute();
  $stmtA->store_result();
  $sym = '';
  $netDefault = '';
  $gasFallback = null;
  $stmtA->bind_result($sym, $netDefault, $gasFallback);
  if (!$stmtA->fetch()) {
    $stmtA->close();
    return ['ok' => false, 'error' => 'asset'];
  }
  $stmtA->free_result();
  $stmtA->close();
  if ($net === '') {
    $net = normalize_network_code($netDefault);
  }
  if ($net === '') {
    return ['ok' => false, 'error' => 'network'];
  }

  // Base network rule.
  $stmtN = $db->prepare('SELECT gas_asset_id, gas_fee_flat, asset_fee_pct, min_gas_reserve, enabled FROM network_fee_rules WHERE network_code = ? LIMIT 1');
  if (!$stmtN) return ['ok' => false, 'error' => 'db'];
  $stmtN->bind_param('s', $net);
  $stmtN->execute();
  $stmtN->store_result();
  $gasId = '';
  $flat = 0.0;
  $pct = 0.0;
  $reserve = 0.0;
  $enabled = 1;
  $stmtN->bind_result($gasId, $flat, $pct, $reserve, $enabled);
  $has = $stmtN->fetch();
  $stmtN->free_result();
  $stmtN->close();
  if (!$has || (int)$enabled !== 1) {
    return ['ok' => false, 'error' => 'network_disabled'];
  }
  if ($gasId === '' && is_string($gasFallback) && $gasFallback !== '') {
    $gasId = strtolower($gasFallback);
  }
  if ($gasId === '') {
    // Default: network gas token is the asset itself (e.g. BTC network).
    $gasId = $assetId;
  }

  // Overrides per asset/network.
  $stmtO = $db->prepare('SELECT gas_fee_flat, asset_fee_pct, min_gas_reserve, enabled FROM asset_network_fee_overrides WHERE asset_id = ? AND network_code = ? LIMIT 1');
  if ($stmtO) {
    $stmtO->bind_param('ss', $assetId, $net);
    $stmtO->execute();
    $stmtO->store_result();
    $oFlat = null;
    $oPct = null;
    $oRes = null;
    $oEn = null;
    $stmtO->bind_result($oFlat, $oPct, $oRes, $oEn);
    if ($stmtO->fetch()) {
      if ($oEn !== null && (int)$oEn !== 1) {
        $stmtO->free_result();
        $stmtO->close();
        return ['ok' => false, 'error' => 'asset_network_disabled'];
      }
      if ($oFlat !== null) $flat = (float)$oFlat;
      if ($oPct !== null) $pct = (float)$oPct;
      if ($oRes !== null) $reserve = (float)$oRes;
    }
    $stmtO->free_result();
    $stmtO->close();
  }

  $gasFee = max(0.0, (float)$flat);
  $assetFeeAmt = 0.0;
  if (is_finite($pct) && $pct > 0) {
    $assetFeeAmt = max(0.0, $amount * $pct);
  }

  // Gas symbol.
  $stmtG = $db->prepare('SELECT symbol FROM asset_catalog WHERE asset_id = ? LIMIT 1');
  $gasSym = strtoupper($gasId);
  if ($stmtG) {
    $stmtG->bind_param('s', $gasId);
    $stmtG->execute();
    $stmtG->store_result();
    $tmp = '';
    $stmtG->bind_result($tmp);
    if ($stmtG->fetch() && $tmp !== '') {
      $gasSym = strtoupper($tmp);
    }
    $stmtG->free_result();
    $stmtG->close();
  }

  return [
    'ok' => true,
    'network_code' => $net,
    'asset_id' => $assetId,
    'asset_symbol' => strtoupper($sym),
    'gas_asset_id' => $gasId,
    'gas_symbol' => $gasSym,
    'gas_fee_flat' => (float)$flat,
    'asset_fee_pct' => (float)$pct,
    'asset_fee_amount' => $assetFeeAmt,
    'gas_fee_amount' => $gasFee,
    'min_gas_reserve' => (float)$reserve,
  ];
}

/**
 * Centralized mandatory validation.
 *
 * @return array{
 *   ok:bool,
 *   required_token_asset_id?:string,
 *   required_token_symbol?:string,
 *   gas_balance?:float,
 *   required_fee?:float,
 *   required_fee_flat?:float,
 *   min_gas_reserve?:float,
 *   alert?:string,
 *   code?:string
 * }
 */
function validateNetworkFeeToken(int $userId, string $assetId, string $network, float $feeAmount): array {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

  $assetId = strtolower(trim($assetId));
  if ($userId <= 0 || $assetId === '' || !is_finite($feeAmount) || $feeAmount < 0) {
    return ['ok' => false, 'code' => 'input', 'alert' => 'Invalid request.'];
  }

  try {
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    ensure_network_fee_rules_schema($db);

    $q = user_quote_network_fees($db, $userId, $assetId, $network, 1.0);
    if (!($q['ok'] ?? false)) {
      $db->close();
      return ['ok' => false, 'code' => 'network', 'alert' => 'Network fee rules unavailable.'];
    }

    $gasId = (string)($q['gas_asset_id'] ?? '');
    $gasSym = (string)($q['gas_symbol'] ?? strtoupper($gasId));
    $reserve = (float)($q['min_gas_reserve'] ?? 0.0);
    $required = (float)$feeAmount + max(0.0, $reserve);
    $bal = $gasId !== '' ? (float)getUserAssetBalance($userId, $gasId) : 0.0;

    if ($required > 0 && $bal + 1e-12 < $required) {
      $db->close();
      $msg = "You do not have enough {$gasSym} to proceed with this transaction.";
      if (strtoupper($gasSym) === 'BTC') {
        $msg = "You do not have enough BTC to cover the network fee.";
      }
      return [
        'ok' => false,
        'code' => 'insufficient_gas',
        'required_token_asset_id' => $gasId,
        'required_token_symbol' => strtoupper($gasSym),
        'gas_balance' => $bal,
        'required_fee' => $required,
        'required_fee_flat' => (float)$feeAmount,
        'min_gas_reserve' => $reserve,
        'alert' => $msg,
      ];
    }

    $db->close();
    return [
      'ok' => true,
      'required_token_asset_id' => $gasId,
      'required_token_symbol' => strtoupper($gasSym),
      'gas_balance' => $bal,
      'required_fee' => $required,
      'required_fee_flat' => (float)$feeAmount,
      'min_gas_reserve' => $reserve,
      'alert' => '',
    ];
  } catch (Throwable $e) {
    error_log('[validateNetworkFeeToken] ' . $e->getMessage());
    return ['ok' => false, 'code' => 'server', 'alert' => 'Validation failed. Try again.'];
  }
}

/**
 * Trade-withdrawal: debits spot_balance_usd only (trade account).
 * Wallet assets remain untouched; we still REQUIRE gas token sufficiency (hard stop).
 *
 * @return array{ok:bool, id?:int, error?:string, message?:string, trade_balance_usd?:string}
 */
function user_create_pending_trade_withdrawal(
  mysqli $db,
  string $schema,
  int $userId,
  string $assetId,
  float $cryptoAmount,
  string $network,
  string $toAddress,
  ?string $clientIp = null,
  ?string $userAgent = null,
  ?string $countryIso = null,
  ?string $countryName = null
): array {
  ensure_user_dashboard_schema($db, $schema);
  ensure_transactions_table($db);
  ensure_network_fee_rules_schema($db);

  $assetId = strtolower(trim($assetId));
  $toAddress = trim($toAddress);
  if ($userId <= 0) return ['ok' => false, 'error' => 'auth'];
  if ($assetId === '' || !preg_match('/^[a-z0-9_]+$/', $assetId)) return ['ok' => false, 'error' => 'asset'];
  if ($toAddress === '') return ['ok' => false, 'error' => 'address'];
  if (!is_finite($cryptoAmount) || $cryptoAmount <= 0) return ['ok' => false, 'error' => 'crypto_amount'];

  $quote = user_quote_network_fees($db, $userId, $assetId, $network, $cryptoAmount);
  if (!($quote['ok'] ?? false)) {
    return ['ok' => false, 'error' => (string)($quote['error'] ?? 'fee')];
  }
  $netCode = (string)($quote['network_code'] ?? '');
  $gasAssetId = (string)($quote['gas_asset_id'] ?? '');
  $gasSym = (string)($quote['gas_symbol'] ?? strtoupper($gasAssetId));
  $gasFeeAmt = (float)($quote['gas_fee_amount'] ?? 0.0);
  $feePct = (float)($quote['asset_fee_pct'] ?? 0.0);
  $reserve = (float)($quote['min_gas_reserve'] ?? 0.0);

  // HARD STOP: validate gas token sufficiency before any DB write.
  // Use the CURRENT db connection (no extra connection) to avoid falling into generic "Request failed".
  $requiredGas = max(0.0, $gasFeeAmt) + max(0.0, $reserve);
  if ($requiredGas > 0 && $gasAssetId !== '') {
    $g = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1');
    if (!$g) {
      return ['ok' => false, 'error' => 'exception'];
    }
    $g->bind_param('is', $userId, $gasAssetId);
    $g->execute();
    $g->store_result();
    $gStr = '0';
    $g->bind_result($gStr);
    $g->fetch();
    $g->free_result();
    $g->close();
    $gBalPre = (float)$gStr;
    if ($gBalPre + 1e-12 < $requiredGas) {
      return [
        'ok' => false,
        'error' => 'insufficient_gas',
        'message' => "You don't have Enough Network Fee To Proceed With This Transaction",
      ];
    }
  }

  // Live price for requested asset and gas token (USD) so trade balance can be debited correctly.
  $assetSym = (string)($quote['asset_symbol'] ?? '');
  if ($assetSym === '') {
    return ['ok' => false, 'error' => 'asset'];
  }
  $assetPx = getLiveAssetPrice($assetSym);
  $gasPx = $gasSym !== '' ? getLiveAssetPrice($gasSym) : 0.0;

  if (!is_finite($assetPx) || $assetPx <= 0) {
    return ['ok' => false, 'error' => 'price_unavailable'];
  }
  $usdValue = $cryptoAmount * $assetPx;
  $platformFeeUsd = ($feePct > 0 && is_finite($feePct)) ? max(0.0, $usdValue * $feePct) : 0.0;
  $gasFeeUsd = ($gasFeeAmt > 0 && $gasPx > 0) ? ($gasFeeAmt * $gasPx) : 0.0;
  $totalUsd = $usdValue + $platformFeeUsd + $gasFeeUsd;

  $db->begin_transaction();
  try {
    $u = $db->prepare('SELECT spot_balance_usd FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$u) throw new RuntimeException('db');
    $u->bind_param('i', $userId);
    $u->execute();
    $u->store_result();
    $spotStr = '0.00';
    $u->bind_result($spotStr);
    if (!$u->fetch()) {
      $u->free_result();
      $u->close();
      $db->rollback();
      return ['ok' => false, 'error' => 'user_not_found'];
    }
    $u->free_result();
    $u->close();

    $spot = (float)$spotStr;
    if ($spot + 1e-9 < $totalUsd) {
      $db->rollback();
      return ['ok' => false, 'error' => 'insufficient_trade_balance', 'trade_balance_usd' => number_format($spot, 2, '.', '')];
    }
    $newSpot = $spot - $totalUsd;
    $newSpotStr = number_format($newSpot, 2, '.', '');
    $up = $db->prepare('UPDATE users SET spot_balance_usd = ? WHERE id = ?');
    if (!$up) throw new RuntimeException('db');
    $up->bind_param('si', $newSpotStr, $userId);
    $up->execute();
    $up->close();

    $meta = [
      'source' => 'withdraw_page_trade',
      'network' => $netCode,
      'to_address' => $toAddress,
      'asset_id' => $assetId,
      'asset_symbol' => $assetSym,
      'crypto_amount' => $cryptoAmount,
      'price_usd' => $assetPx,
      'amount_usd' => $usdValue,
      'platform_fee_usd' => $platformFeeUsd,
      'gas_asset_id' => $gasAssetId,
      'gas_symbol' => strtoupper($gasSym),
      'gas_fee_amount' => $gasFeeAmt,
      'gas_fee_usd' => $gasFeeUsd,
      'min_gas_reserve' => $reserve,
      'reserved_trade_usd' => $totalUsd,
      'at' => date('c'),
    ];
    $ip = $clientIp !== null ? trim($clientIp) : '';
    $ua = $userAgent !== null ? trim($userAgent) : '';
    if ($ip !== '') $meta['ip'] = $ip;
    if ($ua !== '') $meta['ua'] = $ua;
    $cIso = $countryIso !== null ? strtoupper(trim($countryIso)) : '';
    $cName = $countryName !== null ? trim($countryName) : '';
    if ($cIso !== '') $meta['country_iso'] = $cIso;
    if ($cName !== '') $meta['country_name'] = $cName;
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    $type = 'withdrawal';
    $status = 'pending';
    $assetCol = strtoupper($assetId);
    $amountDb = number_format($usdValue, 8, '.', '');
    $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$ins) throw new RuntimeException('db');
    $ins->bind_param('isssss', $userId, $type, $assetCol, $amountDb, $status, $metaJson);
    $ins->execute();
    $txId = (int)$ins->insert_id;
    $ins->close();

    $db->commit();
    return ['ok' => true, 'id' => $txId, 'trade_balance_usd' => number_format($newSpot, 2, '.', '')];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[user_create_pending_trade_withdrawal] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * mysqli_stmt::bind_param with dynamic list (standalone; used in user area too).
 *
 * @param list<mixed> $params
 */
function user_stmt_bind_array(mysqli_stmt $stmt, string $types, array $params): bool {
  $bind = array_merge([$types], $params);
  $refs = [];
  foreach ($bind as $key => $_) {
    $refs[$key] = &$bind[$key];
  }
  return call_user_func_array([$stmt, 'bind_param'], $refs);
}

function asset_catalog_seed_defaults(mysqli $db): void {
  // Seed only if empty (keeps admin/custom assets intact).
  $r = @$db->query('SELECT COUNT(*) AS n FROM asset_catalog');
  $n = 0;
  if ($r && ($row = $r->fetch_assoc())) {
    $n = (int)($row['n'] ?? 0);
    $r->free();
  } elseif ($r) {
    $r->free();
  }
  if ($n > 0) {
    return;
  }

  $defaults = [
    ['usdt_trc20', 'USDT', 'USDT', 'TRC20', 'tether', 'https://assets.coincap.io/assets/icons/usdt@2x.png', 'trx', 1, 10],
    ['trx', 'TRX', 'TRX', '', 'tron', 'https://assets.coincap.io/assets/icons/trx@2x.png', null, 1, 20],
    ['usdt_erc20', 'USDT', 'USDT', 'ERC20', 'tether', 'https://assets.coincap.io/assets/icons/usdt@2x.png', 'eth', 1, 30],
    ['eth', 'Ethereum', 'ETH', '', 'ethereum', 'https://assets.coincap.io/assets/icons/eth@2x.png', null, 1, 40],
    ['btc', 'Bitcoin', 'BTC', '', 'bitcoin', 'https://assets.coincap.io/assets/icons/btc@2x.png', null, 1, 50],
    ['bnb', 'BNB', 'BNB', '', 'binancecoin', 'https://assets.coincap.io/assets/icons/bnb@2x.png', null, 1, 60],
    ['sol', 'Solana', 'SOL', '', 'solana', 'https://assets.coincap.io/assets/icons/sol@2x.png', null, 1, 70],
    ['matic', 'Polygon', 'MATIC', '', 'matic-network', 'https://assets.coincap.io/assets/icons/matic@2x.png', null, 1, 80],
    ['dot', 'Polkadot', 'DOT', '', 'polkadot', 'https://assets.coincap.io/assets/icons/dot@2x.png', null, 1, 90],
  ];

  $stmt = $db->prepare('INSERT INTO asset_catalog (asset_id, name, symbol, network, coingecko_id, icon_url, gas_asset_id, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
  if (!$stmt) {
    return;
  }
  foreach ($defaults as $d) {
    [$assetId, $name, $symbol, $network, $cg, $icon, $gas, $active, $sort] = $d;
    $stmt->bind_param('sssssssii', $assetId, $name, $symbol, $network, $cg, $icon, $gas, $active, $sort);
    @$stmt->execute();
  }
  $stmt->close();
}

/**
 * @return array{ok:bool, status:int, body:string}
 */
function portfolio_http_get(string $url, int $timeoutSeconds = 6): array {
  $timeoutSeconds = max(2, min(20, $timeoutSeconds));
  if (!function_exists('curl_init')) {
    $ctx = stream_context_create([
      'http' => [
        'timeout' => $timeoutSeconds,
        'method' => 'GET',
        'header' => "Accept: application/json\r\nUser-Agent: Crypterra/1.0\r\n",
      ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $ok = is_string($body) && $body !== '';
    return ['ok' => $ok, 'status' => $ok ? 200 : 0, 'body' => $ok ? $body : ''];
  }

  $ch = curl_init($url);
  if ($ch === false) {
    return ['ok' => false, 'status' => 0, 'body' => ''];
  }
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => $timeoutSeconds,
    CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: Crypterra/1.0'],
  ]);
  $body = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if (!is_string($body)) {
    return ['ok' => false, 'status' => $code, 'body' => ''];
  }
  return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'body' => $body];
}

/**
 * Fetch and cache live USD prices + 24h change for coingecko IDs.
 *
 * @param list<string> $coingeckoIds
 * @return array<string, array{price_usd:float, change_24h_pct:float}>
 */
function asset_market_get_quotes(mysqli $db, array $coingeckoIds, int $ttlSeconds = 60): array {
  ensure_asset_portfolio_schema($db);
  $ttlSeconds = max(15, min(900, $ttlSeconds));

  $ids = [];
  foreach ($coingeckoIds as $id) {
    $id = strtolower(trim((string)$id));
    if ($id !== '' && preg_match('/^[a-z0-9\-]+$/', $id)) {
      $ids[$id] = true;
    }
  }
  $ids = array_keys($ids);
  if ($ids === []) {
    return [];
  }

  // Read cache.
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('s', count($ids));
  $stmt = $db->prepare("SELECT coingecko_id, price_usd, change_24h_pct, icon_url, fetched_at FROM asset_market_cache WHERE coingecko_id IN ($placeholders)");
  $cached = [];
  $stale = [];
  $now = time();
  if ($stmt) {
    user_stmt_bind_array($stmt, $types, $ids);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res instanceof mysqli_result) {
      while ($row = $res->fetch_assoc()) {
        $cg = (string)($row['coingecko_id'] ?? '');
        $fetchedAt = (string)($row['fetched_at'] ?? '');
        $ts = $fetchedAt !== '' ? strtotime($fetchedAt) : 0;
        $isFresh = $ts > 0 && ($now - $ts) <= $ttlSeconds;
        $cached[$cg] = [
          'price_usd' => (float)($row['price_usd'] ?? 0),
          'change_24h_pct' => (float)($row['change_24h_pct'] ?? 0),
          'icon_url' => isset($row['icon_url']) ? (string)$row['icon_url'] : null,
          'fresh' => $isFresh,
        ];
      }
      $res->free();
    }
    $stmt->close();
  }

  foreach ($ids as $id) {
    if (!isset($cached[$id]) || !($cached[$id]['fresh'] ?? false)) {
      $stale[] = $id;
    }
  }

  if ($stale !== []) {
    $qs = implode(',', $stale);
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . rawurlencode($qs) . '&vs_currencies=usd&include_24hr_change=true';
    $http = portfolio_http_get($url, 8);
    if ($http['ok']) {
      $decoded = json_decode($http['body'], true);
      if (is_array($decoded)) {
        $up = $db->prepare('REPLACE INTO asset_market_cache (coingecko_id, price_usd, change_24h_pct, icon_url, fetched_at) VALUES (?, ?, ?, NULL, NOW())');
        foreach ($stale as $cgId) {
          $p = $decoded[$cgId]['usd'] ?? null;
          $c = $decoded[$cgId]['usd_24h_change'] ?? null;
          if (is_numeric($p)) {
            $price = (float)$p;
            $chg = is_numeric($c) ? (float)$c : 0.0;
            if ($up) {
              $up->bind_param('sdd', $cgId, $price, $chg);
              @$up->execute();
            }
            $cached[$cgId] = [
              'price_usd' => $price,
              'change_24h_pct' => $chg,
              'icon_url' => $cached[$cgId]['icon_url'] ?? null,
              'fresh' => true,
            ];
          }
        }
        if ($up) {
          $up->close();
        }
      }
    }
  }

  $out = [];
  foreach ($ids as $cgId) {
    if (isset($cached[$cgId])) {
      $out[$cgId] = [
        'price_usd' => (float)($cached[$cgId]['price_usd'] ?? 0),
        'change_24h_pct' => (float)($cached[$cgId]['change_24h_pct'] ?? 0),
      ];
    }
  }
  return $out;
}

/**
 * Single source of truth functions (required).
 * These wrappers open a short DB connection to keep call sites simple.
 */
function getUserAssetBalance(int $userId, string $assetId): string {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  ensure_asset_portfolio_schema($db);
  $assetId = strtolower(trim($assetId));
  if ($userId <= 0 || $assetId === '') {
    $db->close();
    return '0';
  }
  $stmt = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1');
  if (!$stmt) {
    $db->close();
    return '0';
  }
  $stmt->bind_param('is', $userId, $assetId);
  $stmt->execute();
  $stmt->store_result();
  $bal = '0';
  $stmt->bind_result($bal);
  $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  $db->close();
  return is_string($bal) ? $bal : '0';
}

/**
 * @param array<string, mixed> $meta
 */
function creditUserAsset(int $userId, string $assetId, float $amount, array $meta = []): bool {
  return user_asset_apply_delta($userId, $assetId, $amount, 'credit', $meta);
}

/**
 * @param array<string, mixed> $meta
 */
function debitUserAsset(int $userId, string $assetId, float $amount, array $meta = []): bool {
  return user_asset_apply_delta($userId, $assetId, $amount, 'debit', $meta);
}

/**
 * @param 'credit'|'debit' $direction
 * @param array<string, mixed> $meta
 */
function user_asset_apply_delta(int $userId, string $assetId, float $amount, string $direction, array $meta): bool {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  ensure_asset_portfolio_schema($db);

  $assetId = strtolower(trim($assetId));
  if ($userId <= 0 || $assetId === '' || !is_finite($amount) || $amount <= 0) {
    $db->close();
    return false;
  }
  $direction = strtolower($direction) === 'debit' ? 'debit' : 'credit';

  // Ensure asset exists + active.
  $chk = $db->prepare('SELECT asset_id FROM asset_catalog WHERE asset_id = ? AND is_active = 1 LIMIT 1');
  if (!$chk) {
    $db->close();
    return false;
  }
  $chk->bind_param('s', $assetId);
  $chk->execute();
  $chk->store_result();
  $exists = $chk->fetch();
  $chk->free_result();
  $chk->close();
  if (!$exists) {
    $db->close();
    return false;
  }

  $db->begin_transaction();
  try {
    $sel = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
    if (!$sel) {
      throw new RuntimeException('prepare');
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
    $new = $direction === 'credit' ? ($prev + $amount) : ($prev - $amount);
    if ($new < -1e-12) {
      $db->rollback();
      $db->close();
      return false;
    }
    if ($new < 0) {
      $new = 0.0;
    }

    $newStr = number_format($new, 18, '.', '');
    if ($found) {
      $up = $db->prepare('UPDATE user_asset_balances SET balance = ? WHERE user_id = ? AND asset_id = ?');
      if (!$up) {
        throw new RuntimeException('update');
      }
      $up->bind_param('sis', $newStr, $userId, $assetId);
      $up->execute();
      $up->close();
    } else {
      $ins = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?)');
      if (!$ins) {
        throw new RuntimeException('insert');
      }
      $ins->bind_param('iss', $userId, $assetId, $newStr);
      $ins->execute();
      $ins->close();
    }

    $metaJson = null;
    if ($meta !== []) {
      $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    $amtStr = number_format($amount, 18, '.', '');
    $afterStr = number_format($new, 18, '.', '');
    $led = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, ?, ?, ?, ?)');
    if ($led) {
      $led->bind_param('isssss', $userId, $assetId, $direction, $amtStr, $afterStr, $metaJson);
      $led->execute();
      $led->close();
    }

    $db->commit();
    $db->close();
    return true;
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[user_asset_apply_delta] ' . $e->getMessage());
    $db->close();
    return false;
  }
}

function getLiveAssetPrice(string $symbol): float {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  $symbol = strtoupper(trim($symbol));
  if ($symbol === '') {
    return 0.0;
  }
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  ensure_asset_portfolio_schema($db);
  $stmt = $db->prepare('SELECT coingecko_id FROM asset_catalog WHERE symbol = ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 1');
  if (!$stmt) {
    $db->close();
    return 0.0;
  }
  $stmt->bind_param('s', $symbol);
  $stmt->execute();
  $stmt->store_result();
  $cg = '';
  $stmt->bind_result($cg);
  $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if ($cg === '') {
    $db->close();
    return 0.0;
  }
  $quotes = asset_market_get_quotes($db, [$cg], 60);
  $db->close();
  return isset($quotes[$cg]) ? (float)$quotes[$cg]['price_usd'] : 0.0;
}

/**
 * Network fee for trade orders in USD (same rules as send/withdraw: flat gas in native units × live price).
 */
function user_trade_network_fee_usd(mysqli $db, int $userId, string $catalogAssetId, string $networkRaw): float {
  ensure_network_fee_rules_schema($db);
  $catalogAssetId = strtolower(trim($catalogAssetId));
  if ($catalogAssetId === '') {
    return 0.5;
  }
  $q = user_quote_network_fees($db, $userId, $catalogAssetId, $networkRaw, 1.0);
  if (!($q['ok'] ?? false)) {
    $n = strtoupper($networkRaw);

    return str_contains($n, 'ERC20') ? 3.5 : 0.5;
  }
  $gasFeeAmt = (float)($q['gas_fee_amount'] ?? 0.0);
  $gasSym = (string)($q['gas_symbol'] ?? '');
  if ($gasFeeAmt <= 0.0 || $gasSym === '') {
    return 0.0;
  }
  $gasPx = getLiveAssetPrice($gasSym);
  if (!is_finite($gasPx) || $gasPx <= 0.0) {
    $g = strtoupper($gasSym);
    if ($g === 'USDT' || $g === 'USDC' || $g === 'DAI') {
      $gasPx = 1.0;
    } else {
      $n = strtoupper($networkRaw);

      return str_contains($n, 'ERC20') ? 3.5 : 0.5;
    }
  }

  return max(0.0, round($gasFeeAmt * $gasPx, 2));
}

function getPortfolioValue(int $userId): float {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  ensure_asset_portfolio_schema($db);
  if ($userId <= 0) {
    $db->close();
    return 0.0;
  }
  $r = $db->query(
    'SELECT b.asset_id, b.balance, c.coingecko_id
     FROM user_asset_balances b
     INNER JOIN asset_catalog c ON c.asset_id = b.asset_id
     WHERE b.user_id = ' . (int)$userId . ' AND c.is_active = 1'
  );
  if (!$r) {
    $db->close();
    return 0.0;
  }
  $rows = [];
  $ids = [];
  while ($row = $r->fetch_assoc()) {
    $cg = (string)($row['coingecko_id'] ?? '');
    $bal = (float)($row['balance'] ?? 0);
    if ($cg !== '' && $bal > 0) {
      $rows[] = ['cg' => $cg, 'bal' => $bal];
      $ids[$cg] = true;
    }
  }
  $r->free();
  $quotes = asset_market_get_quotes($db, array_keys($ids), 60);
  $total = 0.0;
  foreach ($rows as $it) {
    $cg = $it['cg'];
    $bal = (float)$it['bal'];
    $px = isset($quotes[$cg]) ? (float)$quotes[$cg]['price_usd'] : 0.0;
    if ($px <= 0.0 && ($cg === 'tether' || $cg === 'usd-coin' || $cg === 'dai')) {
      $px = 1.0;
    }
    $total += $bal * $px;
  }
  $db->close();
  return $total;
}

