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
require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/pickup_repair_part_product.php';

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
 * อ่านชื่อ Product จาก pd_id
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @return string
 */
function pre_pickup_save_product_name($conn, $productId)
{
    $stmt = pre_pickup_save_execute(
        $conn,
        'SELECT `pd_name` FROM `product` WHERE `pd_id` = ? LIMIT 1',
        'i',
        array($productId)
    );
    mysqli_stmt_bind_result($stmt, $pdName);
    $name = '';
    if (mysqli_stmt_fetch($stmt)) {
        $name = trim((string) $pdName);
    }
    mysqli_stmt_close($stmt);

    return $name;
}

/**
 * ดึงรายการ part ชุดซ่อมจาก part.part_product (LIKE ชื่อ Product)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array รายการ part พร้อมชื่อ Product สำหรับ pre_product
 */
function pre_pickup_save_fetch_repair_parts($conn, $productId)
{
    $productName = pre_pickup_save_product_name($conn, $productId);
    if ($productName === '') {
        return array();
    }

    $scope = pickup_repair_part_scope_bind($conn, $productId, $productName);
    $stmt = pre_pickup_save_execute(
        $conn,
        'SELECT DISTINCT part.`part_id`, part.`part_name`
         FROM `part`
         INNER JOIN `repair_formula` rf ON rf.`part_id` = part.`part_id`
         WHERE ' . $scope['where'] . '
         ORDER BY part.`part_name` ASC',
        $scope['types'],
        $scope['params']
    );

    mysqli_stmt_bind_result($stmt, $partId, $partName);
    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $rows[] = array(
            'part_id' => (int) $partId,
            'part_name' => (string) $partName,
            'product_name' => $productName,
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

/**
 * อ่านรายการ Product + จำนวนชุดจาก request (รองรับหลายชุดและแบบเดิมชุดเดียว)
 *
 * @return array<int, array{product_id:int, amount:float}>
 */
function pre_pickup_save_collect_sets()
{
    $sets = array();
    $products = isset($_GET['pre_product']) ? $_GET['pre_product'] : null;
    $amounts = isset($_GET['pre_amount']) ? $_GET['pre_amount'] : null;

    if (is_array($products) && is_array($amounts)) {
        $count = min(count($products), count($amounts));
        for ($i = 0; $i < $count; $i++) {
            $productId = pre_pickup_save_positive_int($products[$i]);
            if ($productId <= 0) {
                continue;
            }
            $sets[] = array(
                'product_id' => $productId,
                'amount' => pre_pickup_save_amount($amounts[$i]),
            );
        }

        return $sets;
    }

    $productId = pre_pickup_save_positive_int($products);
    if ($productId > 0) {
        $sets[] = array(
            'product_id' => $productId,
            'amount' => pre_pickup_save_amount($amounts),
        );
    }

    return $sets;
}

/**
 * รวมรายการ part จากหลายชุด Product — รวมจำนวนเบิก part เดียวกัน
 *
 * @param array<int, array{part_id:int, part_name:string, product_name:string, pick_qty:float}> $merged map part_id
 * @param array<int, array{part_id:int, part_name:string, product_name:string}> $parts รายการ part หนึ่งชุด
 * @param float $baseAmount จำนวนชุดของ Product นั้น
 * @return void ไม่มี return value
 */
function pre_pickup_save_merge_parts(&$merged, $parts, $baseAmount)
{
    foreach ($parts as $part) {
        $partId = (int) $part['part_id'];
        $pickQty = pre_pickup_save_part_amount($partId, $baseAmount);
        if (!isset($merged[$partId])) {
            $merged[$partId] = array(
                'part_id' => $partId,
                'part_name' => (string) $part['part_name'],
                'product_name' => (string) $part['product_name'],
                'pick_qty' => $pickQty,
            );
            continue;
        }

        $merged[$partId]['pick_qty'] += $pickQty;
    }
}

/**
 * ดึงชื่อ part จาก part_id สำหรับรายการที่เพิ่มแยกจากหน้าเบิก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId id part
 * @return string ชื่อ part หรือค่าว่าง
 */
function pre_pickup_save_part_name($conn, $partId)
{
    $stmt = pre_pickup_save_execute(
        $conn,
        'SELECT `part_name` FROM `part` WHERE `part_id` = ? LIMIT 1',
        'i',
        array($partId)
    );
    mysqli_stmt_bind_result($stmt, $partName);
    $name = '';
    if (mysqli_stmt_fetch($stmt)) {
        $name = trim((string) $partName);
    }
    mysqli_stmt_close($stmt);

    return $name;
}

/**
 * รวมรายการ part ที่ผู้ใช้เพิ่มแยก (manual) เข้ากับผลจากสูตรเซต
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array<int, array{part_id:int, part_name:string, product_name:string, pick_qty:float}> $mergedParts map part_id
 * @return void ไม่มี return value
 */
function pre_pickup_save_merge_manual_lines($conn, &$mergedParts)
{
    $partIds = isset($_GET['manual_part_id']) ? $_GET['manual_part_id'] : array();
    $qtys = isset($_GET['manual_part_qty']) ? $_GET['manual_part_qty'] : array();
    $productNames = isset($_GET['manual_product_name']) ? $_GET['manual_product_name'] : array();

    if (!is_array($partIds)) {
        $partIds = ($partIds !== '' && $partIds !== null) ? array($partIds) : array();
    }
    if (!is_array($qtys)) {
        $qtys = ($qtys !== '' && $qtys !== null) ? array($qtys) : array();
    }
    if (!is_array($productNames)) {
        $productNames = ($productNames !== '' && $productNames !== null) ? array($productNames) : array();
    }

    $count = count($partIds);
    for ($i = 0; $i < $count; $i++) {
        $partId = pre_pickup_save_positive_int($partIds[$i]);
        if ($partId <= 0) {
            continue;
        }
        $qtyRaw = isset($qtys[$i]) ? $qtys[$i] : 0;
        $pickQty = pre_pickup_save_amount($qtyRaw);
        $productName = pre_pickup_save_text(isset($productNames[$i]) ? $productNames[$i] : '', 255);

        if (isset($mergedParts[$partId])) {
            $mergedParts[$partId]['pick_qty'] += $pickQty;
            continue;
        }

        $partName = pre_pickup_save_part_name($conn, $partId);
        if ($partName === '') {
            throw new Exception('ไม่พบ Part ในรายการที่เพิ่มแยก (id: ' . $partId . ')');
        }

        $mergedParts[$partId] = array(
            'part_id' => $partId,
            'part_name' => $partName,
            'product_name' => $productName !== '' ? $productName : $partName,
            'pick_qty' => $pickQty,
        );
    }
}

// ─ Main Flow ─────────────────────────────────────────────────────────────────

$message = '';
$isSuccess = false;
$savedCount = 0;

try {
    $preDate = pre_pickup_save_text(isset($_GET['pre_date']) ? $_GET['pre_date'] : '', 30);
    $setRows = pre_pickup_save_collect_sets();
    $preType = pre_pickup_save_text(isset($_GET['pre_type']) ? $_GET['pre_type'] : '', 100);
    $preBy = inventory_actor_name_for_save(100);
    $preRemarks = pre_pickup_save_text(isset($_GET['pre_remarks']) ? $_GET['pre_remarks'] : '', 5000);

    $allowedTypes = array(
        'เบิกผลิต',
        'เบิกตาม PO',
        'เบิกซ่อมลูกค้า',
        'เบิกเสียระหว่างผลิต',
        'เบิกเสียระหว่างขนส่ง',
        'เบิกอื่นๆ',
    );
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preDate)) {
        throw new Exception('วันที่เบิกไม่ถูกต้อง');
    }

    $manualPartIds = isset($_GET['manual_part_id']) ? $_GET['manual_part_id'] : array();
    if (!is_array($manualPartIds)) {
        $manualPartIds = ($manualPartIds !== '' && $manualPartIds !== null) ? array($manualPartIds) : array();
    }

    if ($preBy === '' || !in_array($preType, $allowedTypes, true)) {
        throw new Exception('ข้อมูลหลักสำหรับบันทึกรายการเบิกไม่ครบถ้วน');
    }

    if (count($setRows) === 0 && count($manualPartIds) === 0) {
        throw new Exception('ไม่มีรายการเซตหรือรายการแยกสำหรับบันทึก');
    }

    $mergedParts = array();
    $isRepair = ($preType === 'เบิกซ่อมลูกค้า');
    foreach ($setRows as $setRow) {
        $productId = (int) $setRow['product_id'];
        $baseAmount = (float) $setRow['amount'];
        if ($isRepair) {
            $parts = pre_pickup_save_fetch_repair_parts($connect, $productId);
            if (empty($parts)) {
                throw new Exception('ไม่พบรายการเซตซ่อม (part_product) ของ Product ที่เลือก');
            }
        } else {
            $parts = pre_pickup_save_fetch_formula_parts($connect, $productId);
            if (empty($parts)) {
                throw new Exception('ไม่พบสูตร part ของ Product ที่เลือก');
            }
        }
        pre_pickup_save_merge_parts($mergedParts, $parts, $baseAmount);
    }

    pre_pickup_save_merge_manual_lines($connect, $mergedParts);

    if (empty($mergedParts)) {
        throw new Exception('ไม่พบรายการ part สำหรับบันทึก');
    }

    $preId = date('ymd-His');
    mysqli_autocommit($connect, false);

    foreach ($mergedParts as $part) {
        pre_pickup_save_insert_row($connect, array(
            'pre_id' => $preId,
            'pre_date' => $preDate,
            'product_name' => $part['product_name'],
            'part_id' => (string) $part['part_id'],
            'pre_type' => $preType,
            'pre_amount' => $part['pick_qty'],
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
