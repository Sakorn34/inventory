<?php
/**
 * หน้าจัดชุดรายการเบิก Part ตาม Product สำหรับระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือเลือก Product แล้วกำหนดรายการ Part สำหรับเบิกเป็นเซต
 * รองรับสองโหมด: เซตผลิต (`production_formula`) และเซตซ่อม (`repair_formula` + `repair_formula_tech`)
 * ให้สอดคล้องกับ pickup_set_formula / pre_pickup_save
 * โดยใช้ prepared statement, validate input และ CSRF token สำหรับการบันทึก
 *
 * Flow สั้น:
 * 1. เลือกโหมดเซตผลิตหรือเซตซ่อม แล้วเลือก Product
 * 2. ระบบโหลด Part ทั้งหมดและติ๊ก Part ที่อยู่ในชุดปัจจุบัน
 * 3. บันทึก — โหมดผลิต: แทนที่แถวใน production_formula; โหมดซ่อม: อัปเดต part_product
 *
 * ตัวอย่าง: formula_manage.php?pd_id=1&set_mode=repair
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/pickup_repair_part_product.php';
require_once __DIR__ . '/pickup_repair_formula_tech.php';
require_once __DIR__ . '/pickup_tech_parts.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render HTML เพื่อลดความเสี่ยง XSS
 *
 * @param mixed $value ข้อความหรือค่าที่ต้องแสดงผลบนหน้าเว็บ
 * @return string ข้อความที่ผ่าน htmlspecialchars แล้ว
 */
function formula_manage_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * เตรียมและ execute SQL ผ่าน mysqli prepared statement
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL ที่ใช้ placeholder เมื่อมี input
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind เข้า statement
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function formula_manage_execute($conn, $sql, $types = '', $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('เตรียม SQL ไม่สำเร็จ');
    }

    if ($types !== '') {
        $bindParams = array($stmt, $types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('bind SQL parameter ไม่สำเร็จ');
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('execute SQL ไม่สำเร็จ');
    }

    return $stmt;
}

/**
 * ดึงผลลัพธ์ทั้งหมดจาก prepared statement เป็น associative array
 *
 * @param mysqli_stmt $stmt statement ที่ execute แล้ว
 * @return array รายการแถวข้อมูล
 */
function formula_manage_fetch_all($stmt)
{
    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) {
        return array();
    }

    $fields = array();
    $row = array();
    $bind = array($stmt);
    while ($field = mysqli_fetch_field($meta)) {
        $fields[] = $field->name;
        $row[$field->name] = null;
        $bind[] = &$row[$field->name];
    }
    call_user_func_array('mysqli_stmt_bind_result', $bind);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $item = array();
        foreach ($fields as $fieldName) {
            $item[$fieldName] = $row[$fieldName];
        }
        $rows[] = $item;
    }

    mysqli_free_result($meta);

    return $rows;
}

/**
 * execute SQL แล้วคืนผลลัพธ์ทั้งหมดแบบ array
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind เข้า statement
 * @return array รายการแถวข้อมูล
 */
