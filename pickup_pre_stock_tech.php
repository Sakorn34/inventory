<?php
/**
 * รายการเบิกคลังช่างที่ผูกกับเอกสาร pre_stock (แสดงใน popup pickup_overview)
 *
 * ขอบเขต: สร้างตาราง pre_stock_tech เมื่อจำเป็น บันทึก/อ่านรายการ snapshot หลังตัดสต็อก Parts แล้ว
 * ไม่ใช้ใน flow ยืนยันจ่ายคลังกลาง — แสดงผลและตรวจสอบอย่างเดียว
 */

// ────────────────────────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────────────────────────

/**
 * อ่าน scalar จาก POST หรือ GET (POST มาก่อน)
 *
 * @param string $key ชื่อพารามิเตอร์
 * @param mixed  $default ค่าเริ่มต้น
 * @return mixed
 */
function pickup_inventory_request_value($key, $default = null)
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }

    return $default;
}

/**
 * อ่านพารามิเตอร์แบบอาร์เรย์จาก POST หรือ GET
 *
 * @param string $key ชื่อพารามิเตอร์
 * @return array<int, mixed>
 */
function pickup_inventory_request_array($key)
{
    $value = pickup_inventory_request_value($key, null);
    if (is_array($value)) {
        return $value;
    }
    if ($value !== null && $value !== '') {
        return array($value);
    }

    return array();
}

/**
 * ชื่ออะไหล่คลังช่างสำหรับแสดงผล — ตัดรหัส Pxxxxx ออกจากข้อความที่บันทึกไว้
 *
 * @param string $storedName ชื่อที่เก็บใน pre_stock_tech.part_name
 * @param int    $techProductId รหัส products.id (ใช้เติมชื่อเมื่อเก็บแค่รหัส)
 * @return string
 */
function pickup_pre_stock_tech_display_part_name($storedName, $techProductId = 0)
{
    $name = trim((string) $storedName);
    if ($name === '') {
        return pickup_pre_stock_tech_resolve_product_name($techProductId);
    }

    $patterns = array(
        '/^P\d{3,}\s*[—\-–:]+\s*(.+)$/iu',
        '/^P\d{3,}[—\-–](.+)$/iu',
        '/^P\d{3,}\s+(.+)$/iu',
    );
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $name, $matches) && isset($matches[1])) {
            $trimmed = trim((string) $matches[1]);

            return $trimmed !== '' ? $trimmed : $name;
        }
    }
    if (preg_match('/^P\d{3,}$/iu', $name)) {
        $resolved = pickup_pre_stock_tech_resolve_product_name($techProductId);

        return $resolved !== '' ? $resolved : $name;
    }

    return $name;
}

/**
 * เติมชื่อจากระบบ Parts เมื่อ snapshot ไม่มีชื่ออ่านง่าย
 *
 * @param int $techProductId รหัส products.id
 * @return string
 */
