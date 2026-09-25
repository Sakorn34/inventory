<?php
/**
 * Endpoint บันทึกเอกสารเบิกของเป็นเซต
 *
 * รับข้อมูลจาก `production_pre_pickup.php`, ตรวจ input, ดึงสูตร part ของ Product
 * แล้วบันทึกลง `pre_stock` ด้วย prepared statement ภายใต้ transaction เดียว
 * เพื่อไม่ให้เกิดข้อมูลค้างครึ่งทางเมื่อ insert บางแถวไม่สำเร็จ
 *
 * Flow สั้น:
 * 1. หน้าเบิกของเป็นเซต redirect มาพร้อม `pre_date`, `pre_product`, `pre_amount`, `pre_type`, `pre_by`
 * 2. ระบบค้นหา part ตาม `production_formula`
 * 3. คำนวณจำนวนพิเศษสำหรับ part 57/58 แล้ว insert เป็นเอกสารรอเบิก
 *
 * ตัวอย่าง: pre_pickup_save.php?pre_date=2026-05-18&pre_product=1&pre_amount=1&pre_type=เบิกผลิต&pre_by=Ken
 */

require_once __DIR__ . '/connect.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render กลับไปยัง HTML
 *
 * @param mixed $value ค่าที่ต้องแสดงผล
 * @return string ข้อความที่ปลอดภัยสำหรับ HTML
 */
function pre_pickup_save_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * เตรียมและ execute SQL ผ่าน mysqli prepared statement
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL พร้อม placeholder
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function pre_pickup_save_execute($conn, $sql, $types, $params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('pre_pickup_save prepare error: ' . mysqli_error($conn));
        throw new Exception('เตรียม SQL ไม่สำเร็จ');
    }

    if ($types !== '') {
        $bindParams = array($stmt, $types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }

        if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
            mysqli_stmt_close($stmt);
            throw new Exception('bind SQL parameter ไม่สำเร็จ');
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log('pre_pickup_save execute error: ' . $error);
        throw new Exception('execute SQL ไม่สำเร็จ');
    }

    return $stmt;
}

/**
 * อ่านข้อความจาก request พร้อม trim และจำกัดความยาว
 *
 * @param mixed $value ค่าจาก request
 * @param int $maxLength ความยาวสูงสุด
 * @return string ข้อความที่ normalize แล้ว
 */
