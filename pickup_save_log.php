<?php
/**
 * บันทึกการจ่ายของจากเอกสารเบิก (popup pickup_overview และ pickup_list_order_detail)
 *
 * จ่ายได้บางส่วน: แต่ละแถวส่งจำนวนที่จ่ายครั้งนี้ — ตัดสต็อกตามนั้น, บันทึก log_stock,
 * แถวที่จ่ายครบเปลี่ยนเป็น pickup ส่วนแถวที่จ่ายไม่ครบหรือไม่ได้เลือกคงสถานะ pre (ค้างจ่าย)
 * ห้ามจ่ายเกินจำนวนที่ขอหรือเกินสต็อก — ล็อกแถว part / pre_stock ใน transaction
 *
 * ตัวอย่าง: pickup_save_log.php?count=1&pre_id=260518-132656&pre_type=...&check1=YES&pre_running1=55&log_amount1=20
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
date_default_timezone_set('Asia/Bangkok');

// ─ Constants ──────────────────────────────────────────────────────────────────

define('PICKUP_SAVE_LOG_STATUS_NORMAL', 'ปกติ');
define('PICKUP_SAVE_LOG_STATUS_ORDERED', 'สั่งซื้อแล้ว');
define('PICKUP_SAVE_LOG_STATUS_NOT_ORDERED', 'ยังไม่ได้สั่งซื้อ');
define('PICKUP_SAVE_LOG_EPSILON', 0.0001);

// ─ Helpers ────────────────────────────────────────────────────────────────────

function pickup_save_log_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * @param mixed $value ค่าจาก request
 * @param int $maxLength ความยาวสูงสุด
 * @return string
 */
