<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/user_db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_SLASHES);
  exit;
}

$assetId = isset($_GET['asset_id']) && is_string($_GET['asset_id']) ? strtolower(trim($_GET['asset_id'])) : '';
$network = isset($_GET['network']) && is_string($_GET['network']) ? trim($_GET['network']) : '';
$amount = isset($_GET['amount']) && is_numeric($_GET['amount']) ? (float)$_GET['amount'] : 0.0;

if ($assetId === '' || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'input'], JSON_UNESCAPED_SLASHES);
  exit;
}

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  $schema = (string)$DB_NAME;
  ensure_network_fee_rules_schema($db);
  ensure_user_dashboard_schema($db, $schema);

  $tradeBalStr = user_get_trade_balance_usd($db, $schema, $userId);
  $tradeBal = (float)$tradeBalStr;

  $q = user_quote_network_fees($db, $userId, $assetId, $network, $amount);
  if (!($q['ok'] ?? false)) {
    $db->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string)($q['error'] ?? 'fee')], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $assetSym = (string)($q['asset_symbol'] ?? '');
  $gasSym = (string)($q['gas_symbol'] ?? '');
  $gasFeeAmt = (float)($q['gas_fee_amount'] ?? 0.0);
  $feePct = (float)($q['asset_fee_pct'] ?? 0.0);

  $assetPx = $assetSym !== '' ? getLiveAssetPrice($assetSym) : 0.0;
  $gasPx = $gasSym !== '' ? getLiveAssetPrice($gasSym) : 0.0;

  if (!is_finite($assetPx) || $assetPx <= 0) {
    $db->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'price_unavailable'], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $usdValue = $amount * $assetPx;
  $platformFeeUsd = ($feePct > 0 && is_finite($feePct)) ? max(0.0, $usdValue * $feePct) : 0.0;
  $gasFeeUsd = ($gasFeeAmt > 0 && $gasPx > 0) ? ($gasFeeAmt * $gasPx) : 0.0;
  $totalUsd = $usdValue + $platformFeeUsd + $gasFeeUsd;

  // HARD STOP precheck for gas token (no deductions here).
  $v = validateNetworkFeeToken($userId, $assetId, (string)($q['network_code'] ?? ''), $gasFeeAmt);
  $okGas = (bool)($v['ok'] ?? false);

  $okTrade = $tradeBal + 1e-9 >= $totalUsd;
  $can = $okGas && $okTrade;

  $db->close();
  echo json_encode(
    [
      'ok' => true,
      'trade_balance_usd' => $tradeBal,
      'asset_symbol' => $assetSym,
      'price_usd' => $assetPx,
      'amount_usd' => $usdValue,
      'network_code' => (string)($q['network_code'] ?? ''),
      'gas_symbol' => strtoupper((string)($v['required_token_symbol'] ?? $gasSym)),
      'gas_fee_amount' => $gasFeeAmt,
      'gas_fee_usd' => $gasFeeUsd,
      'platform_fee_usd' => $platformFeeUsd,
      'total_usd_deduct' => $totalUsd,
      'can_proceed' => $can,
      'ok_gas' => $okGas,
      'ok_trade' => $okTrade,
      'alert' => $okGas ? '' : (string)($v['alert'] ?? ''),
      'ts' => time(),
    ],
    JSON_UNESCAPED_SLASHES
  );
} catch (Throwable $e) {
  error_log('[trade_withdraw_quote_api] ' . $e->getMessage());
  if (isset($db) && $db instanceof mysqli) {
    @$db->close();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server'], JSON_UNESCAPED_SLASHES);
}

