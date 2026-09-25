<?php
/**
 * บันทึกการตั้งค่าแจ้งเตือน Line ทีละรายการ Part (AJAX)
 *
 * รับ POST `part_id` และ `part_line_alert` (0|1) แล้ว update คอลัมน์ `part_line_alert`
 * ใช้จากหน้า stock_overview เมื่อผู้ใช้ติ๊ก/ยกเลิก checkbox โดยไม่ต้องกดปุ่มบันทึกทั้งหมด
 *
 * Flow: checkbox change → fetch POST → JSON response
 *
 * ตัวอย่าง: POST part_id=401&part_line_alert=1
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/line_stock_alert.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Bangkok');

/**
 * ส่ง JSON response แล้วจบการทำงาน
 *
 * @param bool $ok สำเร็จหรือไม่
 * @param string $message ข้อความแจ้งผู้ใช้
 * @param int $httpCode HTTP status code
 * @return void
 */
function part_line_alert_toggle_json($ok, $message, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode(
        array('ok' => $ok, 'message' => $message),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    part_line_alert_toggle_json(false, 'กรุณาส่งข้อมูลผ่าน POST เท่านั้น', 405);
}

$partId = filter_var(
    isset($_POST['part_id']) ? $_POST['part_id'] : null,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if ($partId === false) {
    part_line_alert_toggle_json(false, 'รหัส Part ไม่ถูกต้อง', 400);
}

$flagRaw = isset($_POST['part_line_alert']) ? (string) $_POST['part_line_alert'] : '0';
$flag = ($flagRaw === '1') ? 1 : 0;

try {
    inventory_line_alert_ensure_schema($connect);

    $sql = 'UPDATE `part` SET `part_line_alert` = ? WHERE `part_id` = ? LIMIT 1';
    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) {
        throw new RuntimeException('เตรียมคำสั่ง update ไม่สำเร็จ');
    }

    mysqli_stmt_bind_param($stmt, 'ii', $flag, $partId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('บันทึกไม่สำเร็จ: ' . $err);
    }

    mysqli_stmt_close($stmt);
    part_line_alert_toggle_json(true, 'บันทึกแล้ว');
} catch (Exception $e) {
    error_log('part_line_alert_toggle error: ' . $e->getMessage());
    part_line_alert_toggle_json(false, $e->getMessage(), 500);
} finally {
    db_close($connect);
}
