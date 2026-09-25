<?php
/**
 * Endpoint สำหรับโหลดรายการ Part ตาม Product ในหน้าเบิกของ
 *
 * รับค่า `pd_sp1` ถึง `pd_sp5` จาก AJAX แล้วคืนเฉพาะ `<option>` สำหรับเติมลง
 * dropdown Part โดยอ่าน mapping จาก `production_formula` ด้วย prepared statement
 *
 * ตัวอย่าง: `list_part.php?pd_sp1=14` จะคืน Part ที่ผูกกับ Product id 14
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
function list_part_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * อ่าน Product id จาก query string ที่รองรับชื่อ field เดิมทั้ง 5 แถว
 *
 * @return int Product id ที่มากกว่า 0 หรือ 0 เมื่อ input ไม่ถูกต้อง
 */
function list_part_get_product_id()
{
    $keys = array('pd_sp1', 'pd_sp2', 'pd_sp3', 'pd_sp4', 'pd_sp5');
    foreach ($keys as $key) {
        if (isset($_GET[$key])) {
            $id = filter_var($_GET[$key], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

            return $id === false ? 0 : (int) $id;
        }
    }

    return 0;
}

/**
 * ดึงรายการ Part ที่อยู่ใน formula ของ Product ที่เลือก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array รายการ Part สำหรับ dropdown
 */
function list_part_get_parts($conn, $productId)
{
    $sql = "SELECT p.`part_id`, p.`part_name`
        FROM `part` p
        INNER JOIN `production_formula` pf ON p.`part_id` = pf.`part_id`
        WHERE pf.`pd_id` = ?
        ORDER BY p.`part_name` ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return array();
    }

    mysqli_stmt_bind_param($stmt, 'i', $productId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array();
    }

    mysqli_stmt_bind_result($stmt, $partId, $partName);
    $parts = array();
    while (mysqli_stmt_fetch($stmt)) {
        $parts[] = array('part_id' => $partId, 'part_name' => $partName);
    }
    mysqli_stmt_close($stmt);

    return $parts;
}

// ─ Response ─────────────────────────────────────────────────────────────────

$productId = list_part_get_product_id();
if ($productId <= 0) {
    echo '<option value="">Not Found</option>';
    db_close($connect);
    exit;
}

echo '<option value="">--เลือก part--</option>';
foreach (list_part_get_parts($connect, $productId) as $part) {
    echo '<option value="' . list_part_h($part['part_id']) . '">' . list_part_h($part['part_name']) . '</option>';
}

db_close($connect);
