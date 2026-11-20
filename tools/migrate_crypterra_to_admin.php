<?php
declare(strict_types=1);

/**
 * One-time migration: copy users / transactions / kyc_requests
 * from `crypterra` -> `crypterra_admin`.
 *
 * Safe to re-run: uses INSERT ... ON DUPLICATE KEY UPDATE.
 */

require_once __DIR__ . '/../Userprofile/includes/user_db.php';

global $DB_HOST, $DB_USER, $DB_PASS;

$SRC_DB = 'crypterra';
$DST_DB = 'crypterra_admin';

function must_db(string $host, string $user, string $pass, string $db): mysqli {
  $m = user_db_connect($host, $user, $pass, $db);
  $m->set_charset('utf8mb4');
  return $m;
}

function table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = @$db->query("SHOW TABLES LIKE '{$t}'");
  if (!$r) return false;
  $ok = $r->num_rows > 0;
  $r->free();
  return $ok;
}

/** @return list<string> */
function table_columns(mysqli $db, string $schema, string $table): array {
  $s = $db->real_escape_string($schema);
  $t = $db->real_escape_string($table);
  $cols = [];
  $r = @$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$s}' AND TABLE_NAME='{$t}' ORDER BY ORDINAL_POSITION");
  if (!$r) return [];
  while ($row = $r->fetch_assoc()) {
    if (isset($row['COLUMN_NAME']) && is_string($row['COLUMN_NAME'])) {
      $cols[] = $row['COLUMN_NAME'];
    }
  }
  $r->free();
  return $cols;
}

/**
 * Copies table rows selecting the intersection of columns between src/dst.
 * Requires a UNIQUE/PRIMARY key on dst for upsert to work.
 */
function migrate_table(mysqli $src, mysqli $dst, string $srcDb, string $dstDb, string $table): int {
  if (!table_exists($src, $table)) {
    echo "[skip] {$srcDb}.{$table} missing\n";
    return 0;
  }
  if (!table_exists($dst, $table)) {
    echo "[skip] {$dstDb}.{$table} missing\n";
    return 0;
  }

  $srcCols = table_columns($src, $srcDb, $table);
  $dstCols = table_columns($dst, $dstDb, $table);
  $dstSet = array_fill_keys($dstCols, true);

  $cols = [];
  foreach ($srcCols as $c) {
    if (isset($dstSet[$c])) {
      $cols[] = $c;
    }
  }
  if (!$cols) {
    echo "[skip] {$table}: no common columns\n";
    return 0;
  }

  // Pull all rows from source.
  $sel = 'SELECT ' . implode(', ', array_map(fn($c) => "`{$c}`", $cols)) . " FROM `{$table}`";
  $r = $src->query($sel);
  if (!$r) {
    echo "[err] select {$table}: " . $src->error . "\n";
    return 0;
  }

  $n = 0;
  while ($row = $r->fetch_assoc()) {
    // Build INSERT ... ON DUPLICATE KEY UPDATE ...
    $placeholders = [];
    $values = [];
    foreach ($cols as $c) {
      $placeholders[] = '?';
      $values[] = $row[$c] ?? null;
    }

    $insertColsSql = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
    $valuesSql = implode(', ', $placeholders);

    $updates = [];
    foreach ($cols as $c) {
      if ($c === 'id') continue; // keep primary id stable
      $updates[] = "`{$c}`=VALUES(`{$c}`)";
    }
    $updSql = $updates ? (' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)) : '';

    $sql = "INSERT INTO `{$table}` ({$insertColsSql}) VALUES ({$valuesSql}){$updSql}";
    $st = $dst->prepare($sql);
    if (!$st) {
      // Some rows may fail if schema mismatch is too wide; stop loudly.
      $r->free();
      throw new RuntimeException("prepare failed for {$table}: " . $dst->error);
    }

    // Bind dynamically as strings (MySQL will coerce); preserve NULL.
    $types = str_repeat('s', count($values));
    $bind = [];
    $bind[] = $types;
    foreach ($values as $i => $v) {
      $bind[] = $values[$i];
    }
    // mysqli bind_param needs references
    $refs = [];
    foreach ($bind as $k => $v) {
      $refs[$k] = &$bind[$k];
    }
    $st->bind_param(...$refs);
    $ok = @$st->execute();
    $st->close();
    if ($ok) $n++;
  }
  $r->free();

  echo "[ok] {$table}: migrated {$n} rows\n";
  return $n;
}

try {
  $src = must_db((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, $SRC_DB);
  $dst = must_db((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, $DST_DB);

  // Ensure destination schema exists/ready.
  ensure_transactions_table($dst);
  ensure_kyc_requests_table($dst);
  ensure_users_kyc_columns($dst, $DST_DB);
  // users table created by tools/ensure_admin_schema.php previously; keep as-is.

  // Order matters: users first, then transactions/kyc.
  migrate_table($src, $dst, $SRC_DB, $DST_DB, 'users');
  migrate_table($src, $dst, $SRC_DB, $DST_DB, 'transactions');
  migrate_table($src, $dst, $SRC_DB, $DST_DB, 'kyc_requests');

  $src->close();
  $dst->close();

  echo "Done.\n";
} catch (Throwable $e) {
  echo "[fatal] " . $e->getMessage() . "\n";
  exit(1);
}

