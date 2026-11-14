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

echo json_encode(array(
    'ok' => true,
    'local_config_readable' => is_readable($localFile),
    'prep_board_token_configured' => ($boardToken !== ''),
    'prep_board_token_source' => inventory_config_pickup_prep_board_token_source(),
    'prep_board_token_length' => strlen($boardToken),
    'files' => array(
        'pickup_prep_board.php' => is_readable(__DIR__ . '/pickup_prep_board.php'),
        'pickup_prep_board_auth.php' => is_readable(__DIR__ . '/pickup_prep_board_auth.php'),
        'pickup_prep_api.php' => is_readable(__DIR__ . '/pickup_prep_api.php'),
        'css/pickup-prep.css' => is_readable(__DIR__ . '/css/pickup-prep.css'),
    ),
    'hint' => 'เปิด pickup_prep_board.php?token=<token ความยาวเดียวกับ prep_board_token_length>',
), JSON_UNESCAPED_UNICODE);
