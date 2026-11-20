<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/user_db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

$schema = (string)$DB_NAME;

function wallet_api_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!is_string($raw) || $raw === '') {
    return [];
  }
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  try {
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    ensure_user_dashboard_schema($db, $schema);
    $row = user_fetch_by_id($db, $userId);
    $walletId = '';
    if (is_array($row)) {
      $walletId = is_string($row['wallet_id'] ?? null) ? (string)$row['wallet_id'] : '';
      if ($walletId === '') {
        $walletId = user_ensure_wallet_id($db, $userId);
      }
    }
    $agg = user_get_wallet_display_aggregates($db, $schema, $userId);
    $mainWallet = $agg['cash_usd'];
    $cryptoPortfolio = $agg['crypto_portfolio_usd'];
    $totalDisplay = $agg['total_balance_usd'];
    $tradable = $mainWallet;
    $tradeBal = user_get_trade_balance_usd($db, $schema, $userId);
    try {
      $p = user_fetch_portfolio($db, $userId);
      if (is_array($p) && isset($p['spot_balance_usd'])) {
        $tradeBal = (string)$p['spot_balance_usd'];
      }
    } catch (Throwable $e) {
      error_log('[wallet_api portfolio] ' . $e->getMessage());
    }
    $db->close();
    $vol = '0.00';
    if (is_array($row)) {
      $vol = (string)($row['volume_24h_usd'] ?? '0.00');
    }

    echo json_encode(
      [
        'ok' => true,
        'wallet' => [
          'wallet_balance_usd' => $totalDisplay,
          'total_balance_usd' => $totalDisplay,
          'main_wallet_usd' => $mainWallet,
          'available_balance_usd' => $totalDisplay,
          'tradable_balance_usd' => $tradable,
          'trade_balance_usd' => $tradeBal,
          'crypto_portfolio_usd' => $cryptoPortfolio,
          'portfolio_value_usd' => $cryptoPortfolio,
          'volume_24h_usd' => $vol,
          'wallet_id' => $walletId,
        ],
        'csrf' => user_csrf_token(),
      ],
      JSON_UNESCAPED_SLASHES
    );
  } catch (Throwable $e) {
    error_log('[wallet_api GET] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load wallet.']);
  }
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$data = wallet_api_json_body();
$csrf = isset($data['csrf_token']) && is_string($data['csrf_token']) ? $data['csrf_token'] : null;
if (!user_verify_csrf($csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
  exit;
}

$action = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
} catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
  exit;
}

