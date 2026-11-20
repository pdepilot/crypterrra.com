<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_dashboard_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
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

function admin_format_compact_number(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return number_format($n / 1_000, 1) . 'k';
  }
  if ($abs >= 100) {
    return (string)(int)round($n);
  }
  return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function admin_format_usd_compact(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return '$' . number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return '$' . number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return '$' . number_format($n / 1_000, 1) . 'k';
  }
  return '$' . number_format($n, 2);
}

function admin_dashboard_metrics(mysqli $db): array {
  // Keep schema in a compatible state if user module exists.
  try {
    if (function_exists('ensure_user_dashboard_schema')) {
      /** @var string $DB_NAME */
      global $DB_NAME;
      ensure_user_dashboard_schema($db, (string)$DB_NAME);
    }
  } catch (Throwable) {
    // ignore
  }

  $metrics = [
    'total_users' => 0,
    'active_users' => 0,
    'total_deposits_usd' => 0.0,
    'total_withdrawals_usd' => 0.0,
    'platform_balance_usd' => 0.0,
    'daily_volume_usd' => 0.0,
  ];

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users')) {
    $row = $r->fetch_assoc();
    $metrics['total_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users WHERE is_active = 1')) {
    $row = $r->fetch_assoc();
    $metrics['active_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  $txExists = false;
  if ($r = @$db->query("SHOW TABLES LIKE 'transactions'")) {
    $txExists = $r->num_rows > 0;
    $r->free();
  }

  if ($txExists) {
    // Deposits (completed only)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'deposit' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_deposits_usd'] = (float)$sum;
    }

    // Withdrawals (completed only)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'withdrawal' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_withdrawals_usd'] = (float)$sum;
    }

    // Daily trade volume (best-effort)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) AS s FROM transactions WHERE type = 'trade' AND created_at >= CURDATE()");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['daily_volume_usd'] = (float)$sum;
    }
  }

  // Platform balance: sum whichever known balance columns exist.
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  $cols = [];
  foreach (['available_balance_usd', 'spot_balance_usd', 'futures_balance_usd', 'portfolio_value_usd'] as $c) {
    if (admin_schema_column_exists($db, $schema, 'users', $c)) {
      $cols[] = "`{$c}`";
    }
  }
  if ($cols) {
    $expr = implode(' + ', $cols);
    $sql = "SELECT COALESCE(SUM({$expr}), 0) AS s FROM users";
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $metrics['platform_balance_usd'] = isset($row['s']) ? (float)$row['s'] : 0.0;
      $r->free();
    }
  }

  return $metrics;
}

__halt_compiler();
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_dashboard_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
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

function admin_format_compact_number(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return number_format($n / 1_000, 1) . 'k';
  }
  if ($abs >= 100) {
    return (string)(int)round($n);
  }
  return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function admin_format_usd_compact(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return '$' . number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return '$' . number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return '$' . number_format($n / 1_000, 1) . 'k';
  }
  return '$' . number_format($n, 2);
}

function admin_dashboard_metrics(mysqli $db): array {
  try {
    if (function_exists('ensure_user_dashboard_schema')) {
      /** @var string $DB_NAME */
      global $DB_NAME;
      ensure_user_dashboard_schema($db, (string)$DB_NAME);
    }
  } catch (Throwable) {
    // ignore
  }

  $metrics = [
    'total_users' => 0,
    'active_users' => 0,
    'total_deposits_usd' => 0.0,
    'total_withdrawals_usd' => 0.0,
    'platform_balance_usd' => 0.0,
    'daily_volume_usd' => 0.0,
  ];

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users')) {
    $row = $r->fetch_assoc();
    $metrics['total_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users WHERE is_active = 1')) {
    $row = $r->fetch_assoc();
    $metrics['active_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  $txExists = false;
  if ($r = @$db->query("SHOW TABLES LIKE 'transactions'")) {
    $txExists = $r->num_rows > 0;
    $r->free();
  }

  if ($txExists) {
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'deposit' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_deposits_usd'] = (float)$sum;
    }

    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'withdrawal' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_withdrawals_usd'] = (float)$sum;
    }

    $stmt = @$db->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) AS s FROM transactions WHERE type = 'trade' AND created_at >= CURDATE()");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['daily_volume_usd'] = (float)$sum;
    }
  }

  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  $cols = [];
  foreach (['available_balance_usd', 'spot_balance_usd', 'futures_balance_usd', 'portfolio_value_usd'] as $c) {
    if (admin_schema_column_exists($db, $schema, 'users', $c)) {
      $cols[] = "`{$c}`";
    }
  }
  if (count($cols) > 0) {
    $expr = implode(' + ', $cols);
    $sql = "SELECT COALESCE(SUM({$expr}), 0) AS s FROM users";
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $metrics['platform_balance_usd'] = isset($row['s']) ? (float)$row['s'] : 0.0;
      $r->free();
    }
  }

  return $metrics;
}

