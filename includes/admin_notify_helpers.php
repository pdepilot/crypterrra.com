<?php
declare(strict_types=1);

require_once __DIR__ . '/contact_helpers.php';

/**
 * Pending platform items for the admin bell (main app DB).
 *
 * @return array{
 *   pending_withdrawals:int,
 *   pending_deposits:int,
 *   kyc_pending:int
 * }
 */
function admin_notify_platform_counts(): array {
  $out = [
    'pending_withdrawals' => 0,
    'pending_deposits' => 0,
    'kyc_pending' => 0,
  ];
  try {
    require_once __DIR__ . '/../Userprofile/includes/user_db.php';
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    $schema = (string)$DB_NAME;

    $r = @$db->query("SHOW TABLES LIKE 'transactions'");
    $tx = $r && $r->num_rows > 0;
    if ($r) {
      $r->free();
    }
    if ($tx) {
      if ($rw = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'withdrawal' AND status = 'pending'")) {
        $row = $rw->fetch_assoc();
        $out['pending_withdrawals'] = (int)($row['c'] ?? 0);
        $rw->free();
      }
      if ($rd = @$db->query("SELECT COUNT(*) AS c FROM transactions WHERE type = 'deposit' AND status = 'pending'")) {
        $row = $rd->fetch_assoc();
        $out['pending_deposits'] = (int)($row['c'] ?? 0);
        $rd->free();
      }
    }

    $esc = $db->real_escape_string($schema);
    $r2 = @$db->query(
      "SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$esc}' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'kyc_status'"
    );
    $hasKyc = false;
    if ($r2) {
      $row = $r2->fetch_assoc();
      $hasKyc = isset($row['n']) && (int)$row['n'] > 0;
      $r2->free();
    }
    if ($hasKyc) {
      if ($rk = @$db->query("SELECT COUNT(*) AS c FROM users WHERE LOWER(kyc_status) = 'pending'")) {
        $row = $rk->fetch_assoc();
        $out['kyc_pending'] = (int)($row['c'] ?? 0);
        $rk->free();
      }
    }

    $db->close();
  } catch (Throwable) {
    // ignore
  }

  return $out;
}

/**
 * Aggregates inbox-style counts + recent items for admin bell + notification center.
 *
 * @return array{
 *   ok: bool,
 *   badge_total: int,
 *   payload_sig: string,
 *   stats: array{total:int, open:int, in_progress:int, resolved:int, urgent:int},
 *   platform: array{pending_withdrawals:int, pending_deposits:int, kyc_pending:int},
 *   items: list<array{type:string, id:int, ticket:string, title:string, subtitle:string, time:string, priority:string, href:string}>
 * }
 */
function admin_notify_snapshot(): array {
  $platform = admin_notify_platform_counts();
  $contactOpen = 0;
  $contactInProgress = 0;
  $total = 0;
  $resolved = 0;
  $urgent = 0;
  $items = [];

  try {
    $db = contact_db_connect();
    try {
      contact_ensure_tables($db);

      $r = @$db->query('SELECT status, COUNT(*) AS c FROM contact_submissions GROUP BY status');
      if ($r) {
        while ($row = $r->fetch_assoc()) {
          $c = (int)($row['c'] ?? 0);
          $total += $c;
          $s = (string)($row['status'] ?? '');
          if ($s === 'open') {
            $contactOpen = $c;
          } elseif ($s === 'in-progress') {
            $contactInProgress = $c;
          } elseif ($s === 'resolved') {
            $resolved = $c;
          }
        }
        $r->free();
      }

      $r2 = @$db->query("SELECT COUNT(*) AS c FROM contact_submissions WHERE priority = 'high' AND status <> 'resolved'");
      if ($r2) {
        $row = $r2->fetch_assoc();
        $urgent = (int)($row['c'] ?? 0);
        $r2->free();
      }

      $r3 = @$db->query(
        "SELECT id, name, email, subject, created_at, priority FROM contact_submissions
         WHERE status IN ('open','in-progress') ORDER BY id DESC LIMIT 12"
      );
      if ($r3) {
        while ($row = $r3->fetch_assoc()) {
          $id = (int)$row['id'];
          $sub = trim((string)$row['subject']);
          $items[] = [
            'type' => 'contact',
            'id' => $id,
            'ticket' => contact_ticket_ref($id),
            'title' => $sub !== '' ? $sub : 'Contact message',
            'subtitle' => (string)$row['name'],
            'time' => (string)$row['created_at'],
            'priority' => (string)$row['priority'],
            'href' => 'admin-contact.php',
          ];
        }
        $r3->free();
      }
    } finally {
      $db->close();
    }
  } catch (Throwable) {
    // keep contact counts at 0; platform counts still apply
  }

  $stats = [
    'total' => $total,
    'open' => $contactOpen,
    'in_progress' => $contactInProgress,
    'resolved' => $resolved,
    'urgent' => $urgent,
  ];

  $badgeTotal =
    $contactOpen +
    $contactInProgress +
    (int)$platform['pending_withdrawals'] +
    (int)$platform['pending_deposits'] +
    (int)$platform['kyc_pending'];

  $payloadSig = implode('|', [
    (string)$badgeTotal,
    (string)$contactOpen,
    (string)$contactInProgress,
    (string)$platform['pending_withdrawals'],
    (string)$platform['pending_deposits'],
    (string)$platform['kyc_pending'],
  ]);

  return [
    'ok' => true,
    'badge_total' => $badgeTotal,
    'payload_sig' => $payloadSig,
    'stats' => $stats,
    'platform' => $platform,
    'items' => $items,
    'server_time' => time(),
  ];
}
