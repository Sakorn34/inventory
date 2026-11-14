<?php
/**
 * Endpoint เพิ่ม/ลบ/ค้นหาอะไหล่ในเอกสารเบิกที่ยังรอคลังจ่าย
 *
 * ใช้จาก `pickup_list_order_detail.php` เพื่อแก้รายการใน `pre_stock`
 * ได้เฉพาะเอกสารที่ `pre_status = 'pre'` เท่านั้น ใช้ prepared statement
 * และ CSRF token เพื่อกันการแก้จาก request ปลอม
 *
 * Flow สั้น:
 * 1. GET action=search ค้นหา part จากชื่อ
 * 2. POST action=add เพิ่มอะไหล่เข้าเอกสารเดิม โดยคัดลอก pre_date/pre_type/pre_by จากแถวที่มีอยู่
 * 3. POST action=delete ลบแถวด้วย pre_running ของเอกสารนั้น
 * 4. POST action=save_document บันทึกจำนวนและหมายเหตุของแต่ละแถวลง pre_stock
 *
 * ตัวอย่าง: POST pickup_list_order_edit.php action=add&pre_id=261017-095249&part_id=384&amount=1
 */

require_once __DIR__ . '/connect.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * คืนค่า CSRF token สำหรับการแก้รายการเอกสารเบิก
 *
 * @return string token ปัจจุบันใน session
 */
