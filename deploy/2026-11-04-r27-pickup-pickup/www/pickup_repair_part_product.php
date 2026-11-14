<?php
/**
 * ค่า part_product สำหรับชุดซ่อม — แยกชุดตาม pd_id
 *
 * ฟิลด์ part.part_product เก็บได้เพียงค่าเดียวต่อ Part การใช้ LIKE ชื่อ Product ทำให้
 * Product ชื่อซ้ำหรือชื่อย่อยทับกัน (เช่น A4 กับ A4-Camera) แย่ง Part กันเมื่อบันทึกชุดซ่อม
 * รูปแบบ canonical: `{pd_name} [pd:{pd_id}]` ใช้กับ formula_manage / pickup_set_formula / pre_pickup_save
 *
 * ตัวอย่าง: pickup_repair_part_product_canonical('A4-Camera', 12) → 'A4-Camera [pd:12]'
 */

// ─ Constants ─────────────────────────────────────────────────────────────────

if (!defined('PICKUP_REPAIR_PD_TAG_PREFIX')) {
    define('PICKUP_REPAIR_PD_TAG_PREFIX', ' [pd:');
}

if (!defined('PICKUP_REPAIR_PD_TAG_SUFFIX')) {
    define('PICKUP_REPAIR_PD_TAG_SUFFIX', ']');
}

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * สร้างค่า part_product แบบ canonical ผูกกับ pd_id
 *
 * @param string $pdName ชื่อ Product
 * @param int $productId pd_id
 * @return string ค่าที่บันทึกลง part_product
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
 * นับจำนวน Product ที่ใช้ชื่อเดียวกัน (ไม่สน pd_id)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $pdName ชื่อ Product
 * @return int จำนวนแถว product
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
 * ตรวจว่าชื่อ Product ไม่ซ้ำในระบบ (ใช้รองรับข้อมูล part_product แบบเก่า)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $pdName ชื่อ Product
 * @return bool true เมื่อมี Product ชื่อนี้เพียงรายการเดียว
 */
function pickup_repair_product_name_is_unique($conn, $pdName)
{
    return pickup_repair_product_name_usage_count($conn, $pdName) <= 1;
}

/**
 * สร้างเงื่อนไข SQL + bind สำหรับ Part ที่อยู่ในชุดซ่อมของ Product นี้เท่านั้น
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product
 * @return array{where:string,types:string,params:array<int|string>}
 */
function pickup_repair_part_scope_bind($conn, $productId, $pdName)
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
 * นับ Part ในชุดซ่อมของ Product ตาม scope pd_id
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product
 * @return int จำนวน Part
 */
function pickup_repair_part_scope_count($conn, $productId, $pdName)
{
    $scope = pickup_repair_part_scope_bind($conn, $productId, $pdName);
    $sql = 'SELECT COUNT(DISTINCT `part_id`) AS `part_count` FROM `part` WHERE ' . $scope['where'];
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        return 0;
    }

    $params = $scope['params'];
    $types = $scope['types'];
    pickup_repair_stmt_bind_params($stmt, $types, $params);
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
 * ดึง part_id ในชุดซ่อมของ Product ตาม scope pd_id
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product
 * @return int[] รายการ part_id
 */
function pickup_repair_part_scope_ids($conn, $productId, $pdName)
{
    $scope = pickup_repair_part_scope_bind($conn, $productId, $pdName);
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
 * ตรวจว่ามี Product ที่ชื่อซ้ำกันในระบบ (แจ้งเตือนบนหน้าจัดชุดซ่อม)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return bool true เมื่อมีชื่อ Product ซ้ำอย่างน้อย 1 ชื่อ
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
