<?php
/**
 * API คืนรายการอะไหล่ของเอกสารที่จ่ายแล้ว (อ่านจาก log_stock)
 *
 * ใช้กับ popup บน pickup_history.php
 * ตัวอย่าง: pickup_history_lines.php?pre_id=261015-133357
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/pickup_history_lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

/**
 * ตรวจรูปแบบเลขเอกสาร pre_id
 *
 * @param string $preId เลขเอกสารจาก request
 * @return bool true เมื่อรูปแบบถูกต้อง
 */
function pickup_history_lines_valid_pre_id($preId)
{
    return (bool) preg_match('/^[0-9A-Za-z-]{1,32}$/', $preId);
}

$preId = isset($_GET['pre_id']) ? trim((string) $_GET['pre_id']) : '';

if ($preId === '' || !pickup_history_lines_valid_pre_id($preId)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'message' => 'เลขเอกสารไม่ถูกต้อง'), JSON_UNESCAPED_UNICODE);
    db_close($connect);
    exit;
}

$lines = pickup_history_fetch_lines($connect, $preId);

if ($lines === array()) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'message' => 'ไม่พบรายการอะไหล่ของเอกสารนี้'), JSON_UNESCAPED_UNICODE);
    db_close($connect);
    exit;
}

$headerSql = 'SELECT MIN(`log_date`) AS log_date, MIN(`log_type`) AS log_type, MIN(`log_by`) AS log_by
              FROM `log_stock`
              WHERE `pre_id` = ? AND `log_type` LIKE ? LIMIT 1';
$stmt = mysqli_prepare($connect, $headerSql);
$headerDate = '';
$headerType = '';
$headerBy = '';
if ($stmt !== false) {
    $typeLike = 'เบิก%';
    mysqli_stmt_bind_param($stmt, 'ss', $preId, $typeLike);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $headerDate, $headerType, $headerBy);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

$remarks = array();
foreach ($lines as $line) {
    if ($line['remarks'] !== '') {
        $remarks[$line['remarks']] = true;
    }
}

echo json_encode(
    array(
        'ok' => true,
        'pre_id' => $preId,
        'pre_date' => stock_report_format_log_datetime((string) $headerDate),
        'pre_by' => (string) $headerBy,
        'pre_type' => (string) $headerType,
        'remarks' => implode(' / ', array_keys($remarks)),
        'line_count' => count($lines),
        'lines' => $lines,
    ),
    JSON_UNESCAPED_UNICODE
);

db_close($connect);
