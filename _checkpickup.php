<?php
/**
 * ตรวจสอบเลขที่งานซ่อม (ts_mtn_number) ว่ามีใน pre_stock หรือไม่
 *
 * รับ POST repair_no แล้วตอบ "ok" หากพบข้อมูล
 */

header('Content-Type: text/html; charset=UTF-8');

require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$searchTerm = isset($_POST['repair_no']) ? trim($_POST['repair_no']) : '';

if ($searchTerm === '') {
    exit;
}

$safeTerm = db_escape($connect, $searchTerm);
$sql = "SELECT * FROM pre_stock WHERE ts_mtn_number LIKE '%" . $safeTerm . "%' LIMIT 1";
$result = db_exec($connect, $sql);

if ($result && db_fetch_array($result)) {
    echo 'ok';
}

db_close($connect);