__halt_compiler();

__halt_compiler();
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_dashboard_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
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

function admin_format_compact_number(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return number_format($n / 1_000, 1) . 'k';
  }
  if ($abs >= 100) {
    return (string)(int)round($n);
  }
  return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function admin_format_usd_compact(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return '$' . number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return '$' . number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return '$' . number_format($n / 1_000, 1) . 'k';
  }
  return '$' . number_format($n, 2);
}

function admin_dashboard_metrics(mysqli $db): array {
  // Best-effort schema ensures so balance columns exist when possible.
  try {
    if (function_exists('ensure_user_dashboard_schema')) {
      /** @var string $DB_NAME */
      global $DB_NAME;
      ensure_user_dashboard_schema($db, (string)$DB_NAME);
    }
  } catch (Throwable) {
    // ignore
  }

  $metrics = [
    'total_users' => 0,
    'active_users' => 0,
    'total_deposits_usd' => 0.0,
    'total_withdrawals_usd' => 0.0,
    'platform_balance_usd' => 0.0,
    'daily_volume_usd' => 0.0,
  ];

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users')) {
    $row = $r->fetch_assoc();
    $metrics['total_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users WHERE is_active = 1')) {
    $row = $r->fetch_assoc();
    $metrics['active_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  $txExists = false;
  if ($r = @$db->query("SHOW TABLES LIKE 'transactions'")) {
    $txExists = $r->num_rows > 0;
    $r->free();
  }

  if ($txExists) {
    // Deposits (completed only)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'deposit' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_deposits_usd'] = (float)$sum;
    }

    // Withdrawals (completed only)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'withdrawal' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_withdrawals_usd'] = (float)$sum;
    }

    // Daily trade volume (best-effort)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) AS s FROM transactions WHERE type = 'trade' AND created_at >= CURDATE()");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['daily_volume_usd'] = (float)$sum;
    }
  }

  // Platform balance: sum known balance columns if present.
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  $cols = [];
  foreach (['available_balance_usd', 'spot_balance_usd', 'futures_balance_usd', 'portfolio_value_usd'] as $c) {
    if (admin_schema_column_exists($db, $schema, 'users', $c)) {
      $cols[] = "`{$c}`";
    }
  }
  if (count($cols) > 0) {
    $expr = implode(' + ', $cols);
    $sql = "SELECT COALESCE(SUM({$expr}), 0) AS s FROM users";
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $metrics['platform_balance_usd'] = isset($row['s']) ? (float)$row['s'] : 0.0;
      $r->free();
    }
  }

  return $metrics;
}

__halt_compiler();
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_dashboard_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
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

function admin_format_compact_number(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return number_format($n / 1_000, 1) . 'k';
  }
  if ($abs >= 100) {
    return (string)(int)round($n);
  }
  return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function admin_format_usd_compact(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return '$' . number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return '$' . number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return '$' . number_format($n / 1_000, 1) . 'k';
  }
  return '$' . number_format($n, 2);
}

function admin_dashboard_metrics(mysqli $db): array {
  // Best-effort schema ensures so balance columns exist when possible.
  try {
    if (function_exists('ensure_user_dashboard_schema')) {
      /** @var string $DB_NAME */
      global $DB_NAME;
      ensure_user_dashboard_schema($db, (string)$DB_NAME);
    }
  } catch (Throwable) {
    // ignore
  }

  $metrics = [
    'total_users' => 0,
    'active_users' => 0,
    'total_deposits_usd' => 0.0,
    'total_withdrawals_usd' => 0.0,
    'platform_balance_usd' => 0.0,
    'daily_volume_usd' => 0.0,
  ];

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users')) {
    $row = $r->fetch_assoc();
    $metrics['total_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users WHERE is_active = 1')) {
    $row = $r->fetch_assoc();
    $metrics['active_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  $txExists = false;
  if ($r = @$db->query("SHOW TABLES LIKE 'transactions'")) {
    $txExists = $r->num_rows > 0;
    $r->free();
  }

  if ($txExists) {
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'deposit' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_deposits_usd'] = (float)$sum;
    }

    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'withdrawal' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_withdrawals_usd'] = (float)$sum;
    }

    $stmt = @$db->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) AS s FROM transactions WHERE type = 'trade' AND created_at >= CURDATE()");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['daily_volume_usd'] = (float)$sum;
    }
  }

  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  $cols = [];
  foreach (['available_balance_usd', 'spot_balance_usd', 'futures_balance_usd', 'portfolio_value_usd'] as $c) {
    if (admin_schema_column_exists($db, $schema, 'users', $c)) {
      $cols[] = "`{$c}`";
    }
  }
  if (count($cols) > 0) {
    $expr = implode(' + ', $cols);
    $sql = "SELECT COALESCE(SUM({$expr}), 0) AS s FROM users";
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $metrics['platform_balance_usd'] = isset($row['s']) ? (float)$row['s'] : 0.0;
      $r->free();
    }
  }

  return $metrics;
}