function pickup_pre_stock_tech_resolve_product_name($techProductId)
{
    $techProductId = filter_var($techProductId, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
    if ($techProductId === false) {
        return '';
    }
    if (!is_readable(__DIR__ . '/pickup_tech_parts.php')) {
        return '';
    }
    require_once __DIR__ . '/pickup_tech_parts.php';
    if (!function_exists('pickup_tech_parts_product_name_by_id')) {
        return '';
    }

    return pickup_tech_parts_product_name_by_id((int) $techProductId);
}

/**
 * สร้างตาราง pre_stock_tech ถ้ายังไม่มี
 *
 * @param mysqli $conn connection ฐานข้อมูล inventory
 * @return bool true เมื่อพร้อมใช้งาน
 */
function pickup_pre_stock_tech_ensure_table($conn)
{
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS `pre_stock_tech` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pre_id` VARCHAR(32) NOT NULL,
        `tech_product_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `part_name` VARCHAR(255) NOT NULL,
        `product_name` VARCHAR(255) NOT NULL DEFAULT \'\',
        `pre_amount` DECIMAL(12,2) NOT NULL,
        `line_sn` VARCHAR(200) NOT NULL DEFAULT \'\',
        `parts_doc_no` VARCHAR(64) NOT NULL DEFAULT \'\',
        PRIMARY KEY (`id`),
        KEY `idx_pre_stock_tech_pre_id` (`pre_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    return mysqli_query($conn, $sql) !== false;
}

/**
 * บันทึกรายการคลังช่างหลายแถวต่อหนึ่งเลขเอกสาร
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $preId เลขเอกสาร pre
 * @param array<int, array<string, mixed>> $lines รายการที่ผ่าน validate แล้ว
 * @return void
 */
function pickup_pre_stock_tech_insert_lines($conn, $preId, array $lines)
{
    if ($preId === '' || $lines === array()) {
        return;
    }
    if (!pickup_pre_stock_tech_ensure_table($conn)) {
        throw new RuntimeException('ไม่สามารถเตรียมตารางรายการคลังช่างได้');
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO `pre_stock_tech`
            (`pre_id`, `tech_product_id`, `part_name`, `product_name`, `pre_amount`, `line_sn`, `parts_doc_no`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt === false) {
        throw new RuntimeException('เตรียมบันทึกรายการคลังช่างไม่สำเร็จ');
    }

    foreach ($lines as $line) {
        $techId = (int) $line['tech_product_id'];
        $partName = (string) $line['part_name'];
        $productName = (string) $line['product_name'];
        $amount = number_format((float) $line['pre_amount'], 2, '.', '');
        $sn = (string) $line['line_sn'];
        $docNo = (string) $line['parts_doc_no'];

        mysqli_stmt_bind_param(
            $stmt,
            'sisssss',
            $preId,
            $techId,
            $partName,
            $productName,
            $amount,
            $sn,
            $docNo
        );
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('บันทึกรายการคลังช่างไม่สำเร็จ');
        }
    }

    mysqli_stmt_close($stmt);
}

/**
 * อ่านรายการคลังช่างของเอกสาร
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $preId เลขเอกสาร
 * @return array<int, array<string, mixed>>
 */
function pickup_pre_stock_tech_fetch_lines($conn, $preId)
{
    if (!($conn instanceof mysqli) || $preId === '') {
        return array();
    }
    if (!pickup_pre_stock_tech_ensure_table($conn)) {
        return array();
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT `tech_product_id`, `part_name`, `product_name`, `pre_amount`, `line_sn`, `parts_doc_no`
         FROM `pre_stock_tech` WHERE `pre_id` = ? ORDER BY `id` ASC'
    );
    if ($stmt === false) {
        return array();
    }

    mysqli_stmt_bind_param($stmt, 's', $preId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result(
        $stmt,
        $techProductId,
        $partName,
        $productName,
        $preAmount,
        $lineSn,
        $partsDocNo
    );

    $lines = array();
    while (mysqli_stmt_fetch($stmt)) {
        $displayName = pickup_pre_stock_tech_display_part_name((string) $partName, (int) $techProductId);
        $lines[] = array(
            'line_kind' => 'tech',
            'part_id' => 0,
            'part_name' => $displayName,
            'product' => (string) $productName,
            'amount' => (string) $preAmount,
            'remarks' => (string) $lineSn,
            'parts_doc_no' => (string) $partsDocNo,
            'tech_product_id' => (int) $techProductId,
        );
    }

    mysqli_stmt_close($stmt);

    return $lines;
}

/**
 * ลบ snapshot รายการคลังช่างของเอกสาร (หลังยกเลิกใน Parts แล้ว)
 *
 * @param mysqli $conn connection
 * @param string $preId เลขเอกสาร
 * @return void
 */
function pickup_pre_stock_tech_delete_by_pre_id($conn, $preId)
{
    $preId = trim((string) $preId);
    if ($preId === '' || !($conn instanceof mysqli)) {
        return;
    }
    if (!pickup_pre_stock_tech_ensure_table($conn)) {
        return;
    }
    $stmt = mysqli_prepare($conn, 'DELETE FROM `pre_stock_tech` WHERE `pre_id` = ?');
    if ($stmt === false) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 's', $preId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * รวบรวมและ validate รายการคลังช่างจาก request (GET)
 *
 * @return array<int, array<string, mixed>>
 */
function pickup_pre_stock_tech_collect_from_request()
{
    $productIds = pickup_inventory_request_array('tech_product_id');
    $names = pickup_inventory_request_array('tech_part_name');
    $amounts = pickup_inventory_request_array('tech_amount');
    $sns = pickup_inventory_request_array('tech_sn');
    $setProducts = pickup_inventory_request_array('tech_set_product');
    $partsDocNos = pickup_inventory_request_array('tech_parts_doc_no');
    $purposeType = pickup_inventory_request_value('tech_purpose', '');
    $purposeType = trim((string) $purposeType);
    if (function_exists('mb_substr')) {
        $purposeType = mb_substr($purposeType, 0, 100, 'UTF-8');
    } else {
        $purposeType = substr($purposeType, 0, 100);
    }

    $count = min(count($productIds), count($amounts));
    if ($count <= 0) {
        return array();
    }

    $lines = array();
    for ($i = 0; $i < $count; $i++) {
        $techId = filter_var($productIds[$i], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($techId === false) {
            continue;
        }
        $amount = filter_var($amounts[$i], FILTER_VALIDATE_FLOAT);
        if ($amount === false || $amount <= 0) {
            continue;
        }
        $partName = isset($names[$i]) ? trim((string) $names[$i]) : '';
        if ($partName === '') {
            $partName = 'อะไหล่คลังช่าง #' . (int) $techId;
        }
        $partName = pickup_pre_stock_tech_display_part_name($partName, (int) $techId);
        if ($partName === '') {
            $partName = 'อะไหล่คลังช่าง #' . (int) $techId;
        }
        if (function_exists('mb_substr')) {
            $partName = mb_substr($partName, 0, 255, 'UTF-8');
        } else {
            $partName = substr($partName, 0, 255);
        }

        $productName = $purposeType !== '' ? $purposeType : '';
        if ($productName === '') {
            $productName = isset($setProducts[$i]) ? trim((string) $setProducts[$i]) : '';
        }
        if ($productName === '') {
            $productName = 'คลังช่าง';
        }
        if (function_exists('mb_substr')) {
            $productName = mb_substr($productName, 0, 255, 'UTF-8');
        } else {
            $productName = substr($productName, 0, 255);
        }

        $sn = isset($sns[$i]) ? trim((string) $sns[$i]) : '';
        if (function_exists('mb_substr')) {
            $sn = mb_substr($sn, 0, 200, 'UTF-8');
        } else {
            $sn = substr($sn, 0, 200);
        }

        $partsDoc = isset($partsDocNos[$i]) ? trim((string) $partsDocNos[$i]) : '';
        if (function_exists('mb_substr')) {
            $partsDoc = mb_substr($partsDoc, 0, 64, 'UTF-8');
        } else {
            $partsDoc = substr($partsDoc, 0, 64);
        }

        $lines[] = array(
            'tech_product_id' => (int) $techId,
            'part_name' => $partName,
            'product_name' => $productName,
            'pre_amount' => (float) $amount,
            'line_sn' => $sn,
            'parts_doc_no' => $partsDoc,
        );
    }

    return $lines;
}