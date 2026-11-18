<?php
/**
 * ชุดซ่อม — อะไหล่คลังช่างที่ผูกกับ Product (repair_formula_tech)
 *
 * เก็บ tech_product_id ของแอป parts ต่อ pd_id พร้อมจำนวนเริ่มต้นต่อชุด (ค่าเริ่มต้น 1)
 * ใช้เมื่อเบิกเซตซ่อมบน pickup_ma.php โหลดแถวคลังช่างอัตโนมัติหลัง repair_formula
 *
 * ตัวอย่าง: pickup_repair_formula_tech_list($conn, 12);
 */

require_once __DIR__ . '/pickup_tech_parts.php';

// ─ Constants ─────────────────────────────────────────────────────────────────

if (!defined('PICKUP_REPAIR_TECH_MAX_LINES')) {
    define('PICKUP_REPAIR_TECH_MAX_LINES', 10);
}

if (!defined('PICKUP_REPAIR_TECH_DEFAULT_QTY')) {
    define('PICKUP_REPAIR_TECH_DEFAULT_QTY', 1.0);
}

// ─ Schema ────────────────────────────────────────────────────────────────────

/**
 * สร้างตาราง repair_formula_tech ถ้ายังไม่มี
 *
 * @param mysqli $conn connection ฐานข้อมูล inventory
 * @return bool true เมื่อพร้อมใช้งาน
 */
function pickup_repair_formula_tech_ensure_table($conn)
{
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS `repair_formula_tech` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pd_id` INT NOT NULL,
        `tech_product_id` INT UNSIGNED NOT NULL,
        `default_qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
        `sort_order` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_repair_tech_pd_product` (`pd_id`, `tech_product_id`),
        KEY `idx_repair_tech_pd_id` (`pd_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    return mysqli_query($conn, $sql) !== false;
}

/**
 * นับรายการคลังช่างในชุดซ่อมของ Product
 *
 * @param mysqli $conn connection
 * @param int    $productId pd_id
 * @return int
 */
function pickup_repair_formula_tech_count($conn, $productId)
{
    pickup_repair_formula_tech_ensure_table($conn);

    $productId = (int) $productId;
    if ($productId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS `cnt` FROM `repair_formula_tech` WHERE `pd_id` = ?'
    );
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $cntRaw);
    $cnt = 0;
    if (mysqli_stmt_fetch($stmt)) {
        $cnt = (int) $cntRaw;
    }
    mysqli_stmt_close($stmt);

    return $cnt;
}

// ─ Read ──────────────────────────────────────────────────────────────────────

/**
 * รายการอะไหล่คลังช่างในชุดซ่อมของ Product
 *
 * @param mysqli $conn connection
 * @param int    $productId pd_id
 * @return array<int, array{tech_product_id:int, default_qty:float, sort_order:int}>
 */
