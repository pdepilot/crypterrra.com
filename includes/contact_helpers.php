<?php
declare(strict_types=1);

/**
 * Shared contact submissions storage.
 * Uses the project-root db-config.php ($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME).
 */

function contact_load_db_config(): array {
  $configPath = dirname(__DIR__) . '/db-config.php';
  if (!is_file($configPath)) {
    throw new RuntimeException('Database configuration is missing.');
  }

  $DB_HOST = null;
  $DB_USER = null;
  $DB_PASS = null;
  $DB_NAME = null;
  require $configPath;

  $hostOk = is_string($DB_HOST) && $DB_HOST !== '' && preg_match('/^[a-zA-Z0-9._:-]+$/', $DB_HOST) === 1;
  $userOk = is_string($DB_USER) && $DB_USER !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $DB_USER) === 1;
  $nameOk = is_string($DB_NAME) && preg_match('/^[a-zA-Z0-9_]+$/', $DB_NAME) === 1;
  if (!$hostOk || !$userOk || !is_string($DB_PASS) || !$nameOk) {
    throw new RuntimeException('Database configuration is invalid.');
  }

  return [$DB_HOST, $DB_USER, $DB_PASS, $DB_NAME];
}

function contact_db_connect(): mysqli {
  [$host, $user, $pass, $name] = contact_load_db_config();
  mysqli_report(MYSQLI_REPORT_OFF);
  $db = @new mysqli($host, $user, $pass, $name);
  if ($db->connect_errno) {
    throw new RuntimeException('Database connection failed.');
  }
  $db->set_charset('utf8mb4');
  return $db;
}

function contact_ensure_tables(mysqli $db): void {
  $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS contact_submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL,
  subject VARCHAR(500) NOT NULL DEFAULT '',
  message TEXT NOT NULL,
  category VARCHAR(32) NOT NULL DEFAULT 'general',
  priority VARCHAR(16) NOT NULL DEFAULT 'medium',
  status VARCHAR(32) NOT NULL DEFAULT 'open',
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_created (created_at),
  KEY idx_email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);

  $sql2 = <<<'SQL'
CREATE TABLE IF NOT EXISTS contact_activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  admin_email VARCHAR(255) NOT NULL DEFAULT '',
  action VARCHAR(80) NOT NULL,
  ticket_ref VARCHAR(32) NOT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql2);
}

function contact_ticket_ref(int $id): string {
  return '#T' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function contact_parse_ticket_ref(string $ref): ?int {
  $ref = trim($ref);
  if (preg_match('/^#?T?(\d+)$/i', $ref, $m)) {
    return (int)$m[1];
  }
  return null;
}

/**
 * @return array{ok:bool, id?:int, error?:string}
 */
function contact_insert_submission(
  string $name,
  string $email,
  string $subject,
  string $message,
  string $ip,
  string $userAgent,
  string $category = 'general'
): array {
  $name = mb_substr(trim($name), 0, 150);
  $email = mb_substr(trim($email), 0, 255);
  $subject = mb_substr(trim($subject), 0, 500);
  $message = trim($message);
  $allowedCat = ['general', 'support', 'security', 'billing'];
  $cat = in_array($category, $allowedCat, true) ? $category : 'general';
  if ($name === '' || $email === '' || $message === '') {
    return ['ok' => false, 'error' => 'missing_fields'];
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'error' => 'invalid_email'];
  }

  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $pri = 'medium';
    $st = 'open';
    $stmt = $db->prepare(
      'INSERT INTO contact_submissions (name, email, subject, message, category, priority, status, ip, user_agent, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
      return ['ok' => false, 'error' => 'prepare_failed'];
    }
    $stmt->bind_param(
      'ssssssssss',
      $name,
      $email,
      $subject,
      $message,
      $cat,
      $pri,
      $st,
      $ip,
      $userAgent,
      $now
    );
    if (!$stmt->execute()) {
      $stmt->close();
      return ['ok' => false, 'error' => 'insert_failed'];
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return ['ok' => true, 'id' => $id];
  } finally {
    $db->close();
  }
}

