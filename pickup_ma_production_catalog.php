<?php
/**
 * โหลดรายการรุ่นสินค้าจากระบบ production (finishgoogs — ตาราง products)
 *
 * ใช้ secrets ผ่าน shared/app_paths.php ไม่ hardcode credential
 * รองรับหน้า pickup_ma.php ช่องรุ่นสินค้าโหมดเบิก MA
 *
 * Flow: pickup_ma_production_products_active_list() → เชื่อม DB → SELECT id,name
 */

// ─ Path secrets ───────────────────────────────────────────────────────────────

/**
 * คืน path ไฟล์ finishgoogs.secrets.php
 *
 * @return string path หรือค่าว่างเมื่อหาไม่พบ
 */
function pickup_ma_production_secrets_path()
{
    $sharedAppPaths = realpath(__DIR__ . '/../production/shared/app_paths.php');
    if ($sharedAppPaths === false || !is_readable($sharedAppPaths)) {
        return '';
    }

    require_once $sharedAppPaths;
    if (!function_exists('app_finishgoogs_secrets_path')) {
        return '';
    }

    $path = app_finishgoogs_secrets_path();

    return is_string($path) ? trim($path) : '';
}

// ─ DB connection ──────────────────────────────────────────────────────────────

/**
 * เปิด mysqli อ่านอย่างเดียวไปยังฐานข้อมูล production (cache ต่อ request)
 *
 * @return mysqli|null null เมื่อเชื่อมต่อไม่ได้
 */
function pickup_ma_production_db()
{
    static $conn = null;
    static $attempted = false;

    if ($attempted) {
        return $conn;
    }
    $attempted = true;

    $secretsPath = pickup_ma_production_secrets_path();
    if ($secretsPath === '' || !is_readable($secretsPath)) {
        error_log('pickup_ma_production_catalog: ไม่พบ secrets production');

        return null;
    }

    $secrets = require $secretsPath;
    if (!is_array($secrets) || !isset($secrets['production']) || !is_array($secrets['production'])) {
        error_log('pickup_ma_production_catalog: รูปแบบ secrets ไม่ถูกต้อง');

        return null;
    }

    $cfg = $secrets['production'];
    $host = isset($cfg['host']) ? trim((string) $cfg['host']) : '';
    $user = isset($cfg['user']) ? (string) $cfg['user'] : '';
    $pass = isset($cfg['pass']) ? (string) $cfg['pass'] : '';
    $dbName = isset($cfg['db']) ? trim((string) $cfg['db']) : '';

    if ($host === '' || $dbName === '') {
        error_log('pickup_ma_production_catalog: ค่า host/db ใน secrets ว่าง');

        return null;
    }

    $mysqli = @new mysqli($host, $user, $pass, $dbName);
    if ($mysqli->connect_errno) {
        error_log('pickup_ma_production_catalog: เชื่อมต่อ DB ไม่ได้');

        return null;
    }

    $mysqli->set_charset('utf8mb4');
    $conn = $mysqli;

    return $conn;
}

// ─ Catalog query ──────────────────────────────────────────────────────────────

/**
 * รายการรุ่นสินค้าที่ active จากระบบ production (เรียงตามชื่อ)
 *
 * @return array<int, array{id: int, name: string}>
 */
function pickup_ma_production_products_active_list()
{
    $conn = pickup_ma_production_db();
    if ($conn === null) {
        return array();
    }

    $sql = 'SELECT `id`, `name` FROM `products` WHERE `is_active` = 1 ORDER BY `name` ASC';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('pickup_ma_production_catalog: prepare ล้มเหลว');

        return array();
    }

    if (!$stmt->execute()) {
        error_log('pickup_ma_production_catalog: execute ล้มเหลว');
        $stmt->close();

        return array();
    }

    $result = $stmt->get_result();
    if ($result === false) {
        $stmt->close();

        return array();
    }

    $items = array();
    while ($row = $result->fetch_assoc()) {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $name = isset($row['name']) ? trim((string) $row['name']) : '';
        if ($id <= 0 || $name === '') {
            continue;
        }
        $items[] = array(
            'id' => $id,
            'name' => $name,
        );
    }

    $stmt->close();

    return $items;
}
