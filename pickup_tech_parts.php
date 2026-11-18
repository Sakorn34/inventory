<?php
/**
 * pickup_tech_parts.php — สะพานเบิกอะไหล่จาก "คลังอะไหล่ช่าง" (แอป parts ของ production)
 *
 * หน้าเบิกของรวม (pickup_ma.php) โหมด "คลังช่าง" เรียกผ่านไฟล์นี้เพื่อ
 *   1. อ่านรายการอะไหล่คงเหลือจาก DB biton_tech_parts
 *   2. บันทึกการเบิกด้วย StockService::stockOutItem() ของแอป parts โดยตรง
 *      (transaction + ล็อกแถว + กันสต็อกติดลบ + sync ไป production + LINE แจ้งเตือนสต็อกต่ำ)
 *
 * อ่าน credentials จาก D:/AppServ/secrets/production/parts.secrets.php ผ่าน
 * production/shared/app_paths.php — เหมือน pattern ของ pickup_ma_production_catalog.php
 * ไม่ hardcode credential และ fail-soft: ต่อไม่ได้คืน null ให้หน้าเบิกโหมดอื่นทำงานต่อได้
 *
 * Flow:
 *   pickup_tech_parts_db() → PDO biton_tech_parts
 *   pickup_tech_parts_service() → StockService (โหลดไลบรารีของ parts แบบ lazy)
 *   pickup_tech_parts_products_list() → รายการอะไหล่สำหรับ picker
 *   pickup_tech_parts_stock_out(...) → เบิกจริง คืนเลขที่เอกสาร OUT-...
 */

// ─ Path โปรเจค production ────────────────────────────────────────────────────

/**
 * path แอป production ที่เป็นพี่น้องกับ inventory ใน www
 *
 * @return string path หรือค่าว่างเมื่อหาไม่พบ
 */
function pickup_tech_parts_production_dir()
{
    $dir = realpath(__DIR__ . '/../production');
    return $dir !== false ? $dir : '';
}

/**
 * โหลด production/shared/app_paths.php (cache ต่อ request)
 *
 * @return bool true เมื่อโหลดได้
 */
function pickup_tech_parts_require_app_paths()
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $dir = pickup_tech_parts_production_dir();
    $file = $dir !== '' ? $dir . '/shared/app_paths.php' : '';
    $loaded = $file !== '' && is_readable($file);
    if ($loaded) {
        require_once $file;
    } else {
        error_log('pickup_tech_parts: ไม่พบ production/shared/app_paths.php');
    }
    return $loaded;
}

// ─ DB connection (biton_tech_parts) ──────────────────────────────────────────

/**
 * คืน PDO ไป biton_tech_parts หรือ null (fail-soft)
 *
 * @return PDO|null
 */
function pickup_tech_parts_db()
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    $pdo = null;
    $GLOBALS['_pickup_tech_parts_error'] = '';

    try {
        if (!pickup_tech_parts_require_app_paths() || !function_exists('app_parts_secrets_path')) {
            $GLOBALS['_pickup_tech_parts_error'] = 'ไม่พบไลบรารี path ของระบบ production';
            return null;
        }
        $secretsPath = app_parts_secrets_path();
        if ($secretsPath === '' || !is_readable($secretsPath)) {
            $GLOBALS['_pickup_tech_parts_error'] = 'ไม่พบไฟล์ secrets ของแอป parts';
            return null;
        }
        // parts.secrets.php define() ค่าคงที่ DB_HOST / DB_NAME / DB_USER / DB_PASS / DB_CHARSET
        // ระบบ inventory ไม่มี define ชื่อเดียวกัน (ตรวจแล้ว) — กัน require ซ้ำด้วย defined()
        if (!defined('DB_HOST')) {
            require $secretsPath;
        }

        $host = defined('DB_HOST') ? trim((string) constant('DB_HOST')) : '';
        $name = defined('DB_NAME') ? trim((string) constant('DB_NAME')) : '';
        $user = defined('DB_USER') ? trim((string) constant('DB_USER')) : '';
        $pass = defined('DB_PASS') ? (string) constant('DB_PASS') : '';
        $charset = defined('DB_CHARSET') && trim((string) constant('DB_CHARSET')) !== ''
            ? trim((string) constant('DB_CHARSET'))
            : 'utf8mb4';

        if ($host === '' || $name === '' || $user === '') {
            $GLOBALS['_pickup_tech_parts_error'] = 'ค่าเชื่อมต่อคลังช่างไม่ครบ (host / db / user)';
            return null;
        }

        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;
        $conn = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ));
        // qty_norm/qty_fmt ใช้ทั้ง products_list และ stock_out — โหลดที่นี่เพื่อให้
        // ทุกฟังก์ชันของไฟล์นี้ใช้ได้แม้ไม่ได้เรียก pickup_tech_parts_service() ก่อน
        $dir = pickup_tech_parts_production_dir();
        if ($dir !== '' && is_readable($dir . '/shared/qty_helpers.php')) {
            require_once $dir . '/shared/qty_helpers.php';
        }
        $pdo = $conn;
    } catch (Throwable $e) {
        error_log('[pickup_tech_parts_db] ' . $e->getMessage());
        $GLOBALS['_pickup_tech_parts_error'] = 'เชื่อมต่อฐานข้อมูลคลังช่างไม่ได้';
        $pdo = null;
    }

    return $pdo;
}

