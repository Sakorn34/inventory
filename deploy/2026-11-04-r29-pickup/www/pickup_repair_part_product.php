<?php
/**
 * ชุดซ่อม (repair set) — ตาราง repair_formula แยกตาม pd_id + part_id
 *
 * อนุญาตให้ Part ตัวเดียวกันอยู่ในชุดซ่อมของหลาย Product ได้ (many-to-many)
 * ไม่ใช้ part.part_product เป็นที่เก็บชุดอีกต่อไป — ฟิลด์นั้นยังใช้เป็นข้อมูลอ้างอิง Part ได้ตามเดิม
 * สร้างตารางอัตโนมัติครั้งแรก และย้ายข้อมูลจาก part_product แบบเก่าเมื่อตารางว่าง
 *
 * ตัวอย่าง: pickup_repair_part_scope_count($conn, 12, 'A4-Camera');
 */

// ─ Constants ─────────────────────────────────────────────────────────────────

if (!defined('PICKUP_REPAIR_PD_TAG_PREFIX')) {
    define('PICKUP_REPAIR_PD_TAG_PREFIX', ' [pd:');
}

if (!defined('PICKUP_REPAIR_PD_TAG_SUFFIX')) {
    define('PICKUP_REPAIR_PD_TAG_SUFFIX', ']');
}

// ─ Schema & bootstrap ────────────────────────────────────────────────────────

/**
 * สร้างตาราง repair_formula ถ้ายังไม่มี
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return void ไม่มี return value
 */
