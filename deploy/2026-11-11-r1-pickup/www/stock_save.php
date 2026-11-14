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
 * 3. redirect ?? `stock_overview.php`
 *
 * ????????: POST stock_save.php ????? count_sp=1 ??? sn_sp1=54
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';

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
 * ??????????????????? redirect ?? stock overview
 *
 * @param string $message ?????????????
 * @param bool $success true ?????????????????
 * @return void
 */
function stock_save_render_result($message, $success)
{
    $class = $success ? 'success' : 'error';
    $refresh = $success ? '<meta http-equiv="refresh" content="2;url=stock_overview.php">' : '';

    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
    echo $refresh;
    echo '<title>' . stock_save_h(stock_save_msg('title')) . '</title>';
    echo '<style>body{font-family:sans-serif;max-width:760px;margin:3rem auto;color:#334155}';
    echo '.success{color:#166534;background:#dcfce7;padding:1rem;border-radius:8px}';
    echo '.error{color:#991b1b;background:#fee2e2;padding:1rem;border-radius:8px}</style>';
    echo '</head><body><div class="' . stock_save_h($class) . '">' . stock_save_h($message) . '</div>';

    echo '<p><a href="stock_overview.php">' . stock_save_h(stock_save_msg('back')) . '</a></p>';
    echo '<p><a href="stock_add.php">' . stock_save_h(json_decode('"\u0e01\u0e25\u0e31\u0e1a\u0e44\u0e1b\u0e40\u0e1e\u0e34\u0e48\u0e21 Stock"')) . '</a></p></body></html>';
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
 * ?????? stock 1 ??????
 *
 * @param mysqli $conn connection ?????????
 * @param string $logDate ????????????
 * @param string $logType ?????? log
 * @param string $logBy ?????????
 * @param string $vendor ???? vendor
 * @param int $productId ???? product
 * @param int $partId ???? part
 * @param float $logAmount ?????????????
 * @param string $logPrice ????
 * @param string $remarks ????????
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
    stock_save_render_result(stock_save_msg('form_only'), false);
    db_close($connect);
    exit;
}

$logDate = stock_save_text('log_date', 30);
$logType = stock_save_text('log_type', 100);
try {
    $logBy = inventory_actor_name_for_save(100);
} catch (RuntimeException $actorError) {
    stock_save_render_result(stock_save_msg('missing_header'), false);
    db_close($connect);
    exit;
}
$vendor = stock_save_text('vendor_com', 255);
$rowCount = stock_save_int('count_sp', 0);

if ($logDate === '' || $logBy === '' || $vendor === '') {
    stock_save_render_result(stock_save_msg('missing_header'), false);
    db_close($connect);
    exit;
}

if ($rowCount <= 0 || $rowCount > 5) {
    stock_save_render_result(stock_save_msg('invalid_count'), false);
    db_close($connect);
    exit;
}

if ($logType === '') {
    $logType = STOCK_SAVE_LOG_TYPE_DEFAULT;
}

try {
    if (!stock_save_ensure_log_autoincrement($connect)) {
        throw new Exception(stock_save_msg('log_prepare'));
    }

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
    stock_save_render_result(sprintf(stock_save_msg('success'), $savedRows), true);
} catch (Exception $e) {
    mysqli_rollback($connect);
    stock_save_render_result($e->getMessage(), false);
}

db_close($connect);