/**
 * ข้อความ error ล่าสุดของการต่อคลังช่าง (สำหรับแสดงบนหน้า)
 *
 * @return string
 */
function pickup_tech_parts_error()
{
    if (!isset($GLOBALS['_pickup_tech_parts_error'])) {
        pickup_tech_parts_db();
    }
    return (string) ($GLOBALS['_pickup_tech_parts_error'] ?? '');
}

/**
 * คลังช่างพร้อมใช้งานหรือไม่ (ใช้ตัดสินใจแสดงโหมดบนหน้า)
 *
 * @return bool
 */
function pickup_tech_parts_available()
{
    return pickup_tech_parts_db() instanceof PDO;
}

// ─ ไลบรารีของแอป parts (StockService) ────────────────────────────────────────

/**
 * โหลดไลบรารีที่ StockService ต้องใช้แล้วคืน instance (cache ต่อ request)
 *
 * ลำดับเดียวกับ parts/includes/parts_bootstrap.php แต่ตัดส่วน session/UI ที่
 * inventory มีระบบของตัวเองออก — เหลือ qty_helpers, helpers, StockService,
 * production_sync และ line_notify_core (ให้ LINE แจ้งเตือนสต็อกต่ำทำงานเหมือนเบิกใน parts)
 *
 * @return StockService|null null เมื่อต่อ DB หรือโหลดไลบรารีไม่สำเร็จ
 */
function pickup_tech_parts_service()
{
    static $service = false;
    if ($service !== false) {
        return $service;
    }
    $service = null;

    $pdo = pickup_tech_parts_db();
    if (!($pdo instanceof PDO)) {
        return null;
    }

    try {
        $dir = pickup_tech_parts_production_dir();
        if ($dir === '') {
            $GLOBALS['_pickup_tech_parts_error'] = 'ไม่พบโฟลเดอร์ระบบ production';
            return null;
        }

        // helpers.php ใช้ค่าคงที่ BASE_PATH เฉพาะในฟังก์ชัน url() ที่ flow นี้ไม่เรียก
        // กำหนดไว้ก่อนเพื่อกัน error ถ้าถูกเรียกโดยไม่คาดคิด
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', '/production/parts');
        }

        require_once $dir . '/shared/qty_helpers.php';
        require_once $dir . '/parts/includes/helpers.php';
        require_once $dir . '/parts/includes/production_sync.php';
        require_once $dir . '/parts/includes/StockService.php';
        require_once $dir . '/shared/line_notify_core.php';

        // line_notify_check_low_stock_product อ่าน connection สต็อกจาก global นี้ (ตาม parts_bootstrap)
        $GLOBALS['line_notify_stock_db'] = $pdo;

        // คอลัมน์ sync (part_movement_id ฯลฯ) — สร้างแล้วข้าม (idempotent เหมือน parts_bootstrap)
        ensure_stock_production_sync_schema($pdo);

        $service = new StockService($pdo);
    } catch (Throwable $e) {
        error_log('[pickup_tech_parts_service] ' . $e->getMessage());
        $GLOBALS['_pickup_tech_parts_error'] = 'โหลดไลบรารีคลังช่างไม่สำเร็จ — แจ้งผู้ดูแลระบบ';
        $service = null;
    }

    return $service;
}

// ─ Catalog ───────────────────────────────────────────────────────────────────

/**
 * ผูก URL รูปอะไหล่จาก production.parts ตามรหัส stock_code
 *
 * @param array<int, array<string, mixed>> $rows รายการ products
 * @return array<int, array<string, mixed>>
 */
