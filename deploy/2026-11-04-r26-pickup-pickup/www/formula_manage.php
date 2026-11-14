<?php
/**
 * หน้าจัดชุดรายการเบิก Part ตาม Product สำหรับระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือเลือก Product แล้วกำหนดรายการ Part สำหรับเบิกเป็นเซต
 * รองรับสองโหมด: เซตผลิต (`production_formula`) และเซตซ่อม (`part.part_product`)
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
        $like = '%' . $pdName . '%';
        $rows = formula_manage_query(
            $conn,
            'SELECT COUNT(DISTINCT `part_id`) AS `part_count` FROM `part` WHERE `part_product` LIKE ?',
            's',
            array($like)
        );
        $partCount = isset($rows[0]['part_count']) ? (int) $rows[0]['part_count'] : 0;
        $summaries[] = array(
            'pd_id' => $pdId,
            'pd_name' => $pdName,
            'part_count' => $partCount,
        );
    }

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
    $configuredIds = array();
    foreach ($summaries as $row) {
        $configuredIds[(int) $row['pd_id']] = true;
    }

    foreach (formula_manage_get_products($conn) as $product) {
        $pdId = (int) $product['pd_id'];
        if (!isset($configuredIds[$pdId])) {
            $summaries[] = array(
                'pd_id' => $pdId,
                'pd_name' => (string) $product['pd_name'],
                'part_count' => 0,
            );
        }
    }

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

    $like = '%' . (string) $product['pd_name'] . '%';
    $rows = formula_manage_query(
        $conn,
        'SELECT `part_id` FROM `part` WHERE `part_product` LIKE ?',
        's',
        array($like)
    );

    $map = array();
    foreach ($rows as $row) {
        $map[(int) $row['part_id']] = true;
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

    $pdName = (string) $product['pd_name'];
    $like = '%' . $pdName . '%';
    $cleanPartIds = formula_manage_normalize_part_ids($partIds);
    $selectedMap = array();
    foreach ($cleanPartIds as $partId) {
        $selectedMap[$partId] = true;
    }

    $validParts = array();
    if (count($cleanPartIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($cleanPartIds), '?'));
        $types = str_repeat('i', count($cleanPartIds));
        $rows = formula_manage_query(
            $conn,
            "SELECT `part_id` FROM `part` WHERE `part_id` IN ($placeholders)",
            $types,
            $cleanPartIds
        );
        foreach ($rows as $row) {
            $validParts[(int) $row['part_id']] = true;
        }
    }

    $currentMap = formula_manage_get_selected_repair_part_map($conn, $productId);

    mysqli_begin_transaction($conn);
    try {
        $savedCount = 0;
        foreach ($cleanPartIds as $partId) {
            if (!isset($validParts[$partId])) {
                continue;
            }
            $updateStmt = formula_manage_execute(
                $conn,
                'UPDATE `part` SET `part_product` = ? WHERE `part_id` = ?',
                'si',
                array($pdName, $partId)
            );
            mysqli_stmt_close($updateStmt);
            $savedCount++;
        }

        foreach (array_keys($currentMap) as $partId) {
            if (isset($selectedMap[$partId])) {
                continue;
            }
            $clearStmt = formula_manage_execute(
                $conn,
                'UPDATE `part` SET `part_product` = ? WHERE `part_id` = ? AND `part_product` LIKE ?',
                'sis',
                array('', $partId, $like)
            );
            mysqli_stmt_close($clearStmt);
        }

        mysqli_commit($conn);

        return $savedCount;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
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
 * อัปเดต part_product หลังเปลี่ยนชื่อ Product ให้สอดคล้องเกณฑ์ชุดซ่อม (LIKE ชื่อเดิม)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $oldName ชื่อ Product ก่อนเปลี่ยน
 * @param string $cleanName ชื่อ Product ใหม่
 * @return void ไม่มี return value
 */
