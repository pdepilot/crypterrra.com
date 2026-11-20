<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

function admin_notifications_db(): mysqli {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  return user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
}

function admin_notifications_ensure_schema(mysqli $db): void {
  $db->query(
    "CREATE TABLE IF NOT EXISTS platform_notifications (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      admin_id INT UNSIGNED NOT NULL DEFAULT 0,
      title VARCHAR(512) NOT NULL DEFAULT '',
      message_html MEDIUMTEXT NULL,
      recipient_scope VARCHAR(32) NOT NULL DEFAULT 'all',
      specific_target VARCHAR(255) NULL,
      notification_type VARCHAR(24) NOT NULL DEFAULT 'info',
      channels_json TEXT NULL,
      status VARCHAR(24) NOT NULL DEFAULT 'sent',
      recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
      meta TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_created (created_at),
      KEY idx_scope (recipient_scope),
      KEY idx_type (notification_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );

  $db->query(
    "CREATE TABLE IF NOT EXISTS user_inapp_notifications (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id INT UNSIGNED NOT NULL,
      platform_notification_id INT UNSIGNED NOT NULL,
      title VARCHAR(512) NOT NULL DEFAULT '',
      body_html MEDIUMTEXT NULL,
      notification_type VARCHAR(24) NOT NULL DEFAULT 'info',
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_user_unread (user_id, is_read, created_at),
      KEY idx_platform (platform_notification_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

/**
 * @return list<int>
 */
function admin_notifications_resolve_user_ids(mysqli $db, string $scope, string $specific): array {
  $scope = strtolower(trim($scope));
  $ids = [];

  if ($scope === 'all') {
    $r = @$db->query('SELECT id FROM users ORDER BY id ASC');
    if ($r) {
      while ($row = $r->fetch_assoc()) {
        $ids[] = (int)$row['id'];
      }
      $r->free();
    }
    return $ids;
  }

  if ($scope === 'specific') {
    $q = trim($specific);
    if ($q === '') {
      return [];
    }
    $like = '%' . $db->real_escape_string($q) . '%';
    $stmt = $db->prepare(
      'SELECT id FROM users WHERE email = ? OR full_name LIKE ? OR wallet_id = ? LIMIT 500'
    );
    if (!$stmt) {
      return [];
    }
    $stmt->bind_param('sss', $q, $like, $q);
    $stmt->execute();
    $stmt->bind_result($uid);
    while ($stmt->fetch()) {
      $ids[] = (int)$uid;
    }
    $stmt->close();
    return array_values(array_unique($ids));
  }

  if ($scope === 'verified') {
    $r = @$db->query("SELECT id FROM users WHERE LOWER(TRIM(COALESCE(kyc_status,''))) = 'approved' ORDER BY id ASC");
    if ($r) {
      while ($row = $r->fetch_assoc()) {
        $ids[] = (int)$row['id'];
      }
      $r->free();
    }
    return $ids;
  }

  if ($scope === 'unverified') {
    $r = @$db->query("SELECT id FROM users WHERE LOWER(TRIM(COALESCE(kyc_status,''))) <> 'approved' ORDER BY id ASC");
    if ($r) {
      while ($row = $r->fetch_assoc()) {
        $ids[] = (int)$row['id'];
      }
      $r->free();
    }
    return $ids;
  }

  if ($scope === 'high-value' || $scope === 'high_value') {
    $stmt = $db->prepare('SELECT id FROM users WHERE COALESCE(portfolio_value_usd, 0) >= 10000 ORDER BY id ASC');
    if ($stmt) {
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res) {
        while ($row = $res->fetch_assoc()) {
          $ids[] = (int)$row['id'];
        }
        $res->free();
      }
      $stmt->close();
    }
    return $ids;
  }

  return [];
}

/**
 * @param array{
 *   title:string,
 *   message_html:string,
 *   recipient:string,
 *   specific_user?:string,
 *   type:string,
 *   channels:array{in_app?:bool,email?:bool,push?:bool}
 * } $input
 * @return array{ok:bool, id?:int, error?:string, recipient_count?:int}
 */
function admin_notifications_merge_meta(mysqli $db, int $platformId, array $extra): void {
  if ($platformId <= 0) {
    return;
  }
  $st = $db->prepare('SELECT meta FROM platform_notifications WHERE id = ? LIMIT 1');
  if (!$st) {
    return;
  }
  $st->bind_param('i', $platformId);
  $st->execute();
  $st->bind_result($metaStr);
  $st->fetch();
  $st->close();
  $meta = [];
  if (is_string($metaStr) && $metaStr !== '') {
    $d = json_decode($metaStr, true);
    if (is_array($d)) {
      $meta = $d;
    }
  }
  foreach ($extra as $k => $v) {
    $meta[$k] = $v;
  }
  $out = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  $up = $db->prepare('UPDATE platform_notifications SET meta = ? WHERE id = ? LIMIT 1');
  if ($up) {
    $up->bind_param('si', $out, $platformId);
    $up->execute();
    $up->close();
  }
}

/**
 * @return array{sent:int, failed:int}
 */
function admin_notifications_dispatch_emails(mysqli $db, int $platformId, array $userIds, string $subject, string $htmlBody): array {
  $sent = 0;
  $failed = 0;
  $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
  if ($platformId <= 0 || $ids === []) {
    return ['sent' => 0, 'failed' => 0];
  }
  $from = 'Crypterra <noreply@localhost>';
  foreach (array_chunk($ids, 80) as $chunk) {
    $in = implode(',', $chunk);
    $r = @$db->query('SELECT id, email FROM users WHERE id IN (' . $in . ')');
    if (!$r) {
      continue;
    }
    while ($row = $r->fetch_assoc()) {
      $em = trim((string)($row['email'] ?? ''));
      if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
        $failed++;
        continue;
      }
      $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: {$from}\r\n";
      $ok = @mail($em, $subject, $htmlBody, $headers);
      if ($ok) {
        $sent++;
      } else {
        $failed++;
      }
    }
    $r->free();
  }
  admin_notifications_merge_meta($db, $platformId, [
    'email_dispatch' => 'completed',
    'email_sent_count' => $sent,
    'email_failed_count' => $failed,
    'email_completed_at' => date('c'),
  ]);
  return ['sent' => $sent, 'failed' => $failed];
}

function admin_notifications_send(mysqli $db, int $adminId, array $input): array {
  admin_notifications_ensure_schema($db);

  $title = isset($input['title']) && is_string($input['title']) ? trim($input['title']) : '';
  $html = isset($input['message_html']) && is_string($input['message_html']) ? $input['message_html'] : '';
  $recipient = isset($input['recipient']) && is_string($input['recipient']) ? trim($input['recipient']) : 'all';
  $specific = isset($input['specific_user']) && is_string($input['specific_user']) ? trim($input['specific_user']) : '';
  $specificUserId = isset($input['specific_user_id']) ? (int)$input['specific_user_id'] : 0;
  $type = isset($input['type']) && is_string($input['type']) ? strtolower(trim($input['type'])) : 'info';
  $channels = isset($input['channels']) && is_array($input['channels']) ? $input['channels'] : [];

  if ($title === '' || trim(strip_tags($html)) === '') {
    return ['ok' => false, 'error' => 'title_message'];
  }

  $allowedTypes = ['info', 'success', 'warning', 'alert'];
  if (!in_array($type, $allowedTypes, true)) {
    $type = 'info';
  }

  $mapScope = [
    'all' => 'all',
    'specific' => 'specific',
    'verified' => 'verified',
    'unverified' => 'unverified',
    'high-value' => 'high_value',
  ];
  $scopeKey = $mapScope[$recipient] ?? 'all';

  $userIds = [];
  $specDb = null;

  if ($specificUserId > 0) {
    $st = $db->prepare('SELECT id, email, full_name FROM users WHERE id = ? LIMIT 1');
    if (!$st) {
      return ['ok' => false, 'error' => 'exception'];
    }
    $st->bind_param('i', $specificUserId);
    $st->execute();
    $st->bind_result($foundId, $em, $fn);
    if (!$st->fetch()) {
      $st->close();
      return ['ok' => false, 'error' => 'user_not_found'];
    }
    $st->close();
    $userIds = [(int)$foundId];
    $scopeKey = 'specific';
    $specDb = trim((string)$em) !== '' ? (string)$em : ('user #' . (string)$foundId);
  } else {
    if ($scopeKey === 'specific' && $specific === '') {
      return ['ok' => false, 'error' => 'specific_required'];
    }
    $userIds = admin_notifications_resolve_user_ids($db, $scopeKey === 'high_value' ? 'high-value' : $scopeKey, $specific);
    if ($scopeKey === 'specific' && count($userIds) === 0) {
      return ['ok' => false, 'error' => 'user_not_found'];
    }
    if (count($userIds) === 0 && $scopeKey !== 'specific') {
      return ['ok' => false, 'error' => 'no_recipients'];
    }
    $specDb = $specific !== '' ? $specific : null;
  }

  $ch = [
    'in_app' => !empty($channels['in_app']),
    'email' => !empty($channels['email']),
    'push' => !empty($channels['push']),
  ];
  $channelsJson = json_encode($ch, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

  $meta = [
    'email_sent' => $ch['email'] ? 'queued' : 'skipped',
    'dashboard_push' => ($ch['in_app'] || $ch['push']) ? 'queued' : 'skipped',
    'at' => date('c'),
  ];
  $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

  $db->begin_transaction();
  try {
    $st = $db->prepare(
      'INSERT INTO platform_notifications
       (admin_id, title, message_html, recipient_scope, specific_target, notification_type, channels_json, status, recipient_count, meta, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, \'sent\', ?, ?, NOW())'
    );
    if (!$st) {
      throw new RuntimeException('insert');
    }
    $scopeDb = $scopeKey;
    $rc = count($userIds);
    $st->bind_param(
      'issssssis',
      $adminId,
      $title,
      $html,
      $scopeDb,
      $specDb,
      $type,
      $channelsJson,
      $rc,
      $metaJson
    );
    $st->execute();
    $pid = (int)$st->insert_id;
    $st->close();

    $dashboardNotify = ($ch['in_app'] || $ch['push']) && $pid > 0 && $rc > 0;
    if ($dashboardNotify) {
      $ins = $db->prepare(
        'INSERT INTO user_inapp_notifications (user_id, platform_notification_id, title, body_html, notification_type, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())'
      );
      if ($ins) {
        foreach ($userIds as $uid) {
          $uid = (int)$uid;
          if ($uid <= 0) {
            continue;
          }
          $ins->bind_param('iisss', $uid, $pid, $title, $html, $type);
          $ins->execute();
        }
        $ins->close();
      }
    }

    $db->commit();

    $emailSent = 0;
    $emailFail = 0;
    if ($ch['email'] && $pid > 0 && $rc > 0) {
      $emRes = admin_notifications_dispatch_emails($db, $pid, $userIds, $title, $html);
      $emailSent = (int)($emRes['sent'] ?? 0);
      $emailFail = (int)($emRes['failed'] ?? 0);
    }

    return [
      'ok' => true,
      'id' => $pid,
      'recipient_count' => $rc,
      'email_sent' => $emailSent,
      'email_failed' => $emailFail,
    ];
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[admin_notifications_send] ' . $e->getMessage());
    return ['ok' => false, 'error' => 'exception'];
  }
}

/**
 * @return list<array{id:int,title:string,recipient:string,type:string,channel:string,status:string,date:string,public_id:string}>
 */
function admin_notifications_list(mysqli $db, int $limit, int $offset): array {
  admin_notifications_ensure_schema($db);
  $limit = max(1, min(200, $limit));
  $offset = max(0, $offset);

  $scopeLabel = [
    'all' => 'All Users',
    'specific' => 'Specific User',
    'verified' => 'Verified Users',
    'unverified' => 'Unverified Users',
    'high_value' => 'High-Value Users (>$10k)',
  ];

  $rows = [];
  $sql = 'SELECT id, recipient_scope, specific_target, title, notification_type, channels_json, status, created_at, recipient_count
          FROM platform_notifications ORDER BY id DESC LIMIT ? OFFSET ?';
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('ii', $limit, $offset);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $id = (int)$r['id'];
      $scope = (string)($r['recipient_scope'] ?? 'all');
      $spec = trim((string)($r['specific_target'] ?? ''));
      $recipient = $scopeLabel[$scope] ?? ucfirst($scope);
      if ($scope === 'specific' && $spec !== '') {
        $recipient .= ' · ' . $spec;
      }

      $ch = [];
      $cj = (string)($r['channels_json'] ?? '');
      if ($cj !== '') {
        $d = json_decode($cj, true);
        if (is_array($d)) {
          if (!empty($d['in_app'])) {
            $ch[] = 'In-app';
          }
          if (!empty($d['email'])) {
            $ch[] = 'Email';
          }
          if (!empty($d['push'])) {
            $ch[] = 'Push';
          }
        }
      }
      $channelStr = $ch !== [] ? implode(', ', $ch) : '—';

      $rows[] = [
        'id' => $id,
        'public_id' => 'NOT-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT),
        'title' => (string)($r['title'] ?? ''),
        'recipient' => $recipient,
        'type' => (string)($r['notification_type'] ?? 'info'),
        'channel' => $channelStr,
        'status' => ucfirst((string)($r['status'] ?? 'sent')),
        'date' => (string)($r['created_at'] ?? ''),
        'recipient_count' => (int)($r['recipient_count'] ?? 0),
      ];
    }
    $res->free();
  }
  $stmt->close();
  return $rows;
}

function admin_notifications_total(mysqli $db): int {
  admin_notifications_ensure_schema($db);
  $r = @$db->query('SELECT COUNT(*) AS c FROM platform_notifications');
  if (!$r) {
    return 0;
  }
  $row = $r->fetch_assoc();
  $r->free();
  return (int)($row['c'] ?? 0);
}

/**
 * @return array{total:int, last7:list<array{date:string,count:int}>, sent:int, pending:int, failed:int}
 */
function admin_notifications_chart_stats(mysqli $db): array {
  admin_notifications_ensure_schema($db);
  $out = [
    'total' => 0,
    'last7' => [],
    'sent' => 0,
    'pending' => 0,
    'failed' => 0,
  ];

  $r = @$db->query('SELECT COUNT(*) AS c FROM platform_notifications');
  if ($r) {
    $row = $r->fetch_assoc();
    $out['total'] = (int)($row['c'] ?? 0);
    $r->free();
  }

  $r2 = @$db->query(
    "SELECT status, COUNT(*) AS c FROM platform_notifications GROUP BY status"
  );
  if ($r2) {
    while ($row = $r2->fetch_assoc()) {
      $s = strtolower((string)($row['status'] ?? ''));
      $c = (int)($row['c'] ?? 0);
      if ($s === 'sent') {
        $out['sent'] = $c;
      } elseif ($s === 'pending') {
        $out['pending'] = $c;
      } elseif ($s === 'failed') {
        $out['failed'] = $c;
      }
    }
    $r2->free();
  }

  $r3 = @$db->query(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM platform_notifications
     WHERE created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY d ASC"
  );
  $byDay = [];
  if ($r3) {
    while ($row = $r3->fetch_assoc()) {
      $byDay[(string)$row['d']] = (int)$row['c'];
    }
    $r3->free();
  }
  for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime('-' . $i . ' days'));
    $out['last7'][] = ['date' => $d, 'count' => $byDay[$d] ?? 0];
  }

  $r4 = @$db->query(
    "SELECT COALESCE(SUM(recipient_count), 0) AS s FROM platform_notifications
     WHERE created_at >= (CURDATE() - INTERVAL 6 DAY)"
  );
  if ($r4) {
    $row = $r4->fetch_assoc();
    $out['recipients_7d'] = (int)($row['s'] ?? 0);
    $r4->free();
  } else {
    $out['recipients_7d'] = 0;
  }

  return $out;
}

