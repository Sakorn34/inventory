<?php
/**
 * Endpoint ?????????????? Stock (??????)
 *
 * ???????????? `stock_add.php` ???? POST ???? insert ?? `log_stock`
 * ???????????????? `part` ???? prepared statement ?????? transaction
 *
 * Flow ????:
 * 1. ???????? log_date, vendor, log_by ????????? pd_sp/sn_sp/amount ?????? 5 ???
 * 2. ?????????? log ????????? part_amount / part_diff_stock / part_status_purchase
 * 3. ตั้ง session flash แล้ว redirect — สำเร็จไป stock_overview.php ผิดพลาดกลับ stock_add.php
 *
 * ????????: POST stock_save.php ????? count_sp=1 ??? sn_sp1=54
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/stock_flash.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Bangkok');

// ? Constants ?????????????????????????????????????????????????????????????????

define('STOCK_SAVE_STATUS_NORMAL', json_decode('"\u0e1b\u0e01\u0e15\u0e34"'));
define('STOCK_SAVE_STATUS_ORDERED', json_decode('"\u0e2a\u0e31\u0e48\u0e07\u0e0b\u0e37\u0e49\u0e41\u0e25\u0e49\u0e27"'));
define('STOCK_SAVE_STATUS_NOT_ORDERED', json_decode('"\u0e22\u0e31\u0e07\u0e44\u0e21\u0e48\u0e44\u0e14\u0e49\u0e2a\u0e31\u0e48\u0e07\u0e0b\u0e37\u0e49"'));
define('STOCK_SAVE_LOG_TYPE_DEFAULT', json_decode('"\u0e19\u0e33\u0e40\u0e02\u0e49\u0e32"'));

/**
 * ???????????????????? encode ???? JSON unicode escape
 *
 * @param string $key ???????????
 * @return string ?????????? decode ????
 */
