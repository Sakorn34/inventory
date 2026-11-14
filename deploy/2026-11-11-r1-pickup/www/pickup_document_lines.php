<?php
/**
 * API คืนรายการอะไหล่ในเอกสารเบิกที่รอจ่าย (สำหรับ popup บน pickup_overview)
 *
 * รับ pre_id แล้วตอบ JSON หัวเอกสาร + แถว part ที่ pre_status = 'pre' (รวม part_id สำหรับยืนยันจ่ายจาก popup)
 * ตัวอย่าง: pickup_document_lines.php?pre_id=261103-155453
 */

require_once __DIR__ . '/connect.php';

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

$sql = 'SELECT ps.`pre_date`, ps.`pre_by`, ps.`pre_type`, ps.`pre_product`, ps.`pre_amount`, ps.`pre_remarks`,
               ps.`ts_mtn_number`, ps.`pre_part`, p.`part_name`
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
    $preDate,
    $preBy,
    $preType,
    $preProduct,
    $preAmount,
    $preRemarks,
    $tsMtn,
    $prePart,
    $partName
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
        'part_id' => (int) $prePart,
        'part_name' => (string) $partName,
        'product' => (string) $preProduct,
        'amount' => (string) $preAmount,
        'remarks' => $lineRemark,
    );
}

mysqli_stmt_close($stmt);
db_close($connect);

if ($lines === array()) {
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
        'line_count' => count($lines),
        'detail_url' => 'pickup_list_order_detail.php?pre_id=' . rawurlencode($preId)
            . '&ts_mtn_number=' . rawurlencode($headerMtn)
            . '&pre_remarks=' . rawurlencode($remarksJoined),
        'lines' => $lines,
    ),
    JSON_UNESCAPED_UNICODE
);
