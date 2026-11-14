<?php
/**
 * Endpoint สำหรับโหลดจำนวน stock ของ Part ในหน้าเบิกของ
 *
 * รับค่า `sn_sp1` ถึง `sn_sp5` จาก AJAX แล้วคืน `<option>` ของจำนวน stock
 * สำหรับ select จำนวนคงเหลือ โดย validate input และใช้ prepared statement
 *
 * ตัวอย่าง: `list_stock.php?sn_sp1=76` จะคืนจำนวนคงเหลือของ Part id 76
 */

require_once __DIR__ . '/connect.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render เป็น option เพื่อลดความเสี่ยง XSS
 *
 * @param mixed $value ค่าที่ต้องแสดงผลใน HTML
 * @return string ข้อความที่ผ่าน htmlspecialchars แล้ว
 */
function list_stock_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * อ่าน Part id จาก query string ที่รองรับชื่อ field เดิมทั้ง 5 แถว
 *
 * @return int Part id ที่มากกว่า 0 หรือ 0 เมื่อ input ไม่ถูกต้อง
 */
function list_stock_get_part_id()
{
    $keys = array('sn_sp1', 'sn_sp2', 'sn_sp3', 'sn_sp4', 'sn_sp5');
    foreach ($keys as $key) {
        if (isset($_GET[$key])) {
            $id = filter_var($_GET[$key], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

            return $id === false ? 0 : (int) $id;
        }
    }

    return 0;
}

/**
 * ดึงจำนวน stock ของ Part ที่เลือก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId id ของ Part
 * @return string จำนวน stock หรือค่าว่างเมื่อไม่พบ
 */
/**
 * ดึงจำนวน stock และขั้นต่ำสั่งของ Part
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId id ของ Part
 * @return array{amount:string,min_alert:string} ค่าว่างเมื่อไม่พบ
 */
function list_stock_get_part_row($conn, $partId)
{
    $empty = array('amount' => '', 'min_alert' => '');
    $stmt = mysqli_prepare($conn, 'SELECT `part_amount`, `part_min_alert` FROM `part` WHERE `part_id` = ? LIMIT 1');
    if (!$stmt) {
        return $empty;
    }

    mysqli_stmt_bind_param($stmt, 'i', $partId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return $empty;
    }

    mysqli_stmt_bind_result($stmt, $amount, $minAlert);
    $hasRow = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$hasRow) {
        return $empty;
    }

    return array(
        'amount' => (string) $amount,
        'min_alert' => (string) $minAlert,
    );
}

// ─ Response ─────────────────────────────────────────────────────────────────

$partId = list_stock_get_part_id();
$row = $partId > 0 ? list_stock_get_part_row($connect, $partId) : array('amount' => '', 'min_alert' => '');

if ($row['amount'] === '') {
    echo '<option value="">none</option>';
} else {
    echo '<option value="' . list_stock_h($row['amount']) . '" data-min="' . list_stock_h($row['min_alert']) . '">'
        . list_stock_h($row['amount']) . '</option>';
}

db_close($connect);