function stock_save_msg($key)
{
    $messages = array(
        'form_only' => '"\u0e01\u0e23\u0e38\u0e13\u0e32\u0e2a\u0e48\u0e07\u0e02\u0e49\u0e2d\u0e21\u0e39\u0e25\u0e1c\u0e48\u0e32\u0e19\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e40\u0e17\u0e48\u0e32\u0e19\u0e31\u0e49\u0e19"',
        'missing_header' => '"\u0e01\u0e23\u0e38\u0e13\u0e32\u0e01\u0e23\u0e2d\u0e01\u0e27\u0e31\u0e19\u0e17\u0e35\u0e48, vendor \u0e41\u0e25\u0e30\u0e1c\u0e39\u0e49\u0e1a\u0e31\u0e19\u0e17\u0e36\u0e01\u0e43\u0e2b\u0e49\u0e04\u0e23\u0e1a"',
        'invalid_count' => '"\u0e08\u0e33\u0e19\u0e27\u0e19\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e44\u0e21\u0e48\u0e16\u0e39\u0e01\u0e15\u0e49\u0e19\u0e07 \u0e01\u0e23\u0e38\u0e13\u0e32\u0e01\u0e14 Add \u0e41\u0e25\u0e49\u0e27\u0e01\u0e23\u0e2d\u0e01\u0e02\u0e49\u0e2d\u0e21\u0e39\u0e25\u0e01\u0e48\u0eAD\u0e19\u0e1a\u0e31\u0e19\u0e17\u0e36\u0e01"',
        'amount_required' => '"\u0e01\u0e23\u0e38\u0e13\u0e32\u0e01\u0e23\u0e2d\u0e01\u0e08\u0e33\u0e19\u0e27\u0e19\u0e43\u0e2b\u0e49\u0e04\u0e23\u0e1a\u0e17\u0e38\u0e01\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23"',
        'amount_invalid' => '"\u0e08\u0e33\u0e19\u0e27\u0e19 stock \u0e15\u0e49\u0e2d\u0e07\u0e40\u0e1b\u0e47\u0e19\u0e15\u0e31\u0e27\u0e40\u0e25\u0e02"',
        'part_prepare' => '"\u0e40\u0e15\u0e23\u0e35\u0e22\u0e21\u0e04\u0e33\u0e2a\u0e31\u0e48\u0e07\u0e04\u0e49\u0e19\u0e2b\u0e32 Part \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'part_search' => '"\u0e04\u0e49\u0e19\u0e2b\u0e32 Part \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'part_not_found' => '"\u0e44\u0e21\u0e48\u0e1e\u0e1a Part \u0e17\u0e35\u0e48\u0e40\u0e25\u0e37\u0e2d\u0e01 (ID %d)"',
        'product_prepare' => '"\u0e40\u0e15\u0e23\u0e35\u0e22\u0e21\u0e04\u0e33\u0e2a\u0e31\u0e48\u0e07\u0e04\u0e49\u0e19\u0e2b\u0e32 Product \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'product_search' => '"\u0e04\u0e49\u0e19\u0e2b\u0e32 Product \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'product_not_found' => '"\u0e44\u0e21\u0e48\u0e1e\u0e1a Product \u0e17\u0e35\u0e48\u0e40\u0e25\u0e37\u0e2d\u0e01"',
        'log_prepare' => '"\u0e40\u0e15\u0e23\u0e35\u0e22\u0e21\u0e04\u0e33\u0e2a\u0e31\u0e48\u0e07 insert log_stock \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'log_insert' => '"\u0e1a\u0e31\u0e19\u0e17\u0e36\u0e01 log stock \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'part_update_prepare' => '"\u0e40\u0e15\u0e23\u0e35\u0e22\u0e21\u0e04\u0e33\u0e2a\u0e31\u0e48\u0e07 update part \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'part_update' => '"\u0e2d\u0e31\u0e1b\u0e40\u0e14\u0e15\u0e08\u0e33\u0e19\u0e27\u0e19 Part \u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08"',
        'row_incomplete' => '"\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e17\u0e35\u0e48 %d \u0e40\u0e25\u0e37\u0e2d\u0e01 Product \u0e2b\u0e23\u0e37\u0e2d Part \u0e44\u0e21\u0e48\u0e04\u0e23\u0e1a"',
        'no_rows' => '"\u0e44\u0e21\u0e48\u0e1e\u0e1a\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e17\u0e35\u0e48\u0e1a\u0e31\u0e19\u0e17\u0e36\u0e01\u0e44\u0e14\u0e49"',
        'success' => '"\u0e1a\u0e31\u0e19\u0e17\u0e36\u0e01 Stock \u0e40\u0e23\u0e35\u0e22\u0e1a\u0e23\u0e49\u0e2d\u0e22\u0e41\u0e25\u0e49\u0e27 %d \u0e23\u0e32\u0e22\u0e01\u0e32\u0e23"',
        'title' => '"\u0e1a\u0e31\u0e19\u0e17\u0e36\u0e01 Stock"',
        'back' => '"\u0e01\u0e25\u0e31\u0e1a\u0e44\u0e1b\u0e20\u0e32\u0e1e\u0e23\u0e27\u0e21 Stock"',
    );

    if (!isset($messages[$key])) {
        return $key;
    }

    return json_decode($messages[$key]);
}

// ? Helpers ??????????????????????????????????????????????????????????????????

/**
 * escape ??????????? render ????????? HTML
 *
 * @param mixed $value ????????????????
 * @return string ??????????????????????? HTML
 */
function stock_save_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * ?????????????? POST ????? trim ???????????????
 *
 * @param string $key ???? field
 * @param int $maxLength ?????????????
 * @return string ?????????? normalize ????
 */
