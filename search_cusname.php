<?php
/**
 * ค้นหาชื่อ vendor แบบ autocomplete
 *
 * รับพารามิเตอร์ term จาก AJAX แล้วคืนรายการชื่อ vendor ที่ตรงกัน
 * ใช้ร่วมกับ stock_add.php และฟอร์มที่มีช่องค้นหาร้านค้า
 */

header('Content-Type: text/html; charset=UTF-8');

require 'connect.php';

if (!isset($_REQUEST['term']) || $_REQUEST['term'] === '') {
    exit;
}

$term = db_escape($connect, $_REQUEST['term']);
$sql = "SELECT vname FROM tbl_vendor WHERE vname LIKE '%" . $term . "%' LIMIT 10";
$result = db_exec($connect, $sql);

$i = 0;
while ($row = db_fetch_array($result)) {
    if ($i <= 3) {
        echo '<p>' . htmlspecialchars($row['vname'], ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $i++;
}

db_close($connect);