function admin_notifications_delete(mysqli $db, int $id): bool {
  admin_notifications_ensure_schema($db);
  if ($id <= 0) {
    return false;
  }
  $db->begin_transaction();
  try {
    $c = $db->prepare('DELETE FROM user_inapp_notifications WHERE platform_notification_id = ?');
    if ($c) {
      $c->bind_param('i', $id);
      $c->execute();
      $c->close();
    }
    $st = $db->prepare('DELETE FROM platform_notifications WHERE id = ? LIMIT 1');
    if (!$st) {
      throw new RuntimeException('del');
    }
    $st->bind_param('i', $id);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    $db->commit();
    return $ok;
  } catch (Throwable) {
    $db->rollback();
    return false;
  }
}

/**
 * @return list<array{id:int,email:string,full_name:string,wallet_id:string}>
 */
function admin_notifications_search_users(mysqli $db, string $q, int $limit): array {
  admin_notifications_ensure_schema($db);
  $q = trim($q);
  if ($q === '') {
    return [];
  }
  $limit = max(1, min(50, $limit));
  $like = '%' . $q . '%';
  $stmt = $db->prepare(
    'SELECT id, email, full_name, COALESCE(wallet_id, \'\') AS wallet_id FROM users
     WHERE email LIKE ? OR full_name LIKE ? OR wallet_id = ? OR CAST(id AS CHAR) = ?
     ORDER BY id DESC LIMIT ?'
  );
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('ssssi', $like, $like, $q, $q, $limit);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $out[] = [
        'id' => (int)($row['id'] ?? 0),
        'email' => (string)($row['email'] ?? ''),
        'full_name' => (string)($row['full_name'] ?? ''),
        'wallet_id' => (string)($row['wallet_id'] ?? ''),
      ];
    }
    $res->free();
  }
  $stmt->close();
  return $out;
}

/**
 * @return list<array{title:string,recipient_count:int,created_at:string,type:string}>
 */
function admin_notifications_recent_feed(mysqli $db, int $limit): array {
  admin_notifications_ensure_schema($db);
  $limit = max(1, min(30, $limit));
  $stmt = $db->prepare(
    'SELECT title, recipient_count, created_at, notification_type FROM platform_notifications
     ORDER BY id DESC LIMIT ?'
  );
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('i', $limit);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $out[] = [
        'title' => (string)($row['title'] ?? ''),
        'recipient_count' => (int)($row['recipient_count'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'type' => (string)($row['notification_type'] ?? 'info'),
      ];
    }
    $res->free();
  }
  $stmt->close();
  return $out;
}