function pickup_repair_formula_create_table($conn)
{
    $sql = 'CREATE TABLE IF NOT EXISTS `repair_formula` (
        `repair_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pd_id` INT NOT NULL,
        `pd_name` VARCHAR(255) NOT NULL DEFAULT \'\',
        `part_id` INT NOT NULL,
        `ct_id` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`repair_id`),
        UNIQUE KEY `uk_repair_pd_part` (`pd_id`, `part_id`),
        KEY `idx_repair_pd_id` (`pd_id`),
        KEY `idx_repair_part_id` (`part_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8';

    if (!mysqli_query($conn, $sql)) {
        error_log('pickup_repair_formula_create_table: ' . mysqli_error($conn));
    }
}

/**
 * นับแถวใน repair_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return int จำนวนแถว
 */
function pickup_repair_formula_row_count($conn)
{
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS `cnt` FROM `repair_formula`');
    if ($result === false) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return isset($row['cnt']) ? (int) $row['cnt'] : 0;
}

/**
 * เตรียม schema และย้ายข้อมูล legacy ครั้งเดียวเมื่อตารางว่าง
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return void ไม่มี return value
 */
function pickup_repair_formula_bootstrap($conn)
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }
    $bootstrapped = true;

    pickup_repair_formula_create_table($conn);
    if (pickup_repair_formula_row_count($conn) > 0) {
        return;
    }

    pickup_repair_formula_migrate_from_part_product($conn);
}

// ─ Legacy part_product (ใช้เฉพาะ migrate) ────────────────────────────────────

/**
 * สร้างค่า part_product แบบ canonical ผูกกับ pd_id (ข้อมูลเก่า)
 *
 * @param string $pdName ชื่อ Product
 * @param int $productId pd_id
 * @return string ค่า part_product แบบเก่า
 */
function pickup_repair_part_product_canonical($pdName, $productId)
{
    $name = trim((string) $pdName);
    $id = (int) $productId;
    if ($name === '' || $id <= 0) {
        return '';
    }

    return $name . PICKUP_REPAIR_PD_TAG_PREFIX . $id . PICKUP_REPAIR_PD_TAG_SUFFIX;
}

/**
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $pdName ชื่อ Product
 * @return int จำนวน Product ที่ใช้ชื่อนี้
 */
function pickup_repair_product_name_usage_count($conn, $pdName)
{
    $name = trim((string) $pdName);
    if ($name === '') {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS `cnt` FROM `product` WHERE LOWER(TRIM(`pd_name`)) = LOWER(?)'
    );
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 's', $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return 0;
    }
    mysqli_stmt_bind_result($stmt, $countRaw);
    $count = 0;
    if (mysqli_stmt_fetch($stmt)) {
        $count = (int) $countRaw;
    }
    mysqli_stmt_close($stmt);

    return $count;
}

/**
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $pdName ชื่อ Product
 * @return bool true เมื่อชื่อไม่ซ้ำ
 */
function pickup_repair_product_name_is_unique($conn, $pdName)
{
    return pickup_repair_product_name_usage_count($conn, $pdName) <= 1;
}

/**
 * เงื่อนไข part_product แบบเก่า ( migrate )
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product
 * @return array{where:string,types:string,params:array}
 */
function pickup_repair_legacy_part_scope_bind($conn, $productId, $pdName)
{
    $canonical = pickup_repair_part_product_canonical($pdName, $productId);
    $trimName = trim((string) $pdName);

    if ($trimName !== '' && pickup_repair_product_name_is_unique($conn, $trimName)) {
        return array(
            'where' => '(`part_product` = ? OR `part_product` = ?)',
            'types' => 'ss',
            'params' => array($canonical, $trimName),
        );
    }

    return array(
        'where' => '`part_product` = ?',
        'types' => 's',
        'params' => array($canonical),
    );
}

/**
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product
 * @return int[] part_id จาก part_product เก่า
 */
function pickup_repair_legacy_part_scope_ids($conn, $productId, $pdName)
{
    $scope = pickup_repair_legacy_part_scope_bind($conn, $productId, $pdName);
    $sql = 'SELECT `part_id` FROM `part` WHERE ' . $scope['where'];
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        return array();
    }

    pickup_repair_stmt_bind_params($stmt, $scope['types'], $scope['params']);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return array();
    }

    mysqli_stmt_bind_result($stmt, $partIdRaw);
    $ids = array();
    while (mysqli_stmt_fetch($stmt)) {
        $ids[] = (int) $partIdRaw;
    }
    mysqli_stmt_close($stmt);

    return $ids;
}

/**
 * ย้ายชุดซ่อมจาก part_product ไป repair_formula เมื่อตารางใหม่ว่าง
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return void ไม่มี return value
 */
function pickup_repair_formula_migrate_from_part_product($conn)
{
    $productResult = mysqli_query($conn, 'SELECT `pd_id`, `pd_name` FROM `product` ORDER BY `pd_id` ASC');
    if ($productResult === false) {
        return;
    }

    while ($product = mysqli_fetch_assoc($productResult)) {
        $pdId = (int) $product['pd_id'];
        $pdName = (string) $product['pd_name'];
        $partIds = pickup_repair_legacy_part_scope_ids($conn, $pdId, $pdName);
        if ($partIds === array()) {
            continue;
        }
        pickup_repair_formula_replace($conn, $pdId, $pdName, $partIds);
    }
    mysqli_free_result($productResult);
}

// ─ repair_formula CRUD ───────────────────────────────────────────────────────

/**
 * แทนที่รายการ Part ในชุดซ่อมของ Product (Part ตัวเดียวกันใช้กับ Product อื่นได้)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product
 * @param int[] $partIds รายการ part_id
 * @return int จำนวน Part ที่บันทึก
 */
function pickup_repair_formula_replace($conn, $productId, $pdName, array $partIds)
{
    pickup_repair_formula_bootstrap($conn);

    $productId = (int) $productId;
    $pdName = trim((string) $pdName);
    if ($productId <= 0) {
        return 0;
    }

    $uniquePartIds = array();
    foreach ($partIds as $partId) {
        $id = (int) $partId;
        if ($id > 0) {
            $uniquePartIds[$id] = true;
        }
    }
    $partIdList = array_keys($uniquePartIds);

    $validCategory = array();
    if ($partIdList !== array()) {
        $placeholders = implode(',', array_fill(0, count($partIdList), '?'));
        $types = str_repeat('i', count($partIdList));
        $stmt = mysqli_prepare(
            $conn,
            "SELECT `part_id`, `part_categories` FROM `part` WHERE `part_id` IN ($placeholders)"
        );
        if ($stmt !== false) {
            pickup_repair_stmt_bind_params($stmt, $types, $partIdList);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_bind_result($stmt, $pidRaw, $catRaw);
                while (mysqli_stmt_fetch($stmt)) {
                    $validCategory[(int) $pidRaw] = (int) $catRaw;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    mysqli_begin_transaction($conn);
    try {
        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM `repair_formula` WHERE `pd_id` = ?');
        if ($deleteStmt === false) {
            throw new RuntimeException('ลบชุดซ่อมเดิมไม่สำเร็จ');
        }
        mysqli_stmt_bind_param($deleteStmt, 'i', $productId);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        $insertStmt = mysqli_prepare(
            $conn,
            'INSERT INTO `repair_formula` (`pd_id`, `pd_name`, `part_id`, `ct_id`) VALUES (?, ?, ?, ?)'
        );
        if ($insertStmt === false) {
            throw new RuntimeException('เตรียมบันทึกชุดซ่อมไม่สำเร็จ');
        }

        $saved = 0;
        foreach ($partIdList as $partId) {
            if (!isset($validCategory[$partId])) {
                continue;
            }
            $categoryId = $validCategory[$partId];
            mysqli_stmt_bind_param($insertStmt, 'isii', $productId, $pdName, $partId, $categoryId);
            mysqli_stmt_execute($insertStmt);
            $saved++;
        }
        mysqli_stmt_close($insertStmt);
        mysqli_commit($conn);

        return $saved;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * ลบชุดซ่อมทั้งหมดของ Product (ไม่กระทบ production_formula)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @return int จำนวนแถวที่ลบ
 */
function pickup_repair_formula_delete_for_product($conn, $productId)
{
    pickup_repair_formula_bootstrap($conn);

    $productId = (int) $productId;
    if ($productId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($conn, 'DELETE FROM `repair_formula` WHERE `pd_id` = ?');
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $deleted >= 0 ? (int) $deleted : 0;
}

/**
 * อัปเดตชื่อ Product ใน repair_formula หลังเปลี่ยนชื่อ
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $newPdName ชื่อใหม่
 * @return void ไม่มี return value
 */
function pickup_repair_formula_update_pd_name($conn, $productId, $newPdName)
{
    pickup_repair_formula_bootstrap($conn);

    $productId = (int) $productId;
    $newPdName = trim((string) $newPdName);
    if ($productId <= 0 || $newPdName === '') {
        return;
    }

    $stmt = mysqli_prepare($conn, 'UPDATE `repair_formula` SET `pd_name` = ? WHERE `pd_id` = ?');
    if ($stmt === false) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'si', $newPdName, $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ─ Public read API (ใช้ทั่ว pickup / formula_manage) ───────────────────────────

/**
 * นับ Part ในชุดซ่อมของ Product
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product (ไม่ใช้ filter — คง signature เดิม)
 * @return int จำนวน Part
 */
function pickup_repair_part_scope_count($conn, $productId, $pdName)
{
    pickup_repair_formula_bootstrap($conn);

    $productId = (int) $productId;
    if ($productId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(DISTINCT `part_id`) AS `part_count` FROM `repair_formula` WHERE `pd_id` = ?'
    );
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return 0;
    }
    mysqli_stmt_bind_result($stmt, $countRaw);
    $count = 0;
    if (mysqli_stmt_fetch($stmt)) {
        $count = (int) $countRaw;
    }
    mysqli_stmt_close($stmt);

    return $count;
}

/**
 * ดึง part_id ในชุดซ่อมของ Product
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product (ไม่ใช้ filter)
 * @return int[] รายการ part_id
 */
function pickup_repair_part_scope_ids($conn, $productId, $pdName)
{
    pickup_repair_formula_bootstrap($conn);

    $productId = (int) $productId;
    if ($productId <= 0) {
        return array();
    }

    $stmt = mysqli_prepare($conn, 'SELECT `part_id` FROM `repair_formula` WHERE `pd_id` = ?');
    if ($stmt === false) {
        return array();
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return array();
    }

    mysqli_stmt_bind_result($stmt, $partIdRaw);
    $ids = array();
    while (mysqli_stmt_fetch($stmt)) {
        $ids[] = (int) $partIdRaw;
    }
    mysqli_stmt_close($stmt);

    return $ids;
}

/**
 * เงื่อนไข JOIN repair_formula สำหรับ query ที่มี alias rf
 *
 * @param int $productId pd_id
 * @return array{where:string,types:string,params:array}
 */
function pickup_repair_part_scope_bind($conn, $productId, $pdName)
{
    pickup_repair_formula_bootstrap($conn);

    return array(
        'where' => 'rf.`pd_id` = ?',
        'types' => 'i',
        'params' => array((int) $productId),
    );
}

/**
 * bind parameter แบบ dynamic สำหรับ mysqli_stmt
 *
 * @param mysqli_stmt $stmt prepared statement
 * @param string $types ชนิด parameter
 * @param array $params ค่า parameter
 * @return void ไม่มี return value
 */
function pickup_repair_stmt_bind_params($stmt, $types, array $params)
{
    if ($types === '' || $params === array()) {
        return;
    }

    $refs = array();
    $refs[] = &$types;
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }

    call_user_func_array(array($stmt, 'bind_param'), $refs);
}

/**
 * ตรวจว่ามี Product ที่ชื่อซ้ำกันในระบบ
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return bool true เมื่อมีชื่อ Product ซ้ำ
 */
function pickup_repair_has_duplicate_product_names($conn)
{
    $sql = 'SELECT 1 FROM `product` GROUP BY LOWER(TRIM(`pd_name`)) HAVING COUNT(*) > 1 LIMIT 1';
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        return false;
    }
    $has = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);

    return $has;
}