function pickup_tech_parts_attach_icon_urls(array $rows)
{
    if ($rows === array()) {
        return $rows;
    }

    $helpers = pickup_tech_parts_production_dir() . '/parts/includes/helpers.php';
    if (!is_readable($helpers)) {
        foreach ($rows as $idx => $row) {
            $rows[$idx]['icon_url'] = '';
        }
        return $rows;
    }

    require_once $helpers;
    $icons = parts_product_icon_map($rows);
    foreach ($rows as $idx => $row) {
        $code = trim((string) ($row['code'] ?? ''));
        $path = $code !== '' && isset($icons[$code]) ? $icons[$code] : '';
        $url = parts_upload_img_url($path !== '' ? $path : null);
        $rows[$idx]['icon_url'] = $url !== null ? $url : '';
    }

    return $rows;
}

/**
 * รายการอะไหล่ที่ใช้งานอยู่ในคลังช่าง (เรียงตามชื่อ) สำหรับ picker บนหน้าเบิก
 *
 * @return array<int, array{id: int, code: string, name: string, unit: string, quantity: float, min_stock: float, icon_url: string}>
 */
/**
 * อ่านชื่ออะไหล่จาก id ในระบบ Parts (ใช้เติมชื่อเมื่อ snapshot เก็บแค่รหัส)
 *
 * @param int $productId รหัส products.id
 * @return string ชื่ออะไหล่ หรือค่าว่าง
 */
function pickup_tech_parts_product_name_by_id($productId)
{
    $productId = filter_var($productId, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
    if ($productId === false) {
        return '';
    }

    $pdo = pickup_tech_parts_db();
    if (!($pdo instanceof PDO)) {
        return '';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT `name` FROM `products` WHERE `id` = ? AND `is_active` = 1 LIMIT 1'
        );
        $stmt->execute(array((int) $productId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !isset($row['name'])) {
            return '';
        }

        return trim((string) $row['name']);
    } catch (Throwable $e) {
        error_log('[pickup_tech_parts_product_name_by_id] ' . $e->getMessage());

        return '';
    }
}

function pickup_tech_parts_products_list()
{
    $pdo = pickup_tech_parts_db();
    if (!($pdo instanceof PDO)) {
        return array();
    }

    $rows = array();
    try {
        $stmt = $pdo->query(
            'SELECT id, code, name, unit, quantity, min_stock FROM products WHERE is_active = 1 ORDER BY name ASC'
        );
        foreach ($stmt->fetchAll() as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($id <= 0 || $name === '') {
                continue;
            }
            $rows[] = array(
                'id'        => $id,
                'code'      => isset($row['code']) ? trim((string) $row['code']) : '',
                'name'      => $name,
                'unit'      => isset($row['unit']) ? trim((string) $row['unit']) : '',
                'quantity'  => qty_norm($row['quantity'] ?? 0),
                'min_stock' => qty_norm($row['min_stock'] ?? 0),
            );
        }
    } catch (Throwable $e) {
        error_log('[pickup_tech_parts_products_list] ' . $e->getMessage());
        try {
            $stmt = $pdo->query(
                'SELECT id, code, name, unit, quantity FROM products WHERE is_active = 1 ORDER BY name ASC'
            );
            foreach ($stmt->fetchAll() as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($id <= 0 || $name === '') {
                    continue;
                }
                $rows[] = array(
                    'id'        => $id,
                    'code'      => isset($row['code']) ? trim((string) $row['code']) : '',
                    'name'      => $name,
                    'unit'      => isset($row['unit']) ? trim((string) $row['unit']) : '',
                    'quantity'  => qty_norm($row['quantity'] ?? 0),
                    'min_stock' => 0.0,
                );
            }
        } catch (Throwable $fallbackEx) {
            error_log('[pickup_tech_parts_products_list:fallback] ' . $fallbackEx->getMessage());
            return array();
        }
    }

    return pickup_tech_parts_attach_icon_urls($rows);
}

// ─ วัตถุประสงค์ (สอดคล้อง pickup_ma / pre_stock) ─────────────────────────────

/**
 * รายการวัตถุประสงค์ที่อนุญาต — ต้องตรงกับปุ่มบนหน้าเบิกของรวม
 *
 * @return string[]
 */
function pickup_tech_parts_purpose_types()
{
    return array(
        'เบิกผลิต',
        'เบิกตาม PO',
        'เบิกซ่อมลูกค้า',
        'เบิกเสียระหว่างผลิต',
        'เบิกเสียระหว่างขนส่ง',
        'เบิก MA',
        'เบิกอื่นๆ',
    );
}

/**
 * ประกอบหมายเหตุใบเบิก Parts — ขึ้นต้นด้วยวัตถุประสงค์ แล้วตามด้วยรายละเอียดเอกสาร
 *
 * @param string $purpose วัตถุประสงค์ (pre_type)
 * @param string $detail  หมายเหตุ / ลูกค้า / S/N
 * @return string
 */
function pickup_tech_parts_compose_stock_note($purpose, $detail)
{
    $purpose = trim((string) $purpose);
    $detail = trim((string) $detail);
    if ($purpose === '') {
        return $detail;
    }
    if ($detail === '') {
        return $purpose;
    }

    return $purpose . ' | ' . $detail;
}

// ─ เบิกจริง ──────────────────────────────────────────────────────────────────

/**
 * เบิกอะไหล่จากคลังช่างหนึ่งรายการ — ตัดสต็อกทันทีผ่าน StockService::stockOutItem()
 *
 * โยน RuntimeException/InvalidArgumentException (ข้อความไทยจาก StockService)
 * เมื่อเบิกไม่สำเร็จ เช่น คงเหลือไม่พอ — caller ต้อง catch แล้วรายงานต่อบรรทัด
 *
 * @param int         $productId รหัส products.id ของคลังช่าง
 * @param float       $quantity  จำนวน (ทศนิยมได้ 2 ตำแหน่ง เช่น 0.5)
 * @param string|null $note      หมายเหตุ
 * @param string|null $issuedBy  ชื่อผู้เบิก (จาก inventory_session_actor)
 * @param string|null $assetCode S/N เครื่อง (ถ้ามี)
 * @return string เลขที่เอกสารเบิก เช่น OUT-20261114-0001
 */
function pickup_tech_parts_stock_out($productId, $quantity, $note = null, $issuedBy = null, $assetCode = null)
{
    $service = pickup_tech_parts_service();
    if (!($service instanceof StockService)) {
        throw new RuntimeException(pickup_tech_parts_error() !== ''
            ? pickup_tech_parts_error()
            : 'ระบบคลังช่างยังไม่พร้อมใช้งาน');
    }

    return $service->stockOutItem(
        (int) $productId,
        qty_norm($quantity),
        $note !== null && trim($note) !== '' ? trim((string) $note) : null,
        $issuedBy !== null && trim($issuedBy) !== '' ? trim((string) $issuedBy) : null,
        $assetCode !== null && trim($assetCode) !== '' ? trim((string) $assetCode) : null
    );
}

// ─ ยกเลิกการเบิก (เมื่อลบเอกสารรอจ่าย) ─────────────────────────────────────

/**
 * แปลงเลขเอกสาร pre_id (ymd-His) เป็น DateTimeImmutable
 *
 * @param string $preId เช่น 261116-112817
 * @return DateTimeImmutable|null
 */
function pickup_tech_parts_pre_id_to_datetime($preId)
{
    $preId = trim((string) $preId);
    if (!preg_match('/^(\d{6})-(\d{6})$/', $preId, $m)) {
        return null;
    }
    $tz = new DateTimeZone('Asia/Bangkok');
    $iso = '20' . substr($m[1], 0, 2) . '-' . substr($m[1], 2, 2) . '-' . substr($m[1], 4, 2)
        . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2) . ':' . substr($m[2], 4, 2);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $iso, $tz);

    return $dt instanceof DateTimeImmutable ? $dt : null;
}