function stock_save_text($key, $maxLength)
{
    $text = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

/**
 * ??????? int ??? POST
 *
 * @param string $key ???? field
 * @param int $default ??? default
 * @return int
 */
function stock_save_int($key, $default)
{
    if (!isset($_POST[$key])) {
        return $default;
    }

    $value = filter_var($_POST[$key], FILTER_VALIDATE_INT);

    return $value === false ? $default : (int) $value;
}

/**
 * ????????? stock ??? POST
 *
 * @param string $key ???? field
 * @return float
 */
function stock_save_amount($key)
{
    if (!isset($_POST[$key]) || $_POST[$key] === '') {
        throw new Exception(stock_save_msg('amount_required'));
    }

    $amount = filter_var($_POST[$key], FILTER_VALIDATE_FLOAT);
    if ($amount === false) {
        throw new Exception(stock_save_msg('amount_invalid'));
    }

    return (float) $amount;
}

/**
 * บันทึกสำเร็จ — ตั้ง flash แล้วไปหน้าภาพรวม stock
 *
 * @param string $message ข้อความสำเร็จ
 * @return void
 */
function stock_save_redirect_overview($message)
{
    stock_flash_set($message, 'success');
    header('Location: stock_overview.php', true, 303);
    exit;
}

/**
 * บันทึกไม่สำเร็จ — ตั้ง flash แล้วกลับหน้าเพิ่ม stock
 *
 * @param string $message ข้อความผิดพลาด
 * @return void
 */
function stock_save_redirect_add_error($message)
{
    stock_flash_set($message, 'error');
    header('Location: stock_add.php', true, 303);
    exit;
}

/**
 * ??????????????????????????? stock
 *
 * @param float $diff ?????????????????????
 * @param string $currentStatus ?????????????
 * @return string ?????????
 */
function stock_save_next_status($diff, $currentStatus)
{
    if ($diff > 0) {
        return STOCK_SAVE_STATUS_NORMAL;
    }

    if ($diff <= 0 && $currentStatus !== STOCK_SAVE_STATUS_ORDERED) {
        return STOCK_SAVE_STATUS_NOT_ORDERED;
    }

    return $currentStatus;
}

/**
 * ????????? part ??? part_id
 *
 * @param mysqli $conn connection ?????????
 * @param int $partId ???? part
 * @return array ??? part
 */
function stock_save_get_part($conn, $partId)
{
    $stmt = mysqli_prepare(
        $conn,
        'SELECT part_id, part_name, part_amount, part_min_alert, part_status_purchase
         FROM part WHERE part_id = ? LIMIT 1'
    );
    if (!$stmt) {
        throw new Exception(stock_save_msg('part_prepare'));
    }

    mysqli_stmt_bind_param($stmt, 'i', $partId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception(stock_save_msg('part_search'));
    }

    mysqli_stmt_bind_result(
        $stmt,
        $rowPartId,
        $rowPartName,
        $rowPartAmount,
        $rowPartMinAlert,
        $rowPartStatus
    );

    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception(sprintf(stock_save_msg('part_not_found'), $partId));
    }
    mysqli_stmt_close($stmt);

    return array(
        'part_id' => $rowPartId,
        'part_name' => $rowPartName,
        'part_amount' => $rowPartAmount,
        'part_min_alert' => $rowPartMinAlert,
        'part_status_purchase' => $rowPartStatus,
    );
}

/**
 * ??????? product ??? pd_id
 *
 * @param mysqli $conn connection ?????????
 * @param int $productId ???? product
 * @return string ???? product
 */
function stock_save_get_product_name($conn, $productId)
{
    $stmt = mysqli_prepare($conn, 'SELECT pd_name FROM product WHERE pd_id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception(stock_save_msg('product_prepare'));
    }

    mysqli_stmt_bind_param($stmt, 'i', $productId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception(stock_save_msg('product_search'));
    }

    mysqli_stmt_bind_result($stmt, $productName);
    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception(stock_save_msg('product_not_found'));
    }
    mysqli_stmt_close($stmt);

    return (string) $productName;
}

