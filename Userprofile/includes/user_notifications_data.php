<?php
declare(strict_types=1);

require_once __DIR__ . '/user_db.php';
require_once dirname(__DIR__, 2) . '/Admin/includes/admin_notifications_data.php';

/**
 * @return list<array{id:int,title:string,body_html:string,notification_type:string,is_read:bool,created_at:string}>
 */
function user_inapp_notifications_list(mysqli $db, int $userId, int $limit): array {
  admin_notifications_ensure_schema($db);
  $limit = max(1, min(50, $limit));
  $stmt = $db->prepare(
    'SELECT id, title, body_html, notification_type, is_read, created_at
     FROM user_inapp_notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?'
  );
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('ii', $userId, $limit);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $out[] = [
        'id' => (int)($row['id'] ?? 0),
        'title' => (string)($row['title'] ?? ''),
        'body_html' => (string)($row['body_html'] ?? ''),
        'notification_type' => (string)($row['notification_type'] ?? 'info'),
        'is_read' => ((int)($row['is_read'] ?? 0)) === 1,
        'created_at' => (string)($row['created_at'] ?? ''),
      ];
    }
    $res->free();
  }
  $stmt->close();
  return $out;
}

function user_inapp_notifications_unread_count(mysqli $db, int $userId): int {
  admin_notifications_ensure_schema($db);
  $stmt = $db->prepare('SELECT COUNT(*) AS c FROM user_inapp_notifications WHERE user_id = ? AND is_read = 0');
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->bind_result($c);
  $stmt->fetch();
  $stmt->close();
  return (int)$c;
}

function user_inapp_notifications_mark_read(mysqli $db, int $userId, int $rowId): bool {
  admin_notifications_ensure_schema($db);
  if ($rowId <= 0) {
    return false;
  }
  $stmt = $db->prepare('UPDATE user_inapp_notifications SET is_read = 1 WHERE id = ? AND user_id = ? LIMIT 1');
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('ii', $rowId, $userId);
  $stmt->execute();
  $ok = $stmt->affected_rows >= 0;
  $stmt->close();
  return $ok;
}

function user_inapp_notifications_mark_all_read(mysqli $db, int $userId): int {
  admin_notifications_ensure_schema($db);
  $stmt = $db->prepare('UPDATE user_inapp_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $n = $stmt->affected_rows;
  $stmt->close();
  return (int)$n;
}