function pickup_repair_formula_tech_list($conn, $productId)
{
    pickup_repair_formula_tech_ensure_table($conn);

    $productId = (int) $productId;
    if ($productId <= 0) {
        return array();
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT `tech_product_id`, `default_qty`, `sort_order`
         FROM `repair_formula_tech`
         WHERE `pd_id` = ?
         ORDER BY `sort_order` ASC, `id` ASC'
    );
    if ($stmt === false) {
        return array();
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $techIdRaw, $qtyRaw, $sortRaw);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $techId = (int) $techIdRaw;
        if ($techId <= 0) {
            continue;
        }
        $qty = filter_var($qtyRaw, FILTER_VALIDATE_FLOAT);
        if ($qty === false || $qty <= 0) {
            $qty = PICKUP_REPAIR_TECH_DEFAULT_QTY;
        }
        $rows[] = array(
            'tech_product_id' => $techId,
            'default_qty' => (float) $qty,
            'sort_order' => (int) $sortRaw,
        );
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * คำนวณจำนวนเบิกต่อแถวเมื่อโหลดชุด (default_qty × จำนวนชุด)
 *
 * @param float $defaultQty จำนวนเริ่มต้นต่อ 1 ชุด
 * @param float $setAmount  จำนวนชุดที่เบิก
 * @return float
 */
function pickup_repair_formula_tech_pick_qty($defaultQty, $setAmount)
{
    $defaultQty = filter_var($defaultQty, FILTER_VALIDATE_FLOAT);
    $setAmount = filter_var($setAmount, FILTER_VALIDATE_FLOAT);
    if ($defaultQty === false || $defaultQty <= 0) {
        $defaultQty = PICKUP_REPAIR_TECH_DEFAULT_QTY;
    }
    if ($setAmount === false || $setAmount <= 0) {
        $setAmount = 1.0;
    }

    return (float) ($defaultQty * $setAmount);
}

/**
 * รายการสำหรับหน้าเบิก — รวม label จากคลังช่าง (ถ้าเชื่อมต่อได้)
 *
 * @param mysqli $conn connection
 * @param int    $productId pd_id
 * @param float  $setAmount จำนวนชุด
 * @return array<int, array<string, mixed>>
 */
function pickup_repair_formula_tech_lines_for_pickup($conn, $productId, $setAmount)
{
    $defs = pickup_repair_formula_tech_list($conn, $productId);
    if ($defs === array()) {
        return array();
    }

    $catalog = array();
    if (pickup_tech_parts_available()) {
        foreach (pickup_tech_parts_products_list() as $product) {
            $id = isset($product['id']) ? (int) $product['id'] : 0;
            if ($id > 0) {
                $catalog[$id] = $product;
            }
        }
    }

    $lines = array();
    foreach ($defs as $def) {
        $techId = (int) $def['tech_product_id'];
        $pickQty = pickup_repair_formula_tech_pick_qty($def['default_qty'], $setAmount);
        $label = 'อะไหล่คลังช่าง #' . $techId;
        $unit = '';
        $stock = null;
        if (isset($catalog[$techId])) {
            $item = $catalog[$techId];
            $name = isset($item['name']) ? trim((string) $item['name']) : '';
            $code = isset($item['code']) ? trim((string) $item['code']) : '';
            $label = $name !== '' ? $name : $code;
            $unit = isset($item['unit']) ? (string) $item['unit'] : '';
            $stock = isset($item['quantity']) ? $item['quantity'] : null;
        }
        $lines[] = array(
            'tech_product_id' => $techId,
            'label' => $label,
            'default_qty' => (float) $def['default_qty'],
            'qty' => $pickQty,
            'unit' => $unit,
            'stock' => $stock,
        );
    }

    return $lines;
}

// ─ Write ─────────────────────────────────────────────────────────────────────

/**
 * validate จำนวนเริ่มต้นต่อชุด
 *
 * @param mixed $value ค่าจากฟอร์ม
 * @return float
 */
function pickup_repair_formula_tech_normalize_qty($value)
{
    $qty = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($qty === false || $qty <= 0) {
        return PICKUP_REPAIR_TECH_DEFAULT_QTY;
    }

    return (float) $qty;
}

/**
 * สร้าง map id ที่มีอยู่ใน catalog คลังช่าง
 *
 * @return array<int, true>
 */
function pickup_repair_formula_tech_valid_product_map()
{
    $map = array();
    if (!pickup_tech_parts_available()) {
        return $map;
    }
    foreach (pickup_tech_parts_products_list() as $product) {
        $id = isset($product['id']) ? (int) $product['id'] : 0;
        if ($id > 0) {
            $map[$id] = true;
        }
    }

    return $map;
}

/**
 * แทนที่รายการคลังช่างในชุดซ่อมของ Product
 *
 * @param mysqli $conn connection
 * @param int    $productId pd_id
 * @param array<int, array{tech_product_id:int, default_qty:float}> $lines รายการที่ผ่าน validate
 * @return int จำนวนแถวที่บันทึก
 */
function pickup_repair_formula_tech_replace($conn, $productId, array $lines)
{
    pickup_repair_formula_tech_ensure_table($conn);

    $productId = (int) $productId;
    if ($productId <= 0) {
        throw new RuntimeException('ไม่พบ Product สำหรับบันทึกอะไหล่คลังช่าง');
    }

    if (count($lines) > PICKUP_REPAIR_TECH_MAX_LINES) {
        throw new RuntimeException(
            'อะไหล่คลังช่างในชุดซ่อมได้ไม่เกิน ' . PICKUP_REPAIR_TECH_MAX_LINES . ' รายการ'
        );
    }

    $validMap = pickup_repair_formula_tech_valid_product_map();
    $seen = array();
    $clean = array();
    foreach ($lines as $line) {
        $techId = isset($line['tech_product_id']) ? (int) $line['tech_product_id'] : 0;
        if ($techId <= 0 || isset($seen[$techId])) {
            continue;
        }
        if ($validMap !== array() && !isset($validMap[$techId])) {
            continue;
        }
        $seen[$techId] = true;
        $clean[] = array(
            'tech_product_id' => $techId,
            'default_qty' => pickup_repair_formula_tech_normalize_qty(
                isset($line['default_qty']) ? $line['default_qty'] : PICKUP_REPAIR_TECH_DEFAULT_QTY
            ),
        );
    }

    mysqli_autocommit($conn, false);

    try {
        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM `repair_formula_tech` WHERE `pd_id` = ?');
        if ($deleteStmt === false) {
            throw new RuntimeException('ลบรายการคลังช่างเดิมไม่สำเร็จ');
        }
        mysqli_stmt_bind_param($deleteStmt, 'i', $productId);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        $insertStmt = mysqli_prepare(
            $conn,
            'INSERT INTO `repair_formula_tech` (`pd_id`, `tech_product_id`, `default_qty`, `sort_order`)
             VALUES (?, ?, ?, ?)'
        );
        if ($insertStmt === false) {
            throw new RuntimeException('เตรียมบันทึกอะไหล่คลังช่างไม่สำเร็จ');
        }

        $saved = 0;
        $sort = 0;
        foreach ($clean as $row) {
            $sort++;
            $techId = (int) $row['tech_product_id'];
            $qty = (float) $row['default_qty'];
            mysqli_stmt_bind_param($insertStmt, 'iidi', $productId, $techId, $qty, $sort);
            if (!mysqli_stmt_execute($insertStmt)) {
                throw new RuntimeException('บันทึกอะไหล่คลังช่างไม่สำเร็จ');
            }
            $saved++;
        }
        mysqli_stmt_close($insertStmt);

        mysqli_commit($conn);
        mysqli_autocommit($conn, true);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        throw $e;
    }

    return $saved;
}

/**
 * อ่าน POST จาก formula_manage แล้วบันทึกชุดคลังช่าง
 *
 * @param mysqli $conn connection
 * @param int    $productId pd_id
 * @param array  $post คัดลอกจาก $_POST
 * @return int จำนวนรายการที่บันทึก
 */
function pickup_repair_formula_tech_save_from_post($conn, $productId, array $post)
{
    $ids = isset($post['tech_product_ids']) && is_array($post['tech_product_ids'])
        ? $post['tech_product_ids']
        : array();
    $qtyMap = isset($post['tech_default_qty']) && is_array($post['tech_default_qty'])
        ? $post['tech_default_qty']
        : array();

    $lines = array();
    foreach ($ids as $rawId) {
        $techId = filter_var($rawId, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($techId === false) {
            continue;
        }
        $key = (string) (int) $techId;
        $defaultQty = isset($qtyMap[$key]) ? $qtyMap[$key] : PICKUP_REPAIR_TECH_DEFAULT_QTY;
        $lines[] = array(
            'tech_product_id' => (int) $techId,
            'default_qty' => pickup_repair_formula_tech_normalize_qty($defaultQty),
        );
    }

    return pickup_repair_formula_tech_replace($conn, $productId, $lines);
}

/**
 * ลบอะไหล่คลังช่างทั้งหมดของ Product ในชุดซ่อม
 *
 * @param mysqli $conn connection
 * @param int    $productId pd_id
 * @return int จำนวนแถวที่ลบ
 */
function pickup_repair_formula_tech_delete_for_product($conn, $productId)
{
    pickup_repair_formula_tech_ensure_table($conn);

    $productId = (int) $productId;
    if ($productId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($conn, 'DELETE FROM `repair_formula_tech` WHERE `pd_id` = ?');
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $deleted >= 0 ? (int) $deleted : 0;
}