try {
  if ($action === 'transfer_to_trade') {
    $rawAmt = $data['amount_usd'] ?? $data['amount'] ?? null;
    $amountUsd = is_numeric($rawAmt) ? (float)$rawAmt : 0.0;
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $ip = is_string($clientIp) ? $clientIp : null;
    $r = user_transfer_wallet_to_trade($db, $schema, $userId, $amountUsd, $ip);
    $db->close();
    if (!($r['ok'] ?? false)) {
      $err = (string)($r['error'] ?? 'transfer_failed');
      $map = [
        'insufficient_balance' => 'Insufficient wallet balance.',
        'amount' => 'Enter a valid amount (minimum $0.01).',
        'auth' => 'Not authenticated.',
      ];
      $msg = $map[$err] ?? 'Transfer failed.';
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => $msg, 'code' => $err], JSON_UNESCAPED_SLASHES);
      exit;
    }
    $wBal = (string)($r['available_balance_usd'] ?? '0.00');
    echo json_encode(
      [
        'ok' => true,
        'wallet_balance_usd' => $wBal,
        'available_balance_usd' => $wBal,
        'trade_balance_usd' => (string)($r['spot_balance_usd'] ?? '0.00'),
        'spot_balance_usd' => (string)($r['spot_balance_usd'] ?? '0.00'),
        'portfolio_value_usd' => (string)($r['portfolio_value_usd'] ?? '0.00'),
        'csrf' => user_csrf_token(),
      ],
      JSON_UNESCAPED_SLASHES
    );
    exit;
  }

  // Send flow (syncs with Admin/admin-send.php):
  // 1) Debits user_asset_balances (send amount only) immediately inside this transaction.
  // 2) Inserts transactions row type=send, status=pending — admin approves → completed; decline → refund in admin_transactions_data.php.
  // 3) Main USD wallet (available_balance_usd) is NOT debited here; only crypto positions change. Wallet/Dashboard USD totals unchanged.
  if ($action === 'send') {
    ensure_asset_portfolio_schema($db);
    ensure_transactions_table($db);

    $assetId = isset($data['asset']) && is_string($data['asset']) ? strtolower(trim($data['asset'])) : '';
    $cryptoAmt = isset($data['crypto_amount']) && is_numeric($data['crypto_amount']) ? (float)$data['crypto_amount'] : 0.0;
    $toAddr = isset($data['to_address']) && is_string($data['to_address']) ? trim($data['to_address']) : '';
    $network = isset($data['network']) && is_string($data['network']) ? trim($data['network']) : '';

    if ($assetId === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Unsupported asset.', 'code' => 'asset'], JSON_UNESCAPED_SLASHES);
      exit;
    }
    if ($toAddr === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Recipient address is required.', 'code' => 'address'], JSON_UNESCAPED_SLASHES);
      exit;
    }
    if (!is_finite($cryptoAmt) || $cryptoAmt <= 0) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Enter a valid amount.', 'code' => 'amount'], JSON_UNESCAPED_SLASHES);
      exit;
    }

    $netDb = $network;
    $stmtA = $db->prepare('SELECT network FROM asset_catalog WHERE asset_id = ? AND is_active = 1 LIMIT 1');
    if ($stmtA) {
      $stmtA->bind_param('s', $assetId);
      $stmtA->execute();
      $stmtA->store_result();
      $netDefault = '';
      $stmtA->bind_result($netDefault);
      if ($stmtA->fetch() && $netDb === '' && $netDefault !== '') {
        $netDb = trim($netDefault);
      }
      $stmtA->free_result();
      $stmtA->close();
    }

    $db->begin_transaction();
    try {
      $sel = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
      if (!$sel) throw new RuntimeException('sel');
      $sel->bind_param('is', $userId, $assetId);
      $sel->execute();
      $sel->store_result();
      $balStr = '0';
      $sel->bind_result($balStr);
      $sel->fetch();
      $sel->free_result();
      $sel->close();
      $bal = (float)$balStr;

      if ($bal + 1e-12 < $cryptoAmt) {
        $db->rollback();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Insufficient balance.', 'code' => 'insufficient_balance'], JSON_UNESCAPED_SLASHES);
        exit;
      }

      $newBal = $bal - $cryptoAmt;
      $newBalStr = number_format($newBal, 18, '.', '');
      $up = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
      if (!$up) throw new RuntimeException('up');
      $up->bind_param('iss', $userId, $assetId, $newBalStr);
      $up->execute();
      $up->close();

      $meta = [
        'to_address' => $toAddr,
        'network' => $network !== '' ? $network : $netDb,
        'asset_id' => $assetId,
        'crypto_amount' => $cryptoAmt,
        'at' => date('c'),
      ];
      $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
      $amtDb = number_format($cryptoAmt, 18, '.', '');
      $afterDb = number_format($newBal, 18, '.', '');
      $led = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'debit\', ?, ?, ?)');
      if ($led) {
        $led->bind_param('issss', $userId, $assetId, $amtDb, $afterDb, $metaJson);
        $led->execute();
        $led->close();
      }

      $txType = 'send';
      $txAsset = strtoupper($assetId);
      $txAmount = number_format($cryptoAmt, 18, '.', '');
      $txStatus = 'pending';
      $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
      if (!$ins) throw new RuntimeException('tx');
      $ins->bind_param('isssss', $userId, $txType, $txAsset, $txAmount, $txStatus, $metaJson);
      $ins->execute();
      $txId = (int)$ins->insert_id;
      $ins->close();

      $db->commit();
      $db->close();

      echo json_encode(
        [
          'ok' => true,
          'id' => $txId,
          'csrf' => user_csrf_token(),
        ],
        JSON_UNESCAPED_SLASHES
      );
      exit;
    } catch (Throwable $e) {
      $db->rollback();
      throw $e;
    }
  }

  if ($action === 'receive') {
    ensure_asset_portfolio_schema($db);
    ensure_transactions_table($db);

    $assetId = isset($data['asset']) && is_string($data['asset']) ? strtolower(trim($data['asset'])) : '';
    $cryptoAmt = isset($data['crypto_amount']) && is_numeric($data['crypto_amount']) ? (float)$data['crypto_amount'] : 0.0;
    $fromAddr = isset($data['from_address']) && is_string($data['from_address']) ? trim($data['from_address']) : '';
    $toAddr = isset($data['to_address']) && is_string($data['to_address']) ? trim($data['to_address']) : '';
    $network = isset($data['network']) && is_string($data['network']) ? trim($data['network']) : '';

    if ($assetId === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Unsupported asset.', 'code' => 'asset'], JSON_UNESCAPED_SLASHES);
      exit;
    }
    if (!is_finite($cryptoAmt) || $cryptoAmt <= 0) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Enter a valid amount.', 'code' => 'amount'], JSON_UNESCAPED_SLASHES);
      exit;
    }

    // Best-effort: credit the user wallet asset balance immediately.
    $db->begin_transaction();
    try {
      $stmtB = $db->prepare('SELECT balance FROM user_asset_balances WHERE user_id = ? AND asset_id = ? LIMIT 1 FOR UPDATE');
      if (!$stmtB) {
        throw new RuntimeException('prepare balance');
      }
      $stmtB->bind_param('is', $userId, $assetId);
      $stmtB->execute();
      $stmtB->store_result();
      $balStr = '0';
      $stmtB->bind_result($balStr);
      $stmtB->fetch();
      $stmtB->free_result();
      $stmtB->close();

      $bal = (float)$balStr;
      $newBal = $bal + $cryptoAmt;
      $newBalStr = number_format($newBal, 18, '.', '');
      $up = $db->prepare('INSERT INTO user_asset_balances (user_id, asset_id, balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)');
      if (!$up) {
        throw new RuntimeException('up balance');
      }
      $up->bind_param('iss', $userId, $assetId, $newBalStr);
      $up->execute();
      $up->close();

      $meta = [
        'from_address' => $fromAddr,
        'to_address' => $toAddr,
        'wallet_address' => $toAddr,
        'network' => $network,
        'asset_id' => $assetId,
        'crypto_amount' => $cryptoAmt,
        'source' => 'receive_page',
        'at' => date('c'),
      ];
      $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

      $amtDb = number_format($cryptoAmt, 18, '.', '');
      $afterDb = number_format($newBal, 18, '.', '');
      $led = $db->prepare('INSERT INTO user_asset_ledger (user_id, asset_id, direction, amount, balance_after, meta) VALUES (?, ?, \'credit\', ?, ?, ?)');
      if ($led) {
        $led->bind_param('issss', $userId, $assetId, $amtDb, $afterDb, $metaJson);
        $led->execute();
        $led->close();
      }

      $txType = 'receive';
      $txAsset = strtoupper($assetId);
      $txAmount = number_format($cryptoAmt, 8, '.', '');
      $txStatus = 'completed';
      $ins = $db->prepare('INSERT INTO transactions (user_id, type, asset, amount, status, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
      if (!$ins) {
        throw new RuntimeException('tx');
      }
      $ins->bind_param('isssss', $userId, $txType, $txAsset, $txAmount, $txStatus, $metaJson);
      $ins->execute();
      $txId = (int)$ins->insert_id;
      $ins->close();

      $db->commit();
      $db->close();

      echo json_encode(
        [
          'ok' => true,
          'id' => $txId,
          'csrf' => user_csrf_token(),
        ],
        JSON_UNESCAPED_SLASHES
      );
      exit;
    } catch (Throwable $e) {
      $db->rollback();
      throw $e;
    }
  }

  $db->close();
  echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
  error_log('[wallet_api POST] ' . $e->getMessage());
  if (isset($db) && $db instanceof mysqli) {
    $db->close();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Request failed.']);
}
