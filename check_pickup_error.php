<?php
/**
 * ตรวจสอบสาเหตุ error 500 ของ pickup_overview.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

echo "=== Check pickup_overview.php error ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "mysqli extension: " . (extension_loaded('mysqli') ? 'OK' : 'MISSING') . "\n\n";

// 1. ตรวจสอบว่า connect.php มีฟังก์ชัน db_exec หรือไม่
echo "--- Check connect.php ---\n";
if (!file_exists(__DIR__ . '/connect.php')) {
    die("ERROR: connect.php NOT FOUND\n");
}

require_once __DIR__ . '/connect.php';

if (function_exists('db_exec')) {
    echo "✓ db_exec() function exists\n";
} else {
    echo "✗ db_exec() function MISSING\n";
    die("ERROR: connect.php ยังไม่มี db_exec() - ต้องอัปโหลด connect.php ใหม่\n");
}

// 2. ทดสอบ query
echo "\n--- Test database query ---\n";
try {
    if (!isset($connect) || !$connect) {
        die("ERROR: Database connection failed\n");
    }
    
    echo "✓ Database connected\n";
    
    $sql = "SELECT COUNT(*) as cnt FROM pre_stock LIMIT 1";
    $result = db_exec($connect, $sql);
    
    if ($result === false) {
        echo "✗ Query failed: " . mysqli_error($connect) . "\n";
    } else {
        echo "✓ Query succeeded\n";
        $row = db_fetch_array($result);
        echo "  pre_stock records: " . ($row ? $row['cnt'] : '0') . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// 3. ตรวจสอบ pickup_overview.php
echo "\n--- Check pickup_overview.php ---\n";
$pickup_file = __DIR__ . '/pickup_overview.php';
if (!file_exists($pickup_file)) {
    die("ERROR: pickup_overview.php NOT FOUND\n");
}

$size = filesize($pickup_file);
$perms = substr(sprintf('%o', fileperms($pickup_file)), -4);
echo "File size: $size bytes\n";
echo "Permissions: $perms\n";

// ตรวจสอบ 100 ไบต์แรก
$handle = fopen($pickup_file, 'r');
$first100 = fread($handle, 100);
fclose($handle);

// เช็ค BOM
$bom = substr($first100, 0, 3);
if ($bom === "\xEF\xBB\xBF") {
    echo "⚠ WARNING: File has UTF-8 BOM (may cause issues)\n";
}

// เช็คว่าขึ้นต้นด้วย <?php หรือไม่
if (strpos($first100, '<?php') === 0) {
    echo "✓ File starts with <?php\n";
} else {
    echo "✗ File does NOT start with <?php\n";
    echo "First 50 chars: " . substr($first100, 0, 50) . "\n";
}

// เช็คว่ามี require_once หรือไม่
$content = file_get_contents($pickup_file);
if (stripos($content, 'require_once') !== false) {
    echo "✓ File uses require_once\n";
} else {
    echo "✗ File does NOT use require_once\n";
}

if (stripos($content, 'odbc_exec') !== false) {
    echo "✗ WARNING: File still contains odbc_exec (old version)\n";
} else {
    echo "✓ File does NOT contain odbc_exec\n";
}

if (stripos($content, 'db_exec') !== false) {
    echo "✓ File uses db_exec\n";
} else {
    echo "⚠ File does NOT use db_exec\n";
}

// 4. ลอง include pickup_overview.php
echo "\n--- Try to execute pickup_overview.php ---\n";
ob_start();
try {
    include $pickup_file;
    $output = ob_get_clean();
    echo "✓ File executed successfully\n";
    echo "Output length: " . strlen($output) . " bytes\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "✗ ERROR executing file:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== End check ===\n";
