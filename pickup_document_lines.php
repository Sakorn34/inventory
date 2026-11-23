<?php
/**
 * API คืนรายการอะไหล่ในเอกสารเบิกที่รอจ่าย (สำหรับ popup บน pickup_overview)
 *
 * รับ pre_id แล้วตอบ JSON หัวเอกสาร + แถว part ที่ pre_status = 'pre' (รวม part_id สำหรับยืนยันจ่ายจาก popup)
 * ตัวอย่าง: pickup_document_lines.php?pre_id=261103-155453
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/pickup_pre_stock_tech.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

/**
 * ตรวจรูปแบบเลขเอกสาร pre_id
 *
 * @param string $preId เลขเอกสารจาก request
 * @return bool true เมื่อรูปแบบถูกต้อง
 */
function pickup_document_lines_valid_pre_id($preId)
{
    return (bool) preg_match('/^[0-9A-Za-z-]{1,32}$/', $preId);
}

$preId = isset($_GET['pre_id']) ? trim((string) $_GET['pre_id']) : '';

if ($preId === '' || !pickup_document_lines_valid_pre_id($preId)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'message' => 'เลขเอกสารไม่ถูกต้อง'), JSON_UNESCAPED_UNICODE);
    db_close($connect);
    exit;
}

// paid_amount = ยอดที่จ่ายไปแล้วจากแถวนี้ (จ่ายบางส่วน) — ดูจาก log_stock ของเอกสาร/อะไหล่/สินค้าเดียวกัน
$sql = 'SELECT ps.`pre_running`, ps.`pre_date`, ps.`pre_by`, ps.`pre_type`, ps.`pre_product`, ps.`pre_amount`, ps.`pre_remarks`,
               ps.`ts_mtn_number`, ps.`pre_part`, p.`part_name`, p.`part_amount`,
               (SELECT COALESCE(SUM(ls.`log_amount`), 0) FROM `log_stock` ls
                WHERE ls.`pre_id` = ps.`pre_id` AND ls.`log_part` = p.`part_name` AND ls.`log_product` = ps.`pre_product`) AS `paid_amount`
        FROM `pre_stock` ps
        INNER JOIN `part` p ON ps.`pre_part` = p.`part_id`
        WHERE ps.`pre_id` = ? AND ps.`pre_status` = ?
        ORDER BY ps.`pre_running` ASC';

$stmt = mysqli_prepare($connect, $sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'message' => 'ไม่สามารถโหลดรายการได้'), JSON_UNESCAPED_UNICODE);
    db_close($connect);
    exit;
}

$statusPre = 'pre';
mysqli_stmt_bind_param($stmt, 'ss', $preId, $statusPre);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result(
    $stmt,
    $preRunning,
    $preDate,
    $preBy,
    $preType,
    $preProduct,
    $preAmount,
    $preRemarks,
    $tsMtn,
    $prePart,
    $partName,
    $partStock,
    $paidAmount
);

$lines = array();
$headerDate = '';
$headerBy = '';
$headerType = '';
$headerMtn = '';
$docRemarks = array();

while (mysqli_stmt_fetch($stmt)) {
    if ($headerDate === '') {
        $headerDate = (string) $preDate;
        $headerBy = (string) $preBy;
        $headerType = (string) $preType;
        $headerMtn = (string) $tsMtn;
    }
    $lineRemark = trim((string) $preRemarks);
    if ($lineRemark !== '') {
        $docRemarks[$lineRemark] = true;
    }
    $lines[] = array(
        'line_kind' => 'inventory',
        'pre_running' => (int) $preRunning,
        'stock' => (float) $partStock,
        'paid_amount' => (float) $paidAmount,
        'part_id' => (int) $prePart,
        'part_name' => (string) $partName,
        'product' => (string) $preProduct,
        'amount' => (string) $preAmount,
        'remarks' => $lineRemark,
    );
}

mysqli_stmt_close($stmt);

$techLines = pickup_pre_stock_tech_fetch_lines($connect, $preId);
$allLines = $lines;
if ($techLines !== array()) {
    $allLines = array_merge($lines, $techLines);
}

db_close($connect);

if ($allLines === array()) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'message' => 'ไม่พบเอกสารหรือไม่มีรายการรอจ่าย'), JSON_UNESCAPED_UNICODE);
    exit;
}

$remarksJoined = implode(' / ', array_keys($docRemarks));

echo json_encode(
    array(
        'ok' => true,
        'pre_id' => $preId,
        'pre_date' => $headerDate,
        'pre_by' => $headerBy,
        'pre_type' => $headerType,
        'ts_mtn_number' => $headerMtn,
        'remarks' => $remarksJoined,
        'line_count' => count($allLines),
        'line_count_inventory' => count($lines),
        'line_count_tech' => count($techLines),
        'detail_url' => 'pickup_list_order_detail.php?pre_id=' . rawurlencode($preId)
            . '&ts_mtn_number=' . rawurlencode($headerMtn)
            . '&pre_remarks=' . rawurlencode($remarksJoined),
        'lines' => $allLines,
    ),
    JSON_UNESCAPED_UNICODE
);
