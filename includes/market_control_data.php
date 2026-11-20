<?php
declare(strict_types=1);

/**
 * Admin-configured trading pairs and prices.
 *
 * Writes: Admin/admin-market-control.php + Admin/api/market_control_api.php (pairs, fees, trading_enabled).
 * Reads: Userprofile/trade.php ?ajax=market_config → market_control_public_snapshot(); order execution uses
 * market_control_* helpers from user_db.php (user_place_trade_order, etc.). Same MySQL tables — no duplicate config.
 */

function market_control_normalize_symbol(string $input): string {
  $input = strtoupper(trim($input));
  if ($input === '') {
    return '';
  }
  if (str_contains($input, '/')) {
    $parts = explode('/', $input, 2);
    $b = preg_replace('/[^A-Z0-9]/', '', (string)($parts[0] ?? ''));
    $q = preg_replace('/[^A-Z0-9]/', '', (string)($parts[1] ?? 'USDT'));
    return $b . $q;
  }
  return (string)preg_replace('/[^A-Z0-9]/', '', $input);
}

function market_control_ensure_schema(mysqli $db): void {
  $sqlSettings = <<<SQL
CREATE TABLE IF NOT EXISTS `market_settings` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  `trading_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `global_fee_pct` DECIMAL(12,6) NOT NULL DEFAULT 0.100000,
  `auto_fluctuation_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `volatility` VARCHAR(16) NOT NULL DEFAULT 'medium',
  `fluctuation_interval_sec` INT UNSIGNED NOT NULL DEFAULT 30,
  `last_fluctuation_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sqlSettings);
  @$db->query('INSERT IGNORE INTO market_settings (id, trading_enabled, global_fee_pct) VALUES (1, 1, 0.1)');

  $sqlPairs = <<<SQL
CREATE TABLE IF NOT EXISTS `market_pairs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `base_asset` VARCHAR(20) NOT NULL,
  `quote_asset` VARCHAR(20) NOT NULL DEFAULT 'USDT',
  `symbol` VARCHAR(40) NOT NULL,
  `price_usd` DECIMAL(24,8) NOT NULL,
  `change_24h_pct` DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
  `volume_24h_usd` DECIMAL(24,4) NOT NULL DEFAULT 0.0000,
  `fee_pct` DECIMAL(12,6) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_market_symbol` (`symbol`),
  KEY `idx_market_base` (`base_asset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sqlPairs);

  $sqlLog = <<<SQL