function pre_pickup_save_text($value, $maxLength)
{
    $text = trim((string) $value);

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

/**
 * อ่านค่า int บวกจาก request
 *
 * @param mixed $value ค่าจาก request
 * @return int ค่า int ที่ valid หรือ 0 เมื่อไม่ถูกต้อง
 */
function pre_pickup_save_positive_int($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * validate จำนวนชุดที่ต้องการเบิก
 *
 * @param mixed $value จำนวนจาก request
 * @return float จำนวนที่ใช้คำนวณ part
 */
function pre_pickup_save_amount($value)
{
    $amount = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount <= 0) {
        throw new Exception('จำนวนเบิกต้องเป็นตัวเลขมากกว่า 0');
    }

    return (float) $amount;
}

/**
 * คำนวณจำนวนเบิกสำหรับ part พิเศษตาม logic เดิม
 *
 * @param int $partId id ของ part
 * @param float $baseAmount จำนวนชุดที่ผู้ใช้เลือก
 * @return float จำนวน part ที่ต้องบันทึก
 */
function pre_pickup_save_part_amount($partId, $baseAmount)
{
    if ((int) $partId === 57) {
        return $baseAmount * 2;
    }

    if ((int) $partId === 58) {
        return $baseAmount / 2;
    }

    return $baseAmount;
}

/**
 * ดึงรายการ part ในสูตรของ Product ที่เลือก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array รายการ part พร้อมชื่อ Product
 */
function pre_pickup_save_fetch_formula_parts($conn, $productId)
{
    $stmt = pre_pickup_save_execute(
        $conn,
        'SELECT part.`part_id`, part.`part_name`, production_formula.`pd_name`
         FROM part
         INNER JOIN production_formula ON part.`part_id` = production_formula.`part_id`
         WHERE production_formula.`pd_id` = ?',
        'i',
        array($productId)
    );

    mysqli_stmt_bind_result($stmt, $partId, $partName, $productName);
    $rows = array();

    while (mysqli_stmt_fetch($stmt)) {
        $rows[] = array(
            'part_id' => (int) $partId,
            'part_name' => (string) $partName,
            'product_name' => (string) $productName,
        );
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * บันทึกรายการ pre_stock หนึ่งแถว
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array $row ข้อมูลที่ผ่านการ validate แล้ว
 * @return void ไม่มี return value
 */
function pre_pickup_save_insert_row($conn, $row)
{
    $stmt = pre_pickup_save_execute(
        $conn,
        'INSERT INTO `pre_stock`
            (`pre_id`, `pre_date`, `pre_product`, `pre_part`, `pre_type`, `pre_amount`, `pre_by`, `pre_remarks`, `ts_mtn_number`, `pre_check`, `pre_status`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssdsssss',
        array(
            $row['pre_id'],
            $row['pre_date'],
            $row['product_name'],
            $row['part_id'],
            $row['pre_type'],
            $row['pre_amount'],
            $row['pre_by'],
            $row['pre_remarks'],
            '',
            'NO',
            'pre',
        )
    );

    mysqli_stmt_close($stmt);
}

/**
 * ส่งแจ้งเตือนหลังบันทึก โดยไม่ให้ failure กระทบการ save
 *
 * @param string $preBy ชื่อผู้เบิก
 * @return void ไม่มี return value
 */
function pre_pickup_save_notify($preBy)
{
    if (!function_exists('line_messaging_push_text')) {
        require_once __DIR__ . '/line_messaging.php';
    }

    if (!line_messaging_is_configured()) {
        return;
    }

    $message = "มีรายการเบิกของใหม่\nขอเบิกโดย " . $preBy . "\nดูรายละเอียดที่หน้า pickup_overview.php";
    line_messaging_push_text($message, 'inventory LINE pickup');
}

// ─ Main Flow ─────────────────────────────────────────────────────────────────

$message = '';
$isSuccess = false;
$savedCount = 0;

try {
    $preDate = pre_pickup_save_text(isset($_GET['pre_date']) ? $_GET['pre_date'] : '', 30);
    $productId = pre_pickup_save_positive_int(isset($_GET['pre_product']) ? $_GET['pre_product'] : 0);
    $baseAmount = pre_pickup_save_amount(isset($_GET['pre_amount']) ? $_GET['pre_amount'] : 0);
    $preType = pre_pickup_save_text(isset($_GET['pre_type']) ? $_GET['pre_type'] : '', 100);
    $preBy = pre_pickup_save_text(isset($_GET['pre_by']) ? $_GET['pre_by'] : '', 100);
    $preRemarks = pre_pickup_save_text(isset($_GET['pre_remarks']) ? $_GET['pre_remarks'] : '', 5000);

    $allowedTypes = array('เบิกผลิต', 'เบิกตาม PO', 'เบิกซ่อมลูกค้า', 'เบิกเสียระหว่างผลิต', 'เบิกอื่นๆ');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preDate)) {
        throw new Exception('วันที่เบิกไม่ถูกต้อง');
    }

    if ($productId <= 0 || $preBy === '' || !in_array($preType, $allowedTypes, true)) {
        throw new Exception('ข้อมูลหลักสำหรับบันทึกรายการเบิกไม่ครบถ้วน');
    }

    $parts = pre_pickup_save_fetch_formula_parts($connect, $productId);
    if (empty($parts)) {
        throw new Exception('ไม่พบสูตร part ของ Product ที่เลือก');
    }

    $preId = date('ymd-His');
    mysqli_autocommit($connect, false);

    foreach ($parts as $part) {
        pre_pickup_save_insert_row($connect, array(
            'pre_id' => $preId,
            'pre_date' => $preDate,
            'product_name' => $part['product_name'],
            'part_id' => (string) $part['part_id'],
            'pre_type' => $preType,
            'pre_amount' => pre_pickup_save_part_amount($part['part_id'], $baseAmount),
            'pre_by' => $preBy,
            'pre_remarks' => $preRemarks,
        ));
        $savedCount++;
    }

    mysqli_commit($connect);
    mysqli_autocommit($connect, true);

    $isSuccess = true;
    $message = 'บันทึกรายการเบิกสำเร็จ จำนวน ' . $savedCount . ' รายการ';
    pre_pickup_save_notify($preBy);
} catch (Exception $e) {
    if ($connect instanceof mysqli) {
        mysqli_rollback($connect);
        mysqli_autocommit($connect, true);
    }

    http_response_code(400);
    $message = $e->getMessage();
}

db_close($connect);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<?php if ($isSuccess) { ?>
<meta http-equiv="Refresh" content="1; URL='pickup_overview.php'">
<?php } ?>
<title>บันทึกเบิกของเป็นเซต</title>
</head>
<body>
<h3><?php echo $isSuccess ? 'บันทึกสำเร็จ' : 'บันทึกไม่สำเร็จ'; ?></h3>
<p><?php echo pre_pickup_save_h($message); ?></p>
<?php if ($isSuccess) { ?>
<p>กำลังกลับไปหน้ารายการเบิกของ...</p>
<?php } else { ?>
<p><a href="javascript:history.back()">กลับไปแก้ไขข้อมูล</a></p>
<?php } ?>
</body>
</html>
