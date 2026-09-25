<?php
/**
 * Endpoint ลบข้อมูล Part
 *
 * รับ POST จาก `part_edit.php` แล้วลบรายการจาก `production_formula` และ `part`
 * ด้วย prepared statement เพื่อลดความเสี่ยง SQL injection
 *
 * Flow สั้น:
 * 1. ฟอร์มส่ง `part_id` จากหน้าแก้ไข Part
 * 2. ระบบตรวจว่ามี Part นี้อยู่จริง
 * 3. ลบ mapping ใน production_formula แล้วลบ part
 * 4. redirect ไป `stock_overview.php`
 *
 * ตัวอย่าง: POST part_delete.php พร้อม part_id=411
 */

require_once __DIR__ . '/connect.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render กลับไปยัง HTML
 *
 * @param mixed $value ค่าที่ต้องแสดงผล
 * @return string ข้อความที่ปลอดภัยสำหรับ HTML
 */
function part_delete_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * อ่าน part_id บวกจาก POST
 *
 * @return int part_id ที่ valid หรือ 0 เมื่อไม่ถูกต้อง
 */
function part_delete_positive_int()
{
    if (!isset($_POST['part_id'])) {
        return 0;
    }

    $id = filter_var($_POST['part_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * execute prepared statement พร้อม bind parameter แบบอ้างอิง
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL
 * @param string $types ชนิดข้อมูล bind_param
 * @param array $params ค่าที่ bind
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function part_delete_execute($conn, $sql, $types, array $params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('เตรียม SQL ไม่สำเร็จ: ' . mysqli_error($conn));
    }

    $bindParams = array($stmt, $types);
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }

    if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
        mysqli_stmt_close($stmt);
        throw new Exception('เตรียมข้อมูลลบ Part ไม่สำเร็จ');
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log('part_delete execute error: ' . $error);
        throw new Exception('ลบ Part ไม่สำเร็จ: ' . $error);
    }

    return $stmt;
}

/**
 * แสดงหน้าผลลัพธ์แล้ว redirect ไป stock overview
 *
 * @param string $message ข้อความแจ้งผู้ใช้
 * @param bool $success true เมื่อลบสำเร็จ
 * @return void
 */
function part_delete_render_result($message, $success)
{
    $class = $success ? 'success' : 'error';
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="2;url=stock_overview.php">';
    echo '<title>ลบ Part</title>';
    echo '<style>body{font-family:sans-serif;max-width:640px;margin:3rem auto;color:#334155}';
    echo '.success{color:#166534;background:#dcfce7;padding:1rem;border-radius:8px}';
    echo '.error{color:#991b1b;background:#fee2e2;padding:1rem;border-radius:8px}</style>';
    echo '</head><body><div class="' . part_delete_h($class) . '">' . part_delete_h($message) . '</div>';
    echo '<p><a href="stock_overview.php">กลับไปภาพรวม Stock</a></p></body></html>';
}

/**
 * ตรวจว่ามี Part ตาม part_id หรือไม่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId รหัส part
 * @return string ชื่อ part เมื่อพบ
 */
function part_delete_get_part_name($conn, $partId)
{
    $stmt = part_delete_execute($conn, 'SELECT part_name FROM part WHERE part_id = ? LIMIT 1', 'i', array($partId));
    mysqli_stmt_bind_result($stmt, $partName);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$found) {
        throw new Exception('ไม่พบ Part ที่ต้องการลบ (ID ' . $partId . ')');
    }

    return (string) $partName;
}

/**
 * ลบ Part และความสัมพันธ์ใน production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId รหัส part
 * @return string ชื่อ part ที่ลบ
 */
function part_delete_remove($conn, $partId)
{
    $partName = part_delete_get_part_name($conn, $partId);

    mysqli_begin_transaction($conn);
    try {
        $formulaStmt = part_delete_execute(
            $conn,
            'DELETE FROM production_formula WHERE part_id = ?',
            'i',
            array($partId)
        );
        mysqli_stmt_close($formulaStmt);

        $partStmt = part_delete_execute(
            $conn,
            'DELETE FROM part WHERE part_id = ? LIMIT 1',
            'i',
            array($partId)
        );

        if (mysqli_stmt_affected_rows($partStmt) < 1) {
            mysqli_stmt_close($partStmt);
            throw new Exception('ลบ Part ไม่สำเร็จ: ไม่พบข้อมูลที่ต้องการลบ');
        }
        mysqli_stmt_close($partStmt);

        mysqli_commit($conn);

        return $partName;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

// ─ Main ─────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    part_delete_render_result('กรุณาส่งคำขอลบผ่านฟอร์มเท่านั้น', false);
    db_close($connect);
    exit;
}

$partId = part_delete_positive_int();
if ($partId <= 0) {
    part_delete_render_result('ไม่พบ part_id ที่ต้องการลบ', false);
    db_close($connect);
    exit;
}

try {
    $partName = part_delete_remove($connect, $partId);
    part_delete_render_result('ลบ Part "' . $partName . '" (ID ' . $partId . ') เรียบร้อยแล้ว', true);
} catch (Exception $e) {
    part_delete_render_result($e->getMessage(), false);
}

db_close($connect);
