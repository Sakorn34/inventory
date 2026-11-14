<?php
/**
 * Endpoint บันทึกรายการเบิกแยกรายการจากหน้า pickup_ma.php
 *
 * ขอบเขตของไฟล์นี้คือรับข้อมูลรายการเบิกจาก query string เดิม ตรวจ input,
 * ดึงข้อมูล Product/Part ที่มีอยู่จริง และบันทึกลง `pre_stock` ด้วย prepared
 * statement ภายใต้ transaction เพื่อกันข้อมูลค้างครึ่งทาง
 *
 * Flow สั้น:
 * 1. `pickup_ma.php` redirect มาพร้อม `log_*`, `pd_sp*`, `sn_sp*`, `amount_sp*`
 * 2. ระบบ validate ทุก field สำคัญและตรวจจำนวนแถวไม่เกิน 5 รายการ
 * 3. insert ลง `pre_stock` แล้ว redirect ไป `pickup_overview.php`
 *
 * ตัวอย่าง: `pickup_ma_save.php?log_date=2026-05-18&count_sp=1&pd_sp1=14&sn_sp1=76&amount_sp1=1`
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render HTML เพื่อลดความเสี่ยง XSS
 *
 * @param mixed $value ค่าที่ต้องแสดงผลบนหน้าเว็บ
 * @return string ข้อความที่ผ่าน htmlspecialchars แล้ว
 */
function pickup_ma_save_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * เตรียมและ execute SQL ผ่าน mysqli prepared statement
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL ที่ใช้ placeholder เมื่อมี input
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind เข้า statement
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function pickup_ma_save_execute($conn, $sql, $types = '', $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('เตรียม SQL ไม่สำเร็จ');
    }

    if ($types !== '') {
        $bindParams = array($stmt, $types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('bind SQL parameter ไม่สำเร็จ');
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('execute SQL ไม่สำเร็จ: ' . $error);
    }

    return $stmt;
}

/**
 * อ่านค่า int ที่ต้องมากกว่า 0 จาก request
 *
 * @param mixed $value ค่าจาก request
 * @return int ค่า int ที่ valid หรือ 0 เมื่อไม่ถูกต้อง
 */
function pickup_ma_save_positive_int($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * อ่านข้อความจาก request พร้อม trim และจำกัดความยาว
 *
 * @param mixed $value ค่าจาก request
 * @param int $maxLength ความยาวสูงสุด
 * @return string ข้อความที่ normalize แล้ว
 */
function pickup_ma_save_text($value, $maxLength)
{
    $text = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

/**
 * validate จำนวนเบิกให้เป็นตัวเลขบวก
 *
 * @param mixed $value จำนวนจาก request
 * @return float จำนวนที่ใช้บันทึก
 */
function pickup_ma_save_amount($value)
{
    $amount = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount <= 0) {
        throw new RuntimeException('จำนวนเบิกต้องเป็นตัวเลขมากกว่า 0');
    }

    return (float) $amount;
}

/**
 * ดึงชื่อ Product จาก id ที่เลือก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return string ชื่อ Product
 */
function pickup_ma_save_get_product_name($conn, $productId)
{
    $stmt = pickup_ma_save_execute(
        $conn,
        'SELECT `pd_name` FROM `product` WHERE `pd_id` = ? LIMIT 1',
        'i',
        array($productId)
    );
    mysqli_stmt_bind_result($stmt, $productName);
    $hasRow = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$hasRow) {
        throw new RuntimeException('ไม่พบ Product ที่เลือก');
    }

    return (string) $productName;
}

/**
 * ตรวจว่า Part id มีอยู่จริงก่อนบันทึก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId id ของ Part
 * @return int id ของ Part ที่ตรวจแล้ว
 */
function pickup_ma_save_get_part_id($conn, $partId)
{
    $stmt = pickup_ma_save_execute(
        $conn,
        'SELECT `part_id` FROM `part` WHERE `part_id` = ? LIMIT 1',
        'i',
        array($partId)
    );
    mysqli_stmt_bind_result($stmt, $foundPartId);
    $hasRow = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$hasRow) {
        throw new RuntimeException('ไม่พบ Part ที่เลือก');
    }

    return (int) $foundPartId;
}

/**
 * บันทึกรายการเบิกหนึ่งแถวลง pre_stock
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array $row ข้อมูลที่ผ่านการ validate แล้ว
 * @return void ไม่มี return value
 */
