<?php
/**
 * บันทึก / ลบ log_stock รายบุคคล
 *
 * POST params:
 *   action     = 'edit_log' | 'delete_log'
 *   log_id     = int
 *   csrf_token = string
 *
 *   edit_log เพิ่มเติม:
 *     log_date, log_vendor, log_amount, log_price, log_remarks
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/stock_flash.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: stock_add_history.php');
    exit;
}

// CSRF
$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken   = isset($_SESSION['stock_log_csrf_token']) ? (string) $_SESSION['stock_log_csrf_token'] : '';
if (!hash_equals($sessionToken, $submittedToken) || $sessionToken === '') {
    stock_flash_set('คำขอไม่ถูกต้อง (CSRF) กรุณาลองใหม่', 'error');
    header('Location: stock_add_history.php');
    exit;
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
if (!in_array($action, array('edit_log', 'delete_log'), true)) {
    stock_flash_set('action ไม่ถูกต้อง', 'error');
    header('Location: stock_add_history.php');
    exit;
}

$logId = isset($_POST['log_id']) ? (int) $_POST['log_id'] : 0;
if ($logId <= 0) {
    stock_flash_set('ไม่พบ log_id', 'error');
    header('Location: stock_add_history.php');
    exit;
}

// โหลด log entry เดิม
$stmtLoad = mysqli_prepare($connect,
    'SELECT log_id, log_part, log_amount FROM log_stock WHERE log_id = ? LIMIT 1');
if (!$stmtLoad) {
    stock_flash_set('ไม่สามารถโหลดข้อมูลได้: ' . mysqli_error($connect), 'error');
    header('Location: stock_add_history.php');
    exit;
}
mysqli_stmt_bind_param($stmtLoad, 'i', $logId);
mysqli_stmt_execute($stmtLoad);
$resLoad = mysqli_stmt_get_result($stmtLoad);
$logRow  = mysqli_fetch_assoc($resLoad);
mysqli_stmt_close($stmtLoad);

if (!$logRow) {
    stock_flash_set('ไม่พบรายการ log_id=' . $logId, 'error');
    header('Location: stock_add_history.php');
    exit;
}

$oldAmount = (float) $logRow['log_amount'];
$partName  = (string) $logRow['log_part'];

// ค้นหา part_id จาก part_name เพื่อปรับ stock
function sls_find_part($conn, $name)
{
    $stmt = mysqli_prepare($conn,
        'SELECT part_id, part_amount, part_min_alert, part_status_purchase FROM part WHERE part_name = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ? $row : null;
}

function sls_next_status($diff, $current)
{
    if ($diff > 0) {
        return 'ปกติ';
    }
    if ($diff <= 0 && $current !== 'สั่งซื้อแล้ว') {
        return 'ยังไม่ได้สั่งซื้อ';
    }
    return $current;
}

function sls_update_part($conn, $partId, $newAmount, $minAlert, $currentStatus)
{
    $diff       = $newAmount - (float) $minAlert;
    $nextStatus = sls_next_status($diff, $currentStatus);
    $stmt = mysqli_prepare($conn,
        'UPDATE part SET part_amount = ?, part_diff_stock = ?, part_status_purchase = ? WHERE part_id = ?');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ddsi', $newAmount, $diff, $nextStatus, $partId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// ─ Delete ────────────────────────────────────────────────────────────────────

if ($action === 'delete_log') {

    $part = sls_find_part($connect, $partName);

    mysqli_begin_transaction($connect);

    if ($part) {
        $newPartAmount = (float) $part['part_amount'] - $oldAmount;
        if ($newPartAmount < 0) {
            $newPartAmount = 0;
        }
        $ok = sls_update_part($connect, (int) $part['part_id'], $newPartAmount,
                              $part['part_min_alert'], (string) $part['part_status_purchase']);
        if (!$ok) {
            mysqli_rollback($connect);
            stock_flash_set('ปรับ Stock Part ไม่สำเร็จ: ' . mysqli_error($connect), 'error');
            header('Location: stock_log_edit.php?id=' . $logId);
            exit;
        }
    }

    $stmtDel = mysqli_prepare($connect, 'DELETE FROM log_stock WHERE log_id = ?');
    if (!$stmtDel || !mysqli_stmt_execute($stmtDel)) {
        mysqli_rollback($connect);
        stock_flash_set('ลบ log ไม่สำเร็จ', 'error');
        header('Location: stock_log_edit.php?id=' . $logId);
        exit;
    }
    mysqli_stmt_close($stmtDel);

    mysqli_commit($connect);

    $msg = 'ลบรายการ "' . $partName . '" เรียบร้อย';
    if (!$part) {
        $msg .= ' (ไม่พบ Part ในระบบ — Stock ไม่ถูกปรับ)';
    }
    stock_flash_set($msg, 'success');
    header('Location: stock_add_history.php');
    exit;
}

// ─ Edit ──────────────────────────────────────────────────────────────────────

$p = static function ($key, $default) {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
};

$newDate    = $p('log_date', $logRow['log_date'] ?? '');
$newVendor  = $p('log_vendor', '');
$newAmountRaw = isset($_POST['log_amount']) ? trim((string) $_POST['log_amount']) : '';
$newPrice   = $p('log_price', '');
$newRemarks = $p('log_remarks', '');

if ($newDate === '') {
    stock_flash_set('กรุณากรอกวันที่', 'error');
    header('Location: stock_log_edit.php?id=' . $logId);
    exit;
}

$newAmount = $newAmountRaw !== '' ? (float) filter_var($newAmountRaw, FILTER_VALIDATE_FLOAT) : $oldAmount;
if ($newAmount === false) {
    stock_flash_set('จำนวนไม่ถูกต้อง', 'error');
    header('Location: stock_log_edit.php?id=' . $logId);
    exit;
}
$newAmount = (float) $newAmount;

$amountDiff = $newAmount - $oldAmount;

mysqli_begin_transaction($connect);

// ปรับ part stock ถ้าจำนวนเปลี่ยน
if (abs($amountDiff) > 0.0001) {
    $part = sls_find_part($connect, $partName);
    if ($part) {
        $adjAmount = (float) $part['part_amount'] + $amountDiff;
        if ($adjAmount < 0) {
            $adjAmount = 0;
        }
        $ok = sls_update_part($connect, (int) $part['part_id'], $adjAmount,
                              $part['part_min_alert'], (string) $part['part_status_purchase']);
        if (!$ok) {
            mysqli_rollback($connect);
            stock_flash_set('ปรับ Stock Part ไม่สำเร็จ: ' . mysqli_error($connect), 'error');
            header('Location: stock_log_edit.php?id=' . $logId);
            exit;
        }
    }
}

$stmtUpd = mysqli_prepare($connect,
    'UPDATE log_stock SET log_date=?, log_vendor=?, log_amount=?, log_price=?, log_remarks=? WHERE log_id=?');
if (!$stmtUpd) {
    mysqli_rollback($connect);
    stock_flash_set('เตรียมคำสั่ง SQL ไม่สำเร็จ: ' . mysqli_error($connect), 'error');
    header('Location: stock_log_edit.php?id=' . $logId);
    exit;
}
mysqli_stmt_bind_param($stmtUpd, 'ssdssi',
    $newDate, $newVendor, $newAmount, $newPrice, $newRemarks, $logId);
if (!mysqli_stmt_execute($stmtUpd)) {
    mysqli_rollback($connect);
    stock_flash_set('อัปเดตไม่สำเร็จ: ' . mysqli_stmt_error($stmtUpd), 'error');
    mysqli_stmt_close($stmtUpd);
    header('Location: stock_log_edit.php?id=' . $logId);
    exit;
}
mysqli_stmt_close($stmtUpd);

mysqli_commit($connect);
stock_flash_set('อัปเดตรายการ "' . $partName . '" เรียบร้อยแล้ว', 'success');
header('Location: stock_add_history.php');
exit;
