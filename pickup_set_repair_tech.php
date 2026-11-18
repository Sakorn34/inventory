<?php
/**
 * JSON — รายการอะไหล่คลังช่างในชุดซ่อม สำหรับโหลดตารางเซตบน pickup_ma.php
 *
 * คูณ default_qty ด้วยจำนวนชุด (pre_amount) เหมือน Part คลังกลาง
 *
 * ตัวอย่าง: pickup_set_repair_tech.php?pre_product=1&pre_amount=2
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/pickup_repair_formula_tech.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/**
 * คืน JSON error
 *
 * @param string $message ข้อความ
 * @param int    $code    HTTP status
 * @return void
 */
function pickup_set_repair_tech_json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(
        array('ok' => false, 'error' => $message, 'lines' => array()),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$productId = filter_var(isset($_GET['pre_product']) ? $_GET['pre_product'] : 0, FILTER_VALIDATE_INT);
$setAmount = filter_var(isset($_GET['pre_amount']) ? $_GET['pre_amount'] : 0, FILTER_VALIDATE_FLOAT);

if ($productId === false || $productId <= 0 || $setAmount === false || $setAmount <= 0) {
    pickup_set_repair_tech_json_error('พารามิเตอร์ pre_product / pre_amount ไม่ถูกต้อง');
}

try {
    $lines = pickup_repair_formula_tech_lines_for_pickup($connect, (int) $productId, (float) $setAmount);
    echo json_encode(
        array(
            'ok' => true,
            'pre_product' => (int) $productId,
            'pre_amount' => (float) $setAmount,
            'lines' => $lines,
        ),
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    error_log('[pickup_set_repair_tech] ' . $e->getMessage());
    pickup_set_repair_tech_json_error('โหลดรายการคลังช่างไม่สำเร็จ', 500);
}

db_close($connect);