CREATE TABLE IF NOT EXISTS `market_activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `icon` VARCHAR(32) NOT NULL DEFAULT 'price',
  `title` VARCHAR(255) NOT NULL,
  `detail` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_market_act_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sqlLog);

  market_control_seed_if_empty($db);
  market_control_merge_extra_pairs($db);
}

/**
 * Adds trading pairs that ship with the wallet catalog (MATIC, DOT, TRX, USDT) if not already present.
 */
function market_control_merge_extra_pairs(mysqli $db): void {
  $extras = [
    ['USDT', 'USDT', 'USDTUSDT', 1.0, 0.0, 500000000.0, 15],
    ['MATIC', 'USDT', 'MATICUSDT', 0.85, 0.0, 12000000.0, 16],
    ['DOT', 'USDT', 'DOTUSDT', 6.5, 0.0, 8000000.0, 17],
    ['TRX', 'USDT', 'TRXUSDT', 0.12, 0.0, 9000000.0, 18],
  ];
  $chk = $db->prepare('SELECT id FROM market_pairs WHERE symbol = ? LIMIT 1');
  if (!$chk) {
    return;
  }
  $ins = $db->prepare(
    'INSERT INTO market_pairs (base_asset, quote_asset, symbol, price_usd, change_24h_pct, volume_24h_usd, fee_pct, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, NULL, 1, ?)'
  );
  if (!$ins) {
    $chk->close();
    return;
  }
  foreach ($extras as $row) {
    [$b, $q, $sym, $px, $ch, $vol, $ord] = $row;
    $chk->bind_param('s', $sym);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->free_result();
    if ($exists) {
      continue;
    }
    $ins->bind_param('sssdddi', $b, $q, $sym, $px, $ch, $vol, $ord);
    @$ins->execute();
  }
  $ins->close();
  $chk->close();
}

function market_control_seed_if_empty(mysqli $db): void {
  $r = @$db->query('SELECT COUNT(*) AS c FROM market_pairs');
  if (!$r) {
    return;
  }
  $row = $r->fetch_assoc();
  $r->free();
  $c = (int)($row['c'] ?? 0);
  if ($c > 0) {
    return;
  }

  $defaults = [
    ['BTC', 'USDT', 'BTCUSDT', 68245.50, 1.25, 125000000.0],
    ['ETH', 'USDT', 'ETHUSDT', 3420.00, -0.40, 78000000.0],
    ['SOL', 'USDT', 'SOLUSDT', 145.20, 3.10, 42000000.0],
    ['BNB', 'USDT', 'BNBUSDT', 612.00, 0.85, 19000000.0],
    ['XRP', 'USDT', 'XRPUSDT', 0.5520, -1.10, 31000000.0],
  ];

  $ins = $db->prepare(
    'INSERT INTO market_pairs (base_asset, quote_asset, symbol, price_usd, change_24h_pct, volume_24h_usd, fee_pct, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, NULL, 1, ?)'
  );
  if (!$ins) {
    return;
  }
  $order = 0;
  foreach ($defaults as $row) {
    $order++;
    $b = $row[0];
    $q = $row[1];
    $sym = $row[2];
    $px = $row[3];
    $ch = $row[4];
    $vol = $row[5];
    $ord = $order;
    $ins->bind_param('sssdddi', $b, $q, $sym, $px, $ch, $vol, $ord);
    @$ins->execute();
  }
  $ins->close();

  @$db->query("INSERT IGNORE INTO market_settings (id, trading_enabled, global_fee_pct, auto_fluctuation_enabled, volatility, fluctuation_interval_sec) VALUES (1, 1, 0.1, 0, 'medium', 30)");
}

/**
 * @return array{
 *   trading_enabled: bool,
 *   global_fee_pct: float,
 *   auto_fluctuation_enabled: bool,
 *   volatility: string,
 *   fluctuation_interval_sec: int,
 *   last_fluctuation_at: ?string,
 *   updated_at: string
 * }
 */
function market_control_get_settings(mysqli $db): array {
  market_control_ensure_schema($db);
  $r = @$db->query('SELECT trading_enabled, global_fee_pct, auto_fluctuation_enabled, volatility, fluctuation_interval_sec, last_fluctuation_at, updated_at FROM market_settings WHERE id = 1 LIMIT 1');
  if (!$r || $r->num_rows === 0) {
    return [
      'trading_enabled' => true,
      'global_fee_pct' => 0.1,
      'auto_fluctuation_enabled' => false,
      'volatility' => 'medium',
      'fluctuation_interval_sec' => 30,
      'last_fluctuation_at' => null,
      'updated_at' => gmdate('c'),
    ];
  }
  $row = $r->fetch_assoc();
  $r->free();
  return [
    'trading_enabled' => ((int)($row['trading_enabled'] ?? 1)) === 1,
    'global_fee_pct' => (float)($row['global_fee_pct'] ?? 0.1),
    'auto_fluctuation_enabled' => ((int)($row['auto_fluctuation_enabled'] ?? 0)) === 1,
    'volatility' => (string)($row['volatility'] ?? 'medium'),
    'fluctuation_interval_sec' => max(5, (int)($row['fluctuation_interval_sec'] ?? 30)),
    'last_fluctuation_at' => isset($row['last_fluctuation_at']) && $row['last_fluctuation_at'] !== null ? (string)$row['last_fluctuation_at'] : null,
    'updated_at' => (string)($row['updated_at'] ?? ''),
  ];
}

/**
 * @param array<string, mixed> $patch
 */
function market_control_save_settings(mysqli $db, array $patch): void {
  market_control_ensure_schema($db);
  $cur = market_control_get_settings($db);
  $trading = array_key_exists('trading_enabled', $patch) ? (bool)$patch['trading_enabled'] : $cur['trading_enabled'];
  $gf = array_key_exists('global_fee_pct', $patch) ? (float)$patch['global_fee_pct'] : $cur['global_fee_pct'];
  $gf = max(0.0, min(5.0, $gf));
  $auto = array_key_exists('auto_fluctuation_enabled', $patch) ? (bool)$patch['auto_fluctuation_enabled'] : $cur['auto_fluctuation_enabled'];
  $vol = array_key_exists('volatility', $patch) && is_string($patch['volatility']) ? $patch['volatility'] : $cur['volatility'];
  if (!in_array($vol, ['low', 'medium', 'high'], true)) {
    $vol = 'medium';
  }
  $interval = array_key_exists('fluctuation_interval_sec', $patch) ? (int)$patch['fluctuation_interval_sec'] : $cur['fluctuation_interval_sec'];
  $interval = max(5, min(3600, $interval));

  $st = $db->prepare(
    'INSERT INTO market_settings (id, trading_enabled, global_fee_pct, auto_fluctuation_enabled, volatility, fluctuation_interval_sec)
     VALUES (1, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE trading_enabled = VALUES(trading_enabled), global_fee_pct = VALUES(global_fee_pct), auto_fluctuation_enabled = VALUES(auto_fluctuation_enabled), volatility = VALUES(volatility), fluctuation_interval_sec = VALUES(fluctuation_interval_sec)'
  );
  if (!$st) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  $te = $trading ? 1 : 0;
  $ae = $auto ? 1 : 0;
  $st->bind_param('idisi', $te, $gf, $ae, $vol, $interval);
  $st->execute();
  $st->close();
}

function market_control_trading_enabled(mysqli $db): bool {
  return market_control_get_settings($db)['trading_enabled'];
}

function market_control_lookup_price(mysqli $db, string $baseAsset): ?float {
  market_control_ensure_schema($db);
  $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $baseAsset) ?? '');
  if ($base === '' || $base === 'USDT') {
    return $base === 'USDT' ? 1.0 : null;
  }
  $stmt = $db->prepare('SELECT price_usd FROM market_pairs WHERE base_asset = ? AND quote_asset = ? AND is_active = 1 LIMIT 1');
  if (!$stmt) {
    return null;
  }
  $q = 'USDT';
  $stmt->bind_param('ss', $base, $q);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($p);
  $ok = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$ok) {
    return null;
  }
  $price = (float)$p;
  return (is_finite($price) && $price > 0) ? $price : null;
}

function market_control_get_fee_pct(mysqli $db, string $baseAsset): float {
  market_control_ensure_schema($db);
  $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $baseAsset) ?? '');
  $global = market_control_get_settings($db)['global_fee_pct'];
  if ($base === '') {
    return $global;
  }
  $stmt = $db->prepare('SELECT fee_pct FROM market_pairs WHERE base_asset = ? AND quote_asset = ? AND is_active = 1 LIMIT 1');
  if (!$stmt) {
    return $global;
  }
  $q = 'USDT';
  $stmt->bind_param('ss', $base, $q);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($fp);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found || $fp === null) {
    return $global;
  }
  $v = (float)$fp;
  return (is_finite($v) && $v >= 0) ? $v : $global;
}

function market_control_asset_tradable(mysqli $db, string $baseAsset): bool {
  market_control_ensure_schema($db);
  $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $baseAsset) ?? '');
  if ($base === '') {
    return false;
  }
  $stmt = $db->prepare('SELECT 1 FROM market_pairs WHERE base_asset = ? AND quote_asset = ? AND is_active = 1 LIMIT 1');
  if (!$stmt) {
    return false;
  }
  $q = 'USDT';
  $stmt->bind_param('ss', $base, $q);
  $stmt->execute();
  $stmt->store_result();
  $n = $stmt->num_rows;
  $stmt->free_result();
  $stmt->close();
  return $n > 0;
}

/**
 * @return list<array<string, mixed>>
 */
function market_control_list_pairs(mysqli $db): array {
  market_control_ensure_schema($db);
  $r = @$db->query('SELECT id, base_asset, quote_asset, symbol, price_usd, change_24h_pct, volume_24h_usd, fee_pct, is_active, sort_order, updated_at FROM market_pairs ORDER BY sort_order ASC, base_asset ASC');
  if (!$r) {
    return [];
  }
  $out = [];
  while ($row = $r->fetch_assoc()) {
    $out[] = $row;
  }
  $r->free();
  return $out;
}

/**
 * @return array{pairs: list<array<string, mixed>>, trading_enabled: bool, global_fee_pct: float}
 */
function market_control_public_snapshot(mysqli $db): array {
  market_control_ensure_schema($db);
  market_control_maybe_autotick($db);
  $settings = market_control_get_settings($db);
  $rows = market_control_list_pairs($db);
  $pairs = [];
  foreach ($rows as $row) {
    if (((int)($row['is_active'] ?? 0)) !== 1) {
      continue;
    }
    $base = (string)$row['base_asset'];
    $quote = (string)$row['quote_asset'];
    $pairs[] = [
      'symbol' => (string)$row['symbol'],
      'base' => $base,
      'quote' => $quote,
      'label' => $base . '/' . $quote,
      'price_usd' => (float)$row['price_usd'],
      'change_24h_pct' => (float)$row['change_24h_pct'],
      'volume_24h_usd' => (float)$row['volume_24h_usd'],
      'fee_pct' => $row['fee_pct'] !== null ? (float)$row['fee_pct'] : $settings['global_fee_pct'],
    ];
  }
  return [
    'pairs' => $pairs,
    'trading_enabled' => $settings['trading_enabled'],
    'global_fee_pct' => $settings['global_fee_pct'],
  ];
}

function market_control_stats(mysqli $db): array {
  market_control_ensure_schema($db);
  $r = @$db->query('SELECT COUNT(*) AS c, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS a, SUM(volume_24h_usd) AS v FROM market_pairs');
  $total = $active = 0;
  $vol = 0.0;
  if ($r && ($row = $r->fetch_assoc())) {
    $total = (int)($row['c'] ?? 0);
    $active = (int)($row['a'] ?? 0);
    $vol = (float)($row['v'] ?? 0);
  }
  if ($r) {
    $r->free();
  }
  $paused = max(0, $total - $active);
  return [
    'total_pairs' => $total,
    'active_markets' => $active,
    'paused_markets' => $paused,
    'volume_24h_usd' => $vol,
    'spark_pairs' => [max(1, $total - 2), max(1, $total - 1), $total, $total, $total, $total, $total],
    'spark_active' => [max(1, $active - 2), max(1, $active - 1), $active, $active, $active, $active, $active],
    'spark_paused' => [$paused + 2, $paused + 1, $paused, $paused, $paused, $paused, $paused],
    'spark_volume' => [
      max(1.0, $vol * 0.95),
      max(1.0, $vol * 0.97),
      $vol * 0.99,
      $vol,
      $vol * 1.01,
      $vol,
      $vol,
    ],
  ];
}

function market_control_maybe_autotick(mysqli $db): void {
  market_control_ensure_schema($db);
  $s = market_control_get_settings($db);
  if (!$s['auto_fluctuation_enabled']) {
    return;
  }
  $interval = max(5, (int)$s['fluctuation_interval_sec']);
  $last = $s['last_fluctuation_at'];
  $lastTs = $last !== null && $last !== '' ? strtotime($last) : 0;
  if ($lastTs > 0 && (time() - $lastTs) < $interval) {
    return;
  }

  $range = match ($s['volatility']) {
    'low' => 2.0,
    'high' => 10.0,
    default => 5.0,
  };

  $rows = market_control_list_pairs($db);
  $active = array_values(array_filter($rows, static fn ($r) => ((int)($r['is_active'] ?? 0)) === 1));
  if ($active === []) {
    return;
  }

  $p = $active[array_rand($active)];
  $pct = (mt_rand(-10000, 10000) / 100000.0) * $range;
  $old = (float)$p['price_usd'];
  $new = $old * (1.0 + $pct / 100.0);
  if (!is_finite($new) || $new <= 0) {
    return;
  }
  $chg = (float)$p['change_24h_pct'] + $pct * 0.1;
  $sym = (string)$p['symbol'];
  $st = $db->prepare('UPDATE market_pairs SET price_usd = ?, change_24h_pct = ?, updated_at = NOW() WHERE symbol = ? LIMIT 1');
  if ($st) {
    $st->bind_param('dds', $new, $chg, $sym);
    $st->execute();
    $st->close();
  }

  $u = $db->prepare('UPDATE market_settings SET last_fluctuation_at = NOW() WHERE id = 1 LIMIT 1');
  if ($u) {
    $u->execute();
    $u->close();
  }
}

function market_control_create_pair(
  mysqli $db,
  string $base,
  string $quote,
  float $priceUsd,
  ?float $feePct
): array {
  market_control_ensure_schema($db);
  $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $base) ?? '');
  $quote = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $quote) ?? 'USDT');
  if ($base === '' || $quote === '') {
    return ['ok' => false, 'error' => 'invalid_asset'];
  }
  if (!is_finite($priceUsd) || $priceUsd <= 0) {
    return ['ok' => false, 'error' => 'invalid_price'];
  }
  $symbol = $base . $quote;
  if ($feePct === null) {
    $stmt = $db->prepare(
      'INSERT INTO market_pairs (base_asset, quote_asset, symbol, price_usd, change_24h_pct, volume_24h_usd, fee_pct, is_active, sort_order)
       VALUES (?, ?, ?, ?, 0, 0, NULL, 1, 999)'
    );
    if (!$stmt) {
      return ['ok' => false, 'error' => 'db'];
    }
    $stmt->bind_param('sssd', $base, $quote, $symbol, $priceUsd);
  } else {
    $fv = max(0.0, min(5.0, $feePct));
    $stmt = $db->prepare(
      'INSERT INTO market_pairs (base_asset, quote_asset, symbol, price_usd, change_24h_pct, volume_24h_usd, fee_pct, is_active, sort_order)
       VALUES (?, ?, ?, ?, 0, 0, ?, 1, 999)'
    );
    if (!$stmt) {
      return ['ok' => false, 'error' => 'db'];
    }
    $stmt->bind_param('sssdd', $base, $quote, $symbol, $priceUsd, $fv);
  }
  try {
    $ok = $stmt->execute();
  } catch (Throwable $e) {
    $stmt->close();
    return ['ok' => false, 'error' => 'duplicate_or_db'];
  }
  $stmt->close();
  return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'insert_failed'];
}

function market_control_update_pair_price(mysqli $db, string $symbol, float $newPriceUsd): array {
  market_control_ensure_schema($db);
  $symbol = market_control_normalize_symbol($symbol);
  if ($symbol === '' || !is_finite($newPriceUsd) || $newPriceUsd <= 0) {
    return ['ok' => false, 'error' => 'invalid'];
  }
  $stmt = $db->prepare('UPDATE market_pairs SET price_usd = ?, updated_at = NOW() WHERE symbol = ? LIMIT 1');
  if (!$stmt) {
    return ['ok' => false, 'error' => 'db'];
  }
  $stmt->bind_param('ds', $newPriceUsd, $symbol);
  $stmt->execute();
  $aff = $stmt->affected_rows;
  $stmt->close();
  return $aff > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'not_found'];
}

function market_control_apply_price_percent(mysqli $db, string $symbol, float $pct): array {
  market_control_ensure_schema($db);
  $symbol = market_control_normalize_symbol($symbol);
  if ($symbol === '') {
    return ['ok' => false, 'error' => 'invalid_symbol'];
  }
  $stmt = $db->prepare('SELECT price_usd FROM market_pairs WHERE symbol = ? LIMIT 1');
  if (!$stmt) {
    return ['ok' => false, 'error' => 'db'];
  }
  $stmt->bind_param('s', $symbol);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($p);
  $found = $stmt->fetch();
  $stmt->free_result();
  $stmt->close();
  if (!$found) {
    return ['ok' => false, 'error' => 'not_found'];
  }
  $old = (float)$p;
  $new = $old * (1.0 + $pct / 100.0);
  if (!is_finite($new) || $new <= 0) {
    return ['ok' => false, 'error' => 'invalid_result'];
  }
  return market_control_update_pair_price($db, $symbol, $new);
}

function market_control_set_pair_active(mysqli $db, string $symbol, bool $active): array {
  market_control_ensure_schema($db);
  $symbol = market_control_normalize_symbol($symbol);
  $v = $active ? 1 : 0;
  $stmt = $db->prepare('UPDATE market_pairs SET is_active = ?, updated_at = NOW() WHERE symbol = ? LIMIT 1');
  if (!$stmt) {
    return ['ok' => false, 'error' => 'db'];
  }
  $stmt->bind_param('is', $v, $symbol);
  $stmt->execute();
  $aff = $stmt->affected_rows;
  $stmt->close();
  return $aff > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'not_found'];
}

function market_control_delete_pair(mysqli $db, string $symbol): array {
  market_control_ensure_schema($db);
  $symbol = market_control_normalize_symbol($symbol);
  $stmt = $db->prepare('DELETE FROM market_pairs WHERE symbol = ? LIMIT 1');
  if (!$stmt) {
    return ['ok' => false, 'error' => 'db'];
  }
  $stmt->bind_param('s', $symbol);
  $stmt->execute();
  $aff = $stmt->affected_rows;
  $stmt->close();
  return $aff > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'not_found'];
}

function market_control_manual_random_tick(mysqli $db): void {
  market_control_ensure_schema($db);
  $rows = market_control_list_pairs($db);
  $active = array_values(array_filter($rows, static fn ($r) => ((int)($r['is_active'] ?? 0)) === 1));
  if ($active === []) {
    return;
  }
  $pick = $active[array_rand($active)];
  $pct = (mt_rand(-500, 500) / 100.0);
  $sym = (string)$pick['symbol'];
  market_control_apply_price_percent($db, $sym, $pct);
  market_control_log_event(
    $db,
    'price',
    'Random price tick',
    sprintf('%s adjusted ~%+.2f%%', $sym, $pct)
  );
}

function market_control_log_event(mysqli $db, string $icon, string $title, ?string $detail = null): void {
  market_control_ensure_schema($db);
  $icon = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $icon) ?? '');
  if ($icon === '') {
    $icon = 'price';
  }
  if (!in_array($icon, ['price', 'pause', 'add', 'settings', 'info'], true)) {
    $icon = 'price';
  }
  $stmt = $db->prepare('INSERT INTO market_activity_log (icon, title, detail) VALUES (?, ?, ?)');
  if (!$stmt) {
    return;
  }
  $d = $detail === null ? '' : $detail;
  $stmt->bind_param('sss', $icon, $title, $d);
  $stmt->execute();
  $stmt->close();
}

/**
 * @return list<array{id:int, icon:string, title:string, detail:?string, created_at:string, time_label:string}>
 */
function market_control_fetch_feed(mysqli $db, int $limit = 35): array {
  market_control_ensure_schema($db);
  $limit = max(1, min(100, $limit));
  $r = @$db->query(
    'SELECT id, icon, title, detail, created_at FROM market_activity_log ORDER BY id DESC LIMIT ' . (int)$limit
  );
  if (!$r) {
    return [];
  }
  $out = [];
  while ($row = $r->fetch_assoc()) {
    $ts = strtotime((string)($row['created_at'] ?? ''));
    $timeLabel = $ts ? date('M j, H:i', $ts) : '';
    $out[] = [
      'id' => (int)$row['id'],
      'icon' => (string)$row['icon'],
      'title' => (string)$row['title'],
      'detail' => isset($row['detail']) && $row['detail'] !== null ? (string)$row['detail'] : null,
      'created_at' => (string)($row['created_at'] ?? ''),
      'time_label' => $timeLabel,
    ];
  }
  $r->free();
  return $out;
}

/**
 * Admin view: open trade orders (pending).
 *
 * @return list<array{
 *   id:int,
 *   user_id:int,
 *   user:string,
 *   email:string,
 *   asset:string,
 *   pair:string,
 *   side:string,
 *   amount_usd:float,
 *   qty:float,
 *   price_usd:float,
 *   created_at:string
 * }>
 */
function market_control_fetch_open_orders(mysqli $db, int $limit = 50): array {
  market_control_ensure_schema($db);
  $limit = max(1, min(200, $limit));

  // If the app hasn't created transactions yet, just return empty.
  if (!@$db->query("SHOW TABLES LIKE 'transactions'")) {
    return [];
  }

  $sql = '
    SELECT t.id, t.user_id, u.full_name, u.email, t.asset, t.amount, t.meta, t.created_at,
           COALESCE(mp.price_usd, 0) AS admin_price_usd
    FROM transactions t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN market_pairs mp
      ON mp.base_asset = t.asset AND mp.quote_asset = "USDT"
    WHERE t.type = "trade" AND t.status = "pending"
    ORDER BY t.id DESC
    LIMIT ' . (int)$limit;

  $r = @$db->query($sql);
  if (!$r) {
    return [];
  }

  $out = [];
  while ($row = $r->fetch_assoc()) {
    $metaRaw = $row['meta'] ?? null;
    $m = null;
    if (is_string($metaRaw) && $metaRaw !== '') {
      $m = json_decode($metaRaw, true);
    }
    $side = (is_array($m) && isset($m['side']) && is_string($m['side'])) ? strtoupper($m['side']) : '';
    $amountUsd = is_array($m) && isset($m['amount_usd']) ? (float)$m['amount_usd'] : (float)($row['amount'] ?? 0);
    $price = is_array($m) && isset($m['price_usd']) ? (float)$m['price_usd'] : (float)($row['admin_price_usd'] ?? 0);
    if (!is_finite($price) || $price <= 0) {
      $price = 0.0;
    }
    $qty = ($price > 0) ? ($amountUsd / $price) : 0.0;
    $asset = (string)($row['asset'] ?? '');
    $pair = strtoupper($asset) . '/USDT';

    $out[] = [
      'id' => (int)($row['id'] ?? 0),
      'user_id' => (int)($row['user_id'] ?? 0),
      'user' => (string)($row['full_name'] ?? ''),
      'email' => (string)($row['email'] ?? ''),
      'asset' => $asset,
      'pair' => $pair,
      'side' => $side !== '' ? $side : '—',
      'amount_usd' => (float)$amountUsd,
      'qty' => (float)$qty,
      'price_usd' => (float)$price,
      'created_at' => (string)($row['created_at'] ?? ''),
    ];
  }
  $r->free();
  return $out;
}

/**
 * @param list<array<string, mixed>> $pairs
 * @param array<string, mixed> $settings
 * @return list<array{severity:string, icon:string, title:string, line:string, hint:string}>
 */
function market_control_compute_alert_cards(array $pairs, array $settings): array {
  $active = [];
  foreach ($pairs as $r) {
    if (((int)($r['is_active'] ?? 0)) === 1) {
      $active[] = $r;
    }
  }
  $tradingOn = !empty($settings['trading_enabled']);
  if ($active === []) {
    return [
      [
        'severity' => 'critical',
        'icon' => 'critical',
        'title' => 'No active markets',
        'line' => 'All pairs are paused or none exist yet.',
        'hint' => $tradingOn ? 'Enable a pair or create a new one.' : 'Global trading is paused.',
      ],
      [
        'severity' => 'warning',
        'icon' => 'warning',
        'title' => 'Liquidity',
        'line' => 'Users cannot execute until at least one pair is active.',
        'hint' => 'Check Market Status below.',
      ],
      [
        'severity' => 'info',
        'icon' => 'info',
        'title' => 'System',
        'line' => $tradingOn ? 'Trading engine ready when pairs are active.' : 'Trading halted globally.',
        'hint' => 'Updated from live database.',
      ],
    ];
  }

  $byMove = $active;
  usort(
    $byMove,
    static fn ($a, $b) => abs((float)$b['change_24h_pct']) <=> abs((float)$a['change_24h_pct'])
  );
  $mover = $byMove[0];
  $chg = (float)$mover['change_24h_pct'];
  $mlabel = (string)$mover['base_asset'] . '/' . (string)$mover['quote_asset'];
  $sev = abs($chg) >= 5.0 ? 'critical' : (abs($chg) >= 2.0 ? 'warning' : 'info');
  $ic = abs($chg) >= 5.0 ? 'critical' : 'warning';

  $byVol = $active;
  usort(
    $byVol,
    static fn ($a, $b) => (float)$b['volume_24h_usd'] <=> (float)$a['volume_24h_usd']
  );
  $vol = $byVol[0];
  $vv = (float)$vol['volume_24h_usd'];
  $vlabel = $vv >= 1_000_000.0
    ? '$' . number_format($vv / 1_000_000.0, 1) . 'M'
    : '$' . number_format($vv, 0);

  $sumAbs = 0.0;
  foreach ($active as $r) {
    $sumAbs += abs((float)$r['change_24h_pct']);
  }
  $avg = $sumAbs / count($active);
  $risk = $avg > 3.0 ? 'High' : ($avg > 1.2 ? 'Medium' : 'Low');

  return [
    [
      'severity' => $sev,
      'icon' => $ic,
      'title' => 'Largest 24h move',
      'line' => sprintf('%s %+.2f%% · price $%s', $mlabel, $chg, number_format((float)$mover['price_usd'], 2)),
      'hint' => abs($chg) >= 5.0 ? 'Elevated volatility — monitor exposure.' : 'Strongest mover among active pairs.',
    ],
    [
      'severity' => 'warning',
      'icon' => 'warning',
      'title' => 'Volume leader (24h)',
      'line' => sprintf(
        '%s/%s — %s volume',
        (string)$vol['base_asset'],
        (string)$vol['quote_asset'],
        $vlabel
      ),
      'hint' => 'Highest reported 24h volume in your catalog.',
    ],
    [
      'severity' => 'info',
      'icon' => 'info',
      'title' => 'Market pulse',
      'line' => ($tradingOn ? 'Trading enabled' : 'Trading paused globally') . ' · mean |Δ| ' . number_format($avg, 2) . '%',
      'hint' => 'Implied activity band: ' . $risk . ' · ' . count($active) . ' active pair(s)',
    ],
  ];
}
