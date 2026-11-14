<?php
/**
 * ตรวจ token สำหรับหน้ามือถือจัดของรอเบิก
 *
 * อ่าน token จาก query/header แล้วเทียบกับ inventory_config_pickup_prep_board_token()
 *
 * ตัวอย่าง: require จาก pickup_prep_board.php และ pickup_prep_api.php
 */

require_once __DIR__ . '/inventory_config.php';

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * อ่าน token จาก request (query token= หรือ header X-Prep-Board-Token)
 *
 * @return string token ที่ normalize แล้ว
 */
function pickup_prep_board_request_token()
{
    $token = '';
    if (isset($_GET['token'])) {
        $token = trim((string) $_GET['token']);
    } elseif (isset($_SERVER['HTTP_X_PREP_BOARD_TOKEN'])) {
        $token = trim((string) $_SERVER['HTTP_X_PREP_BOARD_TOKEN']);
    }

    if ($token !== '' && strpos($token, '%') !== false) {
        $token = trim(rawurldecode($token));
    }

    return $token;
}

/**
 * ตรวจว่า token ใน request ตรงกับค่าที่ server ตั้งไว้
 *
 * @param string $given token จากผู้ใช้
 * @return bool true เมื่อผ่าน
 */
function pickup_prep_board_token_valid($given)
{
    $expected = inventory_config_pickup_prep_board_token();
    if ($expected === '' || $given === '') {
        return false;
    }

    return hash_equals($expected, $given);
}

/**
 * คืนข้อความช่วยเหลือเมื่อเปิดหน้าไม่ได้ (ไม่เปิดเผย secret)
 *
 * @param string $given token จาก URL
 * @return string ข้อความภาษาไทย
 */
function pickup_prep_board_gate_hint($given)
{
    $expected = inventory_config_pickup_prep_board_token();
    $source = inventory_config_pickup_prep_board_token_source();

    if ($expected === '') {
        return 'ยังไม่มี token บน server — ตั้ง pickup_prep_board_token ใน inventory_config.local.php '
            . 'หรือใช้ line_stock_url_token ที่มีอยู่แล้ว (หลังอัปโหลด pickup_prep_board_auth.php รุ่นใหม่)';
    }

    if ($given === '') {
        return 'ต้องเปิดด้วย pickup_prep_board.php?token=... (คัดลอก token จาก inventory_config.local.php บน server นี้)';
    }

    $sourceHint = '';
    if ($source === 'line_stock') {
        $sourceHint = ' บน server นี้ใช้ token เดียวกับ line_stock_url_token';
    } elseif ($source === 'dedicated') {
        $sourceHint = ' บน server นี้ใช้ pickup_prep_board_token';
    }

    return 'token ใน URL ไม่ตรงกับ server' . $sourceHint
        . ' — ตรวจว่าแก้ไฟล์บน production (ไม่ใช่แค่เครื่อง dev) และไม่มีช่องว่าง/ขึ้นบรรทัดใหม่';
}
