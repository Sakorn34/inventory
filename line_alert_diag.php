<?php
/**
 * ตรวจสอบสภาพแวดล้อมสำหรับ line_stock_alert_url (ไม่เชื่อม DB)
 *
 * เรียก: line_alert_diag.php?token=รหัสลับเดียวกับ cron
 * ใช้เมื่อได้ HTTP 500 จาก line_stock_alert_url.php
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$jsonFlags = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;

/**
 * ตอบ JSON แล้วจบ request
 *
 * @param int $code HTTP status
 * @param array $payload ข้อมูล
 * @return void
 */
function line_alert_diag_json($code, $payload)
{
    global $jsonFlags;
    http_response_code($code);
    echo json_encode($payload, $jsonFlags);
    exit;
}

// token จริงอยู่ใน inventory_config.local.php (ไม่อยู่ใน repo)
$defaultToken = '';
$expectedToken = $defaultToken;
$localFile = __DIR__ . '/inventory_config.local.php';

if (is_readable($localFile)) {
    $cfg = include $localFile;
    if (is_array($cfg) && !empty($cfg['line_stock_url_token'])) {
        $expectedToken = trim((string) $cfg['line_stock_url_token']);
    }
}

$requestToken = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$tokenOk = $requestToken !== '' && $requestToken === $expectedToken;
if (function_exists('hash_equals')) {
    $tokenOk = $requestToken !== '' && hash_equals($expectedToken, $requestToken);
}
if (!$tokenOk) {
    line_alert_diag_json(403, array(
        'ok' => false,
        'message' => 'token ไม่ถูกต้อง',
    ));
}

$files = array(
    'inventory_config.php',
    'inventory_config.local.php',
    'line_messaging.php',
    'line_stock_alert.php',
    'line_stock_alert_url.php',
    'low_stock_alert.php',
    'connect.php',
);

$fileStatus = array();
foreach ($files as $name) {
    $path = __DIR__ . '/' . $name;
    $fileStatus[$name] = array(
        'exists' => file_exists($path),
        'readable' => is_readable($path),
    );
}

$lineChannel = '';
$lineTo = '';
if (is_readable($localFile)) {
    $cfg = include $localFile;
    if (is_array($cfg)) {
        $lineChannel = isset($cfg['line_channel_access_token']) ? trim((string) $cfg['line_channel_access_token']) : '';
        $lineTo = isset($cfg['line_to_id']) ? trim((string) $cfg['line_to_id']) : '';
    }
}

$dbCheck = isset($_GET['db']) && (string) $_GET['db'] === '1';
if (!$dbCheck) {
    line_alert_diag_json(200, array(
        'ok' => true,
        'php_version' => PHP_VERSION,
        'has_json_unicode' => defined('JSON_UNESCAPED_UNICODE'),
        'has_curl' => function_exists('curl_init'),
        'has_mysqli' => extension_loaded('mysqli'),
        'files' => $fileStatus,
        'line' => array(
            'has_channel_token' => $lineChannel !== '',
            'has_to_id' => $lineTo !== '',
            'to_id_prefix' => $lineTo !== '' ? substr($lineTo, 0, 1) : '',
        ),
        'next' => 'ถ้า files ครบ ลอง line_stock_alert_url.php?token=...&check=1 หรือ &db=1 ตรวจ DB',
    ));
}

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/low_stock_alert.php';

$connect = inventory_db_connection();
$dbPayload = array(
    'database' => inventory_alert_current_database($connect),
    'part_table_usable' => inventory_alert_table_usable($connect, 'part'),
    'part_line_alert_usable' => inventory_alert_table_column_usable($connect, 'part', 'part_line_alert'),
    'part_line_alert_exists' => inventory_alert_table_column_exists($connect, 'part', 'part_line_alert'),
    'line_runs_table_usable' => inventory_alert_table_usable($connect, 'line_stock_alert_runs'),
);

if ($connect instanceof mysqli) {
    db_close($connect);
}

line_alert_diag_json(200, array(
    'ok' => true,
    'php_version' => PHP_VERSION,
    'has_json_unicode' => defined('JSON_UNESCAPED_UNICODE'),
    'has_curl' => function_exists('curl_init'),
    'has_mysqli' => extension_loaded('mysqli'),
    'files' => $fileStatus,
    'line' => array(
        'has_channel_token' => $lineChannel !== '',
        'has_to_id' => $lineTo !== '',
        'to_id_prefix' => $lineTo !== '' ? substr($lineTo, 0, 1) : '',
    ),
    'db' => $dbPayload,
    'next' => 'ถ้า part_line_alert_usable=false รัน database/migrations/20250604_part_line_alert.sql ใน phpMyAdmin',
));
