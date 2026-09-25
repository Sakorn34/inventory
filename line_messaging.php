<?php
/**
 * โมดูลส่งข้อความผ่าน LINE Messaging API (Push Message)
 *
 * ใช้ Channel access token และ to (User ID / Group ID / Room ID) จาก environment
 * ไม่ใช้ LINE Notify อีกต่อไป
 *
 * ตั้งค่าใน inventory_config.local.php หรือ environment:
 * - line_channel_access_token / LINE_CHANNEL_ACCESS_TOKEN
 * - line_to_id / LINE_TO_ID — ปลายทาง U..., C..., R... (หลายค่าคั่น comma)
 *
 * Flow สั้น:
 * 1. โหลดไฟล์นี้
 * 2. เรียก line_messaging_push_text('ข้อความ')
 * 3. ระบบ POST ไป https://api.line.me/v2/bot/message/push
 *
 * ตัวอย่าง:
 * line_messaging_push_text("[Inventory] สต็อกต่ำกว่าเกณฑ์");
 */

// ─ Constants ────────────────────────────────────────────────────────────────

if (!defined('LINE_MESSAGING_PUSH_URL')) {
    define('LINE_MESSAGING_PUSH_URL', 'https://api.line.me/v2/bot/message/push');
}

if (!defined('LINE_MESSAGING_TEXT_MAX')) {
    define('LINE_MESSAGING_TEXT_MAX', 5000);
}

// ─ Config ───────────────────────────────────────────────────────────────────

/**
 * อ่าน Channel access token จาก environment
 *
 * @return string token หรือ string ว่างเมื่อไม่ได้ตั้งค่า
 */
function line_messaging_channel_token()
{
    if (!function_exists('inventory_config_get')) {
        require_once __DIR__ . '/inventory_config.php';
    }

    return inventory_config_get('line_channel_access_token', '');
}

/**
 * อ่านรายการปลายทาง push (รองรับหลาย ID คั่นด้วย comma)
 *
 * @return array รายการ to ที่ไม่ว่าง
 */
function line_messaging_recipient_ids()
{
    if (!function_exists('inventory_config_get')) {
        require_once __DIR__ . '/inventory_config.php';
    }

    $raw = inventory_config_get('line_to_id', '');
    if ($raw === '') {
        $raw = inventory_config_get('line_messaging_to_id', '');
    }
    if ($raw === '') {
        return array();
    }

    $ids = array();
    foreach (explode(',', (string) $raw) as $part) {
        $id = trim($part);
        if ($id !== '' && preg_match('/^[UCR][a-zA-Z0-9]{20,}$/', $id)) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * ตรวจว่าตั้งค่า Messaging API พร้อมส่งหรือไม่
 *
 * @return bool true เมื่อมี token และ to อย่างน้อยหนึ่งรายการ
 */
function line_messaging_is_configured()
{
    return line_messaging_channel_token() !== ''
        && count(line_messaging_recipient_ids()) > 0
        && function_exists('curl_init');
}

/**
 * สถานะการตั้งค่า LINE Messaging (ไม่เปิดเผย secret)
 *
 * @return array<string, mixed> รายละเอียดสำหรับ JSON debug
 */
function line_messaging_config_status()
{
    $recipients = line_messaging_recipient_ids();

    return array(
        'configured' => line_messaging_is_configured(),
        'has_channel_token' => line_messaging_channel_token() !== '',
        'has_recipients' => count($recipients) > 0,
        'recipient_count' => count($recipients),
        'has_curl' => function_exists('curl_init'),
        'config_file' => 'inventory_config.local.php',
        'config_keys' => array('line_channel_access_token', 'line_to_id'),
    );
}

/**
 * ข้อความสรุปเมื่อยังไม่ได้ตั้งค่า (สำหรับ log/UI)
 *
 * @return string
 */
function line_messaging_config_error_message()
{
    if (line_messaging_channel_token() === '') {
        return 'ไม่ได้ตั้งค่า line_channel_access_token ใน inventory_config.local.php';
    }
    if (count(line_messaging_recipient_ids()) === 0) {
        return 'ไม่ได้ตั้งค่า line_to_id ใน inventory_config.local.php (User/Group/Room ID)';
    }
    if (!function_exists('curl_init')) {
        return 'เซิร์ฟเวอร์ไม่มี PHP curl';
    }

    return 'การตั้งค่า LINE Messaging API ไม่ครบ';
}

// ─ Send ─────────────────────────────────────────────────────────────────────

/**
 * ตัดข้อความให้ไม่เกินขีดจำกัดของ LINE text message
 *
 * @param string $text ข้อความต้นฉบับ
 * @return string ข้อความที่ตัดแล้ว
 */
function line_messaging_truncate_text($text)
{
    $text = (string) $text;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= LINE_MESSAGING_TEXT_MAX) {
            return $text;
        }

        return mb_substr($text, 0, LINE_MESSAGING_TEXT_MAX, 'UTF-8');
    }

    if (strlen($text) <= LINE_MESSAGING_TEXT_MAX) {
        return $text;
    }

    return substr($text, 0, LINE_MESSAGING_TEXT_MAX);
}

/**
 * ส่ง push message ข้อความเดียวไปยัง to หนึ่งรายการ
 *
 * @param string $channelToken Channel access token
 * @param string $toId User/Group/Room ID
 * @param string $text เนื้อหาข้อความ
 * @param string $logPrefix คำนำหน้า error_log
 * @return bool true เมื่อ LINE API ตอบสำเร็จ
 */
function line_messaging_push_text_to($channelToken, $toId, $text, $logPrefix = 'inventory LINE')
{
    $payload = json_encode(
        array(
            'to' => $toId,
            'messages' => array(
                array(
                    'type' => 'text',
                    'text' => line_messaging_truncate_text($text),
                ),
            ),
        ),
        JSON_UNESCAPED_UNICODE
    );
    if ($payload === false) {
        error_log($logPrefix . ' json encode failed');
        return false;
    }

    $ch = curl_init();
    if ($ch === false) {
        return false;
    }

    curl_setopt($ch, CURLOPT_URL, LINE_MESSAGING_PUSH_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $channelToken,
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        error_log($logPrefix . ' curl error: ' . $curlError);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log($logPrefix . ' API HTTP ' . $httpCode . ' to=' . $toId . ' body=' . (string) $body);
        return false;
    }

    return true;
}

/**
 * ส่งข้อความไปทุกปลายทางที่ตั้งใน LINE_TO_ID
 *
 * @param string $text ข้อความที่ต้องการส่ง
 * @param string $logPrefix คำนำหน้า error_log
 * @return bool true เมื่อส่งสำเร็จอย่างน้อยหนึ่งปลายทาง และไม่มีปลายทางใดล้มเหลว
 */
function line_messaging_push_text($text, $logPrefix = 'inventory LINE')
{
    if (!line_messaging_is_configured()) {
        return false;
    }

    $channelToken = line_messaging_channel_token();
    $recipients = line_messaging_recipient_ids();
    $allOk = true;
    $anySent = false;

    foreach ($recipients as $toId) {
        $ok = line_messaging_push_text_to($channelToken, $toId, $text, $logPrefix);
        if ($ok) {
            $anySent = true;
        } else {
            $allOk = false;
        }
    }

    return $anySent && $allOk;
}
