<?php
/**
 * โหลดรายการ part สำหรับเบิกเป็นเซต / เบิกเซตซ่อม บนหน้า pickup_ma.php
 *
 * โหมด production (ค่าเริ่มต้น): production_formula ตาม Product
 * โหมด repair: part.part_product LIKE ชื่อ Product — ชุดซ่อมเดิม (production_pre_pickup2)
 *
 * ตัวอย่าง: pickup_set_formula.php?pre_product=1&pre_amount=2&set_mode=repair
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/pickup_repair_part_product.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ─ Helpers ────────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อนแสดงใน HTML
 *
 * @param mixed $value ค่าที่ต้องแสดง
 * @return string
 */
function pickup_set_formula_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * คำนวณจำนวนเบิกต่อ part ตาม logic เดิม (part 57/58)
 *
 * @param int $partId id part
 * @param float $baseAmount จำนวนชุด
 * @return float
 */
function pickup_set_formula_part_amount($partId, $baseAmount)
{
    if ((int) $partId === 57) {
        return $baseAmount * 2;
    }
    if ((int) $partId === 58) {
        return $baseAmount / 2;
    }

    return $baseAmount;
}

/**
 * อ่านชื่อ Product จาก pd_id
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @return string ชื่อ Product หรือค่าว่างถ้าไม่พบ
 */
function pickup_set_formula_product_name($conn, $productId)
{
    $stmt = mysqli_prepare($conn, 'SELECT `pd_name` FROM `product` WHERE `pd_id` = ? LIMIT 1');
    if ($stmt === false) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $pdName);
    $name = '';
    if (mysqli_stmt_fetch($stmt)) {
        $name = trim((string) $pdName);
    }
    mysqli_stmt_close($stmt);

    return $name;
}

/**
 * ดึงแถว part จากสูตรผลิต
 *
 * @param mysqli $conn connection
 * @param int $productId pd_id
 * @return array<int, array{part_id:int, part_name:string, part_min_alert:mixed, part_amount:mixed}>
 */
function pickup_set_formula_fetch_production_rows($conn, $productId)
{
    $sql = 'SELECT part.`part_id`, part.`part_name`, part.`part_min_alert`, part.`part_amount`
            FROM `part`
            INNER JOIN `production_formula` ON part.`part_id` = production_formula.`part_id`
            WHERE production_formula.`pd_id` = ?
            ORDER BY part.`part_name` ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        return array();
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_bind_result($stmt, $partIdRaw, $partNameRaw, $minRaw, $stockRaw);
    mysqli_stmt_execute($stmt);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $rows[] = array(
            'part_id' => (int) $partIdRaw,
            'part_name' => (string) $partNameRaw,
            'part_min_alert' => $minRaw,
            'part_amount' => $stockRaw,
        );
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * ดึงแถว part ชุดซ่อมจาก part_product (LIKE ชื่อ Product)
 *
 * @param mysqli $conn connection
 * @param int $productId pd_id
 * @return array<int, array{part_id:int, part_name:string, part_min_alert:mixed, part_amount:mixed}>
 */
function pickup_set_formula_fetch_repair_rows($conn, $productId)
{
    $pdName = pickup_set_formula_product_name($conn, $productId);
    if ($pdName === '') {
        return array();
    }

    $scope = pickup_repair_part_scope_bind($conn, $productId, $pdName);
    $sql = 'SELECT DISTINCT part.`part_id`, part.`part_name`, part.`part_min_alert`, part.`part_amount`
            FROM `part`
            INNER JOIN `repair_formula` rf ON rf.`part_id` = part.`part_id`
            WHERE ' . $scope['where'] . '
            ORDER BY part.`part_name` ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        return array();
    }
    pickup_repair_stmt_bind_params($stmt, $scope['types'], $scope['params']);
    mysqli_stmt_bind_result($stmt, $partIdRaw, $partNameRaw, $minRaw, $stockRaw);
    mysqli_stmt_execute($stmt);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $rows[] = array(
            'part_id' => (int) $partIdRaw,
            'part_name' => (string) $partNameRaw,
            'part_min_alert' => $minRaw,
            'part_amount' => $stockRaw,
        );
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * render แถว tbody จากรายการ part
 *
 * @param array<int, array<string, mixed>> $rows รายการ part
 * @param float $baseAmount จำนวนชุด
 * @return bool true เมื่อมีอย่างน้อยหนึ่งแถว
 */
function pickup_set_formula_render_rows($rows, $baseAmount)
{
    $num = 1;
    $hasRow = false;
    foreach ($rows as $row) {
        $hasRow = true;
        $partId = (int) $row['part_id'];
        $pickQty = pickup_set_formula_part_amount($partId, (float) $baseAmount);
        $stockQty = $row['part_amount'];
        $stockOk = checkStock($pickQty, $stockQty) === 'ok';
        $statusClass = $stockOk ? 'stock-ok' : 'stock-bad';
        $statusText = $stockOk ? 'OK' : 'Stock ไม่พอ';
        ?>
<tr data-part-id="<?php echo (int) $partId; ?>" data-pick-qty="<?php echo pickup_set_formula_h($pickQty); ?>">
    <td class="inv-col-num"><?php echo (int) $num; ?></td>
    <td><?php echo pickup_set_formula_h($row['part_name']); ?></td>
    <td class="inv-col-num"><?php echo pickup_set_formula_h($row['part_min_alert']); ?></td>
    <td class="inv-col-num"><?php echo pickup_set_formula_h($stockQty); ?></td>
    <td class="inv-col-num"><?php echo pickup_set_formula_h($pickQty); ?></td>
    <td class="<?php echo pickup_set_formula_h($statusClass); ?>"><?php echo pickup_set_formula_h($statusText); ?></td>
</tr>
        <?php
        $num++;
    }

    return $hasRow;
}

// ─ Main ─────────────────────────────────────────────────────────────────────

$productId = filter_var(isset($_GET['pre_product']) ? $_GET['pre_product'] : 0, FILTER_VALIDATE_INT);
$baseAmount = filter_var(isset($_GET['pre_amount']) ? $_GET['pre_amount'] : 0, FILTER_VALIDATE_FLOAT);
$setMode = isset($_GET['set_mode']) ? trim((string) $_GET['set_mode']) : 'production';
$isRepair = ($setMode === 'repair');

if ($productId === false || $productId <= 0 || $baseAmount === false || $baseAmount <= 0) {
    echo '<tr><td colspan="6" class="pickup-set-empty">เลือก Product และจำนวนชุดเพื่อโหลดสูตร</td></tr>';
    db_close($connect);
    exit;
}

if ($isRepair) {
    $rows = pickup_set_formula_fetch_repair_rows($connect, $productId);
} else {
    $rows = pickup_set_formula_fetch_production_rows($connect, $productId);
}

$hasRow = pickup_set_formula_render_rows($rows, (float) $baseAmount);
db_close($connect);

if (!$hasRow) {
    if ($isRepair) {
        echo '<tr><td colspan="6" class="pickup-set-empty">ไม่พบรายการเซตซ่อม (part_product) ของ Product นี้</td></tr>';
    } else {
        echo '<tr><td colspan="6" class="pickup-set-empty">ไม่พบสูตร part ของ Product นี้</td></tr>';
    }
}
