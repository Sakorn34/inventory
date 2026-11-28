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

// ─ ล็อกด้วย PIN ──────────────────────────────────────────────────────────────
// ตรวจรหัสที่ server แล้วออก cookie ที่ลงลายเซ็น (HMAC) — ไม่ต้องพึ่ง session ที่หมดอายุเร็ว
// ใส่ผิดครบ PICKUP_PREP_PIN_MAX_FAILS ครั้ง ล็อก IP นั้นไว้ PICKUP_PREP_PIN_LOCK_SECONDS วินาที

define('PICKUP_PREP_PIN_COOKIE', 'prep_board_unlock');
define('PICKUP_PREP_PIN_TTL', 12 * 3600);
define('PICKUP_PREP_PIN_MAX_FAILS', 5);
define('PICKUP_PREP_PIN_LOCK_SECONDS', 60);

function pickup_prep_board_pin_enabled()
{
    return inventory_config_pickup_prep_board_pin() !== '';
}

/**
 * secret ฝั่ง server สำหรับลงลายเซ็น cookie — สุ่มครั้งแรกแล้วเก็บไว้ใน temp
 * ถ้าเขียนไฟล์ไม่ได้ใช้ค่าที่ derive จากเครื่องแทน (ผู้ใช้ทั่วไปเดาไม่ได้)
 */
function pickup_prep_board_secret()
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $file = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'inv_prep_board_secret_' . md5(__DIR__);
    $stored = is_readable($file) ? trim((string) @file_get_contents($file)) : '';
    if (strlen($stored) < 64) {
        $fresh = bin2hex(random_bytes(32));
        if (@file_put_contents($file, $fresh, LOCK_EX) !== false) {
            @chmod($file, 0600);
            $stored = trim((string) @file_get_contents($file));
        }
    }
    if (strlen($stored) < 64) {
        $stored = hash('sha256', __DIR__ . '|' . php_uname() . '|' . inventory_config_pickup_prep_board_token());
    }
    $secret = $stored;

    return $secret;
}

function pickup_prep_board_unlock_sig($expiry, $token)
{
    return hash_hmac(
        'sha256',
        $expiry . '|' . hash('sha256', $token) . '|' . inventory_config_pickup_prep_board_pin(),
        pickup_prep_board_secret()
    );
}

/**
 * ปลดล็อกแล้วหรือยัง (cookie ยังไม่หมดอายุ ลายเซ็นตรง และผูกกับ token/PIN ปัจจุบัน)
 */
function pickup_prep_board_is_unlocked($token)
{
    if (!pickup_prep_board_pin_enabled()) {
        return true;
    }
    $raw = isset($_COOKIE[PICKUP_PREP_PIN_COOKIE]) ? (string) $_COOKIE[PICKUP_PREP_PIN_COOKIE] : '';
    if (!preg_match('/^(\d{10})\.([0-9a-f]{64})$/', $raw, $m)) {
        return false;
    }
    if ((int) $m[1] < time()) {
        return false;
    }

    return hash_equals(pickup_prep_board_unlock_sig($m[1], $token), $m[2]);
}

function pickup_prep_board_set_unlocked($token)
{
    $expiry = time() + PICKUP_PREP_PIN_TTL;
    $value = $expiry . '.' . pickup_prep_board_unlock_sig((string) $expiry, $token);
    $path = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/') . '/';
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        setcookie(PICKUP_PREP_PIN_COOKIE, $value, array(
            'expires' => $expiry,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        return;
    }
    // PHP < 7.3 ไม่มีตัวเลือก samesite — ส่ง header เอง
    header(
        'Set-Cookie: ' . PICKUP_PREP_PIN_COOKIE . '=' . $value
        . '; Expires=' . gmdate('D, d M Y H:i:s', $expiry) . ' GMT; Max-Age=' . PICKUP_PREP_PIN_TTL
        . '; Path=' . $path . ($secure ? '; Secure' : '') . '; HttpOnly; SameSite=Lax',
        false
    );
}

function pickup_prep_board_pin_state_file()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';

    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'inv_prep_pin_' . md5($ip . '|' . __DIR__) . '.json';
}

/**
 * ตรวจ PIN พร้อมนับครั้งที่ผิดต่อ IP
 *
 * @return array{ok:bool, locked_for:int, tries_left:int}
 */
function pickup_prep_board_try_pin($pin, $token)
{
    $file = pickup_prep_board_pin_state_file();
    $state = array('fails' => 0, 'lock_until' => 0);
    if (is_readable($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $state = array_merge($state, $decoded);
        }
    }
    $now = time();
    if ((int) $state['lock_until'] > $now) {
        return array('ok' => false, 'locked_for' => (int) $state['lock_until'] - $now, 'tries_left' => 0);
    }

    if (hash_equals(inventory_config_pickup_prep_board_pin(), (string) $pin)) {
        @unlink($file);
        pickup_prep_board_set_unlocked($token);

        return array('ok' => true, 'locked_for' => 0, 'tries_left' => PICKUP_PREP_PIN_MAX_FAILS);
    }

    $state['fails'] = (int) $state['fails'] + 1;
    $lockedFor = 0;
    if ($state['fails'] >= PICKUP_PREP_PIN_MAX_FAILS) {
        $state['fails'] = 0;
        $state['lock_until'] = $now + PICKUP_PREP_PIN_LOCK_SECONDS;
        $lockedFor = PICKUP_PREP_PIN_LOCK_SECONDS;
    }
    @file_put_contents($file, json_encode($state), LOCK_EX);

    return array(
        'ok' => false,
        'locked_for' => $lockedFor,
        'tries_left' => $lockedFor > 0 ? 0 : PICKUP_PREP_PIN_MAX_FAILS - $state['fails'],
    );
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
