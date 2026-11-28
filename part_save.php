<?php
/**
 * Endpoint บันทึกและแก้ไขข้อมูล Part
 *
 * รับข้อมูลจาก `part_add.php` และ `part_edit.php` ผ่าน POST แล้ว insert หรือ update
 * ตาราง `part` ด้วย prepared statement เพื่อลดความเสี่ยง SQL injection
 *
 * Flow สั้น:
 * 1. ฟอร์มส่ง `status=insert|update` พร้อมฟิลด์ part
 * 2. ระบบ map หมวด product เป็น `ct_id`
 * 3. บันทึกข้อมูลแล้ว redirect ไป `stock_overview.php`
 *
 * ตัวอย่าง: POST part_save.php พร้อม status=update และ part_id=401
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/line_stock_alert.php';
require_once __DIR__ . '/part_location_lib.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render กลับไปยัง HTML
 *
 * @param mixed $value ค่าที่ต้องแสดงผล
 * @return string ข้อความที่ปลอดภัยสำหรับ HTML
 */
function part_save_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * อ่านข้อความจาก request พร้อม trim และจำกัดความยาว
 *
 * @param string $key ชื่อ field ใน POST
 * @param int $maxLength ความยาวสูงสุด
 * @return string ข้อความที่ normalize แล้ว
 */