function formula_manage_query($conn, $sql, $types = '', $params = array())
{
    $stmt = formula_manage_execute($conn, $sql, $types, $params);
    $rows = formula_manage_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * สร้างหรืออ่าน CSRF token สำหรับฟอร์มบันทึก
 *
 * @return string token ปัจจุบันใน session
 */
function formula_manage_csrf_token()
{
    if (empty($_SESSION['formula_manage_csrf'])) {
        $_SESSION['formula_manage_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['formula_manage_csrf'];
}

/**
 * ตรวจ CSRF token จาก POST ก่อนแก้ไขข้อมูล
 *
 * @param string $token token ที่ส่งมาจากฟอร์ม
 * @return void ไม่มี return value และ throw exception เมื่อ token ไม่ถูกต้อง
 */
function formula_manage_validate_csrf($token)
{
    $sessionToken = isset($_SESSION['formula_manage_csrf']) ? $_SESSION['formula_manage_csrf'] : '';
    if (!is_string($token) || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

/**
 * แปลงค่า id จาก request ให้เป็น int ที่ปลอดภัย
 *
 * @param mixed $value ค่า id จาก GET/POST
 * @return int id ที่มากกว่า 0 หรือ 0 เมื่อไม่ถูกต้อง
 */
function formula_manage_positive_int($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * normalize โหมดชุดเบิกจาก request ให้เป็น production หรือ repair เท่านั้น
 *
 * @param mixed $value ค่าจาก GET/POST
 * @return string `production` หรือ `repair`
 */
function formula_manage_normalize_set_mode($value)
{
    $mode = is_string($value) ? trim($value) : '';

    return $mode === 'repair' ? 'repair' : 'production';
}

/**
 * ดึงรายการ Product ทั้งหมดสำหรับ dropdown
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ Product
 */
function formula_manage_get_products($conn)
{
    return formula_manage_query(
        $conn,
        'SELECT `pd_id`, `pd_name` FROM `product` ORDER BY `pd_name` ASC'
    );
}

/**
 * สรุปจำนวน Part ต่อ Product สำหรับโหมดเซตผลิต (production_formula)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ pd_id, pd_name, part_count
 */
function formula_manage_get_production_set_summaries($conn)
{
    return formula_manage_query(
        $conn,
        'SELECT
            f.`pd_id`,
            COALESCE(NULLIF(p.`pd_name`, \'\'), MAX(f.`pd_name`)) AS `pd_name`,
            COUNT(DISTINCT f.`part_id`) AS `part_count`
        FROM `production_formula` f
        LEFT JOIN `product` p ON p.`pd_id` = f.`pd_id`
        GROUP BY f.`pd_id`
        ORDER BY `pd_name` ASC'
    );
}

/**
 * สรุปจำนวน Part ต่อ Product สำหรับโหมดเซตซ่อม (part_product LIKE ชื่อ Product)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ pd_id, pd_name, part_count (รวม Product ที่ยังไม่มี Part ในชุด)
 */
function formula_manage_get_repair_set_summaries($conn)
{
    $products = formula_manage_get_products($conn);
    $summaries = array();

    foreach ($products as $product) {
        $pdId = (int) $product['pd_id'];
        $pdName = (string) $product['pd_name'];
        $partCount = pickup_repair_part_scope_count($conn, $pdId, $pdName);
        $techCount = pickup_repair_formula_tech_count($conn, $pdId);
        $totalCount = $partCount + $techCount;
        if ($totalCount <= 0) {
            continue;
        }
        $summaries[] = array(
            'pd_id' => $pdId,
            'pd_name' => $pdName,
            'part_count' => $totalCount,
        );
    }

    usort($summaries, function ($a, $b) {
        return strcasecmp((string) $a['pd_name'], (string) $b['pd_name']);
    });

    return $summaries;
}

/**
 * ดึงสรุปชุดเบิกตามโหมดที่เลือก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $setMode production หรือ repair
 * @return array รายการสรุปชุด
 */
function formula_manage_get_set_summaries($conn, $setMode)
{
    if ($setMode === 'repair') {
        return formula_manage_get_repair_set_summaries($conn);
    }

    $summaries = formula_manage_get_production_set_summaries($conn);

    usort($summaries, function ($a, $b) {
        return strcasecmp((string) $a['pd_name'], (string) $b['pd_name']);
    });

    return $summaries;
}

/**
 * ดึง Product ตาม id เพื่อยืนยันว่ามีอยู่จริงก่อนบันทึก formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array|null ข้อมูล Product หรือ null เมื่อไม่พบ
 */
function formula_manage_get_product($conn, $productId)
{
    $rows = formula_manage_query(
        $conn,
        'SELECT `pd_id`, `pd_name` FROM `product` WHERE `pd_id` = ? LIMIT 1',
        'i',
        array($productId)
    );

    return count($rows) > 0 ? $rows[0] : null;
}

/**
 * ดึงรายการ Part ทั้งหมดสำหรับ checkbox
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ Part พร้อมหมวดหมู่และจำนวน stock
 */
function formula_manage_get_parts($conn)
{
    return formula_manage_query(
        $conn,
        "SELECT
            p.`part_id`,
            p.`part_name`,
            p.`part_product`,
            p.`part_amount`,
            p.`part_categories`,
            COALESCE(c.`ct_name`, '') AS `ct_name`
        FROM `part` p
        LEFT JOIN `categories_product` c ON p.`part_categories` = c.`ct_id`
        ORDER BY c.`ct_name` ASC, p.`part_name` ASC"
    );
}

/**
 * ดึง part_id ที่ถูกผูกกับ Product ใน production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array map ของ part_id ที่เลือกแล้ว
 */
function formula_manage_get_selected_part_map($conn, $productId)
{
    if ($productId <= 0) {
        return array();
    }

    $rows = formula_manage_query(
        $conn,
        'SELECT `part_id` FROM `production_formula` WHERE `pd_id` = ?',
        'i',
        array($productId)
    );

    $map = array();
    foreach ($rows as $row) {
        $map[(int) $row['part_id']] = true;
    }

    return $map;
}

/**
 * ดึง part_id ที่อยู่ในเซตซ่อมของ Product (part_product LIKE ชื่อ Product)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array map ของ part_id ที่เลือกแล้ว
 */
function formula_manage_get_selected_repair_part_map($conn, $productId)
{
    if ($productId <= 0) {
        return array();
    }

    $product = formula_manage_get_product($conn, $productId);
    if ($product === null) {
        return array();
    }

    $partIds = pickup_repair_part_scope_ids($conn, $productId, (string) $product['pd_name']);
    $map = array();
    foreach ($partIds as $partId) {
        $map[$partId] = true;
    }

    return $map;
}

/**
 * ดึง metadata ของ primary key เพื่อรองรับฐานข้อมูลเดิมที่ใช้ production_id
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array ชื่อ primary key และสถานะ auto_increment
 */
function formula_manage_get_formula_primary_key($conn)
{
    $rows = formula_manage_query(
        $conn,
        "SELECT `COLUMN_NAME`, `EXTRA`
        FROM `INFORMATION_SCHEMA`.`COLUMNS`
        WHERE `TABLE_SCHEMA` = DATABASE()
            AND `TABLE_NAME` = 'production_formula'
            AND `COLUMN_NAME` IN ('formula_id', 'production_id')"
    );

    $primaryKey = array('name' => '', 'auto_increment' => true);
    foreach ($rows as $row) {
        $name = (string) $row['COLUMN_NAME'];
        if ($name === 'formula_id' || $name === 'production_id') {
            $primaryKey['name'] = $name;
            $primaryKey['auto_increment'] = strpos((string) $row['EXTRA'], 'auto_increment') !== false;
            if ($name === 'formula_id') {
                break;
            }
        }
    }

    return $primaryKey;
}

/**
 * ดึงเลข production_id ถัดไปสำหรับ schema เดิมที่ primary key ไม่ auto increment
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return int id ถัดไปสำหรับ insert
 */
function formula_manage_next_production_id($conn)
{
    $rows = formula_manage_query(
        $conn,
        'SELECT COALESCE(MAX(`production_id`), 0) + 1 AS `next_id` FROM `production_formula` FOR UPDATE'
    );

    return isset($rows[0]['next_id']) ? (int) $rows[0]['next_id'] : 1;
}

/**
 * normalize และ validate รายการ part_id ที่ผู้ใช้เลือกจากฟอร์ม
 *
 * @param array $partIds รายการ id จาก POST
 * @return array รายการ part_id ที่เป็น int และไม่ซ้ำ
 */
function formula_manage_normalize_part_ids($partIds)
{
    $selected = array();
    foreach ($partIds as $partId) {
        $cleanPartId = formula_manage_positive_int($partId);
        if ($cleanPartId > 0) {
            $selected[$cleanPartId] = true;
        }
    }

    return array_keys($selected);
}

/**
 * บันทึก mapping ของ Product กับ Part ลง production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @param array $partIds รายการ part_id ที่เลือก
 * @return int จำนวน Part ที่บันทึกสำเร็จ
 */
function formula_manage_save_formula($conn, $productId, $partIds)
{
    $product = formula_manage_get_product($conn, $productId);
    if ($product === null) {
        throw new RuntimeException('ไม่พบ Product ที่เลือก');
    }

    $cleanPartIds = formula_manage_normalize_part_ids($partIds);
    $validParts = array();

    if (count($cleanPartIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($cleanPartIds), '?'));
        $types = str_repeat('i', count($cleanPartIds));
        $rows = formula_manage_query(
            $conn,
            "SELECT `part_id`, `part_categories`
            FROM `part`
            WHERE `part_id` IN ($placeholders)",
            $types,
            $cleanPartIds
        );

        foreach ($rows as $row) {
            $validParts[(int) $row['part_id']] = $row;
        }
    }

    $primaryKey = formula_manage_get_formula_primary_key($conn);

    mysqli_begin_transaction($conn);
    try {
        $deleteStmt = formula_manage_execute(
            $conn,
            'DELETE FROM `production_formula` WHERE `pd_id` = ?',
            'i',
            array($productId)
        );
        mysqli_stmt_close($deleteStmt);

        $savedCount = 0;
        foreach ($cleanPartIds as $partId) {
            if (!isset($validParts[$partId])) {
                continue;
            }

            $categoryId = isset($validParts[$partId]['part_categories']) ? (int) $validParts[$partId]['part_categories'] : 0;
            if ($primaryKey['name'] === 'production_id' && !$primaryKey['auto_increment']) {
                $nextId = formula_manage_next_production_id($conn);
                $insertStmt = formula_manage_execute(
                    $conn,
                    'INSERT INTO `production_formula`
                        (`production_id`, `pd_id`, `pd_name`, `part_id`, `formula_name_id`, `ct_id`)
                    VALUES (?, ?, ?, ?, ?, ?)',
                    'iisiii',
                    array($nextId, $productId, $product['pd_name'], $partId, $productId, $categoryId)
                );
            } else {
                $insertStmt = formula_manage_execute(
                    $conn,
                    'INSERT INTO `production_formula`
                        (`pd_id`, `pd_name`, `part_id`, `formula_name_id`, `ct_id`)
                    VALUES (?, ?, ?, ?, ?)',
                    'isiii',
                    array($productId, $product['pd_name'], $partId, $productId, $categoryId)
                );
            }
            mysqli_stmt_close($insertStmt);
            $savedCount++;
        }

        mysqli_commit($conn);

        return $savedCount;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * บันทึกเซตซ่อมโดยตั้ง part_product ของ Part ที่เลือก และถอด Part ที่เอาออกจากชุด
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @param array $partIds รายการ part_id ที่เลือก
 * @return int จำนวน Part ที่อยู่ในชุดหลังบันทึก
 */
function formula_manage_save_repair_set($conn, $productId, $partIds)
{
    $product = formula_manage_get_product($conn, $productId);
    if ($product === null) {
        throw new RuntimeException('ไม่พบ Product ที่เลือก');
    }

    $cleanPartIds = formula_manage_normalize_part_ids($partIds);

    return pickup_repair_formula_replace(
        $conn,
        $productId,
        (string) $product['pd_name'],
        $cleanPartIds
    );
}

/**
 * ลบรายการชุดผลิตของ Product จาก production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @return int จำนวนแถวที่ลบ
 */
function formula_manage_delete_production_set($conn, $productId)
{
    $product = formula_manage_get_product($conn, $productId);
    if ($product === null) {
        throw new RuntimeException('ไม่พบ Product ที่ต้องการลบชุด');
    }

    $deleteStmt = formula_manage_execute(
        $conn,
        'DELETE FROM `production_formula` WHERE `pd_id` = ?',
        'i',
        array($productId)
    );
    $deleted = mysqli_stmt_affected_rows($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    return $deleted >= 0 ? (int) $deleted : 0;
}

/**
 * ลบรายการชุดเบิกตามโหมด (ไม่ลบข้ามโหมดผลิต/ซ่อม)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $setMode production หรือ repair
 * @return int จำนวนแถวที่ลบ
 */
function formula_manage_delete_set($conn, $productId, $setMode)
{
    $productId = formula_manage_positive_int($productId);
    if ($productId <= 0) {
        throw new RuntimeException('ไม่พบ Product ที่ต้องการลบชุด');
    }

    if ($setMode === 'repair') {
        $deleted = pickup_repair_formula_delete_for_product($conn, $productId);
        $deleted += pickup_repair_formula_tech_delete_for_product($conn, $productId);

        return $deleted;
    }

    return formula_manage_delete_production_set($conn, $productId);
}

/**
 * normalize ชื่อ Product ก่อนบันทึก
 *
 * @param mixed $value ชื่อจากฟอร์ม
 * @return string ชื่อที่ trim แล้ว
 */
function formula_manage_normalize_product_name($value)
{
    $name = trim((string) $value);
    $name = preg_replace('/\s+/u', ' ', $name);

    return $name === null ? '' : $name;
}

/**
 * ตรวจว่ามี Product ชื่อเดียวกัน (ไม่รวม pd_id ที่ยกเว้น)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $productName ชื่อที่ต้องตรวจ
 * @param int $excludePdId pd_id ที่ไม่นับซ้ำ
 * @return bool true เมื่อชื่อถูกใช้แล้ว
 */
function formula_manage_product_name_taken($conn, $productName, $excludePdId)
{
    $rows = formula_manage_query(
        $conn,
        'SELECT `pd_id` FROM `product` WHERE LOWER(`pd_name`) = LOWER(?) AND `pd_id` <> ? LIMIT 1',
        'si',
        array($productName, $excludePdId)
    );

    return count($rows) > 0;
}

/**
 * อัปเดต part_product หลังเปลี่ยนชื่อ Product (เฉพาะชุดซ่อมของ pd_id นี้)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $oldName ชื่อ Product ก่อนเปลี่ยน
 * @param string $cleanName ชื่อ Product ใหม่
 * @return void ไม่มี return value
 */
function formula_manage_rename_sync_part_product($conn, $productId, $oldName, $cleanName)
{
    if ($oldName === '' || strcasecmp($oldName, $cleanName) === 0) {
        return;
    }

    pickup_repair_formula_update_pd_name($conn, $productId, $cleanName);
}

/**
 * เปลี่ยนชื่อ Product (ชื่อชุดเบิก) และ sync ชื่อใน production_formula / part_product
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $newName ชื่อใหม่
 * @param bool $syncRepairPartProduct พารามิเตอร์คงไว้เพื่อความเข้ากันได้ ( sync part_product ทำเสมอ )
 * @return void ไม่มี return value
 */
function formula_manage_rename_product($conn, $productId, $newName, $syncRepairPartProduct)
{
    $product = formula_manage_get_product($conn, $productId);
    if ($product === null) {
        throw new RuntimeException('ไม่พบ Product ที่ต้องการเปลี่ยนชื่อ');
    }

    $oldName = (string) $product['pd_name'];
    $cleanName = formula_manage_normalize_product_name($newName);
    if ($cleanName === '') {
        throw new RuntimeException('กรุณากรอกชื่อชุด / Product');
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($cleanName, 'UTF-8') > 255) {
            throw new RuntimeException('ชื่อต้องไม่เกิน 255 ตัวอักษร');
        }
    } elseif (strlen($cleanName) > 255) {
        throw new RuntimeException('ชื่อต้องไม่เกิน 255 ตัวอักษร');
    }

    if (strcasecmp($oldName, $cleanName) === 0) {
        return;
    }

    if (formula_manage_product_name_taken($conn, $cleanName, $productId)) {
        throw new RuntimeException('มีชื่อ Product นี้อยู่แล้ว');
    }

    mysqli_begin_transaction($conn);
    try {
        $updateProduct = formula_manage_execute(
            $conn,
            'UPDATE `product` SET `pd_name` = ? WHERE `pd_id` = ?',
            'si',
            array($cleanName, $productId)
        );
        mysqli_stmt_close($updateProduct);

        $updateFormula = formula_manage_execute(
            $conn,
            'UPDATE `production_formula` SET `pd_name` = ? WHERE `pd_id` = ?',
            'si',
            array($cleanName, $productId)
        );
        mysqli_stmt_close($updateFormula);

        formula_manage_rename_sync_part_product($conn, $productId, $oldName, $cleanName);

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * เก็บข้อความ flash หลัง POST แล้ว redirect (PRG)
 *
 * @param string $message ข้อความแจ้งผู้ใช้
 * @param string $messageType success|error|info
 * @return void ไม่มี return value
 */
function formula_manage_flash_set($message, $messageType)
{
    $_SESSION['formula_manage_flash'] = array(
        'message' => (string) $message,
        'type' => (string) $messageType,
    );
}

/**
 * อ่านและลบข้อความ flash หนึ่งครั้ง
 *
 * @return array|null ข้อมูล message/type หรือ null
 */
function formula_manage_flash_consume()
{
    if (empty($_SESSION['formula_manage_flash']) || !is_array($_SESSION['formula_manage_flash'])) {
        return null;
    }

    $flash = $_SESSION['formula_manage_flash'];
    unset($_SESSION['formula_manage_flash']);

    return $flash;
}

/**
 * redirect หลัง POST
 *
 * @param string $setMode โหมดชุด
 * @param int $selectedProductId pd_id หรือ 0
 * @return void ไม่มี return value
 */
function formula_manage_redirect_after_post($setMode, $selectedProductId)
{
    $url = 'formula_manage.php?set_mode=' . rawurlencode($setMode);
    if ($selectedProductId > 0) {
        $url .= '&pd_id=' . (int) $selectedProductId;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * ดึงรายการ Part ที่ผูกในชุดของ Product ตามโหมด
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $setMode production หรือ repair
 * @return array รายการ part_id, part_name, ct_name
 */
function formula_manage_get_set_parts_list($conn, $productId, $setMode)
{
    $productId = formula_manage_positive_int($productId);
    if ($productId <= 0) {
        return array();
    }

    if ($setMode === 'repair') {
        $sql = 'SELECT p.`part_id`, p.`part_name`, COALESCE(c.`ct_name`, \'\') AS `ct_name`
            FROM `repair_formula` rf
            INNER JOIN `part` p ON p.`part_id` = rf.`part_id`
            LEFT JOIN `categories_product` c ON p.`part_categories` = c.`ct_id`
            WHERE rf.`pd_id` = ?
            ORDER BY p.`part_name` ASC';
    } else {
        $sql = 'SELECT p.`part_id`, p.`part_name`, COALESCE(c.`ct_name`, \'\') AS `ct_name`
            FROM `production_formula` pf
            INNER JOIN `part` p ON p.`part_id` = pf.`part_id`
            LEFT JOIN `categories_product` c ON p.`part_categories` = c.`ct_id`
            WHERE pf.`pd_id` = ?
            ORDER BY p.`part_name` ASC';
    }

    return formula_manage_query($conn, $sql, 'i', array($productId));
}

/**
 * ตอบ JSON รายการ Part ในชุด (popup สรุปบนตารางรายการชุด)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return void ไม่มี return value — exit หลัง echo JSON
 */
function formula_manage_respond_set_parts_json($conn)
{
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $pdId = formula_manage_positive_int(isset($_GET['pd_id']) ? $_GET['pd_id'] : 0);
    $setMode = formula_manage_normalize_set_mode(isset($_GET['set_mode']) ? $_GET['set_mode'] : 'production');
    if ($pdId <= 0) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'ไม่ระบุ Product'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $product = formula_manage_get_product($conn, $pdId);
    if ($product === null) {
        http_response_code(404);
        echo json_encode(array('ok' => false, 'error' => 'ไม่พบ Product'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $parts = formula_manage_get_set_parts_list($conn, $pdId, $setMode);
    $modeLabel = $setMode === 'repair' ? 'เซตซ่อม' : 'เซตผลิต';
    $techParts = array();
    if ($setMode === 'repair') {
        $techRows = pickup_repair_formula_tech_list($conn, $pdId);
        $catalog = pickup_repair_formula_tech_valid_product_map();
        foreach ($techRows as $techRow) {
            $techId = (int) $techRow['tech_product_id'];
            $label = 'อะไหล่คลังช่าง #' . $techId;
            if (pickup_tech_parts_available()) {
                foreach (pickup_tech_parts_products_list() as $tp) {
                    if ((int) $tp['id'] === $techId) {
                        $code = isset($tp['code']) ? trim((string) $tp['code']) : '';
                        $name = isset($tp['name']) ? trim((string) $tp['name']) : '';
                        $label = $code !== '' ? $code . ' — ' . $name : $name;
                        break;
                    }
                }
            }
            $techParts[] = array(
                'tech_product_id' => $techId,
                'label' => $label,
                'default_qty' => (float) $techRow['default_qty'],
                'active' => $catalog === array() || isset($catalog[$techId]),
            );
        }
    }

    echo json_encode(
        array(
            'ok' => true,
            'pd_id' => $pdId,
            'pd_name' => (string) $product['pd_name'],
            'set_mode' => $setMode,
            'set_mode_label' => $modeLabel,
            'parts' => $parts,
            'tech_parts' => $techParts,
        ),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// ─ Request Handling ──────────────────────────────────────────────────────────

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'set_parts_json'
) {
    formula_manage_respond_set_parts_json($connect);
}

$message = '';
$messageType = 'info';
$selectedProductId = 0;
$setMode = 'production';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        formula_manage_validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '');
        $setMode = formula_manage_normalize_set_mode(isset($_POST['set_mode']) ? $_POST['set_mode'] : 'production');
        $formAction = isset($_POST['form_action']) ? trim((string) $_POST['form_action']) : 'save_set';
        $redirectPdId = 0;

        if ($formAction === 'rename_product') {
            $renamePdId = formula_manage_positive_int(isset($_POST['rename_pd_id']) ? $_POST['rename_pd_id'] : 0);
            $newName = isset($_POST['new_pd_name']) ? $_POST['new_pd_name'] : '';
            formula_manage_rename_product($connect, $renamePdId, $newName, $setMode === 'repair');
            $message = 'เปลี่ยนชื่อชุด (Product) สำเร็จ';
            $messageType = 'success';
            $redirectPdId = 0;
        } elseif ($formAction === 'delete_set') {
            $deletePdId = formula_manage_positive_int(isset($_POST['delete_pd_id']) ? $_POST['delete_pd_id'] : 0);
            if ($deletePdId <= 0) {
                throw new RuntimeException('ไม่พบ Product ที่ต้องการลบชุด');
            }
            $deletedRows = formula_manage_delete_set($connect, $deletePdId, $setMode);
            $tableLabel = $setMode === 'repair' ? 'repair_formula' : 'production_formula';
            if ($deletedRows > 0) {
                $message = 'ลบรายการชุดสำเร็จ (' . $tableLabel . ', ' . $deletedRows . ' แถว) — อีกโหมดไม่ถูกลบ';
                $messageType = 'success';
            } else {
                $message = 'ไม่มีชุดในฐานข้อมูลให้ลบ (จำนวน Part เป็น 0 อยู่แล้ว) — แถว Product ยังแสดงในรายการ';
                $messageType = 'info';
            }
            $redirectPdId = 0;
        } else {
            $redirectPdId = formula_manage_positive_int(isset($_POST['pd_id']) ? $_POST['pd_id'] : 0);
            $selectedProductId = $redirectPdId;
            $postedParts = isset($_POST['part_ids']) && is_array($_POST['part_ids']) ? $_POST['part_ids'] : array();
            if ($setMode === 'repair') {
                $savedCount = formula_manage_save_repair_set($connect, $selectedProductId, $postedParts);
                $savedTech = pickup_repair_formula_tech_save_from_post($connect, $selectedProductId, $_POST);
                if ($savedCount > 0 && $savedTech > 0) {
                    $message = 'บันทึกเซตซ่อมสำเร็จ — คลังกลาง ' . $savedCount . ' รายการ · คลังช่าง ' . $savedTech . ' รายการ';
                } elseif ($savedTech > 0) {
                    $message = 'บันทึกเซตซ่อมสำเร็จ — คลังช่าง ' . $savedTech . ' รายการ (repair_formula_tech)';
                } else {
                    $message = 'บันทึกเซตซ่อมสำเร็จ จำนวน Part ในชุด: ' . $savedCount . ' รายการ (repair_formula)';
                }
            } else {
                $savedCount = formula_manage_save_formula($connect, $selectedProductId, $postedParts);
                $message = 'บันทึกเซตผลิตสำเร็จ จำนวน Part ในชุด: ' . $savedCount . ' รายการ (production_formula)';
            }
            $messageType = 'success';
        }

        formula_manage_flash_set($message, $messageType);
        formula_manage_redirect_after_post($setMode, $redirectPdId);
    } else {
        $selectedProductId = formula_manage_positive_int(isset($_GET['pd_id']) ? $_GET['pd_id'] : 0);
        $setMode = formula_manage_normalize_set_mode(isset($_GET['set_mode']) ? $_GET['set_mode'] : 'production');
    }
} catch (Throwable $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        formula_manage_flash_set($e->getMessage(), 'error');
        $redirectMode = formula_manage_normalize_set_mode(isset($_POST['set_mode']) ? $_POST['set_mode'] : 'production');
        $redirectPd = formula_manage_positive_int(isset($_POST['pd_id']) ? $_POST['pd_id'] : 0);
        formula_manage_redirect_after_post($redirectMode, $redirectPd);
    }
    $message = $e->getMessage();
    $messageType = 'error';
}

$flash = formula_manage_flash_consume();
if ($flash !== null) {
    $message = isset($flash['message']) ? (string) $flash['message'] : '';
    $messageType = isset($flash['type']) ? (string) $flash['type'] : 'info';
}

$products = formula_manage_get_products($connect);
$parts = formula_manage_get_parts($connect);
if ($setMode === 'repair') {
    $selectedPartMap = formula_manage_get_selected_repair_part_map($connect, $selectedProductId);
} else {
    $selectedPartMap = formula_manage_get_selected_part_map($connect, $selectedProductId);
}
$csrfToken = formula_manage_csrf_token();
$isRepairMode = ($setMode === 'repair');
$formulaManageDeleteSetLabel = $isRepairMode ? 'เซตซ่อม' : 'เซตผลิต';
$formulaManageTechProducts = array();
$formulaManageSelectedTechQty = array();
$formulaManageTechAvailable = false;
$formulaManageTechError = '';
if ($isRepairMode && $selectedProductId > 0) {
    $formulaManageTechAvailable = pickup_tech_parts_available();
    $formulaManageTechError = pickup_tech_parts_error();
    if ($formulaManageTechAvailable) {
        $formulaManageTechProducts = pickup_tech_parts_products_list();
    }
    foreach (pickup_repair_formula_tech_list($connect, $selectedProductId) as $techRow) {
        $tid = isset($techRow['tech_product_id']) ? (int) $techRow['tech_product_id'] : 0;
        if ($tid > 0) {
            $formulaManageSelectedTechQty[$tid] = isset($techRow['default_qty'])
                ? (float) $techRow['default_qty']
                : PICKUP_REPAIR_TECH_DEFAULT_QTY;
        }
    }
}
$formulaManageInitialSelected = count($selectedPartMap) + count($formulaManageSelectedTechQty);
$setSummaries = array();
if ($selectedProductId <= 0) {
    $setSummaries = formula_manage_get_set_summaries($connect, $setMode);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดชุดรายการเบิก</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
    <link type="text/css" rel="stylesheet" href="css.css">
    <style>
        body {
            margin: 0;
            color: #555;
            font-family: Arial, Tahoma, sans-serif;
            background: #fff;
        }

        .navbar {
            background-color: #E9E9E9;
            overflow: hidden;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 10;
        }

        .content {
            max-width: 1200px;
            margin: 120px auto 40px;
            padding: 0 20px;
            font-size: 16px;
        }

        .panel {
            border: 1px solid #d8eee9;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            background: #fff;
        }

        .page-title {
            display: inline-block;
            border-radius: 10px;
            background: #FFCCCC;
            padding: 7px 14px;
            margin-bottom: 18px;
            color: #555;
        }

        .toolbar {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        select,
        button,
        input[type="search"] {
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 15px;
            background-color: #e8f5e9;
        }

        button {
            cursor: pointer;
            color: #145c2c;
            font-weight: bold;
        }

        .link-button {
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 15px;
            background-color: #e8f5e9;
            color: #145c2c;
            font-weight: bold;
            text-decoration: none;
        }

        .message {
            border-radius: 8px;
            margin: 12px 0;
            padding: 10px 12px;
        }

        .message--success {
            background: #e5f7e8;
            color: #1f6b2c;
        }

        .message--error {
            background: #fde7e7;
            color: #a22222;
        }

        .message--info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .part-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 16px 0;
        }

        .part-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .part-item {
            border: 1px solid #e4e4e4;
            border-radius: 10px;
            padding: 10px;
            background: #fafafa;
        }

        .part-item label {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            cursor: pointer;
        }

        .part-meta {
            color: #777;
            display: block;
            font-size: 13px;
            margin-top: 3px;
        }

        .tech-set-block {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 2px dashed #c8e6c9;
        }

        .tech-set-block h2 {
            font-size: 18px;
            margin: 0 0 8px;
            color: #1b5e20;
        }

        .tech-set-hint {
            color: #555;
            font-size: 14px;
            margin: 0 0 12px;
        }

        .part-item--tech {
            border-color: #b2dfdb;
            background: #f1faf8;
        }

        .tech-default-qty {
            width: 4.5rem;
            margin-left: 6px;
            padding: 4px 6px;
            border: 1px solid #4caf50;
            border-radius: 6px;
            font-size: 14px;
        }

        .tech-set-badge {
            display: inline-block;
            font-size: 11px;
            background: #00897b;
            color: #fff;
            border-radius: 4px;
            padding: 1px 6px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .empty-state {
            border: 1px dashed #ccc;
            border-radius: 10px;
            color: #777;
            padding: 18px;
            text-align: center;
        }

        .mode-switch-row {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 10px 12px;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .mode-switch-row .mode-switch {
            margin-bottom: 0;
        }

        .mode-switch-back {
            flex-shrink: 0;
            text-decoration: none;
        }

        .mode-switch {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .mode-switch__btn {
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 15px;
            background: #fff;
            color: #145c2c;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .mode-switch__btn.is-active {
            background: #4caf50;
            color: #fff;
        }

        .mode-hint {
            color: #666;
            font-size: 14px;
            margin: 0 0 14px;
            line-height: 1.45;
        }

        .mode-hint--warn {
            color: #8a4b00;
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 8px;
            padding: 10px 12px;
        }

        .set-summary {
            margin-top: 8px;
        }

        .set-summary__title {
            font-size: 15px;
            font-weight: bold;
            color: #333;
            margin: 0 0 10px;
        }

        .set-summary__hint {
            color: #777;
            font-size: 14px;
            margin: 0 0 12px;
        }

        .set-summary-table-wrap {
            overflow-x: auto;
            border: 1px solid #e4e4e4;
            border-radius: 10px;
        }

        .set-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }

        .set-summary-table th,
        .set-summary-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .set-summary-table th {
            background: #f3faf4;
            color: #145c2c;
        }

        .set-summary-table tr:last-child td {
            border-bottom: none;
        }

        .set-summary-table tr.is-empty td {
            color: #999;
        }

        .set-summary-table .col-count {
            width: 120px;
            text-align: center;
        }

        .set-summary-table .col-action {
            width: 168px;
            text-align: center;
        }

        .set-summary-actions {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            align-items: center;
        }

        .set-summary-open {
            display: inline-block;
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 14px;
            background-color: #e8f5e9;
            color: #145c2c;
            font-weight: bold;
            text-decoration: none;
        }

        .set-summary-delete {
            display: inline-block;
            border: 2px solid #e53935;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 14px;
            background-color: #ffebee;
            color: #b71c1c;
            font-weight: bold;
            cursor: pointer;
        }

        .set-summary-delete:hover {
            background-color: #ffcdd2;
        }

        .set-summary-delete:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .set-summary-delete-form {
            display: inline;
            margin: 0;
        }

        .set-summary-no-delete {
            display: inline-block;
            font-size: 12px;
            color: #888;
            padding: 4px 6px;
        }

        .set-summary-product-view.is-hidden {
            display: none;
        }

        .set-summary-product__name {
            font-weight: 600;
            color: #333;
        }

        .set-summary-rename-trigger {
            margin-left: 8px;
            padding: 0;
            border: none;
            background: none;
            color: #1565c0;
            font-size: 13px;
            cursor: pointer;
            text-decoration: underline;
        }

        .set-summary-rename-trigger:hover {
            color: #0d47a1;
        }

        .set-summary-rename {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .set-summary-rename.is-hidden {
            display: none;
        }

        .set-summary-rename input[type="text"] {
            flex: 1 1 180px;
            min-width: 120px;
            padding: 6px 10px;
            border: 1px solid #bdbdbd;
            border-radius: 6px;
            font-size: 14px;
        }

        .set-summary-rename__actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .set-summary-rename__save,
        .set-summary-rename__cancel {
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 13px;
            cursor: pointer;
        }

        .set-summary-rename__save {
            border: 1px solid #4caf50;
            background: #e8f5e9;
            color: #145c2c;
            font-weight: 600;
        }

        .set-summary-rename__cancel {
            border: 1px solid #bdbdbd;
            background: #fafafa;
            color: #424242;
        }

        .set-summary-product-cell {
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .set-summary-product-cell:hover {
            background: #f1f8f4;
        }

        .set-summary-product-cell:focus-visible {
            outline: 2px solid #2e7d32;
            outline-offset: -2px;
        }

        .fm-parts-pop {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .fm-parts-pop.is-open {
            display: flex;
        }

        .fm-parts-pop__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.38);
            backdrop-filter: blur(4px);
        }

        .fm-parts-pop__card,
        .fm-parts-pop__card * {
            box-sizing: border-box;
        }

        .fm-parts-pop__card {
            position: relative;
            width: min(420px, 100%);
            max-height: min(72vh, 520px);
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(46, 125, 50, 0.12);
            box-shadow:
                0 4px 6px rgba(15, 23, 42, 0.04),
                0 24px 48px rgba(15, 23, 42, 0.12);
            overflow: hidden;
            animation: fmPartsPopIn 0.22s ease;
        }

        @keyframes fmPartsPopIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .fm-parts-pop__head {
            padding: 18px 20px 14px;
            border-bottom: 1px solid #eef2f0;
        }

        .fm-parts-pop__eyebrow {
            margin: 0 0 4px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
        }

        .fm-parts-pop__title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
        }

        .fm-parts-pop__meta {
            margin: 8px 0 0;
            font-size: 13px;
            color: #475569;
        }

        .fm-parts-pop__close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }

        .fm-parts-pop__close:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .fm-parts-pop__body {
            padding: 8px 12px 16px;
            overflow-y: auto;
            flex: 1;
        }

        .fm-parts-pop__list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .fm-parts-pop__item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 12px;
            margin-bottom: 6px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #eef2f6;
        }

        .fm-parts-pop__item:last-child {
            margin-bottom: 0;
        }

        .fm-parts-pop__part-name {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .fm-parts-pop__part-cat {
            flex-shrink: 0;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 999px;
            background: #e8f5e9;
            color: #1b5e20;
        }

        .fm-parts-pop__empty,
        .fm-parts-pop__loading,
        .fm-parts-pop__error {
            padding: 24px 16px;
            text-align: center;
            font-size: 14px;
            color: #64748b;
        }

        .fm-parts-pop__error {
            color: #b91c1c;
        }

        .fm-parts-pop__foot {
            padding: 12px 16px 16px;
            border-top: 1px solid #eef2f0;
            min-width: 0;
        }

        .fm-parts-pop__edit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            max-width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #2e7d32;
            background: linear-gradient(180deg, #f1f8f4 0%, #e8f5e9 100%);
            color: #1b5e20;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
        }

        .fm-parts-pop__edit:hover {
            background: #e8f5e9;
        }
    </style>
    <script>
        var formulaManageSetMode = <?php echo json_encode($setMode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function formulaManageBuildUrl(pdId) {
            var url = 'formula_manage.php?set_mode=' + encodeURIComponent(formulaManageSetMode);
            if (pdId !== undefined && pdId !== null && String(pdId) !== '') {
                url += '&pd_id=' + encodeURIComponent(pdId);
            }
            return url;
        }

        function formulaManageSubmitProduct() {
            var select = document.getElementById('pd_id_filter');
            if (select.value === '') {
                window.location = formulaManageBuildUrl('');
                return;
            }
            window.location = formulaManageBuildUrl(select.value);
        }

        function formulaManageSwitchMode(mode) {
            formulaManageSetMode = mode;
            var select = document.getElementById('pd_id_filter');
            window.location = formulaManageBuildUrl(select.value);
        }

        function formulaManageSetAll(checked) {
            var boxes = document.querySelectorAll('input[name="part_ids[]"]');
            for (var i = 0; i < boxes.length; i++) {
                if (boxes[i].closest('.part-item').style.display !== 'none') {
                    boxes[i].checked = checked;
                }
            }
            formulaManageUpdateCount();
        }

        function formulaManageFilterParts() {
            var keyword = document.getElementById('part_search').value.toLowerCase();
            var items = document.querySelectorAll('.part-item');
            for (var i = 0; i < items.length; i++) {
                var text = items[i].getAttribute('data-search').toLowerCase();
                items[i].style.display = text.indexOf(keyword) !== -1 ? '' : 'none';
            }
        }

        function formulaManageUpdateCount() {
            var checkedParts = document.querySelectorAll('input[name="part_ids[]"]:checked').length;
            var checkedTech = document.querySelectorAll('input[name="tech_product_ids[]"]:checked').length;
            var counter = document.getElementById('selected_count');
            if (counter) {
                counter.innerText = String(checkedParts + checkedTech);
            }
        }

        function formulaManageFilterTechParts() {
            var search = document.getElementById('tech_part_search');
            if (!search) {
                return;
            }
            var keyword = String(search.value || '').toLowerCase();
            var items = document.querySelectorAll('.tech-set-block .part-item');
            for (var i = 0; i < items.length; i++) {
                var text = String(items[i].getAttribute('data-search') || '').toLowerCase();
                items[i].style.display = text.indexOf(keyword) !== -1 ? '' : 'none';
            }
        }

        function formulaManageSetAllTech(checked) {
            var boxes = document.querySelectorAll('.tech-set-block input[name="tech_product_ids[]"]');
            for (var i = 0; i < boxes.length; i++) {
                var item = boxes[i].closest('.part-item');
                if (item && item.style.display === 'none') {
                    continue;
                }
                boxes[i].checked = checked;
            }
            formulaManageUpdateCount();
        }

        var formulaManagePartsPopPdId = 0;

        function formulaManageOnPageLoad() {
            formulaManageUpdateCount();
            formulaManageBindPartsPopup();
            var flash = document.getElementById('formula_manage_flash');
            if (flash) {
                flash.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function formulaManageSetPartsPopUrl(pdId) {
            return 'formula_manage.php?set_mode=' + encodeURIComponent(formulaManageSetMode)
                + '&action=set_parts_json&pd_id=' + encodeURIComponent(String(pdId));
        }

        function formulaManageBindPartsPopup() {
            var cells = document.querySelectorAll('.set-summary-product-cell');
            for (var i = 0; i < cells.length; i++) {
                cells[i].addEventListener('click', formulaManageOnProductCellClick);
                cells[i].addEventListener('keydown', formulaManageOnProductCellKeydown);
            }
            var pop = document.getElementById('fm_parts_pop');
            if (pop) {
                pop.addEventListener('click', function (event) {
                    if (event.target.classList.contains('fm-parts-pop__backdrop')) {
                        formulaManageClosePartsPopup();
                    }
                });
            }
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    formulaManageClosePartsPopup();
                }
            });
        }

        function formulaManageOnProductCellClick(event) {
            if (event.target.closest('.set-summary-rename-trigger')
                || event.target.closest('.set-summary-rename')
                || event.target.closest('form')) {
                return;
            }
            var cell = event.currentTarget;
            var pdId = parseInt(cell.getAttribute('data-pd-id') || '0', 10);
            var pdName = cell.getAttribute('data-pd-name') || '';
            if (pdId > 0) {
                formulaManageOpenPartsPopup(pdId, pdName);
            }
        }

        function formulaManageOnProductCellKeydown(event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            formulaManageOnProductCellClick(event);
        }

        function formulaManageOpenPartsPopup(pdId, pdName) {
            var pop = document.getElementById('fm_parts_pop');
            var body = document.getElementById('fm_parts_pop_body');
            var title = document.getElementById('fm_parts_pop_title');
            var meta = document.getElementById('fm_parts_pop_meta');
            var edit = document.getElementById('fm_parts_pop_edit');
            if (!pop || !body || !title) {
                return;
            }
            formulaManagePartsPopPdId = pdId;
            title.textContent = pdName || 'Product';
            if (meta) {
                meta.textContent = 'กำลังโหลด…';
            }
            body.innerHTML = '<div class="fm-parts-pop__loading">กำลังโหลดรายการ Part…</div>';
            if (edit) {
                edit.href = formulaManageBuildUrl(pdId);
            }
            pop.classList.add('is-open');
            pop.setAttribute('aria-hidden', 'false');

            fetch(formulaManageSetPartsPopUrl(pdId), { credentials: 'same-origin' })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        throw new Error((data && data.error) ? data.error : 'โหลดไม่สำเร็จ');
                    }
                    var techCount = data.tech_parts ? data.tech_parts.length : 0;
                    var totalCount = data.parts.length + techCount;
                    if (meta) {
                        var metaParts = data.parts.length > 0 ? ('คลังกลาง ' + data.parts.length) : '';
                        var metaTech = techCount > 0 ? ('คลังช่าง ' + techCount) : '';
                        meta.textContent = data.set_mode_label + ' · ' + totalCount + ' รายการ'
                            + (metaParts !== '' || metaTech !== '' ? ' (' + [metaParts, metaTech].filter(Boolean).join(' · ') + ')' : '');
                    }
                    if (totalCount <= 0) {
                        body.innerHTML = '<div class="fm-parts-pop__empty">ยังไม่มี Part ในชุดนี้</div>';
                        return;
                    }
                    var html = '';
                    if (data.parts.length) {
                        html += '<p class="fm-parts-pop__tech-head"><b>คลังกลาง</b></p><ul class="fm-parts-pop__list">';
                        for (var i = 0; i < data.parts.length; i++) {
                            var part = data.parts[i];
                            var cat = part.ct_name ? String(part.ct_name) : '';
                            html += '<li class="fm-parts-pop__item">';
                            html += '<span class="fm-parts-pop__part-name">' + formulaManageEscapeHtml(String(part.part_name)) + '</span>';
                            if (cat !== '') {
                                html += '<span class="fm-parts-pop__part-cat">' + formulaManageEscapeHtml(cat) + '</span>';
                            }
                            html += '</li>';
                        }
                        html += '</ul>';
                    }
                    if (techCount) {
                        html += '<p class="fm-parts-pop__tech-head"><b>คลังช่าง</b></p><ul class="fm-parts-pop__list">';
                        for (var ti = 0; ti < data.tech_parts.length; ti++) {
                            var tp = data.tech_parts[ti];
                            html += '<li class="fm-parts-pop__item fm-parts-pop__item--tech">';
                            html += '<span class="fm-parts-pop__part-name">' + formulaManageEscapeHtml(String(tp.label)) + '</span>';
                            html += '<span class="fm-parts-pop__part-cat">× ' + formulaManageEscapeHtml(String(tp.default_qty)) + ' ต่อชุด</span>';
                            html += '</li>';
                        }
                        html += '</ul>';
                    }
                    body.innerHTML = html;
                })
                .catch(function (err) {
                    if (meta) {
                        meta.textContent = '';
                    }
                    body.innerHTML = '<div class="fm-parts-pop__error">' + formulaManageEscapeHtml(err.message || 'โหลดไม่สำเร็จ') + '</div>';
                });
        }

        function formulaManageClosePartsPopup() {
            var pop = document.getElementById('fm_parts_pop');
            if (!pop) {
                return;
            }
            pop.classList.remove('is-open');
            pop.setAttribute('aria-hidden', 'true');
        }

        function formulaManageEscapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formulaManageShowRename(pdId) {
            var view = document.getElementById('set-name-view-' + pdId);
            var form = document.getElementById('set-name-form-' + pdId);
            if (!view || !form) {
                return;
            }
            view.classList.add('is-hidden');
            form.classList.remove('is-hidden');
            var input = form.querySelector('input[name="new_pd_name"]');
            if (input) {
                input.focus();
                input.select();
            }
        }

        function formulaManageCancelRename(pdId) {
            var view = document.getElementById('set-name-view-' + pdId);
            var form = document.getElementById('set-name-form-' + pdId);
            if (!view || !form) {
                return;
            }
            form.classList.add('is-hidden');
            view.classList.remove('is-hidden');
        }

        function formulaManageConfirmSaveSet(form) {
            var checkedParts = document.querySelectorAll('input[name="part_ids[]"]:checked').length;
            var checkedTech = document.querySelectorAll('input[name="tech_product_ids[]"]:checked').length;
            var initial = parseInt(form.getAttribute('data-initial-selected') || '0', 10);
            if (initial > 0 && checkedParts === 0 && checkedTech === 0) {
                return window.confirm(
                    'ไม่ได้เลือก Part หรืออะไหล่คลังช่าง — การบันทึกจะลบรายการในชุดของ Product นี้ทั้งหมด\nต้องการบันทึกต่อหรือไม่?'
                );
            }
            var techBoxes = document.querySelectorAll('input[name="tech_product_ids[]"]:checked');
            if (techBoxes.length > 10) {
                alert('อะไหล่คลังช่างในชุดซ่อมได้ไม่เกิน 10 รายการ');
                return false;
            }
            return true;
        }

        function formulaManageConfirmDeleteSet(form) {
            var productName = form.getAttribute('data-product-name') || 'Product นี้';
            var modeLabel = form.getAttribute('data-set-mode-label') || 'ชุด';
            return window.confirm(
                'ลบ' + modeLabel + 'ของ "' + productName + '" ทั้งหมด?\n(อีกโหมดจะไม่ถูกลบ)'
            );
        }

        function formulaManageSubmitDeleteSet(button) {
            var form = button.closest('form');
            if (!form) {
                return false;
            }
            if (!formulaManageConfirmDeleteSet(form)) {
                return false;
            }
            form.submit();
            return false;
        }
    </script>
</head>
<body onload="formulaManageOnPageLoad()">
    <div class="navbar"><?php require __DIR__ . '/title-h.php'; ?></div>

    <main class="content">
        <section class="panel">
            <h1 class="page-title">จัดชุดรายการเบิก</h1>

            <div class="mode-switch-row">
                <div class="mode-switch" role="tablist" aria-label="โหมดชุดเบิก">
                <button type="button" class="mode-switch__btn<?php echo !$isRepairMode ? ' is-active' : ''; ?>" onclick="formulaManageSwitchMode('production'); return false;">
                    <i class="fa fa-industry"></i> เซตผลิต
                </button>
                <button type="button" class="mode-switch__btn<?php echo $isRepairMode ? ' is-active' : ''; ?>" onclick="formulaManageSwitchMode('repair'); return false;">
                    <i class="fa fa-wrench"></i> เซตซ่อม
                </button>
                </div>
                <?php if ($selectedProductId > 0) {
                    $formulaManageBackUrl = 'formula_manage.php?set_mode=' . rawurlencode($setMode);
                    ?>
                <a class="link-button mode-switch-back" href="<?php echo formula_manage_h($formulaManageBackUrl); ?>" title="กลับไปตารางรายการชุด">
                    <i class="fa fa-arrow-left"></i> กลับรายการชุด
                </a>
                <?php } ?>
            </div>
            <?php if ($isRepairMode) { ?>
                <p class="mode-hint">
                    ใช้กับปุ่ม <b>เบิกเซตซ่อม</b> — คลังกลางลง <code>repair_formula</code>
                    · คลังช่างลง <code>repair_formula_tech</code> (จำนวนเริ่มต้นต่อชุด คูณด้วยจำนวนชุดตอนเบิก)
                    <br>Part ตัวเดียวกันใช้ในหลาย Product ได้ ไม่แย่งฟิลด์ <code>part_product</code>
                </p>
            <?php } else { ?>
                <p class="mode-hint">
                    ใช้กับปุ่ม <b>เบิกเป็นเซต</b> — บันทึกลงตาราง <code>production_formula</code>
                </p>
            <?php } ?>

            <div class="toolbar">
                <label for="pd_id_filter"><b>Product :</b></label>
                <select id="pd_id_filter" onchange="formulaManageSubmitProduct()">
                    <option value="">-- เลือก Product --</option>
                    <?php foreach ($products as $product) { ?>
                        <option value="<?php echo formula_manage_h($product['pd_id']); ?>" <?php echo (int) $product['pd_id'] === $selectedProductId ? 'selected' : ''; ?>>
                            <?php echo formula_manage_h($product['pd_name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <span>เลือกแล้ว <b id="selected_count">0</b> รายการ</span>
                <a class="link-button" href="product_manage.php"><i class="fa fa-plus-circle"></i> เพิ่มชื่อ Product</a>
            </div>

            <?php if ($message !== '') { ?>
                <div id="formula_manage_flash" class="message message--<?php echo formula_manage_h($messageType); ?>" role="status">
                    <?php echo formula_manage_h($message); ?>
                </div>
            <?php } ?>

            <?php if ($selectedProductId <= 0) { ?>
                <div class="set-summary">
                    <p class="set-summary__title">
                        รายการชุด<?php echo $isRepairMode ? 'ซ่อม' : 'ผลิต'; ?> (<?php echo $isRepairMode ? 'repair_formula' : 'production_formula'; ?>)
                    </p>
                    <p class="set-summary__hint">
                        คลิกชื่อ Product ในตารางเพื่อดูรายการ Part ในชุด · เลือกจาก dropdown ด้านบนเพื่อแก้ไข<br>
                        <strong>จำนวน Part</strong> นับจาก
                        <?php echo $isRepairMode ? 'ตาราง repair_formula (ชุดซ่อม — Part ใช้ร่วมหลาย Product ได้)' : 'ตาราง production_formula (ชุดผลิต)'; ?>
                        — สองโหมดแยกข้อมูลกัน สลับแท็บด้านบนเพื่อดูอีกฝั่ง
                    </p>
                    <?php if (count($setSummaries) === 0) { ?>
                        <div class="empty-state">ยังไม่มีชุดที่ผูกอะไหล่ — เลือก Product จาก dropdown ด้านบนเพื่อจัดชุด หรือเพิ่มชื่อ Product ใหม่</div>
                    <?php } else {
                        $formulaManageListPostAction = 'formula_manage.php?set_mode=' . rawurlencode($setMode);
                        ?>
                        <div class="set-summary-table-wrap">
                            <table class="set-summary-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="col-count">จำนวน Part</th>
                                        <th class="col-action">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($setSummaries as $summary) {
                                        $summaryPdId = (int) $summary['pd_id'];
                                        $summaryCount = (int) $summary['part_count'];
                                        $editUrl = 'formula_manage.php?set_mode=' . rawurlencode($setMode) . '&pd_id=' . $summaryPdId;
                                        ?>
                                        <tr class="<?php echo $summaryCount <= 0 ? 'is-empty' : ''; ?>">
                                            <td class="col-product set-summary-product-cell" tabindex="0" role="button" title="ดูรายการ Part ในชุด" data-pd-id="<?php echo formula_manage_h($summaryPdId); ?>" data-pd-name="<?php echo formula_manage_h($summary['pd_name']); ?>" aria-label="<?php echo formula_manage_h($summary['pd_name'] . ' — ดูรายการ Part'); ?>">
                                                <div class="set-summary-product-view" id="set-name-view-<?php echo formula_manage_h($summaryPdId); ?>">
                                                    <span class="set-summary-product__name"><?php echo formula_manage_h($summary['pd_name']); ?></span>
                                                    <button type="button" class="set-summary-rename-trigger" onclick="formulaManageShowRename(<?php echo (int) $summaryPdId; ?>); return false;">แก้ชื่อ</button>
                                                </div>
                                                <form method="post" action="<?php echo formula_manage_h($formulaManageListPostAction); ?>" class="set-summary-rename is-hidden" id="set-name-form-<?php echo formula_manage_h($summaryPdId); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo formula_manage_h($csrfToken); ?>">
                                                    <input type="hidden" name="set_mode" value="<?php echo formula_manage_h($setMode); ?>">
                                                    <input type="hidden" name="form_action" value="rename_product">
                                                    <input type="hidden" name="rename_pd_id" value="<?php echo formula_manage_h($summaryPdId); ?>">
                                                    <input type="text" name="new_pd_name" value="<?php echo formula_manage_h($summary['pd_name']); ?>" maxlength="255" required autocomplete="off" aria-label="ชื่อชุด Product">
                                                    <div class="set-summary-rename__actions">
                                                        <button type="submit" class="set-summary-rename__save"><i class="fa fa-check"></i> บันทึก</button>
                                                        <button type="button" class="set-summary-rename__cancel" onclick="formulaManageCancelRename(<?php echo (int) $summaryPdId; ?>); return false;">ยกเลิก</button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td class="col-count"><?php echo formula_manage_h($summaryCount); ?></td>
                                            <td class="col-action">
                                                <div class="set-summary-actions">
                                                    <a class="set-summary-open" href="<?php echo formula_manage_h($editUrl); ?>">
                                                        <i class="fa fa-pencil"></i> แก้ไข
                                                    </a>
                                                    <?php if ($summaryCount > 0) { ?>
                                                    <form method="post" action="<?php echo formula_manage_h($formulaManageListPostAction); ?>" class="set-summary-delete-form" data-product-name="<?php echo formula_manage_h($summary['pd_name']); ?>" data-set-mode-label="<?php echo formula_manage_h($formulaManageDeleteSetLabel); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo formula_manage_h($csrfToken); ?>">
                                                        <input type="hidden" name="set_mode" value="<?php echo formula_manage_h($setMode); ?>">
                                                        <input type="hidden" name="form_action" value="delete_set">
                                                        <input type="hidden" name="delete_pd_id" value="<?php echo formula_manage_h($summaryPdId); ?>">
                                                        <button type="button" class="set-summary-delete" title="ลบรายการชุดในโหมดปัจจุบัน" onclick="formulaManageSubmitDeleteSet(this); return false;">
                                                            <i class="fa fa-trash"></i> ลบ
                                                        </button>
                                                    </form>
                                                    <?php } else { ?>
                                                    <span class="set-summary-no-delete" title="ยังไม่มีชุดในฐานข้อมูล (0 Part)">—</span>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <form method="post" action="formula_manage.php?set_mode=<?php echo formula_manage_h($setMode); ?>&amp;pd_id=<?php echo formula_manage_h($selectedProductId); ?>" data-initial-selected="<?php echo formula_manage_h($formulaManageInitialSelected); ?>" onsubmit="return formulaManageConfirmSaveSet(this);">
                    <input type="hidden" name="csrf_token" value="<?php echo formula_manage_h($csrfToken); ?>">
                    <input type="hidden" name="form_action" value="save_set">
                    <input type="hidden" name="pd_id" value="<?php echo formula_manage_h($selectedProductId); ?>">
                    <input type="hidden" name="set_mode" value="<?php echo formula_manage_h($setMode); ?>">

                    <div class="part-actions">
                        <input type="search" id="part_search" onkeyup="formulaManageFilterParts()" placeholder="ค้นหา Part">
                        <button type="button" onclick="formulaManageSetAll(true)">เลือกทั้งหมดที่แสดง</button>
                        <button type="button" onclick="formulaManageSetAll(false)">ยกเลิกทั้งหมดที่แสดง</button>
                        <button type="submit"><i class="fa fa-save"></i> <?php echo $isRepairMode ? 'บันทึกเซตซ่อม' : 'บันทึกเซตผลิต'; ?></button>
                    </div>

                    <?php if (count($parts) === 0) { ?>
                        <div class="empty-state">ยังไม่มีข้อมูล Part</div>
                    <?php } else { ?>
                        <div class="part-grid">
                            <?php foreach ($parts as $part) {
                                $partId = (int) $part['part_id'];
                                $isChecked = isset($selectedPartMap[$partId]);
                                $searchText = $part['part_name'] . ' ' . $part['ct_name'] . ' ' . $partId;
                                ?>
                                <div class="part-item" data-search="<?php echo formula_manage_h($searchText); ?>">
                                    <label>
                                        <input type="checkbox" name="part_ids[]" value="<?php echo formula_manage_h($partId); ?>" <?php echo $isChecked ? 'checked' : ''; ?> onchange="formulaManageUpdateCount()">
                                        <span>
                                            <b><?php echo formula_manage_h($part['part_name']); ?></b>
                                            <span class="part-meta">
                                                ID: <?php echo formula_manage_h($partId); ?>
                                                | หมวด: <?php echo formula_manage_h($part['ct_name'] !== '' ? $part['ct_name'] : '-'); ?>
                                                | Stock: <?php echo formula_manage_h($part['part_amount']); ?>
                                                <?php if ($isRepairMode) {
                                                    $pp = isset($part['part_product']) ? trim((string) $part['part_product']) : '';
                                                    ?>
                                                | Product: <?php echo formula_manage_h($pp !== '' ? $pp : '-'); ?>
                                                <?php } ?>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <?php if ($isRepairMode) { ?>
                        <div class="tech-set-block">
                            <h2><i class="fa fa-cogs"></i> อะไหล่คลังช่างในชุดซ่อม</h2>
                            <p class="tech-set-hint">
                                เลือกอะไหล่จากแอป parts — จำนวน «ต่อชุด» ค่าเริ่มต้น 1 (ตอนเบิก × จำนวนชุด) สูงสุด 10 รายการต่อ Product
                            </p>
                            <?php if (!$formulaManageTechAvailable) { ?>
                                <div class="empty-state">เชื่อมต่อคลังช่างไม่ได้<?php echo $formulaManageTechError !== '' ? ' — ' . formula_manage_h($formulaManageTechError) : ''; ?></div>
                            <?php } elseif (count($formulaManageTechProducts) === 0) { ?>
                                <div class="empty-state">ไม่มีรายการอะไหล่ในคลังช่าง</div>
                            <?php } else { ?>
                                <div class="part-actions">
                                    <input type="search" id="tech_part_search" onkeyup="formulaManageFilterTechParts()" placeholder="ค้นหาอะไหล่คลังช่าง">
                                    <button type="button" onclick="formulaManageSetAllTech(true)">เลือกทั้งหมดที่แสดง</button>
                                    <button type="button" onclick="formulaManageSetAllTech(false)">ยกเลิกทั้งหมดที่แสดง</button>
                                </div>
                                <div class="part-grid">
                                    <?php foreach ($formulaManageTechProducts as $techProduct) {
                                        $techId = isset($techProduct['id']) ? (int) $techProduct['id'] : 0;
                                        if ($techId <= 0) {
                                            continue;
                                        }
                                        $isTechChecked = isset($formulaManageSelectedTechQty[$techId]);
                                        $defaultQty = $isTechChecked
                                            ? $formulaManageSelectedTechQty[$techId]
                                            : PICKUP_REPAIR_TECH_DEFAULT_QTY;
                                        $code = isset($techProduct['code']) ? trim((string) $techProduct['code']) : '';
                                        $name = isset($techProduct['name']) ? trim((string) $techProduct['name']) : '';
                                        $displayName = $code !== '' ? $code . ' — ' . $name : $name;
                                        $searchText = $displayName . ' ' . $techId;
                                        ?>
                                        <div class="part-item part-item--tech" data-search="<?php echo formula_manage_h($searchText); ?>">
                                            <label>
                                                <input type="checkbox" name="tech_product_ids[]" value="<?php echo formula_manage_h($techId); ?>" <?php echo $isTechChecked ? 'checked' : ''; ?> onchange="formulaManageUpdateCount()">
                                                <span>
                                                    <b><?php echo formula_manage_h($displayName); ?></b><span class="tech-set-badge">คลังช่าง</span>
                                                    <span class="part-meta">
                                                        ID: <?php echo formula_manage_h($techId); ?>
                                                        | คงเหลือ: <?php echo formula_manage_h(isset($techProduct['quantity']) ? $techProduct['quantity'] : ''); ?>
                                                        <?php echo formula_manage_h(isset($techProduct['unit']) ? ' ' . $techProduct['unit'] : ''); ?>
                                                        | ต่อชุด:
                                                        <input type="number" class="tech-default-qty" name="tech_default_qty[<?php echo formula_manage_h($techId); ?>]" value="<?php echo formula_manage_h($defaultQty); ?>" min="0.01" step="0.01" inputmode="decimal" title="จำนวนต่อ 1 ชุด">
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </form>
            <?php } ?>
        </section>
    </main>

    <div id="fm_parts_pop" class="fm-parts-pop" aria-hidden="true" role="dialog" aria-labelledby="fm_parts_pop_title" aria-modal="true">
        <div class="fm-parts-pop__backdrop" onclick="formulaManageClosePartsPopup();"></div>
        <div class="fm-parts-pop__card">
            <button type="button" class="fm-parts-pop__close" aria-label="ปิด" onclick="formulaManageClosePartsPopup(); return false;"><i class="fa fa-times"></i></button>
            <header class="fm-parts-pop__head">
                <p class="fm-parts-pop__eyebrow">อะไหล่ในชุด</p>
                <h2 id="fm_parts_pop_title" class="fm-parts-pop__title">Product</h2>
                <p id="fm_parts_pop_meta" class="fm-parts-pop__meta"></p>
            </header>
            <div id="fm_parts_pop_body" class="fm-parts-pop__body"></div>
            <footer class="fm-parts-pop__foot">
                <a id="fm_parts_pop_edit" class="fm-parts-pop__edit" href="formula_manage.php"><i class="fa fa-pencil"></i> แก้ไขชุด</a>
            </footer>
        </div>
    </div>
</body>
</html>
