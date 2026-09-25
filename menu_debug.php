<?php
/**
 * ตรวจสอบสาเหตุ HTTP 500 ของเมนู inventory (ลบหลังแก้ปัญหาเสร็จ)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

echo "=== menu_debug ===\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'mysqli: ' . (extension_loaded('mysqli') ? 'OK' : 'MISSING') . "\n";
echo 'odbc: ' . (extension_loaded('odbc') ? 'OK' : 'MISSING') . "\n";
echo 'db_exec: ' . (function_exists('db_exec') ? 'OK' : 'MISSING') . "\n\n";

$files = array(
    'connect.php',
    'pickup_overview.php',
    'pickup_list_order.php',
    'stock_overview.php',
    'stock_add.php',
);

foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (!is_readable($path)) {
        echo "$f: NOT FOUND\n";
        continue;
    }
    $src = file_get_contents($path);
    $hasOdbc = (stripos($src, 'odbc_exec') !== false || stripos($src, 'odbc_connect') !== false);
    $hasDb = stripos($src, 'db_exec') !== false;
    $shortTag = (bool) preg_match('/<\?(?!php|=)/', $src);
    $usesRequireOnce = stripos($src, 'require_once __DIR__') !== false;
    echo "$f:\n";
    echo '  size=' . strlen($src) . ' md5=' . md5($src)
        . ' odbc=' . ($hasOdbc ? 'YES' : 'no')
        . ' db_exec=' . ($hasDb ? 'yes' : 'no')
        . ' require_once=' . ($usesRequireOnce ? 'yes' : 'no')
        . ' short_tag=<?=' . ($shortTag ? 'YES' : 'no') . "\n";
}

echo "\n--- ทดสอบ query แบบ pickup_list_order ---\n";
require __DIR__ . '/connect.php';
$sql = "SELECT pre_id, pre_by, pre_type, ts_mtn_number, pre_date
        FROM pre_stock WHERE pre_status = 'pre'
        GROUP BY pre_id, pre_by, pre_type, ts_mtn_number, pre_date
        LIMIT 3";
$r = db_exec($connect, $sql);
if ($r === false) {
    echo 'QUERY FAIL: ' . mysqli_error($connect) . "\n";
} else {
    $n = 0;
    while ($row = db_fetch_array($r)) {
        $n++;
        echo "  row $n: pre_id=" . $row['pre_id'] . "\n";
    }
    echo "OK — $n rows\n";
}

db_close($connect);
