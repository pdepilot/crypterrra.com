<?php
declare(strict_types=1);

require_once __DIR__ . '/user_db.php';

/**
 * KYC sidebar badge for the logged-in user (call after `user_auth.php`).
 *
 * @return array{text:string,bg:string}
 */
function kyc_sidebar_badge_for_session(): array {
  $default = ['text' => 'NOT SUBMITTED', 'bg' => '#64748b'];
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid <= 0) {
    return $default;
  }
  try {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    $b = user_kyc_sidebar_badge($db, (string)$DB_NAME, $uid);
    $db->close();
    return $b;
  } catch (Throwable) {
    return $default;
  }
}

/**
 * @param array{text:string,bg:string} $b
 */
function kyc_sidebar_badge_span(array $b): string {
  return '<span class="action-badge kyc-sidebar-badge" style="background: ' . htmlspecialchars($b['bg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($b['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
}

/**
 * Security & KYC card on dashboard (same source as sidebar badge).
 *
 * @return array{status:string,label:string,message:string,color:string,badge_bg:string,cta_bg:string,rejection_reason:string,cta:string}
 */
function kyc_profile_card_for_session(): array {
  $default = [
    'status' => 'not_submitted',
    'label' => 'Not submitted',
    'message' => 'Complete identity verification to protect your account and unlock full features.',
    'color' => 'var(--text-muted)',
    'badge_bg' => '#64748b',
    'cta_bg' => 'rgba(100, 116, 139, 0.2)',
    'rejection_reason' => '',
    'cta' => 'Start verification',
  ];
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid <= 0) {
    return $default;
  }
  try {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    $b = user_kyc_profile_card($db, (string)$DB_NAME, $uid);
    $db->close();
    return $b;
  } catch (Throwable) {
    return $default;
  }
}