/**
 * ?????????? log_stock.log_id ??????? AUTO_INCREMENT ??? migrate ???????????
 *
 * Access schema ????????? AUTO_INCREMENT ????? INSERT ??????? log_id=0 ??? PRIMARY KEY
 *
 * @param mysqli $conn connection ?????????
 * @return bool true ?????????? insert (?? AUTO_INCREMENT ?????????????????????)
 */
function stock_save_ensure_log_autoincrement($conn)
{
    $columnResult = mysqli_query($conn, "SHOW COLUMNS FROM `log_stock` LIKE 'log_id'");
    if (!$columnResult instanceof mysqli_result) {
        return false;
    }

    $column = mysqli_fetch_assoc($columnResult);
    mysqli_free_result($columnResult);

    if (!$column) {
        return false;
    }

    if (stripos((string) $column['Extra'], 'auto_increment') !== false) {
        return true;
    }

    if (!mysqli_query($conn, 'ALTER TABLE `log_stock` MODIFY `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT')) {
        return false;
    }

    $nextId = 1;
    $maxResult = mysqli_query($conn, 'SELECT IFNULL(MAX(`log_id`), 0) + 1 AS next_id FROM `log_stock`');
    if ($maxResult instanceof mysqli_result) {
        $maxRow = mysqli_fetch_assoc($maxResult);
        if ($maxRow && isset($maxRow['next_id'])) {
            $nextId = max(1, (int) $maxRow['next_id']);
        }
        mysqli_free_result($maxResult);
    }

    if (!mysqli_query($conn, 'ALTER TABLE `log_stock` AUTO_INCREMENT = ' . $nextId)) {
        return false;
    }

    return true;
}

/**
 * บันทึกชื่อ vendor ลง tbl_vendor ถ้ายังไม่มี — ให้ค้นหาครั้งถัดไปเจอใน autocomplete
 *
 * @param mysqli $conn connection
 * @param string $vendorName ชื่อร้านจากฟอร์ม
 * @return void
 */