function pickup_list_order_edit_csrf_token()
{
    if (empty($_SESSION['pickup_list_order_detail_csrf'])) {
        $_SESSION['pickup_list_order_detail_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pickup_list_order_detail_csrf'];
}

/**
 * ตรวจ CSRF token จาก POST ก่อนเพิ่มหรือลบรายการ
 *
 * @param string $token token ที่ส่งมาจากฟอร์ม
 * @return void ไม่มี return value และ throw เมื่อ token ไม่ถูกต้อง
 */
function pickup_list_order_edit_validate_csrf($token)
{
    $sessionToken = isset($_SESSION['pickup_list_order_detail_csrf'])
        ? (string) $_SESSION['pickup_list_order_detail_csrf']
        : '';

    if (!is_string($token) || $token === '' || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('session ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

/**
 * ส่ง JSON แล้วจบการทำงาน
 *
 * @param array $payload ข้อมูลที่ต้องการส่งกลับ
 * @param int $statusCode HTTP status
 * @return void ไม่มี return value
 */
function pickup_list_order_edit_json($payload, $statusCode = 200)
{
    http_response_code((int) $statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * อ่านข้อความจาก request พร้อม trim และจำกัดความยาว
 *
 * @param mixed $value ค่าจาก request
 * @param int $maxLength ความยาวสูงสุด
 * @return string ข้อความที่ normalize แล้ว
 */
function pickup_list_order_edit_text($value, $maxLength)
{
    $text = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

/**
 * อ่านค่า int ที่ต้องมากกว่า 0
 *
 * @param mixed $value ค่าจาก request
 * @return int ค่า int ที่ valid หรือ 0 เมื่อไม่ถูกต้อง
 */
function pickup_list_order_edit_positive_int($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * validate จำนวนอะไหล่ที่ต้องการเพิ่ม
 *
 * @param mixed $value จำนวนจาก request
 * @return float จำนวนที่ใช้บันทึก
 */
function pickup_list_order_edit_amount($value)
{
    $amount = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount <= 0 || $amount > 99999) {
        throw new RuntimeException('จำนวนต้องเป็นตัวเลขมากกว่า 0 และไม่เกิน 99999');
    }

    return (float) $amount;
}

/**
 * ตรวจรูปแบบเลขเอกสารเบิก เช่น 261017-095249
 *
 * @param string $preId เลขเอกสาร
 * @return string เลขเอกสารที่ผ่านการตรวจ
 */
function pickup_list_order_edit_pre_id($preId)
{
    $preId = pickup_list_order_edit_text($preId, 30);
    if (!preg_match('/^\d{6}-\d{6}$/', $preId)) {
        throw new RuntimeException('เลขเอกสารไม่ถูกต้อง');
    }

    return $preId;
}

/**
 * เตรียมและ execute SQL ผ่าน mysqli prepared statement
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL พร้อม placeholder
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function pickup_list_order_edit_execute($conn, $sql, $types = '', $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('pickup_list_order_edit prepare error: ' . mysqli_error($conn));
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
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log('pickup_list_order_edit execute error: ' . $error);
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
function pickup_list_order_edit_fetch_all($stmt)
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
 * @param array $params ค่าที่ต้อง bind
 * @return array รายการแถวข้อมูล
 */
function pickup_list_order_edit_query($conn, $sql, $types = '', $params = array())
{
    $stmt = pickup_list_order_edit_execute($conn, $sql, $types, $params);
    $rows = pickup_list_order_edit_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * ค้นหาอะไหล่จากชื่อเพื่อใช้ในช่องเพิ่มรายการ
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $term คำค้น
 * @return array รายการ part ที่ตรงกับคำค้น
 */
function pickup_list_order_edit_search_parts($conn, $term)
{
    $term = pickup_list_order_edit_text($term, 100);
    $term = str_replace(array('%', '_'), '', $term);
    if ($term === '') {
        return array();
    }

    $like = '%' . $term . '%';
    $rows = pickup_list_order_edit_query(
        $conn,
        'SELECT `part_id`, `part_name`, `part_amount`
         FROM `part`
         WHERE `part_id` > 0 AND `part_name` LIKE ?
         ORDER BY `part_name` ASC
         LIMIT 20',
        's',
        array($like)
    );

    $parts = array();
    foreach ($rows as $row) {
        $parts[] = array(
            'part_id' => (int) $row['part_id'],
            'part_name' => (string) $row['part_name'],
            'part_amount' => (float) $row['part_amount'],
        );
    }

    return $parts;
}

/**
 * อ่านหัวเอกสารเบิกที่ยังรอจ่าย เพื่อคัดลอก metadata ตอนเพิ่มอะไหล่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $preId เลขเอกสาร
 * @return array แถวหัวเอกสาร
 */
function pickup_list_order_edit_get_document($conn, $preId)
{
    $rows = pickup_list_order_edit_query(
        $conn,
        'SELECT `pre_id`, `pre_date`, `pre_product`, `pre_type`, `pre_by`, `ts_mtn_number`
         FROM `pre_stock`
         WHERE `pre_id` = ? AND `pre_status` = ?
         ORDER BY `pre_running` ASC
         LIMIT 1',
        'ss',
        array($preId, 'pre')
    );

    if (empty($rows)) {
        throw new RuntimeException('ไม่พบเอกสารรอเบิกนี้ หรือคลังจ่ายไปแล้ว');
    }

    return $rows[0];
}

/**
 * ตรวจว่า part มีอยู่จริง
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $partId id ของ Part
 * @return array ข้อมูล part ที่พบ
 */
function pickup_list_order_edit_get_part($conn, $partId)
{
    $rows = pickup_list_order_edit_query(
        $conn,
        'SELECT `part_id`, `part_name` FROM `part` WHERE `part_id` = ? LIMIT 1',
        'i',
        array($partId)
    );

    if (empty($rows)) {
        throw new RuntimeException('ไม่พบอะไหล่ที่เลือก');
    }

    return $rows[0];
}

/**
 * ตรวจว่าอะไหล่นี้มีในเอกสารรอเบิกอยู่แล้วหรือไม่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $preId เลขเอกสาร
 * @param int $partId id ของ Part
 * @return bool true เมื่อมีรายการซ้ำ
 */
function pickup_list_order_edit_part_exists($conn, $preId, $partId)
{
    $partKey = (string) $partId;
    $rows = pickup_list_order_edit_query(
        $conn,
        'SELECT `pre_running`
         FROM `pre_stock`
         WHERE `pre_id` = ? AND `pre_part` = ? AND `pre_status` = ?
         LIMIT 1',
        'sss',
        array($preId, $partKey, 'pre')
    );

    return !empty($rows);
}

/**
 * เพิ่มอะไหล่เข้าเอกสารเบิกที่ยังรอจ่าย
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array $input ข้อมูลที่ผ่านการ validate แล้ว
 * @return array ผลลัพธ์การบันทึก
 */
function pickup_list_order_edit_add_part($conn, $input)
{
    if ($input['part_id'] <= 0) {
        throw new RuntimeException('กรุณาเลือกอะไหล่จากรายการค้นหา');
    }

    $document = pickup_list_order_edit_get_document($conn, $input['pre_id']);
    $part = pickup_list_order_edit_get_part($conn, $input['part_id']);

    if (pickup_list_order_edit_part_exists($conn, $input['pre_id'], $input['part_id'])) {
        throw new RuntimeException('อะไหล่นี้อยู่ในเอกสารแล้ว หากต้องการเปลี่ยนจำนวน ให้ลบรายการเดิมแล้วเพิ่มใหม่');
    }

    $partKey = (string) $part['part_id'];
    $preCheck = 'NO';
    $preStatus = 'pre';
    $stmt = pickup_list_order_edit_execute(
        $conn,
        'INSERT INTO `pre_stock`
            (`pre_id`, `pre_date`, `pre_product`, `pre_part`, `pre_type`, `pre_amount`, `pre_by`, `pre_remarks`, `ts_mtn_number`, `pre_check`, `pre_status`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssdsssss',
        array(
            $document['pre_id'],
            $document['pre_date'],
            $document['pre_product'],
            $partKey,
            $document['pre_type'],
            $input['amount'],
            $document['pre_by'],
            $input['remarks'],
            $document['ts_mtn_number'],
            $preCheck,
            $preStatus,
        )
    );
    mysqli_stmt_close($stmt);

    return array(
        'ok' => true,
        'message' => 'เพิ่มอะไหล่ ' . $part['part_name'] . ' เรียบร้อยแล้ว',
        'empty' => false,
    );
}

/**
 * ลบอะไหล่หนึ่งแถวออกจากเอกสารเบิกที่ยังรอจ่าย
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $preId เลขเอกสาร
 * @param int $preRunning id แถวใน pre_stock
 * @return array ผลลัพธ์การลบ
 */
function pickup_list_order_edit_delete_part($conn, $preId, $preRunning)
{
    if ($preRunning <= 0) {
        throw new RuntimeException('ไม่พบรายการที่ต้องการลบ');
    }

    pickup_list_order_edit_get_document($conn, $preId);

    $preStatus = 'pre';
    $stmt = pickup_list_order_edit_execute(
        $conn,
        'DELETE FROM `pre_stock`
         WHERE `pre_running` = ? AND `pre_id` = ? AND `pre_status` = ?
         LIMIT 1',
        'iss',
        array($preRunning, $preId, $preStatus)
    );
    $deleted = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($deleted <= 0) {
        throw new RuntimeException('ไม่พบรายการที่ลบได้ หรือเอกสารนี้ไม่ได้อยู่ในสถานะรอเบิก');
    }

    $remaining = pickup_list_order_edit_query(
        $conn,
        'SELECT COUNT(*) AS `total`
         FROM `pre_stock`
         WHERE `pre_id` = ? AND `pre_status` = ?',
        'ss',
        array($preId, 'pre')
    );
    $left = isset($remaining[0]['total']) ? (int) $remaining[0]['total'] : 0;

    return array(
        'ok' => true,
        'message' => 'ลบรายการเรียบร้อยแล้ว',
        'empty' => $left <= 0,
    );
}

/**
 * บันทึกจำนวนและหมายเหตุของรายการในเอกสารรอจ่าย
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $preId เลขเอกสาร
 * @param array $lines รายการ pre_running, amount, remarks
 * @return array ผลลัพธ์การบันทึก
 */
function pickup_list_order_edit_save_document($conn, $preId, $lines)
{
    pickup_list_order_edit_get_document($conn, $preId);

    if (empty($lines)) {
        throw new RuntimeException('ไม่พบรายการที่ต้องบันทึก');
    }

    if (count($lines) > 50) {
        throw new RuntimeException('จำนวนรายการเกินขีดจำกัดที่อนุญาต');
    }

    $preStatus = 'pre';
    mysqli_begin_transaction($conn);

    try {
        foreach ($lines as $line) {
            $exists = pickup_list_order_edit_query(
                $conn,
                'SELECT `pre_running`
                 FROM `pre_stock`
                 WHERE `pre_running` = ? AND `pre_id` = ? AND `pre_status` = ?
                 LIMIT 1',
                'iss',
                array($line['pre_running'], $preId, $preStatus)
            );
            if (empty($exists)) {
                throw new RuntimeException('ไม่พบรายการที่ ' . $line['index'] . ' ในเอกสารนี้');
            }

            $stmt = pickup_list_order_edit_execute(
                $conn,
                'UPDATE `pre_stock`
                 SET `pre_amount` = ?, `pre_remarks` = ?
                 WHERE `pre_running` = ? AND `pre_id` = ? AND `pre_status` = ?',
                'dsiss',
                array(
                    $line['amount'],
                    $line['remarks'],
                    $line['pre_running'],
                    $preId,
                    $preStatus,
                )
            );
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }

    return array(
        'ok' => true,
        'message' => 'บันทึกแก้ไขเอกสารเรียบร้อยแล้ว',
        'empty' => false,
    );
}

/**
 * รวบรวมและ validate รายการแก้ไขจาก POST
 *
 * @return array pre_id และรายการแถวที่พร้อมบันทึก
 */
function pickup_list_order_edit_build_save_document_request()
{
    $preId = pickup_list_order_edit_pre_id(isset($_POST['pre_id']) ? $_POST['pre_id'] : '');
    $count = pickup_list_order_edit_positive_int(isset($_POST['count']) ? $_POST['count'] : 0);

    if ($count <= 0 || $count > 50) {
        throw new RuntimeException('จำนวนรายการไม่ถูกต้อง');
    }

    $lines = array();
    for ($i = 1; $i <= $count; $i++) {
        $preRunning = pickup_list_order_edit_positive_int(
            isset($_POST['pre_running' . $i]) ? $_POST['pre_running' . $i] : 0
        );
        if ($preRunning <= 0) {
            throw new RuntimeException('ข้อมูลรายการที่ ' . $i . ' ไม่ครบถ้วน');
        }

        $lines[] = array(
            'index' => $i,
            'pre_running' => $preRunning,
            'amount' => pickup_list_order_edit_amount(
                isset($_POST['pre_amount' . $i]) ? $_POST['pre_amount' . $i] : ''
            ),
            'remarks' => pickup_list_order_edit_text(
                isset($_POST['pre_remarks' . $i]) ? $_POST['pre_remarks' . $i] : '',
                5000
            ),
        );
    }

    return array(
        'pre_id' => $preId,
        'lines' => $lines,
    );
}

// ─ Main Flow ─────────────────────────────────────────────────────────────────

try {
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    $action = '';

    if ($method === 'GET') {
        $action = pickup_list_order_edit_text(isset($_GET['action']) ? $_GET['action'] : '', 20);
        if ($action !== 'search') {
            throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        }

        $parts = pickup_list_order_edit_search_parts(
            $connect,
            isset($_GET['q']) ? $_GET['q'] : ''
        );
        pickup_list_order_edit_json(array('ok' => true, 'parts' => $parts));
    }

    if ($method !== 'POST') {
        throw new RuntimeException('วิธีเรียกไม่ถูกต้อง');
    }

    $action = pickup_list_order_edit_text(isset($_POST['action']) ? $_POST['action'] : '', 20);
    pickup_list_order_edit_validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '');

    if ($action === 'add') {
        $result = pickup_list_order_edit_add_part($connect, array(
            'pre_id' => pickup_list_order_edit_pre_id(isset($_POST['pre_id']) ? $_POST['pre_id'] : ''),
            'part_id' => pickup_list_order_edit_positive_int(isset($_POST['part_id']) ? $_POST['part_id'] : 0),
            'amount' => pickup_list_order_edit_amount(isset($_POST['amount']) ? $_POST['amount'] : ''),
            'remarks' => pickup_list_order_edit_text(isset($_POST['remarks']) ? $_POST['remarks'] : '', 5000),
        ));
        pickup_list_order_edit_json($result);
    }

    if ($action === 'delete') {
        $result = pickup_list_order_edit_delete_part(
            $connect,
            pickup_list_order_edit_pre_id(isset($_POST['pre_id']) ? $_POST['pre_id'] : ''),
            pickup_list_order_edit_positive_int(isset($_POST['pre_running']) ? $_POST['pre_running'] : 0)
        );
        pickup_list_order_edit_json($result);
    }

    if ($action === 'save_document') {
        $request = pickup_list_order_edit_build_save_document_request();
        $result = pickup_list_order_edit_save_document(
            $connect,
            $request['pre_id'],
            $request['lines']
        );
        pickup_list_order_edit_json($result);
    }

    throw new RuntimeException('คำสั่งไม่ถูกต้อง');
} catch (Throwable $e) {
    pickup_list_order_edit_json(array(
        'ok' => false,
        'message' => $e->getMessage(),
    ), 400);
}
