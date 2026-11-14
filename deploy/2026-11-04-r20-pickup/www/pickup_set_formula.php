<?php
/**
 * โหลดรายการ part จากสูตรผลิต (เบิกเป็นเซต) สำหรับหน้า pickup รวม
 *
 * รับ pre_product และ pre_amount แล้วคืน HTML แถวตาราง (tbody fragment)
 * ใช้ prepared statement และ escape ผลลัพธ์ก่อน render
 *
 * ตัวอย่าง: pickup_set_formula.php?pre_product=1&pre_amount=2
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

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

$productId = filter_var(isset($_GET['pre_product']) ? $_GET['pre_product'] : 0, FILTER_VALIDATE_INT);
$baseAmount = filter_var(isset($_GET['pre_amount']) ? $_GET['pre_amount'] : 0, FILTER_VALIDATE_FLOAT);

if ($productId === false || $productId <= 0 || $baseAmount === false || $baseAmount <= 0) {
    echo '<tr><td colspan="6" class="pickup-set-empty">เลือก Product และจำนวนชุดเพื่อโหลดสูตร</td></tr>';
    db_close($connect);
    exit;
}

$sql = 'SELECT part.`part_id`, part.`part_name`, part.`part_min_alert`, part.`part_amount`
        FROM part
        INNER JOIN production_formula ON part.`part_id` = production_formula.`part_id`
        WHERE production_formula.`pd_id` = ?
        ORDER BY part.`part_name` ASC';

$stmt = mysqli_prepare($connect, $sql);
if ($stmt === false) {
    echo '<tr><td colspan="6" class="pickup-set-empty">ไม่สามารถโหลดสูตรได้</td></tr>';
    db_close($connect);
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_bind_result($stmt, $partIdRaw, $partNameRaw, $partMinAlertRaw, $partAmountRaw);
mysqli_stmt_execute($stmt);

$num = 1;
$hasRow = false;

while (mysqli_stmt_fetch($stmt)) {
    $hasRow = true;
    $partId = (int) $partIdRaw;
    $pickQty = pickup_set_formula_part_amount($partId, (float) $baseAmount);
    $stockQty = $partAmountRaw;
    $stockOk = checkStock($pickQty, $stockQty) === 'ok';
    $statusClass = $stockOk ? 'stock-ok' : 'stock-bad';
    $statusText = $stockOk ? 'OK' : 'Stock ไม่พอ';
    ?>
<tr>
    <td class="inv-col-num"><?php echo (int) $num; ?></td>
    <td><?php echo pickup_set_formula_h($partNameRaw); ?></td>
    <td class="inv-col-num"><?php echo pickup_set_formula_h($partMinAlertRaw); ?></td>
    <td class="inv-col-num"><?php echo pickup_set_formula_h($stockQty); ?></td>
    <td class="inv-col-num"><?php echo pickup_set_formula_h($pickQty); ?></td>
    <td class="<?php echo pickup_set_formula_h($statusClass); ?>"><?php echo pickup_set_formula_h($statusText); ?></td>
</tr>
    <?php
    $num++;
}

mysqli_stmt_close($stmt);
db_close($connect);

if (!$hasRow) {
    echo '<tr><td colspan="6" class="pickup-set-empty">ไม่พบสูตร part ของ Product นี้</td></tr>';
}