function pickup_ma_save_insert_pre_stock($conn, $row)
{
    $stmt = pickup_ma_save_execute(
        $conn,
        'INSERT INTO `pre_stock`
            (`pre_id`, `pre_date`, `pre_product`, `pre_part`, `pre_type`, `pre_amount`, `pre_by`, `pre_remarks`, `ts_mtn_number`, `pre_check`, `pre_status`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssdsssss',
        array(
            $row['pre_id'],
            $row['log_date'],
            $row['product_name'],
            $row['part_id'],
            $row['log_type'],
            $row['amount'],
            $row['log_by'],
            $row['remarks'],
            $row['search_id'],
            'NO',
            'pre',
        )
    );
    mysqli_stmt_close($stmt);
}

/**
 * รวบรวมและ validate request ทั้งหมดก่อนบันทึก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการแถวที่พร้อมบันทึก
 */
function pickup_ma_save_build_rows($conn)
{
    $logDate = pickup_ma_save_text(isset($_GET['log_date']) ? $_GET['log_date'] : '', 30);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
        throw new RuntimeException('วันที่เบิกไม่ถูกต้อง');
    }

    $logType = pickup_ma_save_text(isset($_GET['log_type']) ? $_GET['log_type'] : '', 100);
    $logBy = inventory_actor_name_for_save(100);
    $remarks = pickup_ma_save_text(isset($_GET['log_remarks']) ? $_GET['log_remarks'] : '', 5000);
    $searchId = pickup_ma_save_text(isset($_GET['search_id']) ? $_GET['search_id'] : '', 100);
    $count = pickup_ma_save_positive_int(isset($_GET['count_sp']) ? $_GET['count_sp'] : 0);

    if ($logType === '' || $logBy === '' || $count <= 0 || $count > 5) {
        throw new RuntimeException('ข้อมูลหลักสำหรับบันทึกรายการเบิกไม่ครบถ้วน');
    }

    $preId = date('ymd-His');
    $rows = array();

    for ($i = 1; $i <= $count; $i++) {
        $productId = pickup_ma_save_positive_int(isset($_GET['pd_sp' . $i]) ? $_GET['pd_sp' . $i] : 0);
        $partId = pickup_ma_save_positive_int(isset($_GET['sn_sp' . $i]) ? $_GET['sn_sp' . $i] : 0);
        $amountValue = isset($_GET['amount_sp' . $i]) ? $_GET['amount_sp' . $i] : '';

        if ($productId <= 0 || $partId <= 0 || $amountValue === '') {
            throw new RuntimeException('ข้อมูลรายการที่ ' . $i . ' ไม่ครบถ้วน');
        }

        $rows[] = array(
            'pre_id' => $preId,
            'log_date' => $logDate,
            'product_name' => pickup_ma_save_get_product_name($conn, $productId),
            'part_id' => (string) pickup_ma_save_get_part_id($conn, $partId),
            'log_type' => $logType,
            'amount' => pickup_ma_save_amount($amountValue),
            'log_by' => $logBy,
            'remarks' => $remarks,
            'search_id' => $searchId,
        );
    }

    return $rows;
}

// ─ Main Flow ─────────────────────────────────────────────────────────────────

$message = '';
$isSuccess = false;

try {
    $rows = pickup_ma_save_build_rows($connect);

    mysqli_begin_transaction($connect);
    foreach ($rows as $row) {
        pickup_ma_save_insert_pre_stock($connect, $row);
    }
    mysqli_commit($connect);

    $isSuccess = true;
    $message = 'บันทึกรายการเบิกสำเร็จ จำนวน ' . count($rows) . ' รายการ';
} catch (Throwable $e) {
    mysqli_rollback($connect);
    http_response_code(400);
    $message = $e->getMessage();
}

db_close($connect);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($isSuccess) { ?>
        <meta http-equiv="refresh" content="1; URL='pickup_overview.php'">
    <?php } ?>
    <title>บันทึกรายการเบิก</title>
</head>
<body>
    <h3><?php echo $isSuccess ? 'บันทึกสำเร็จ' : 'บันทึกไม่สำเร็จ'; ?></h3>
    <p><?php echo pickup_ma_save_h($message); ?></p>

    <?php if ($isSuccess) { ?>
        <p>กำลังกลับไปหน้ารายการเบิกของ...</p>
    <?php } else { ?>
        <p><a href="javascript:history.back()">กลับไปแก้ไขข้อมูล</a></p>
    <?php } ?>
</body>
</html>