function contact_count_recent_by_ip(string $ip, int $withinSeconds): int {
  if ($ip === '') {
    return 0;
  }
  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $since = (new DateTimeImmutable('now'))->modify('-' . $withinSeconds . ' seconds')->format('Y-m-d H:i:s');
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM contact_submissions WHERE ip = ? AND created_at >= ?');
    if (!$stmt) {
      return 999;
    }
    $stmt->bind_param('ss', $ip, $since);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($c);
    $stmt->fetch();
    $stmt->close();
    return (int)$c;
  } finally {
    $db->close();
  }
}

/**
 * @return list<array<string, mixed>>
 */
function contact_fetch_all_for_admin(): array {
  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $r = @$db->query(
      'SELECT id, name, email, subject, message, category, priority, status, created_at
       FROM contact_submissions ORDER BY id DESC'
    );
    if (!$r) {
      return [];
    }
    $out = [];
    while ($row = $r->fetch_assoc()) {
      $out[] = $row;
    }
    $r->free();
    return $out;
  } finally {
    $db->close();
  }
}

/**
 * @return array{total:int, open:int, in_progress:int, resolved:int, urgent:int}|null
 */
function contact_stats(): array {
  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $total = 0;
    $open = 0;
    $inProgress = 0;
    $resolved = 0;
    $r = @$db->query(
      'SELECT status, COUNT(*) AS c FROM contact_submissions GROUP BY status'
    );
    if ($r) {
      while ($row = $r->fetch_assoc()) {
        $c = (int)($row['c'] ?? 0);
        $total += $c;
        $s = (string)($row['status'] ?? '');
        if ($s === 'open') {
          $open = $c;
        } elseif ($s === 'in-progress') {
          $inProgress = $c;
        } elseif ($s === 'resolved') {
          $resolved = $c;
        }
      }
      $r->free();
    }
    $r2 = @$db->query("SELECT COUNT(*) AS c FROM contact_submissions WHERE priority = 'high' AND status <> 'resolved'");
    $urgent = 0;
    if ($r2) {
      $row = $r2->fetch_assoc();
      $urgent = (int)($row['c'] ?? 0);
      $r2->free();
    }
    return [
      'total' => $total,
      'open' => $open,
      'in_progress' => $inProgress,
      'resolved' => $resolved,
      'urgent' => $urgent,
    ];
  } finally {
    $db->close();
  }
}

function contact_update_field(int $id, string $field, string $value): bool {
  $allowed = [
    'status' => ['open', 'in-progress', 'resolved'],
    'priority' => ['low', 'medium', 'high'],
    'category' => ['support', 'security', 'billing', 'general'],
  ];
  if (!isset($allowed[$field]) || !in_array($value, $allowed[$field], true)) {
    return false;
  }
  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $col = '`' . str_replace('`', '', $field) . '`';
    $sql = "UPDATE contact_submissions SET {$col} = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('si', $value, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  } finally {
    $db->close();
  }
}

function contact_delete(int $id): bool {
  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $stmt = $db->prepare('DELETE FROM contact_submissions WHERE id = ?');
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  } finally {
    $db->close();
  }
}

function contact_log_admin_activity(
  int $adminId,
  string $adminEmail,
  string $action,
  string $ticketRef,
  string $details
): void {
  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $stmt = $db->prepare(
      'INSERT INTO contact_activity_log (admin_id, admin_email, action, ticket_ref, details, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
      $stmt->bind_param('isssss', $adminId, $adminEmail, $action, $ticketRef, $details, $now);
      @$stmt->execute();
      $stmt->close();
    }
  } finally {
    $db->close();
  }
}

/**
 * @return list<array<string, mixed>>
 */
function contact_fetch_activity_logs(int $limit = 100): array {
  $db = contact_db_connect();
  try {
    contact_ensure_tables($db);
    $limit = max(1, min(500, $limit));
    $r = @$db->query(
      'SELECT created_at, admin_email, action, ticket_ref, details FROM contact_activity_log ORDER BY id DESC LIMIT ' . (int)$limit
    );
    if (!$r) {
      return [];
    }
    $out = [];
    while ($row = $r->fetch_assoc()) {
      $out[] = $row;
    }
    $r->free();
    return $out;
  } finally {
    $db->close();
  }
}
