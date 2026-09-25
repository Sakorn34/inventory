<?php
/**
 * ไล่ debug stock_overview.php — ลบหลังแก้ปัญหาเสร็จ
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

echo "=== stock_debug ===\n";
echo 'PHP: ' . PHP_VERSION . "\n\n";

$steps = array();

try {
    $steps[] = '1. require connect.php';
    require __DIR__ . '/connect.php';
    $steps[] = '   OK — connected to ' . $DB_NAME;

    $steps[] = '2. include function.php';
    include __DIR__ . '/function.php';
    $steps[] = '   OK';

    $steps[] = '3. categories_product';
    $sqlSP = 'SELECT * FROM categories_product';
    $exeSP = db_exec($connect, $sqlSP);
    $n = 0;
    while (db_fetch_array($exeSP)) {
        $n++;
    }
    $steps[] = '   OK — ' . $n . ' rows';

    $steps[] = '4. part + categories JOIN';
    $sql = 'SELECT * FROM part INNER JOIN categories_product ON part.`part_categories` = categories_product.`ct_id` ORDER BY part.`part_categories`, part.`part_name` ASC LIMIT 3';
    $exec = db_exec($connect, $sql);
    $n = 0;
    while ($row = db_fetch_array($exec)) {
        $n++;
        $steps[] = '   sample: ' . $row['part_id'] . ' ' . $row['part_name'];
    }
    $steps[] = '   OK — fetched ' . $n . ' rows';

    $steps[] = '5. setlocale';
    $loc = @setlocale(LC_TIME, 'tha', 'th_TH.UTF-8', 'th_TH');
    $steps[] = '   ' . ($loc ? "OK ($loc)" : 'skip (locale not installed)');

    $steps[] = '6. title-h.php';
    ob_start();
    require __DIR__ . '/title-h.php';
    $html = ob_get_clean();
    $steps[] = '   OK — ' . strlen($html) . ' bytes';

} catch (Throwable $e) {
    $steps[] = 'FAIL: ' . $e->getMessage();
    $steps[] = $e->getFile() . ':' . $e->getLine();
}

echo implode("\n", $steps) . "\n\n";
echo "=== ถ้าทุกขั้นตอน OK แต่ stock_overview ยัง 500 ===\n";
echo "อัปโหลด stock_overview.php เวอร์ชันล่าสุด (ต้องขึ้นต้นด้วย <?php ไม่ใช่ <?)\n";
