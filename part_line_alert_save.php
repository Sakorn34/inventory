<?php
/**
 * บันทึกการตั้งค่าแจ้งเตือน Line ต่อรายการ Part จากหน้า stock_overview
 *
 * รับ POST `part_line_alert[part_id]=0|1` แล้ว update เฉพาะ part ที่ส่งมาในฟอร์ม
 *
 * Flow: stock_overview → POST → redirect กลับพร้อม query เดิม
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/line_stock_alert.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Bangkok');

/**
 * escape สำหรับ HTML
 *
 * @param mixed $value ค่าที่แสดง
 * @return string
 */
function part_line_alert_save_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * สร้าง URL กลับ stock_overview ตาม filter เดิม
 *
 * @return string
 */
function part_line_alert_save_back_url()
{
    $params = array();
    if (isset($_POST['product']) && trim((string) $_POST['product']) !== '' && trim((string) $_POST['product']) !== 'All') {
        $params['product'] = trim((string) $_POST['product']);
    }
    if (isset($_POST['part_status']) && trim((string) $_POST['part_status']) !== '' && trim((string) $_POST['part_status']) !== 'All') {
        $params['part_status'] = trim((string) $_POST['part_status']);
    }
    $allowedStockFilters = array('low', 'ok', 'zero', 'negative');
    if (isset($_POST['stock_filter'])) {
        $sf = trim((string) $_POST['stock_filter']);
        if (in_array($sf, $allowedStockFilters, true)) {
            $params['stock_filter'] = $sf;
        }
    }
    $allowedSeverityFilters = array('slight', 'critical', 'empty');
    if (isset($_POST['severity_filter'])) {
        $sv = trim((string) $_POST['severity_filter']);
        if (in_array($sv, $allowedSeverityFilters, true)) {
            $params['severity_filter'] = $sv;
        }
    }

    $url = 'stock_overview.php';
    if (count($params) > 0) {
        $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    return $url;
}

/**
 * แสดงผลแล้ว redirect
 *
 * @param string $message ข้อความ
 * @param bool $success สำเร็จหรือไม่
 * @param string $backUrl URL กลับ
 * @return void
 */
function part_line_alert_save_render($message, $success, $backUrl)
{
    $class = $success ? 'success' : 'error';
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="2;url=' . part_line_alert_save_h($backUrl) . '">';
    echo '<title>บันทึกแจ้งเตือน Line</title>';
    echo '<style>body{font-family:sans-serif;max-width:640px;margin:3rem auto;color:#334155}';
    echo '.success{color:#166534;background:#dcfce7;padding:1rem;border-radius:8px}';
    echo '.error{color:#991b1b;background:#fee2e2;padding:1rem;border-radius:8px}</style>';
    echo '</head><body><div class="' . part_line_alert_save_h($class) . '">' . part_line_alert_save_h($message) . '</div>';
    echo '<p><a href="' . part_line_alert_save_h($backUrl) . '">กลับภาพรวม Stock</a></p></body></html>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    part_line_alert_save_render('กรุณาส่งข้อมูลผ่านฟอร์มเท่านั้น', false, 'stock_overview.php');
    db_close($connect);
    exit;
}

$backUrl = part_line_alert_save_back_url();
$flags = isset($_POST['part_line_alert']) && is_array($_POST['part_line_alert'])
    ? $_POST['part_line_alert']
    : array();

if (count($flags) === 0) {
    part_line_alert_save_render('ไม่พบรายการ Part ในฟอร์ม', false, $backUrl);
    db_close($connect);
    exit;
}

try {
    inventory_line_alert_ensure_schema($connect);

    $sql = 'UPDATE `part` SET `part_line_alert` = ? WHERE `part_id` = ? LIMIT 1';
    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) {
        throw new RuntimeException('เตรียมคำสั่ง update ไม่สำเร็จ');
    }

    $updated = 0;
    foreach ($flags as $partIdRaw => $flagRaw) {
        $partId = filter_var($partIdRaw, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($partId === false) {
            continue;
        }
        $flag = ((string) $flagRaw === '1') ? 1 : 0;
        mysqli_stmt_bind_param($stmt, 'ii', $flag, $partId);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('part_line_alert_save error part_id=' . $partId . ': ' . mysqli_stmt_error($stmt));
            continue;
        }
        if (mysqli_stmt_affected_rows($stmt) >= 0) {
            $updated++;
        }
    }
    mysqli_stmt_close($stmt);

    part_line_alert_save_render('บันทึกการตั้งค่าแจ้งเตือน Line แล้ว (' . $updated . ' รายการ)', true, $backUrl);
} catch (Exception $e) {
    part_line_alert_save_render($e->getMessage(), false, $backUrl);
}

db_close($connect);