/**
 * หา stock_out.id จากเลขที่เอกสาร OUT-...
 *
 * @param string $docNo
 * @return int|null
 */
function pickup_tech_parts_stock_out_id_by_doc_no($docNo)
{
    $docNo = trim((string) $docNo);
    if ($docNo === '') {
        return null;
    }
    $pdo = pickup_tech_parts_db();
    if (!($pdo instanceof PDO)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM stock_out WHERE doc_no = ? LIMIT 1');
    $stmt->execute(array($docNo));
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/**
 * จับคู่ใบเบิก Parts กับแถว pre_stock_tech ที่ไม่มี parts_doc_no (เอกสารเก่า)
 *
 * @param int                  $productId   products.id คลังช่าง
 * @param float                $quantity    จำนวนเบิก
 * @param string               $assetCode   S/N บรรทัด (ว่างได้)
 * @param string               $preId       เลขเอกสาร inventory
 * @param array<int, int>      $excludeIds  รหัสที่ลบไปแล้วในรอบเดียวกัน
 * @return int|null
 */
function pickup_tech_parts_find_stock_out_for_tech_line($productId, $quantity, $assetCode, $preId, array $excludeIds = array())
{
    $productId = (int) $productId;
    if ($productId <= 0) {
        return null;
    }
    $pdo = pickup_tech_parts_db();
    if (!($pdo instanceof PDO)) {
        return null;
    }
    if (!function_exists('qty_norm')) {
        $dir = pickup_tech_parts_production_dir();
        if ($dir !== '' && is_readable($dir . '/shared/qty_helpers.php')) {
            require_once $dir . '/shared/qty_helpers.php';
        }
    }
    $quantity = function_exists('qty_norm') ? qty_norm($quantity) : (float) $quantity;
    $qtySql = function_exists('qty_sql') ? qty_sql($quantity) : number_format($quantity, 2, '.', '');

    $anchor = pickup_tech_parts_pre_id_to_datetime($preId);
    if ($anchor === null) {
        return null;
    }
    $from = $anchor->modify('-10 minutes')->format('Y-m-d H:i:s');
    $to = $anchor->modify('+30 minutes')->format('Y-m-d H:i:s');

    $assetCode = trim((string) $assetCode);
    $exclude = array();
    foreach ($excludeIds as $eid) {
        $eid = (int) $eid;
        if ($eid > 0) {
            $exclude[] = $eid;
        }
    }
    $excludeSql = $exclude !== array() ? ' AND so.id NOT IN (' . implode(',', $exclude) . ')' : '';

    $sql = 'SELECT so.id
            FROM stock_out so
            INNER JOIN stock_out_items soi ON soi.stock_out_id = so.id
            WHERE so.set_id IS NULL
              AND soi.product_id = ?
              AND soi.quantity = ?
              AND so.created_at >= ?
              AND so.created_at <= ?'
        . ($assetCode !== '' ? ' AND so.asset_code = ?' : ' AND (so.asset_code IS NULL OR so.asset_code = \'\')')
        . $excludeSql
        . ' ORDER BY so.created_at ASC
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $params = array($productId, $qtySql, $from, $to);
    if ($assetCode !== '') {
        $params[] = $assetCode;
    }
    $stmt->execute($params);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/**
 * ลบใบเบิก Parts ที่ผูกกับเอกสารรอจ่าย (อ่านจาก pre_stock_tech)
 *
 * @param mysqli $conn    connection inventory
 * @param string $preId   เลขเอกสาร pre
 * @return array{ok:bool, deleted:int, message:string}
 */
function pickup_tech_parts_revert_for_pre_document($conn, $preId)
{
    $preId = trim((string) $preId);
    $result = array('ok' => true, 'deleted' => 0, 'message' => '');

    if ($preId === '' || !($conn instanceof mysqli)) {
        return $result;
    }

    if (!function_exists('pickup_pre_stock_tech_fetch_lines')) {
        require_once __DIR__ . '/pickup_pre_stock_tech.php';
    }

    $lines = pickup_pre_stock_tech_fetch_lines($conn, $preId);
    if ($lines === array()) {
        return $result;
    }

    $service = pickup_tech_parts_service();
    if (!($service instanceof StockService)) {
        $result['ok'] = false;
        $result['message'] = pickup_tech_parts_error() !== ''
            ? pickup_tech_parts_error()
            : 'ไม่สามารถเชื่อมต่อระบบ Parts เพื่อยกเลิกการเบิกคลังช่างได้';

        return $result;
    }

    $usedIds = array();
    foreach ($lines as $line) {
        $outId = null;
        $docNo = isset($line['parts_doc_no']) ? trim((string) $line['parts_doc_no']) : '';
        if ($docNo !== '') {
            $outId = pickup_tech_parts_stock_out_id_by_doc_no($docNo);
        }
        if ($outId === null || $outId <= 0) {
            $sn = isset($line['remarks']) ? (string) $line['remarks'] : '';
            $techId = isset($line['tech_product_id']) ? (int) $line['tech_product_id'] : 0;
            $amount = isset($line['amount']) ? (float) $line['amount'] : 0.0;
            $outId = pickup_tech_parts_find_stock_out_for_tech_line($techId, $amount, $sn, $preId, $usedIds);
        }
        if ($outId === null || $outId <= 0) {
            $label = isset($line['part_name']) ? (string) $line['part_name'] : 'รายการคลังช่าง';
            $result['ok'] = false;
            $result['message'] = 'ไม่พบใบเบิก Parts ของ「' . $label . '」— ลบเอกสารไม่สำเร็จ (กรุณายกเลิกใน Parts ด้วยตนเองแล้วลองใหม่)';

            return $result;
        }

        try {
            $service->deleteStockOut($outId);
            $usedIds[] = $outId;
            $result['deleted']++;
        } catch (Throwable $e) {
            error_log('[pickup_tech_parts_revert_for_pre] pre_id=' . $preId . ' out=' . $outId . ': ' . $e->getMessage());
            $result['ok'] = false;
            $result['message'] = 'ยกเลิกการเบิก Parts ไม่สำเร็จ: ' . $e->getMessage();

            return $result;
        }
    }

    return $result;
}
