<?php
/**
 * ตรวจสอบการตั้งค่าหน้ามือถือจัดของ (ไม่เปิดเผยค่า token)
 *
 * เรียกด้วย token เดียวกับ line_stock_alert_url.php:
 * pickup_prep_board_status.php?token=<line_stock_url_token>
 *
 * ตัวอย่างใช้เมื่อ pickup_prep_board.php เปิดไม่ได้บน production
 */

require_once __DIR__ . '/pickup_prep_board_auth.php';
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$adminToken = inventory_config_line_stock_url_token();
$given = pickup_prep_board_request_token();

if ($adminToken === '' || $given === '' || !hash_equals($adminToken, $given)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'message' => 'token ไม่ถูกต้อง'), JSON_UNESCAPED_UNICODE);
    exit;
}

$localFile = __DIR__ . '/inventory_config.local.php';
$boardToken = inventory_config_pickup_prep_board_token();

$preCount = 0;
$statusPre = 'pre';
$stmt = mysqli_prepare($connect, 'SELECT COUNT(*) FROM `pre_stock` WHERE TRIM(`pre_status`) = ?');
if ($stmt !== false) {
    mysqli_stmt_bind_param($stmt, 's', $statusPre);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $preCountVal);
        if (mysqli_stmt_fetch($stmt)) {
            $preCount = (int) $preCountVal;
        }
    }
    mysqli_stmt_close($stmt);
}
db_close($connect);

echo json_encode(array(
    'ok' => true,
    'local_config_readable' => is_readable($localFile),
    'prep_board_token_configured' => ($boardToken !== ''),
    'prep_board_token_source' => inventory_config_pickup_prep_board_token_source(),
    'prep_board_token_length' => strlen($boardToken),
    'pre_stock_pre_row_count' => $preCount,
    'files' => array(
        'pickup_prep_board.php' => is_readable(__DIR__ . '/pickup_prep_board.php'),
        'pickup_prep_board_auth.php' => is_readable(__DIR__ . '/pickup_prep_board_auth.php'),
        'pickup_prep_api.php' => is_readable(__DIR__ . '/pickup_prep_api.php'),
        'css/pickup-prep.css' => is_readable(__DIR__ . '/css/pickup-prep.css'),
    ),
    'hint' => 'เปิด pickup_prep_board.php?token=<token ความยาวเดียวกับ prep_board_token_length>',
), JSON_UNESCAPED_UNICODE);
