<?php
/**
 * ทดสอบการเชื่อมต่อ MariaDB — ลบไฟล์นี้หลังแก้ปัญหาเสร็จ
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

echo "PHP " . PHP_VERSION . "\n";
echo "mysqli: " . (extension_loaded('mysqli') ? 'OK' : 'MISSING') . "\n\n";

require 'connect.php';

echo "Database connected: " . $DB_NAME . "\n";

$r = mysqli_query($connect, 'SHOW TABLES');
if ($r) {
    echo "Tables:\n";
    while ($row = mysqli_fetch_array($r)) {
        echo '  - ' . $row[0] . "\n";
    }
} else {
    echo 'SHOW TABLES failed: ' . mysqli_error($connect) . "\n";
}

db_close($connect);