function pickup_save_log_text($value, $maxLength)
{
    $text = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

/**
 * @param mixed $value ค่าจาก request
 * @return int ค่า int ที่มากกว่า 0 หรือ 0 เมื่อไม่ถูกต้อง
 */
function pickup_save_log_positive_int($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * @param mixed $value จำนวนจาก request
 * @param int $index ลำดับแถว (ใช้ในข้อความ error)
 * @return float จำนวนที่มากกว่า 0
 */
function pickup_save_log_amount($value, $index)
{
    $amount = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount <= 0) {
        throw new RuntimeException('จำนวนที่จ่ายของรายการที่ ' . $index . ' ต้องมากกว่า 0');
    }

    return round((float) $amount, 2);
}

/**
 * execute SQL ผ่าน mysqli prepared statement
 *
 * @return mysqli_stmt
 */
function pickup_save_log_execute($conn, $sql, $types = '', $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('เตรียมคำสั่งฐานข้อมูลไม่สำเร็จ');
    }

    if ($types !== '') {
        $bindParams = array($stmt, $types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('ผูกค่ากับคำสั่งฐานข้อมูลไม่สำเร็จ');
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('บันทึกฐานข้อมูลไม่สำเร็จ: ' . $error);
    }

    return $stmt;
}

/**
 * สถานะสั่งซื้อของ part หลังตัดสต็อก (logic เดิม)
 */
function pickup_save_log_next_status($diff, $currentStatus)
{
    if ($diff > 0) {
        return PICKUP_SAVE_LOG_STATUS_NORMAL;
    }

    if ($currentStatus !== PICKUP_SAVE_LOG_STATUS_ORDERED) {
        return PICKUP_SAVE_LOG_STATUS_NOT_ORDERED;
    }

    return $currentStatus;
}

/**
 * แถวเอกสารที่ยังรอจ่าย — ล็อกไว้กันจ่ายซ้อนกันสองเครื่อง
 *
 * @return array{pre_part:int, pre_product:string, pre_amount:float, pre_remarks:string}
 */
function pickup_save_log_get_pre_line($conn, $preRunning, $preId)
{
    $stmt = pickup_save_log_execute(
        $conn,
        'SELECT `pre_part`, `pre_product`, `pre_amount`, `pre_remarks`
         FROM `pre_stock`
         WHERE `pre_running` = ? AND `pre_id` = ? AND `pre_status` = ?
         LIMIT 1 FOR UPDATE',
        'iss',
        array($preRunning, $preId, 'pre')
    );
    mysqli_stmt_bind_result($stmt, $prePart, $preProduct, $preAmount, $preRemarks);
    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('มีรายการที่ถูกจ่ายหรือแก้ไขไปแล้ว — กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    mysqli_stmt_close($stmt);

    return array(
        'pre_part' => (int) $prePart,
        'pre_product' => (string) $preProduct,
        'pre_amount' => (float) $preAmount,
        'pre_remarks' => (string) $preRemarks,
    );
}

/**
 * ข้อมูล part พร้อมล็อกแถวสต็อก
 *
 * @return array{part_id:int, part_name:string, part_amount:float, part_min_alert:float, part_status_purchase:string}
 */
function pickup_save_log_get_part($conn, $partId)
{
    $stmt = pickup_save_log_execute(
        $conn,
        'SELECT `part_id`, `part_name`, `part_amount`, `part_min_alert`, `part_status_purchase`
         FROM `part` WHERE `part_id` = ? LIMIT 1 FOR UPDATE',
        'i',
        array($partId)
    );

    mysqli_stmt_bind_result($stmt, $rowPartId, $rowPartName, $rowPartAmount, $rowPartMinAlert, $rowPartStatus);

    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('ไม่พบอะไหล่ในระบบ (ID ' . $partId . ')');
    }

    mysqli_stmt_close($stmt);

    return array(
        'part_id' => (int) $rowPartId,
        'part_name' => (string) $rowPartName,
        'part_amount' => (float) $rowPartAmount,
        'part_min_alert' => (float) $rowPartMinAlert,
        'part_status_purchase' => (string) $rowPartStatus,
    );
}

/**
 * จ่ายหนึ่งแถว: ตรวจไม่เกินที่ขอ/ไม่เกินสต็อก → log_stock → ตัดสต็อก → ปิดแถวหรือลดยอดค้าง
 */
function pickup_save_log_process_row($conn, $row)
{
    $line = pickup_save_log_get_pre_line($conn, $row['pre_running'], $row['pre_id']);
    $part = pickup_save_log_get_part($conn, $line['pre_part']);
    $pay = $row['log_amount'];

    if ($pay > $line['pre_amount'] + PICKUP_SAVE_LOG_EPSILON) {
        throw new RuntimeException(
            'จ่ายเกินจำนวนที่ขอ: ' . $part['part_name'] . ' (ขอ ' . $line['pre_amount'] . ', จะจ่าย ' . $pay . ')'
        );
    }
    if ($pay > $part['part_amount'] + PICKUP_SAVE_LOG_EPSILON) {
        throw new RuntimeException(
            'ของในคลังไม่พอ: ' . $part['part_name'] . ' (มี ' . $part['part_amount'] . ', จะจ่าย ' . $pay . ')'
        );
    }

    $insertStmt = pickup_save_log_execute(
        $conn,
        'INSERT INTO `log_stock`
            (`pre_id`, `log_date`, `log_product`, `log_part`, `log_type`, `log_amount`, `log_by`, `log_remarks`, `ts_mtn_number`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssdsss',
        array(
            $row['pre_id'],
            $row['log_date'],
            $line['pre_product'],
            $part['part_name'],
            $row['pre_type'],
            $pay,
            $row['log_by'],
            $line['pre_remarks'],
            $row['ts_mtn_number'],
        )
    );
    mysqli_stmt_close($insertStmt);

    $totalAmount = $part['part_amount'] - $pay;
    $diff = $totalAmount - $part['part_min_alert'];
    $nextStatus = pickup_save_log_next_status($diff, $part['part_status_purchase']);

    $updateStmt = pickup_save_log_execute(
        $conn,
        'UPDATE `part`
         SET `part_amount` = ?, `part_diff_stock` = ?, `part_status_purchase` = ?
         WHERE `part_id` = ?',
        'ddsi',
        array($totalAmount, $diff, $nextStatus, $part['part_id'])
    );
    mysqli_stmt_close($updateStmt);

    $remaining = round($line['pre_amount'] - $pay, 2);
    if ($remaining <= PICKUP_SAVE_LOG_EPSILON) {
        $stmt = pickup_save_log_execute(
            $conn,
            'UPDATE `pre_stock` SET `pre_status` = ? WHERE `pre_running` = ?',
            'si',
            array('pickup', $row['pre_running'])
        );
    } else {
        // ส่วนที่ค้างต้องจัดใหม่ตอนของเข้า — รีเซ็ตสถานะ "จัดแล้ว" บนหน้าจัดของ
        $stmt = pickup_save_log_execute(
            $conn,
            'UPDATE `pre_stock` SET `pre_amount` = ?, `pre_check` = ? WHERE `pre_running` = ?',
            'dsi',
            array($remaining, 'NO', $row['pre_running'])
        );
    }
    mysqli_stmt_close($stmt);
}

/**
 * จำนวนแถวที่ยังค้างจ่ายในเอกสาร
 */
function pickup_save_log_pending_count($conn, $preId)
{
    $stmt = pickup_save_log_execute(
        $conn,
        'SELECT COUNT(*) FROM `pre_stock` WHERE `pre_id` = ? AND `pre_status` = ?',
        'ss',
        array($preId, 'pre')
    );
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return (int) $count;
}

/**
 * อ่านและตรวจ request — ต้องมี pre_running ของทุกแถวที่เลือก
 *
 * @return array{pre_id:string, rows:array}
 */
function pickup_save_log_build_request()
{
    $count = pickup_save_log_positive_int(isset($_GET['count']) ? $_GET['count'] : 0);
    $preId = pickup_save_log_text(isset($_GET['pre_id']) ? $_GET['pre_id'] : '', 30);
    $logBy = inventory_actor_name_for_save(100);
    $preType = pickup_save_log_text(isset($_GET['pre_type']) ? $_GET['pre_type'] : '', 100);
    $tsMtnNumber = pickup_save_log_text(isset($_GET['ts_mtn_number']) ? $_GET['ts_mtn_number'] : '', 100);

    if ($count <= 0 || $count > 50 || $preId === '' || $logBy === '' || $preType === '') {
        throw new RuntimeException('ข้อมูลสำหรับบันทึกไม่ครบ — กรุณาเข้าสู่ระบบใหม่แล้วลองอีกครั้ง');
    }

    $logDate = date('ymd-His');
    $rows = array();
    $seen = array();

    for ($i = 1; $i <= $count; $i++) {
        $check = pickup_save_log_text(isset($_GET['check' . $i]) ? $_GET['check' . $i] : '', 10);
        if ($check !== 'YES') {
            continue;
        }

        $preRunning = pickup_save_log_positive_int(isset($_GET['pre_running' . $i]) ? $_GET['pre_running' . $i] : 0);
        if ($preRunning <= 0) {
            throw new RuntimeException('ไม่พบรหัสแถวของรายการที่ ' . $i . ' — กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
        }
        if (isset($seen[$preRunning])) {
            continue;
        }
        $seen[$preRunning] = true;

        $rows[] = array(
            'pre_running' => $preRunning,
            'log_amount' => pickup_save_log_amount(isset($_GET['log_amount' . $i]) ? $_GET['log_amount' . $i] : '', $i),
            'pre_id' => $preId,
            'log_date' => $logDate,
            'pre_type' => $preType,
            'log_by' => $logBy,
            'ts_mtn_number' => $tsMtnNumber,
        );
    }

    if (empty($rows)) {
        throw new RuntimeException('กรุณาเลือกรายการที่จะจ่ายอย่างน้อย 1 รายการ');
    }

    return array(
        'pre_id' => $preId,
        'rows' => $rows,
    );
}

// ─ Main Flow ──────────────────────────────────────────────────────────────────

$message = '';
$isSuccess = false;

try {
    $request = pickup_save_log_build_request();

    mysqli_begin_transaction($connect);
    foreach ($request['rows'] as $row) {
        pickup_save_log_process_row($connect, $row);
    }
    $pendingLeft = pickup_save_log_pending_count($connect, $request['pre_id']);
    mysqli_commit($connect);

    $isSuccess = true;
    $message = 'บันทึกการจ่ายเรียบร้อย ' . count($request['rows']) . ' รายการ';
    if ($pendingLeft > 0) {
        $message .= ' · ค้างไว้ในเอกสาร ' . $pendingLeft . ' รายการ';
    }
} catch (Throwable $e) {
    if ($connect instanceof mysqli) {
        mysqli_rollback($connect);
    }
    http_response_code(400);
    $message = $e->getMessage();
    error_log('pickup_save_log error: ' . $e->getMessage());
}

db_close($connect);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($isSuccess) { ?>
        <meta http-equiv="refresh" content="2; URL='pickup_overview.php'">
    <?php } ?>
    <title>บันทึกการจ่ายของ</title>
</head>
<body style="font-family: sans-serif; padding: 1.5rem;">
    <h3><?php echo $isSuccess ? 'บันทึกสำเร็จ' : 'บันทึกไม่สำเร็จ'; ?></h3>
    <p><?php echo pickup_save_log_h($message); ?></p>

    <?php if ($isSuccess) { ?>
        <p>กำลังกลับไปหน้ารายการเบิก...</p>
    <?php } else { ?>
        <p><a href="javascript:history.back()">กลับไปแก้ไข</a></p>
    <?php } ?>
</body>
</html>
