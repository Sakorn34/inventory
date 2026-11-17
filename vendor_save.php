<?php
/**
 * บันทึก Vendor — รองรับทั้ง create และ update
 *
 * POST params:
 *   action      = 'create' | 'update'
 *   vendor_id   = int (เฉพาะ update)
 *   csrf_token  = string
 *   vname, type, contactname, add1-3, zipcode, Location,
 *   tel1, tel2, fax1, fax2, email,
 *   bank, accname, accid, branch, taxid, vdetail
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/stock_flash.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ─ ตรวจ method ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vendor_manage.php');
    exit;
}

// ─ CSRF ────────────────────────────────────────────────────────────────────

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken   = isset($_SESSION['vendor_csrf_token']) ? (string) $_SESSION['vendor_csrf_token'] : '';

if (!hash_equals($sessionToken, $submittedToken) || $sessionToken === '') {
    stock_flash_set('คำขอไม่ถูกต้อง (CSRF) กรุณาลองใหม่อีกครั้ง', 'error');
    header('Location: vendor_manage.php');
    exit;
}

// ─ ตรวจ action ─────────────────────────────────────────────────────────────

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
if (!in_array($action, array('create', 'update'), true)) {
    stock_flash_set('action ไม่ถูกต้อง', 'error');
    header('Location: vendor_manage.php');
    exit;
}

// ─ ตรวจ PK column ──────────────────────────────────────────────────────────

function vendor_save_pk_column($conn)
{
    static $col = null;
    if ($col !== null) {
        return $col;
    }
    $r = mysqli_query($conn, 'SELECT `vid` FROM `tbl_vendor` LIMIT 1');
    $col = ($r !== false) ? 'vid' : 'vno';
    return $col;
}

$pkCol = vendor_save_pk_column($connect);

// ─ รับค่า POST ──────────────────────────────────────────────────────────────

$p = static function ($key, $default = '') {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
};

$vname       = $p('vname');
$type        = $p('type');
$contactname = $p('contactname');
$add1        = $p('add1');
$add2        = $p('add2');
$add3        = $p('add3');
$zipcode     = $p('zipcode');
$location    = $p('Location');
$tel1        = $p('tel1');
$tel2        = $p('tel2');
$fax1        = $p('fax1');
$fax2        = $p('fax2');
$email       = $p('email');
$bank        = $p('bank');
$accname     = $p('accname');
$accid       = $p('accid');
$branch      = $p('branch');
$taxid       = $p('taxid');
$vdetail     = $p('vdetail');

// ─ Validate ─────────────────────────────────────────────────────────────────

if ($vname === '') {
    stock_flash_set('กรุณากรอกชื่อ Vendor', 'error');
    $back = $action === 'update'
        ? 'vendor_edit.php?id=' . (int) ($_POST['vendor_id'] ?? 0)
        : 'vendor_add.php';
    header('Location: ' . $back);
    exit;
}

// ─ Create ───────────────────────────────────────────────────────────────────

if ($action === 'create') {

    if ($pkCol === 'vid') {
        // AUTO_INCREMENT — ไม่ต้องระบุ PK
        $sql = 'INSERT INTO `tbl_vendor`
                  (`vname`,`type`,`contactname`,`add1`,`add2`,`add3`,`zipcode`,`Location`,
                   `tel1`,`tel2`,`fax1`,`fax2`,`email`,
                   `bank`,`accname`,`accid`,`branch`,`taxid`,`vdetail`)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = mysqli_prepare($connect, $sql);
        if (!$stmt) {
            stock_flash_set('เกิดข้อผิดพลาด: ' . mysqli_error($connect), 'error');
            header('Location: vendor_add.php');
            exit;
        }
        mysqli_stmt_bind_param(
            $stmt, 'sssssssssssssssssss',
            $vname, $type, $contactname, $add1, $add2, $add3, $zipcode, $location,
            $tel1, $tel2, $fax1, $fax2, $email,
            $bank, $accname, $accid, $branch, $taxid, $vdetail
        );
    } else {
        // vno — ไม่มี AUTO_INCREMENT ใช้ MAX+1
        $maxRow = mysqli_query($connect, 'SELECT COALESCE(MAX(`vno`), 0) + 1 FROM `tbl_vendor`');
        $newId  = (int) mysqli_fetch_row($maxRow)[0];

        $sql = 'INSERT INTO `tbl_vendor`
                  (`vno`,`vname`,`type`,`contactname`,`add1`,`add2`,`add3`,`zipcode`,`Location`,
                   `tel1`,`tel2`,`fax1`,`fax2`,`email`,
                   `bank`,`accname`,`accid`,`branch`,`taxid`,`vdetail`)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = mysqli_prepare($connect, $sql);
        if (!$stmt) {
            stock_flash_set('เกิดข้อผิดพลาด: ' . mysqli_error($connect), 'error');
            header('Location: vendor_add.php');
            exit;
        }
        mysqli_stmt_bind_param(
            $stmt, 'isssssssssssssssssss',
            $newId,
            $vname, $type, $contactname, $add1, $add2, $add3, $zipcode, $location,
            $tel1, $tel2, $fax1, $fax2, $email,
            $bank, $accname, $accid, $branch, $taxid, $vdetail
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        stock_flash_set('บันทึกไม่สำเร็จ: ' . mysqli_stmt_error($stmt), 'error');
        mysqli_stmt_close($stmt);
        header('Location: vendor_add.php');
        exit;
    }
    mysqli_stmt_close($stmt);
    stock_flash_set('เพิ่ม Vendor "' . $vname . '" สำเร็จ', 'success');
    header('Location: vendor_manage.php');
    exit;
}

// ─ Update ────────────────────────────────────────────────────────────────────

$vendorId = (int) ($_POST['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    stock_flash_set('ไม่พบ ID Vendor', 'error');
    header('Location: vendor_manage.php');
    exit;
}

$sql = 'UPDATE `tbl_vendor` SET
          `vname`=?, `type`=?, `contactname`=?,
          `add1`=?, `add2`=?, `add3`=?, `zipcode`=?, `Location`=?,
          `tel1`=?, `tel2`=?, `fax1`=?, `fax2`=?, `email`=?,
          `bank`=?, `accname`=?, `accid`=?, `branch`=?, `taxid`=?, `vdetail`=?
        WHERE `' . $pkCol . '` = ?';

$stmt = mysqli_prepare($connect, $sql);
if (!$stmt) {
    stock_flash_set('เกิดข้อผิดพลาด: ' . mysqli_error($connect), 'error');
    header('Location: vendor_edit.php?id=' . $vendorId);
    exit;
}

mysqli_stmt_bind_param(
    $stmt, 'ssssssssssssssssssi',
    $vname, $type, $contactname, $add1, $add2, $add3, $zipcode, $location,
    $tel1, $tel2, $fax1, $fax2, $email,
    $bank, $accname, $accid, $branch, $taxid, $vdetail,
    $vendorId
);

if (!mysqli_stmt_execute($stmt)) {
    stock_flash_set('อัปเดตไม่สำเร็จ: ' . mysqli_stmt_error($stmt), 'error');
    mysqli_stmt_close($stmt);
    header('Location: vendor_edit.php?id=' . $vendorId);
    exit;
}
mysqli_stmt_close($stmt);
stock_flash_set('อัปเดต Vendor "' . $vname . '" สำเร็จ', 'success');
header('Location: vendor_manage.php');
exit;