function part_save_text($key, $maxLength)
{
    $text = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

/**
 * อ่านค่า int บวกจาก POST
 *
 * @param string $key ชื่อ field
 * @return int ค่า int ที่ valid หรือ 0 เมื่อไม่ถูกต้อง
 */
function part_save_positive_int($key)
{
    if (!isset($_POST[$key])) {
        return 0;
    }

    $id = filter_var($_POST[$key], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * อ่านค่าเปิดแจ้งเตือน Line เมื่อสต็อกต่ำจาก checkbox
 *
 * @return int 1 เมื่อเลือก มิฉะนั้น 0
 */
function part_save_line_alert_flag()
{
    return isset($_POST['part_line_alert']) && (string) $_POST['part_line_alert'] === '1' ? 1 : 0;
}

/**
 * แปลง input หมวด product เป็น ct_id
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $categoryInput ค่าจากฟอร์ม (ct_id หรือ ct_name)
 * @return int ct_id ที่พบ หรือ 0 เมื่อไม่พบ
 */
function part_save_resolve_category_id($conn, $categoryInput)
{
    $categoryInput = trim($categoryInput);
    if ($categoryInput === '') {
        return 0;
    }

    if (ctype_digit($categoryInput)) {
        return (int) $categoryInput;
    }

    $stmt = mysqli_prepare($conn, 'SELECT ct_id FROM categories_product WHERE ct_name = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 's', $categoryInput);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }

    mysqli_stmt_bind_result($stmt, $ctId);
    $resolved = 0;
    if (mysqli_stmt_fetch($stmt)) {
        $resolved = (int) $ctId;
    }
    mysqli_stmt_close($stmt);

    return $resolved;
}

/**
 * แสดงหน้าผลลัพธ์แล้ว redirect ไป stock overview
 *
 * @param string $message ข้อความแจ้งผู้ใช้
 * @param bool $success true เมื่อบันทึกสำเร็จ
 * @return void
 */
function part_save_render_result($message, $success)
{
    $class = $success ? 'success' : 'error';
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="2;url=stock_overview.php">';
    echo '<title>บันทึก Part</title>';
    echo '<style>body{font-family:sans-serif;max-width:640px;margin:3rem auto;color:#334155}';
    echo '.success{color:#166534;background:#dcfce7;padding:1rem;border-radius:8px}';
    echo '.error{color:#991b1b;background:#fee2e2;padding:1rem;border-radius:8px}</style>';
    echo '</head><body><div class="' . part_save_h($class) . '">' . part_save_h($message) . '</div>';
    echo '<p><a href="stock_overview.php">กลับไปภาพรวม Stock</a></p></body></html>';
}

/**
 * execute prepared statement พร้อม bind parameter แบบอ้างอิง (รองรับ PHP 8+)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL
 * @param string $types ชนิดข้อมูล bind_param
 * @param array $params ค่าที่ bind
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function part_save_execute($conn, $sql, $types, array $params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('เตรียม SQL ไม่สำเร็จ: ' . mysqli_error($conn));
    }

    $bindParams = array($stmt, $types);
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }

    if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log('part_save bind error: ' . $error . ' types=' . $types . ' count=' . count($params));
        throw new Exception('เตรียมข้อมูล Part ไม่สำเร็จ');
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log('part_save execute error: ' . $error);
        throw new Exception('บันทึก Part ไม่สำเร็จ: ' . $error);
    }

    return $stmt;
}

/**
 * อัปเดต part_line_alert เมื่อมีคอลัมน์ในฐานข้อมูล
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId รหัส part
 * @param int $flag 0|1
 * @return void
 */
function part_save_apply_line_alert($conn, $partId, $flag)
{
    if (!inventory_alert_table_column_usable($conn, 'part', 'part_line_alert')) {
        return;
    }

    $stmt = part_save_execute($conn, 'UPDATE part SET part_line_alert = ? WHERE part_id = ? LIMIT 1', 'ii', array(
        (int) $flag,
        (int) $partId,
    ));
    mysqli_stmt_close($stmt);
}

/**
 * ตรวจว่า part_id ใช้ AUTO_INCREMENT หรือไม่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return bool true เมื่อ part_id auto increment
 */
function part_save_part_id_auto_increment($conn)
{
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `part` LIKE 'part_id'");
    if (!$result instanceof mysqli_result) {
        return false;
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return $row !== null
        && isset($row['Extra'])
        && stripos((string) $row['Extra'], 'auto_increment') !== false;
}

/**
 * ดึง part_id ถัดไปสำหรับ schema เดิมที่ไม่มี AUTO_INCREMENT
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return int part_id ถัดไปสำหรับ insert
 */
function part_save_next_part_id($conn)
{
    $result = mysqli_query($conn, 'SELECT COALESCE(MAX(`part_id`), 0) + 1 AS next_id FROM `part` FOR UPDATE');
    if (!$result instanceof mysqli_result) {
        throw new Exception('ไม่สามารถสร้าง part_id ใหม่ได้: ' . mysqli_error($conn));
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return isset($row['next_id']) ? max(1, (int) $row['next_id']) : 1;
}

/**
 * ดึง metadata primary key ของ production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array{name:string,auto_increment:bool}
 */
function part_save_formula_primary_key($conn)
{
    $primaryKey = array('name' => 'formula_id', 'auto_increment' => true);

    foreach (array('formula_id', 'production_id') as $columnName) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `production_formula` LIKE '" . mysqli_real_escape_string($conn, $columnName) . "'");
        if (!$result instanceof mysqli_result || mysqli_num_rows($result) === 0) {
            if ($result instanceof mysqli_result) {
                mysqli_free_result($result);
            }
            continue;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        $primaryKey['name'] = $columnName;
        $primaryKey['auto_increment'] = isset($row['Extra'])
            && stripos((string) $row['Extra'], 'auto_increment') !== false;

        if ($columnName === 'formula_id') {
            break;
        }
    }

    return $primaryKey;
}

/**
 * ดึง primary key ถัดไปของ production_formula เมื่อไม่ auto increment
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $columnName ชื่อคอลัมน์ primary key
 * @return int id ถัดไปสำหรับ insert
 */
function part_save_next_formula_row_id($conn, $columnName)
{
    $columnSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    if ($columnSafe === '') {
        throw new Exception('ไม่พบ primary key ของ production_formula');
    }

    $result = mysqli_query(
        $conn,
        'SELECT COALESCE(MAX(`' . $columnSafe . '`), 0) + 1 AS next_id FROM `production_formula` FOR UPDATE'
    );
    if (!$result instanceof mysqli_result) {
        throw new Exception('ไม่สามารถสร้างรหัส production_formula ใหม่ได้: ' . mysqli_error($conn));
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return isset($row['next_id']) ? max(1, (int) $row['next_id']) : 1;
}

/**
 * บันทึก production_formula สำหรับ Part ที่สร้างใหม่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $pdId รหัส product
 * @param string $pdName ชื่อ product
 * @param int $partId รหัส part
 * @param int $ctId รหัส category
 * @return void
 */
function part_save_insert_formula($conn, $pdId, $pdName, $partId, $ctId)
{
    $primaryKey = part_save_formula_primary_key($conn);

    if ($primaryKey['name'] === 'production_id' && !$primaryKey['auto_increment']) {
        $formulaRowId = part_save_next_formula_row_id($conn, 'production_id');
        $stmt = part_save_execute(
            $conn,
            'INSERT INTO production_formula (production_id, pd_id, pd_name, part_id, formula_name_id, ct_id) VALUES (?, ?, ?, ?, ?, ?)',
            'iisiii',
            array($formulaRowId, $pdId, $pdName, $partId, $pdId, $ctId)
        );
    } else {
        $stmt = part_save_execute(
            $conn,
            'INSERT INTO production_formula (pd_id, pd_name, part_id, formula_name_id, ct_id) VALUES (?, ?, ?, ?, ?)',
            'isiii',
            array($pdId, $pdName, $partId, $pdId, $ctId)
        );
    }

    mysqli_stmt_close($stmt);
}

/**
 * บันทึก Part ใหม่และสร้าง production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return int part_id ที่สร้างใหม่
 */
function part_save_insert($conn)
{
    $ctId = part_save_resolve_category_id($conn, part_save_text('part_categories', 255));
    if ($ctId <= 0) {
        throw new Exception('ไม่พบหมวด Categories ที่เลือก');
    }

    $sql = 'INSERT INTO part (
        part_name, part_product, part_amount, part_vendor, part_price, part_min_alert,
        part_shipment, part_link, part_image, part_leadtime, part_by,
        part_categories, part_stock_no, part_puchase_quantity, part_transport_company,
        part_period_use, part_check_draft, part_remarks
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $partName = part_save_text('part_name', 255);
    $partProduct = part_save_text('part_product', 255);
    $partAmount = part_save_text('part_amount', 50);
    $partVendor = part_save_text('part_vendor', 255);
    $partPrice = part_save_text('part_price', 50);
    $partMinAlert = part_save_text('part_min_alert', 50);
    $partLineAlert = part_save_line_alert_flag();
    $partShipment = part_save_text('part_shipment', 100);
    $partLink = part_save_text('part_link', 500);
    $partImage = part_save_text('part_image', 500);
    $partLeadtime = part_save_text('part_leadtime', 100);
    $partBy = inventory_actor_name_for_save(100);
    $partStockNo = part_save_text('part_stock_no', 100);
    $partPurchaseQty = part_save_text('part_puchase_quantity', 50);
    $partTransport = part_save_text('part_transport_company', 100);
    $partPeriodUse = part_save_text('part_period_use', 100);
    $partCheckDraft = part_save_text('part_check_draft', 50);
    $partRemarks = part_save_text('part_remarks', 2000);
    $insertParams = array(
        $partName,
        $partProduct,
        $partAmount,
        $partVendor,
        $partPrice,
        $partMinAlert,
        $partShipment,
        $partLink,
        $partImage,
        $partLeadtime,
        $partBy,
        $ctId,
        $partStockNo,
        $partPurchaseQty,
        $partTransport,
        $partPeriodUse,
        $partCheckDraft,
        $partRemarks,
    );

    mysqli_begin_transaction($conn);
    try {
        if (part_save_part_id_auto_increment($conn)) {
            $stmt = part_save_execute($conn, $sql, 'sssssssssssissssss', $insertParams);
            mysqli_stmt_close($stmt);
            $partId = (int) mysqli_insert_id($conn);
        } else {
            $partId = part_save_next_part_id($conn);
            $sqlWithId = 'INSERT INTO part (
                part_id, part_name, part_product, part_amount, part_vendor, part_price, part_min_alert,
                part_shipment, part_link, part_image, part_leadtime, part_by,
                part_categories, part_stock_no, part_puchase_quantity, part_transport_company,
                part_period_use, part_check_draft, part_remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = part_save_execute($conn, $sqlWithId, 'isssssssssssissssss', array_merge(array($partId), $insertParams));
            mysqli_stmt_close($stmt);
        }

        if ($partId <= 0) {
            throw new Exception('ไม่สามารถอ่าน part_id ที่สร้างใหม่ได้');
        }

        part_save_apply_line_alert($conn, $partId, $partLineAlert);

        $pdStmt = mysqli_prepare($conn, 'SELECT pd_id, pd_name FROM product WHERE pd_name = ? LIMIT 1');
        if (!$pdStmt) {
            throw new Exception('เตรียมคำสั่งค้นหา Product ไม่สำเร็จ');
        }

        mysqli_stmt_bind_param($pdStmt, 's', $partProduct);
        if (!mysqli_stmt_execute($pdStmt)) {
            mysqli_stmt_close($pdStmt);
            throw new Exception('ค้นหา Product ไม่สำเร็จ');
        }

        mysqli_stmt_bind_result($pdStmt, $pdId, $pdName);
        if (!mysqli_stmt_fetch($pdStmt)) {
            mysqli_stmt_close($pdStmt);
            throw new Exception('ไม่พบ Product ที่เลือก');
        }
        mysqli_stmt_close($pdStmt);

        part_save_insert_formula($conn, (int) $pdId, (string) $pdName, $partId, $ctId);
        mysqli_commit($conn);

        return $partId;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * อัปเดตข้อมูล Part ที่มีอยู่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return void
 */
function part_save_update($conn)
{
    $partId = part_save_positive_int('part_id');
    if ($partId <= 0) {
        throw new Exception('ไม่พบ part_id ที่ต้องการแก้ไข');
    }

    $ctId = part_save_resolve_category_id($conn, part_save_text('part_categories', 255));
    if ($ctId <= 0) {
        throw new Exception('ไม่พบหมวด Categories ที่เลือก');
    }

    $sql = 'UPDATE part SET
        part_name = ?,
        part_product = ?,
        part_amount = ?,
        part_vendor = ?,
        part_price = ?,
        part_min_alert = ?,
        part_line_alert = ?,
        part_link = ?,
        part_image = ?,
        part_leadtime = ?,
        part_by = ?,
        part_categories = ?,
        part_status_purchase = ?,
        part_date_purchase = ?,
        part_shipment = ?,
        part_stock_no = ?,
        part_puchase_quantity = ?,
        part_transport_company = ?,
        part_period_use = ?,
        part_check_draft = ?,
        part_remarks = ?
        WHERE part_id = ?';

    $partName = part_save_text('part_name', 255);
    $partProduct = part_save_text('part_product', 255);
    $partAmount = part_save_text('part_amount', 50);
    $partVendor = part_save_text('part_vendor', 255);
    $partPrice = part_save_text('part_price', 50);
    $partMinAlert = part_save_text('part_min_alert', 50);
    $partLineAlert = part_save_line_alert_flag();
    $partLink = part_save_text('part_link', 500);
    $partImage = part_save_text('part_image', 500);
    $partLeadtime = part_save_text('part_leadtime', 100);
    $partBy = inventory_actor_name_for_save(100);
    $partStatusPurchase = part_save_text('part_status_purchase', 100);
    $partDatePurchase = part_save_text('part_date_purchase', 50);
    $partShipment = part_save_text('part_shipment', 100);
    $partStockNo = part_save_text('part_stock_no', 100);
    $partPurchaseQty = part_save_text('part_puchase_quantity', 50);
    $partTransport = part_save_text('part_transport_company', 100);
    $partPeriodUse = part_save_text('part_period_use', 100);
    $partCheckDraft = part_save_text('part_check_draft', 50);
    $partRemarks = part_save_text('part_remarks', 2000);

    $stmt = part_save_execute($conn, $sql, 'ssssssissssisssssssssi', array(
        $partName,
        $partProduct,
        $partAmount,
        $partVendor,
        $partPrice,
        $partMinAlert,
        $partLineAlert,
        $partLink,
        $partImage,
        $partLeadtime,
        $partBy,
        $ctId,
        $partStatusPurchase,
        $partDatePurchase,
        $partShipment,
        $partStockNo,
        $partPurchaseQty,
        $partTransport,
        $partPeriodUse,
        $partCheckDraft,
        $partRemarks,
        $partId,
    ));

    if (mysqli_stmt_affected_rows($stmt) < 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('แก้ไข Part ไม่สำเร็จ');
    }
    mysqli_stmt_close($stmt);

    part_save_apply_location($conn, $partId);
}

/**
 * บันทึกตำแหน่งเก็บ (ชั้นวาง/ระดับ/หมายเหตุ) จากฟอร์มแก้ไข Part — ส่งมาเฉพาะเมื่อฟอร์มมีช่องตำแหน่ง
 *
 * @param mysqli $conn
 * @param int $partId
 * @return void
 */
function part_save_apply_location($conn, $partId)
{
    if (empty($_POST['loc_present'])) {
        return;
    }
    $loc = part_location_normalize(
        part_save_text('loc_shelf', 4),
        part_save_text('loc_level', 2),
        part_save_text('loc_note', 100)
    );
    if (!$loc['ok']) {
        throw new Exception('บันทึก Part แล้ว แต่ตำแหน่งเก็บไม่ถูกต้อง: ' . $loc['message']);
    }
    if (!part_location_save($conn, $partId, $loc, inventory_actor_name_for_save(100))) {
        throw new Exception('บันทึก Part แล้ว แต่บันทึกตำแหน่งเก็บไม่สำเร็จ');
    }
}

// ─ Main ─────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    part_save_render_result('กรุณาส่งข้อมูลผ่านฟอร์มเท่านั้น', false);
    db_close($connect);
    exit;
}

$status = part_save_text('status', 20);

try {
    if ($status === 'insert') {
        $partId = part_save_insert($connect);
        part_save_render_result('บันทึก Part ใหม่เรียบร้อยแล้ว (ID ' . $partId . ')', true);
    } elseif ($status === 'update') {
        part_save_update($connect);
        part_save_render_result('แก้ไข Part เรียบร้อยแล้ว', true);
    } else {
        throw new Exception('สถานะการบันทึกไม่ถูกต้อง');
    }
} catch (Exception $e) {
    part_save_render_result($e->getMessage(), false);
}

db_close($connect);