function stock_save_ensure_vendor_in_catalog($conn, $vendorName)
{
    $vendorName = trim((string) $vendorName);
    if ($vendorName === '' || !($conn instanceof mysqli)) {
        return;
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO `tbl_vendor` (`vname`) SELECT ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_vendor` WHERE `vname` = ? LIMIT 1)'
    );
    if ($stmt === false) {
        error_log('[stock_save_ensure_vendor_in_catalog] ' . mysqli_error($conn));

        return;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $vendorName, $vendorName);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[stock_save_ensure_vendor_in_catalog] execute: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
}

/**
 * บันทึก stock 1 แถวลง log_stock และอัปเดต stock ของ part
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $logDate วันที่ log
 * @param string $logType ประเภท log
 * @param string $logBy ผู้บันทึก
 * @param string $vendor ชื่อ vendor
 * @param int $productId รหัส product
 * @param int $partId รหัส part
 * @param float $logAmount จำนวน
 * @param string $logPrice ราคา
 * @param string $remarks หมายเหตุ
 * @return void
 */
function stock_save_process_row($conn, $logDate, $logType, $logBy, $vendor, $productId, $partId, $logAmount, $logPrice, $remarks)
{
    $part = stock_save_get_part($conn, $partId);
    $productName = stock_save_get_product_name($conn, $productId);
    $partName = (string) $part['part_name'];

    $insertSql = 'INSERT INTO log_stock
        (log_date, log_product, log_part, log_type, log_amount, log_by, log_price, log_vendor, log_remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        throw new Exception(stock_save_msg('log_prepare'));
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        'ssssdssss',
        $logDate,
        $productName,
        $partName,
        $logType,
        $logAmount,
        $logBy,
        $logPrice,
        $vendor,
        $remarks
    );

    if (!mysqli_stmt_execute($insertStmt)) {
        mysqli_stmt_close($insertStmt);
        throw new Exception(stock_save_msg('log_insert'));
    }
    mysqli_stmt_close($insertStmt);

    $currentAmount = (float) $part['part_amount'];
    $minAlert = (float) $part['part_min_alert'];
    $totalAmount = $currentAmount + $logAmount;
    $diff = $totalAmount - $minAlert;
    $nextStatus = stock_save_next_status($diff, (string) $part['part_status_purchase']);

    $updateSql = 'UPDATE part
        SET part_amount = ?, part_diff_stock = ?, part_status_purchase = ?
        WHERE part_id = ?';
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if (!$updateStmt) {
        throw new Exception(stock_save_msg('part_update_prepare'));
    }

    mysqli_stmt_bind_param($updateStmt, 'ddsi', $totalAmount, $diff, $nextStatus, $partId);
    if (!mysqli_stmt_execute($updateStmt)) {
        mysqli_stmt_close($updateStmt);
        throw new Exception(stock_save_msg('part_update'));
    }
    mysqli_stmt_close($updateStmt);
}

// ? Main ?????????????????????????????????????????????????????????????????????

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    db_close($connect);
    stock_save_redirect_add_error(stock_save_msg('form_only'));
}

$logDate = stock_save_text('log_date', 30);
$logType = stock_save_text('log_type', 100);
try {
    $logBy = inventory_actor_name_for_save(100);
} catch (RuntimeException $actorError) {
    db_close($connect);
    stock_save_redirect_add_error(stock_save_msg('missing_header'));
}
$vendor = stock_save_text('vendor_com', 255);
$rowCount = stock_save_int('count_sp', 0);

if ($logDate === '' || $logBy === '' || $vendor === '') {
    db_close($connect);
    stock_save_redirect_add_error(stock_save_msg('missing_header'));
}

if ($rowCount <= 0 || $rowCount > 5) {
    db_close($connect);
    stock_save_redirect_add_error(stock_save_msg('invalid_count'));
}

if ($logType === '') {
    $logType = STOCK_SAVE_LOG_TYPE_DEFAULT;
}

try {
    if (!stock_save_ensure_log_autoincrement($connect)) {
        throw new Exception(stock_save_msg('log_prepare'));
    }

    stock_save_ensure_vendor_in_catalog($connect, $vendor);

    mysqli_begin_transaction($connect);

    $savedRows = 0;
    for ($i = 1; $i <= $rowCount; $i++) {
        $productKey = 'pd_sp' . $i;
        $partKey = 'sn_sp' . $i;
        $amountKey = 'amount_sp' . $i;
        $priceKey = 'price_sp' . $i;
        $remarksKey = 'remarks_sp' . $i;

        if (!isset($_POST[$productKey], $_POST[$partKey], $_POST[$amountKey], $_POST[$priceKey])) {
            continue;
        }

        $productId = stock_save_int($productKey, 0);
        $partId = stock_save_int($partKey, 0);
        $logAmount = stock_save_amount($amountKey);
        $logPrice = stock_save_text($priceKey, 50);
        $remarks = stock_save_text($remarksKey, 2000);

        if ($productId <= 0 || $partId <= 0) {
            throw new Exception(sprintf(stock_save_msg('row_incomplete'), $i));
        }

        stock_save_process_row(
            $connect,
            $logDate,
            $logType,
            $logBy,
            $vendor,
            $productId,
            $partId,
            $logAmount,
            $logPrice,
            $remarks
        );
        $savedRows++;
    }

    if ($savedRows <= 0) {
        throw new Exception(stock_save_msg('no_rows'));
    }

    mysqli_commit($connect);
    db_close($connect);
    stock_save_redirect_overview(sprintf(stock_save_msg('success'), $savedRows));
} catch (Exception $e) {
    mysqli_rollback($connect);
    db_close($connect);
    stock_save_redirect_add_error($e->getMessage());
}

db_close($connect);
