<?php
/**
 * โมดูล endpoint สำหรับเรียกแจ้งเตือนสต็อกต่ำผ่าน URL
 *
 * ขอบเขตของไฟล์นี้คือรับ request จาก cron service ภายนอกเวลา 09:00 น.,
 * ตรวจ token เพื่อกันการเรียกโดยไม่ได้รับอนุญาต, แล้วเรียก flow แจ้งเตือนจาก
 * `low_stock_alert.php` โดยคืนผลลัพธ์เป็น JSON สำหรับดูสถานะจาก cron log
 *
 * Flow สั้น:
 * 1. Cron เรียก `low_stock_alert_url.php?token=...`
 * 2. endpoint ตรวจ method และ token
 * 3. เรียก `inventory_alert_run()` และตอบ JSON
 *
 * ตัวอย่าง:
 * `https://example.com/inventory/low_stock_alert_url.php?token=CHANGE_ME`
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/low_stock_alert.php';
require_once __DIR__ . '/inventory_config.php';

// ─ Constants ────────────────────────────────────────────────────────────────

// token จริงอยู่ใน inventory_config.local.php (ไม่อยู่ใน repo)
if (!defined('INVENTORY_LOW_STOCK_URL_TOKEN')) {
    $token = getenv('INVENTORY_LOW_STOCK_URL_TOKEN');
    define(
        'INVENTORY_LOW_STOCK_URL_TOKEN',
        $token !== false && $token !== '' ? $token : inventory_config_line_stock_url_token()
    );
}

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * ส่ง JSON response พร้อม secure headers พื้นฐาน
 *
 * @param int $statusCode HTTP status code ที่ต้องการส่งกลับ
 * @param array $payload ข้อมูลที่จะ encode เป็น JSON
 * @return void ไม่มี return value เพราะจบ request ทันที
 */
function inventory_alert_url_json_response($statusCode, $payload)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, max-age=0');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * เปรียบเทียบ token แบบลด timing leak เมื่อ PHP มี hash_equals
 *
 * @param string $expected token ที่ตั้งค่าไว้ในระบบ
 * @param string $actual token ที่มากับ URL
 * @return bool คืน true เมื่อ token ตรงกัน
 */
function inventory_alert_url_token_equals($expected, $actual)
{
    // ยังไม่ได้ตั้ง token → ปฏิเสธเสมอ (กัน hash_equals('', '') ผ่าน)
    if ((string) $expected === '') {
        return false;
    }

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
 * อ่าน token จาก query string โดยไม่ใช้ค่าจาก cookie/session
 *
 * @return string token ที่ส่งมากับ URL หรือ string ว่างเมื่อไม่มีค่า
 */
function inventory_alert_url_request_token()
{
    return isset($_GET['token']) ? trim((string) $_GET['token']) : '';
}

/**
 * อ่าน flag force สำหรับทดสอบแบบ manual ผ่าน URL
 *
 * @return bool คืน true เมื่อ query string มี `force=1`
 */
function inventory_alert_url_force_flag()
{
    return isset($_GET['force']) && (string) $_GET['force'] === '1';
}

// ─ Main ─────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    inventory_alert_url_json_response(405, array(
        'ok' => false,
        'status' => 'method_not_allowed',
        'message' => 'อนุญาตเฉพาะ GET สำหรับ scheduler URL',
    ));
}

if (!inventory_alert_url_token_equals(INVENTORY_LOW_STOCK_URL_TOKEN, inventory_alert_url_request_token())) {
    inventory_alert_url_json_response(403, array(
        'ok' => false,
        'status' => 'forbidden',
        'message' => 'token ไม่ถูกต้อง',
    ));
}

try {
    $result = inventory_alert_run(inventory_db_connection(), inventory_alert_url_force_flag());
    inventory_alert_url_json_response($result['status'] === 'failed' ? 500 : 200, array(
        'ok' => $result['status'] !== 'failed',
        'status' => $result['status'],
        'message' => $result['message'],
        'item_count' => $result['item_count'],
        'recipient_count' => $result['recipient_count'],
    ));
} catch (Exception $e) {
    error_log('inventory low stock alert url error: ' . $e->getMessage());
    inventory_alert_url_json_response(500, array(
        'ok' => false,
        'status' => 'failed',
        'message' => 'เกิดข้อผิดพลาดระหว่างแจ้งเตือนสต็อกต่ำ',
    ));
}
