<?php
/**
 * โมดูลเชื่อมต่อฐานข้อมูล MariaDB สำหรับระบบ inventory
 *
 * โหลดการตั้งค่า สร้าง mysqli connection เก็บใน $connect
 * และให้ฟังก์ชัน db_* แทน odbc_* เดิม (ใช้กับ MariaDB)
 * รวมถึงลงทะเบียน output buffer สำหรับแทรก favicon ในหน้า HTML
 *
 * ตัวอย่าง: require 'connect.php'; แล้วใช้ db_exec($connect, $sql);
 */

// ─ การตั้งค่าฐานข้อมูล ────────────────────────────────────────────────────────

$DB_HOST = 'localhost';
$DB_USER = 'biton_inventory';
$DB_PASS = '';
$DB_NAME = 'biton_inventory';

$DsnName = 'inventory';
$UserName = $DB_USER;
$Password = $DB_PASS;

$arr_username = array();
$arr_password = array();

date_default_timezone_set('Asia/Bangkok');

if (!extension_loaded('mysqli')) {
    die('เซิร์ฟเวอร์ไม่มี PHP extension mysqli — ติดต่อผู้ดูแล hosting');
}

// ─ เชื่อมต่อ MariaDB ─────────────────────────────────────────────────────────

$connect = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$connect) {
    http_response_code(500);
    die('ไม่สามารถเชื่อมต่อ MariaDB ได้: ' . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');
$GLOBALS['connect'] = $connect;

/**
 * คืน mysqli connection หลัง require connect.php
 *
 * ใช้ $GLOBALS เพราะบาง endpoint โหลด connect.php ผ่านฟังก์ชัน (local scope)
 *
 * @return mysqli connection ที่พร้อมใช้งาน
 */
function inventory_db_connection()
{
    if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
        return $GLOBALS['connect'];
    }

    global $connect;

    if (isset($connect) && $connect instanceof mysqli) {
        $GLOBALS['connect'] = $connect;

        return $connect;
    }

    throw new RuntimeException('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ — ตรวจ connect.php');
}

// ─ แปลง SQL จากรูปแบบ Access/ODBC ─────────────────────────────────────────────

/**
 * แปลงคำสั่ง SQL จากรูปแบบ Microsoft Access ให้ใช้กับ MariaDB
 *
 * @param string $sql คำสั่ง SQL ต้นฉบับ
 * @return string คำสั่งที่ปรับแล้ว
 */
if (!function_exists('db_normalize_sql')) {
    function db_normalize_sql($sql)
    {
        $sql = preg_replace('/\[([^\]]+)\]/', '`$1`', $sql);

        if (preg_match('/^\s*SELECT\s+TOP\s+(\d+)\s+/i', $sql, $m)) {
            $sql = preg_replace('/^\s*SELECT\s+TOP\s+\d+\s+/i', 'SELECT ', $sql);
            $sql = rtrim(trim($sql), ';') . ' LIMIT ' . (int) $m[1];
        }

        return $sql;
    }
}

// ─ ฟังก์ชัน query (แทน odbc_* เดิม) ───────────────────────────────────────────

/** @var array|null แถวปัจจุบันสำหรับ db_result */
$_db_current_row = null;

/**
 * รันคำสั่ง SQL ผ่าน mysqli
 *
 * @param mysqli $conn การเชื่อมต่อ
 * @param string $sql คำสั่ง SQL
 * @return mysqli_result|bool
 */
if (!function_exists('db_exec')) {

function db_exec($conn, $sql)
{
    $sql = db_normalize_sql($sql);
    $result = mysqli_query($conn, $sql);

    if ($result === false) {
        $err = mysqli_error($conn);
        error_log('inventory SQL error: ' . $err . ' | ' . $sql);
        if (defined('INVENTORY_DEBUG') && INVENTORY_DEBUG) {
            die('Error Query [' . $sql . '] ' . $err);
        }
        return false;
    }

    return $result;
}

/**
 * ดึงแถวถัดไปเป็น associative array
 *
 * @param mysqli_result $result
 * @return array|false
 */
function db_fetch_array($result)
{
    if (!$result instanceof mysqli_result) {
        return false;
    }

    return mysqli_fetch_assoc($result);
}

/**
 * ดึงแถวถัดไป (รองรับ db_result)
 *
 * @param mysqli_result $result
 * @return bool
 */
function db_fetch_row($result)
{
    global $_db_current_row;

    if (!$result instanceof mysqli_result) {
        return false;
    }

    $_db_current_row = mysqli_fetch_assoc($result);

    return $_db_current_row !== null;
}

/**
 * อ่านค่าคอลัมน์จากแถวปัจจุบัน
 *
 * @param mysqli_result $result
 * @param string $field ชื่อคอลัมน์
 * @return string
 */
function db_result($result, $field)
{
    global $_db_current_row;

    if ($_db_current_row !== null && isset($_db_current_row[$field])) {
        return $_db_current_row[$field];
    }

    return '';
}

/**
 * นับจำนวนแถวในผลลัพธ์
 *
 * @param mysqli_result $result
 * @return int
 */
function db_num_rows($result)
{
    if (!$result instanceof mysqli_result) {
        return 0;
    }

    return mysqli_num_rows($result);
}

/**
 * ปิดการเชื่อมต่อ
 *
 * @param mysqli $conn
 * @return bool
 */
function db_close($conn)
{
    return mysqli_close($conn);
}

/**
 * escape สตริงสำหรับใส่ใน SQL
 *
 * @param mysqli $conn
 * @param string $value
 * @return string
 */
function db_escape($conn, $value)
{
    return mysqli_real_escape_string($conn, $value);
}

} // end function_exists guard

// ─ โหลด users สำหรับตรวจสอบรหัสผ่านแบบเดิม ───────────────────────────────────

$sql_user = 'SELECT username, password FROM users';
$exec_user = mysqli_query($connect, $sql_user);

if ($exec_user instanceof mysqli_result) {
    while ($result_user = mysqli_fetch_assoc($exec_user)) {
        $arr_username[] = $result_user['username'];
        $arr_password[] = $result_user['password'];
    }
    mysqli_free_result($exec_user);
}

// ─ Favicon ───────────────────────────────────────────────────────────────────

require_once __DIR__ . '/head-favicon.php';
inventory_register_favicon_output_buffer();
