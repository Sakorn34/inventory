<?php
/**
 * ค้นหาชื่อ vendor แบบ autocomplete (AJAX)
 *
 * รับพารามิเตอร์ term จาก GET/POST แล้วคืน HTML รายการชื่อจาก tbl_vendor
 * ใช้กับ stock_add.php, part_add.php และฟอร์มที่มี search-box-user
 *
 * ตัวอย่าง: GET search_cusname.php?term=shop → รายการ <p>ชื่อร้าน</p>
 */

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ────────────────────────────────────────────────────────────────────────────────
// bootstrap
// ────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/connect.php';

$term = isset($_REQUEST['term']) ? trim((string) $_REQUEST['term']) : '';
if ($term === '' || !isset($connect) || !($connect instanceof mysqli)) {
    if (isset($connect)) {
        db_close($connect);
    }
    exit;
}

// ────────────────────────────────────────────────────────────────────────────────
// query
// ────────────────────────────────────────────────────────────────────────────────

$likeTerm = '%' . $term . '%';
$stmt = mysqli_prepare(
    $connect,
    'SELECT `vname` FROM `tbl_vendor` WHERE `vname` LIKE ? ORDER BY `vname` ASC LIMIT 12'
);
if ($stmt === false) {
    db_close($connect);
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $likeTerm);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $vname);

while (mysqli_stmt_fetch($stmt)) {
    echo '<p>' . htmlspecialchars((string) $vname, ENT_QUOTES, 'UTF-8') . '</p>';
}

mysqli_stmt_close($stmt);
db_close($connect);