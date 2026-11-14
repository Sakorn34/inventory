<?php
/**
 * อ่านชื่อผู้ทำรายการจาก SSO session profile (แนวเดียวกับ production actor_name)
 *
 * โหลด helper จาก finishgoogs_ma_update/includes/session_profile.php ถ้ามี
 * ใช้ login_name จาก $_SESSION['profile'] เป็นหลัก แล้ว fallback display_name
 *
 * ตัวอย่าง: require_once 'inventory_session_actor.php'; $who = inventory_actor_name();
 */

// ─ Session ──────────────────────────────────────────────────────────────────

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ─ Profile helpers (production หรือ fallback ย่อ) ─────────────────────────────

$inventorySessionProfileFile = realpath(__DIR__ . '/../production/finishgoogs_ma_update/includes/session_profile.php');
if ($inventorySessionProfileFile !== false && is_readable($inventorySessionProfileFile)) {
    require_once $inventorySessionProfileFile;
}

if (!function_exists('maintenance_new_session_profile')) {
    /**
     * คืนค่า profile จาก session
     *
     * @return object|array|string|null
     */
    function maintenance_new_session_profile()
    {
        if (!isset($_SESSION['profile'])) {
            return null;
        }

        return $_SESSION['profile'];
    }
}

if (!function_exists('maintenance_new_profile_login_name')) {
    /**
     * ดึง login_name จาก profile
     *
     * @return string|null
     */
    function maintenance_new_profile_login_name()
    {
        $profile = maintenance_new_session_profile();
        if ($profile === null) {
            return null;
        }
        if (is_object($profile) && isset($profile->login_name)) {
            return (string) $profile->login_name;
        }
        if (is_array($profile) && isset($profile['login_name'])) {
            return (string) $profile['login_name'];
        }

        return null;
    }
}

if (!function_exists('maintenance_new_profile_display_name')) {
    /**
     * ชื่อแสดงจาก profile
     *
     * @return string
     */
    function maintenance_new_profile_display_name()
    {
        $profile = maintenance_new_session_profile();
        if ($profile === null) {
            return '';
        }
        $keys = array('display_name', 'name', 'full_name', 'emp_name', 'nickname', 'login_name');
        foreach ($keys as $key) {
            if (is_object($profile) && isset($profile->{$key})) {
                $value = trim((string) $profile->{$key});
                if ($value !== '') {
                    return $value;
                }
            }
            if (is_array($profile) && isset($profile[$key])) {
                $value = trim((string) $profile[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

// ─ Dev localhost (สอดคล้อง production ss_dev_localhost_bootstrap) ─────────────

/**
 * ตรวจว่า request มาจาก localhost สำหรับ dev
 *
 * @return bool true เมื่อ REMOTE_ADDR และ HTTP_HOST เป็น localhost
 */
function inventory_is_localhost_request()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $ip = (string) (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $localIp = in_array($ip, array('127.0.0.1', '::1'), true);
    $host = (string) (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    $localHost = (bool) preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host);
    $cached = $localIp && $localHost;

    return $cached;
}

/**
 * Dev — ตั้ง session profile เป็น Tom เมื่อยังไม่ login SSO (ห้ามใช้บน production)
 *
 * @return void ไม่มี return value
 */
function inventory_dev_localhost_bootstrap()
{
    if (!inventory_is_localhost_request()) {
        return;
    }
    $hasProfile = false;
    if (function_exists('ss_session_has_profile')) {
        $hasProfile = ss_session_has_profile();
    } elseif (isset($_SESSION['profile']) && $_SESSION['profile'] !== null && $_SESSION['profile'] !== '') {
        $hasProfile = true;
    }
    if ($hasProfile) {
        return;
    }
    $_SESSION['profile'] = (object) array(
        'login_name' => 'Tom',
        'display_name' => 'Tom',
    );
}

inventory_dev_localhost_bootstrap();

// ─ Public API ───────────────────────────────────────────────────────────────

/**
 * ชื่อที่ใช้บันทึก log_by / pre_by = login_name จาก SSO (สอดคล้อง production actor_name)
 *
 * @return string ว่างเมื่อไม่มี profile ใน session (ยกเว้น dev localhost → Tom)
 */
function inventory_actor_name()
{
    $loginName = maintenance_new_profile_login_name();
    if ($loginName !== null && trim($loginName) !== '') {
        return trim($loginName);
    }

    $displayName = maintenance_new_profile_display_name();
    if ($displayName !== '') {
        return $displayName;
    }

    if (inventory_is_localhost_request()) {
        return 'Tom';
    }

    return '';
}

/**
 * ชื่อผู้บันทึกสำหรับบันทึกลงฐานข้อมูล — บังคับมีค่าจาก session
 *
 * @param int $maxLength ความยาวสูงสุดของชื่อ
 * @return string ชื่อผู้ทำรายการ
 */
function inventory_actor_name_for_save($maxLength = 100)
{
    $name = inventory_actor_name();
    if ($name === '') {
        throw new RuntimeException('ไม่พบชื่อผู้บันทึกจาก session — กรุณาเข้าสู่ระบบ SSO ใหม่');
    }
    $maxLength = (int) $maxLength;
    if ($maxLength <= 0) {
        return $name;
    }
    if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > $maxLength) {
        return mb_substr($name, 0, $maxLength, 'UTF-8');
    }
    if (strlen($name) > $maxLength) {
        return substr($name, 0, $maxLength);
    }

    return $name;
}

/**
 * แสดงฟิลด์ผู้บันทึก (hidden + ข้อความอ่านอย่างเดียว) จากชื่อผู้ทำรายการ
 *
 * @param string $inputName name ของ input
 * @param string $inputId id ของ input
 * @param string $label ข้อความ label
 * @param string $wrapperClass class เพิ่มบน inv-field
 * @return void ไม่มี return value — echo HTML
 */
function inventory_render_recorder_field($inputName, $inputId, $label = 'ผู้บันทึก', $wrapperClass = '')
{
    $actorName = inventory_actor_name();
    $escapedName = htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8');
    $escapedInputName = htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8');
    $escapedInputId = htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8');
    $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $emptyClass = $actorName === '' ? ' is-empty' : '';
    $displayText = $actorName !== '' ? $escapedName : '— ไม่พบชื่อจาก session —';
    $wrapperClass = trim('inv-field inv-recorder-field ' . $wrapperClass);

    echo '<div class="' . htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label for="' . $escapedInputId . '">' . $escapedLabel . '</label>';
    echo '<input type="hidden" name="' . $escapedInputName . '" id="' . $escapedInputId . '" value="' . $escapedName . '" />';
    echo '<div class="inv-recorder-value' . $emptyClass . '" id="' . $escapedInputId . '_display" aria-live="polite">';
    echo $displayText;
    echo '</div></div>';
}