function formula_manage_rename_sync_part_product($conn, $oldName, $cleanName)
{
    if ($oldName === '' || strcasecmp($oldName, $cleanName) === 0) {
        return;
    }

    $like = '%' . $oldName . '%';
    $rows = formula_manage_query(
        $conn,
        'SELECT `part_id`, `part_product` FROM `part` WHERE `part_product` LIKE ?',
        's',
        array($like)
    );

    foreach ($rows as $row) {
        $partId = (int) $row['part_id'];
        $current = (string) $row['part_product'];
        if ($current === $oldName) {
            $next = $cleanName;
        } else {
            $next = str_replace($oldName, $cleanName, $current);
        }
        if ($next === $current) {
            continue;
        }
        $updateStmt = formula_manage_execute(
            $conn,
            'UPDATE `part` SET `part_product` = ? WHERE `part_id` = ?',
            'si',
            array($next, $partId)
        );
        mysqli_stmt_close($updateStmt);
    }
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

        formula_manage_rename_sync_part_product($conn, $oldName, $cleanName);

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

// ─ Request Handling ──────────────────────────────────────────────────────────

$message = '';
$messageType = 'info';
$selectedProductId = 0;
$setMode = 'production';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        formula_manage_validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '');
        $setMode = formula_manage_normalize_set_mode(isset($_POST['set_mode']) ? $_POST['set_mode'] : 'production');
        $formAction = isset($_POST['form_action']) ? trim((string) $_POST['form_action']) : 'save_set';

        if ($formAction === 'rename_product') {
            $renamePdId = formula_manage_positive_int(isset($_POST['rename_pd_id']) ? $_POST['rename_pd_id'] : 0);
            $newName = isset($_POST['new_pd_name']) ? $_POST['new_pd_name'] : '';
            formula_manage_rename_product($connect, $renamePdId, $newName, $setMode === 'repair');
            $message = 'เปลี่ยนชื่อชุด (Product) สำเร็จ';
            $messageType = 'success';
            $selectedProductId = 0;
        } else {
            $selectedProductId = formula_manage_positive_int(isset($_POST['pd_id']) ? $_POST['pd_id'] : 0);
            $postedParts = isset($_POST['part_ids']) && is_array($_POST['part_ids']) ? $_POST['part_ids'] : array();
            if ($setMode === 'repair') {
                $savedCount = formula_manage_save_repair_set($connect, $selectedProductId, $postedParts);
                $message = 'บันทึกเซตซ่อมสำเร็จ จำนวน Part ในชุด: ' . $savedCount . ' รายการ (part_product)';
            } else {
                $savedCount = formula_manage_save_formula($connect, $selectedProductId, $postedParts);
                $message = 'บันทึกเซตผลิตสำเร็จ จำนวน Part ในชุด: ' . $savedCount . ' รายการ (production_formula)';
            }
            $messageType = 'success';
        }
    } else {
        $selectedProductId = formula_manage_positive_int(isset($_GET['pd_id']) ? $_GET['pd_id'] : 0);
        $setMode = formula_manage_normalize_set_mode(isset($_GET['set_mode']) ? $_GET['set_mode'] : 'production');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
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
            width: 110px;
            text-align: center;
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
            var checked = document.querySelectorAll('input[name="part_ids[]"]:checked').length;
            document.getElementById('selected_count').innerText = checked;
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
            var checked = document.querySelectorAll('input[name="part_ids[]"]:checked').length;
            var initial = parseInt(form.getAttribute('data-initial-selected') || '0', 10);
            if (initial > 0 && checked === 0) {
                return window.confirm(
                    'ไม่ได้เลือก Part ใดๆ — การบันทึกจะลบรายการในชุดของ Product นี้ทั้งหมด\nต้องการบันทึกต่อหรือไม่?'
                );
            }
            return true;
        }
    </script>
</head>
<body onload="formulaManageUpdateCount()">
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
                    ใช้กับปุ่ม <b>เบิกเซตซ่อม</b> บนหน้าเบิกของ — บันทึกลงฟิลด์ <b>Product</b> ของ Part (<code>part_product</code>)
                    ให้ตรงชื่อ Product ที่เลือก (โหลดด้วยเงื่อนไขเดียวกับหน้าเบิก)
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
                <div class="message message--<?php echo formula_manage_h($messageType); ?>">
                    <?php echo formula_manage_h($message); ?>
                </div>
            <?php } ?>

            <?php if ($selectedProductId <= 0) { ?>
                <div class="set-summary">
                    <p class="set-summary__title">
                        รายการชุด<?php echo $isRepairMode ? 'ซ่อม' : 'ผลิต'; ?> (<?php echo $isRepairMode ? 'part_product' : 'production_formula'; ?>)
                    </p>
                    <p class="set-summary__hint">
                        เลือก Product จากตารางหรือ dropdown ด้านบนเพื่อแก้ไขรายการ Part ในชุด<br>
                        <strong>จำนวน Part</strong> นับจาก
                        <?php echo $isRepairMode ? 'ฟิลด์ part_product (ชุดซ่อม)' : 'ตาราง production_formula (ชุดผลิต)'; ?>
                        — สองโหมดแยกข้อมูลกัน สลับแท็บด้านบนเพื่อดูอีกฝั่ง
                    </p>
                    <?php if (count($setSummaries) === 0) { ?>
                        <div class="empty-state">ยังไม่มี Product — เพิ่มชื่อ Product ก่อนจัดชุด</div>
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
                                            <td class="col-product">
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
                                                <a class="set-summary-open" href="<?php echo formula_manage_h($editUrl); ?>">
                                                    <i class="fa fa-pencil"></i> แก้ไข
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <form method="post" action="formula_manage.php?set_mode=<?php echo formula_manage_h($setMode); ?>&amp;pd_id=<?php echo formula_manage_h($selectedProductId); ?>" data-initial-selected="<?php echo formula_manage_h(count($selectedPartMap)); ?>" onsubmit="return formulaManageConfirmSaveSet(this);">
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
                </form>
            <?php } ?>
        </section>
    </main>
</body>
</html>
