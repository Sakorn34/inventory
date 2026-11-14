<?php
/**
 * ทดสอบลบชุดผลิต/ซ่อม (CLI) — ใช้ transaction แล้ว ROLLBACK ไม่ทิ้งข้อมูลจริง
 *
 * ตัวอย่าง: php tools/test_formula_manage_delete_set.php
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "รันจาก CLI เท่านั้น\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/connect.php';
require_once $projectRoot . '/pickup_repair_part_product.php';

function test_count_production_formula($conn, $pdId)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM `production_formula` WHERE `pd_id` = ?');
    mysqli_stmt_bind_param($stmt, 'i', $pdId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $c);
    $n = 0;
    if (mysqli_stmt_fetch($stmt)) {
        $n = (int) $c;
    }
    mysqli_stmt_close($stmt);

    return $n;
}

function test_pick_product_with_parts($conn)
{
    $sql = 'SELECT pf.`pd_id`, p.`pd_name`, MIN(pf.`part_id`) AS `part_id`
            FROM `production_formula` pf
            INNER JOIN `product` p ON p.`pd_id` = pf.`pd_id`
            GROUP BY pf.`pd_id`, p.`pd_name`
            HAVING COUNT(pf.`part_id`) >= 2
            ORDER BY COUNT(pf.`part_id`) DESC
            LIMIT 1';
    $result = mysqli_query($conn, $sql);
    if ($result === false || mysqli_num_rows($result) === 0) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return array(
        'pd_id' => (int) $row['pd_id'],
        'pd_name' => (string) $row['pd_name'],
        'part_id' => (int) $row['part_id'],
    );
}

$sample = test_pick_product_with_parts($connect);
if ($sample === null) {
    echo "SKIP: ไม่มี production_formula สำหรับทดสอบ\n";
    exit(0);
}

$pdId = $sample['pd_id'];
$partId = $sample['part_id'];

mysqli_begin_transaction($connect);

$beforeProd = test_count_production_formula($connect, $pdId);
$beforeRepair = pickup_repair_part_scope_count($connect, $pdId, $sample['pd_name']);

echo "Product pd_id={$pdId} ({$sample['pd_name']}) [transaction — จะ ROLLBACK]\n";
echo "ก่อน: production_formula={$beforeProd}, repair_formula={$beforeRepair}\n";

pickup_repair_formula_replace($connect, $pdId, $sample['pd_name'], array($partId));
$seedRepair = pickup_repair_part_scope_count($connect, $pdId, $sample['pd_name']);

$delProd = mysqli_prepare($connect, 'DELETE FROM `production_formula` WHERE `pd_id` = ?');
mysqli_stmt_bind_param($delProd, 'i', $pdId);
mysqli_stmt_execute($delProd);
$deletedProd = mysqli_stmt_affected_rows($delProd);
mysqli_stmt_close($delProd);

$afterProd = test_count_production_formula($connect, $pdId);
$afterRepair = pickup_repair_part_scope_count($connect, $pdId, $sample['pd_name']);

$delRepair = pickup_repair_formula_delete_for_product($connect, $pdId);
$afterRepairDel = pickup_repair_part_scope_count($connect, $pdId, $sample['pd_name']);

mysqli_rollback($connect);

$okProd = ($afterProd === 0 && $deletedProd > 0);
$okRepair = ($afterRepair === $seedRepair && $seedRepair >= 1);
$okRepairDel = ($afterRepairDel === 0 && $delRepair >= 1);

echo "ลบผลิต: deleted={$deletedProd}, prod หลัง={$afterProd}, repair หลัง={$afterRepair}\n";
echo "ลบซ่อม: deleted={$delRepair}, repair หลัง={$afterRepairDel}\n";
echo "ROLLBACK แล้ว — ข้อมูล DB ไม่เปลี่ยน\n";

if ($okProd && $okRepair && $okRepairDel) {
    echo "PASS\n";
    exit(0);
}

echo "FAIL\n";
exit(1);