function admin_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_dashboard_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
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

function admin_format_compact_number(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return number_format($n / 1_000, 1) . 'k';
  }
  if ($abs >= 100) {
    return (string)(int)round($n);
  }
  // Keep small numbers readable.
  return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function admin_format_usd_compact(float|int $n): string {
  $n = (float)$n;
  $abs = abs($n);
  if ($abs >= 1_000_000_000) {
    return '$' . number_format($n / 1_000_000_000, 1) . 'B';
  }
  if ($abs >= 1_000_000) {
    return '$' . number_format($n / 1_000_000, 1) . 'M';
  }
  if ($abs >= 1_000) {
    return '$' . number_format($n / 1_000, 1) . 'k';
  }
  return '$' . number_format($n, 2);
}

/**
 * Dashboard metrics derived from existing tables:
 * - `users` (from `login.php`)
 * - `transactions` (from `Userprofile/includes/user_db.php`)
 */
function admin_dashboard_metrics(mysqli $db): array {
  // Ensure columns used by wallet/portfolio views exist (best-effort).
  // This keeps platform balance query from failing if your DB is partially migrated.
  try {
    if (function_exists('ensure_user_dashboard_schema')) {
      /** @var string $DB_NAME */
      global $DB_NAME;
      ensure_user_dashboard_schema($db, (string)$DB_NAME);
    }
  } catch (Throwable) {
    // ignore
  }

  $metrics = [
    'total_users' => 0,
    'active_users' => 0,
    'total_deposits_usd' => 0.0,
    'total_withdrawals_usd' => 0.0,
    'platform_balance_usd' => 0.0,
    'daily_volume_usd' => 0.0,
  ];

  // Total users
  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users')) {
    $row = $r->fetch_assoc();
    $metrics['total_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  // Active users (is_active = 1)
  if ($r = @$db->query('SELECT COUNT(*) AS c FROM users WHERE is_active = 1')) {
    $row = $r->fetch_assoc();
    $metrics['active_users'] = isset($row['c']) ? (int)$row['c'] : 0;
    $r->free();
  }

  // Deposits / withdrawals / trade volume
  $txExists = false;
  if ($r = @$db->query("SHOW TABLES LIKE 'transactions'")) {
    $txExists = $r->num_rows > 0;
    $r->free();
  }

  if ($txExists) {
    // Deposits (completed only)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'deposit' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_deposits_usd'] = (float)$sum;
    }

    // Withdrawals (completed only)
    $stmt = @$db->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM transactions WHERE type = 'withdrawal' AND status = 'completed'");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['total_withdrawals_usd'] = (float)$sum;
    }

    // Daily "volume" (best-effort): sum of trade amounts today.
    $stmt = @$db->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) AS s FROM transactions WHERE type = 'trade' AND created_at >= CURDATE()");
    if ($stmt) {
      $stmt->execute();
      $stmt->store_result();
      $sum = '0';
      $stmt->bind_result($sum);
      $stmt->fetch();
      $stmt->free_result();
      $stmt->close();
      $metrics['daily_volume_usd'] = (float)$sum;
    }
  }

  // Platform balance: sum any known balance columns (best-effort).
  /** @var string $DB_NAME */
  global $DB_NAME;
  $schema = (string)$DB_NAME;
  $cols = [];
  foreach (['available_balance_usd', 'spot_balance_usd', 'futures_balance_usd', 'portfolio_value_usd'] as $c) {
    if (admin_schema_column_exists($db, $schema, 'users', $c)) {
      $cols[] = "`{$c}`";
    }
  }
  if (count($cols) > 0) {
    $expr = implode(' + ', $cols);
    $sql = "SELECT COALESCE(SUM({$expr}), 0) AS s FROM users";
    if ($r = @$db->query($sql)) {
      $row = $r->fetch_assoc();
      $metrics['platform_balance_usd'] = isset($row['s']) ? (float)$row['s'] : 0.0;
      $r->free();
    }
  }

  return $metrics;
}

