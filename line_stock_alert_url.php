<?php
/**
 * Endpoint เรียกแจ้งเตือนสต็อกต่ำผ่าน Line จาก cron URL
 *
 * โหมด check=1 ไม่เชื่อมฐานข้อมูล
 * ตัวอย่าง: line_stock_alert_url.php?token=...&check=1
 */

// ─ Bootstrap (ตอบ JSON แม้ require ล้มเหลว) ───────────────────────────────────

/**
 * @param int $statusCode
 * @param array $payload
 * @return void
 */
function inventory_line_alert_url_json_response($statusCode, $payload)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, max-age=0');
    }

    $flags = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
    echo json_encode($payload, $flags);
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null || headers_sent()) {
        return;
    }

    $fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
    if (!in_array($error['type'], $fatal, true)) {
        return;
    }

    inventory_line_alert_url_json_response(500, array(
        'ok' => false,
        'status' => 'fatal',
        'message' => $error['message'],
        'file' => basename((string) $error['file']),
        'line' => (int) $error['line'],
        'hint' => 'อัปโหลดไฟล์ครบหรือเรียก line_alert_diag.php?token=... เพื่อตรวจ',
    ));
});

/**
 * @param string $relative path ใต้โฟลเดอร์สคริปต์
 * @return void
 */
function inventory_line_alert_url_require_file($relative)
{
    $path = __DIR__ . '/' . $relative;
    if (!is_readable($path)) {
        inventory_line_alert_url_json_response(500, array(
            'ok' => false,
            'status' => 'missing_file',
            'message' => 'ไม่พบไฟล์บนเซิร์ฟเวอร์: ' . $relative,
            'hint' => 'อัปโหลดไฟล์จากโปรเจกต์ inventory หรือเรียก line_alert_diag.php?token=...',
        ));
    }

    require_once $path;
}

inventory_line_alert_url_require_file('inventory_config.php');
inventory_line_alert_url_require_file('line_messaging.php');

if (!defined('INVENTORY_LINE_STOCK_URL_TOKEN')) {
    define('INVENTORY_LINE_STOCK_URL_TOKEN', inventory_config_line_stock_url_token());
}

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * @param string $expected
 * @param string $actual
 * @return bool
 */
function inventory_line_alert_url_token_equals($expected, $actual)
{
    if (function_exists('hash_equals')) {
        return hash_equals($expected, $actual);
    }

    if (strlen($expected) !== strlen($actual)) {
        return false;
    }

    $result = 0;
    for ($i = 0; $i < strlen($expected); $i++) {
        $result |= ord($expected[$i]) ^ ord($actual[$i]);
    }

    return $result === 0;
}

/**
 * @return string
 */
function inventory_line_alert_url_request_token()
{
    return isset($_GET['token']) ? trim((string) $_GET['token']) : '';
}

/**
 * @return bool
 */
function inventory_line_alert_url_force_flag()
{
    return isset($_GET['force']) && (string) $_GET['force'] === '1';
}

/**
 * @return void
 */
function inventory_line_alert_url_require_token()
{
    $requestToken = inventory_line_alert_url_request_token();
    if ($requestToken === '') {
        inventory_line_alert_url_json_response(403, array(
            'ok' => false,
            'status' => 'forbidden',
            'message' => 'ไม่ได้ส่ง token ใน URL',
        ));
    }

    if (!inventory_line_alert_url_token_equals(INVENTORY_LINE_STOCK_URL_TOKEN, $requestToken)) {
        inventory_line_alert_url_json_response(403, array(
            'ok' => false,
            'status' => 'forbidden',
            'message' => 'token ไม่ถูกต้อง',
        ));
    }
}

// ─ Main ─────────────────────────────────────────────────────────────────────

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
    inventory_line_alert_url_json_response(405, array(
        'ok' => false,
        'status' => 'method_not_allowed',
        'message' => 'อนุญาตเฉพาะ GET',
    ));
}

inventory_line_alert_url_require_token();

if (isset($_GET['check']) && (string) $_GET['check'] === '1') {
    $lineStatus = function_exists('line_messaging_config_status')
        ? line_messaging_config_status()
        : array('configured' => line_messaging_is_configured());

    inventory_line_alert_url_json_response(200, array(
        'ok' => true,
        'status' => 'token_ok',
        'message' => !empty($lineStatus['configured'])
            ? 'พร้อมส่ง Line — ใช้ force=1 เพื่อทดสอบ'
            : 'token OK แต่ยังไม่ครบ line_channel_access_token / line_to_id ใน inventory_config.local.php',
        'line' => $lineStatus,
        'config_local_readable' => is_readable(__DIR__ . '/inventory_config.local.php'),
        'php_version' => PHP_VERSION,
    ));
}

inventory_line_alert_url_require_file('connect.php');
inventory_line_alert_url_require_file('line_stock_alert.php');

try {
    $connect = inventory_db_connection();
    $result = inventory_line_alert_run($connect, inventory_line_alert_url_force_flag());
    $payload = array(
        'ok' => $result['status'] !== 'failed',
        'status' => $result['status'],
        'message' => $result['message'],
        'item_count' => $result['item_count'],
    );
    if ($result['status'] === 'failed' && function_exists('line_messaging_config_status')) {
        $payload['line'] = line_messaging_config_status();
    }
    inventory_line_alert_url_json_response($result['status'] === 'failed' ? 500 : 200, $payload);
} catch (Exception $e) {
    inventory_line_alert_url_json_response(500, array(
        'ok' => false,
        'status' => 'failed',
        'message' => $e->getMessage(),
    ));
}
