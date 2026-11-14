<?php
/**
 * API หน้ารายการจัดของมือถือ (pickup prep board)
 *
 * คืนรายการอะไหล่ในเอกสาร pre_status = pre จัดกลุ่มตาม pre_id
 * และอัปเดต pre_check (YES/NO) เมื่อคลังติ๊กจัดเตรียมแล้ว
 * ทุก request ต้องส่ง token ตรงกับ inventory_config pickup_prep_board_token
 *
 * Flow สั้น:
 * 1. GET  ?token=...&action=list
 * 2. POST ?token=...&action=set_prepared  body JSON { pre_running, prepared }
 *
 * ตัวอย่าง: pickup_prep_api.php?token=SECRET&action=list
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_config.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * ส่ง JSON แล้วปิด connection
 *
 * @param array $payload ข้อมูลตอบกลับ
 * @param int $statusCode HTTP status
 * @return void
 */
function pickup_prep_api_json($payload, $statusCode = 200)
{
    http_response_code((int) $statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (isset($GLOBALS['connect'])) {
        db_close($GLOBALS['connect']);
    }
    exit;
}

/**
 * อ่าน token ที่ผู้ใช้ส่งมา (query หรือ header X-Prep-Board-Token)
 *
 * @return string token ที่ trim แล้ว
 */
function pickup_prep_api_request_token()
{
    if (isset($_GET['token'])) {
        return trim((string) $_GET['token']);
    }
    if (isset($_SERVER['HTTP_X_PREP_BOARD_TOKEN'])) {
        return trim((string) $_SERVER['HTTP_X_PREP_BOARD_TOKEN']);
    }

    return '';
}

/**
 * ตรวจว่า token ตรงกับค่าที่ตั้งใน config
 *
 * @return void ไม่มี return — 403/503 เมื่อไม่ผ่าน
 */
function pickup_prep_api_require_token()
{
    $expected = inventory_config_get('pickup_prep_board_token', '');
    if ($expected === '') {
        pickup_prep_api_json(
            array('ok' => false, 'message' => 'ยังไม่ได้ตั้งค่า pickup_prep_board_token บน server'),
            503
        );
    }

    $given = pickup_prep_api_request_token();
    if ($given === '' || !hash_equals($expected, $given)) {
        pickup_prep_api_json(
            array('ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง'),
            403
        );
    }
}

/**
 * รวมหมายเหตุเอกสารจากหลายแถวให้เหลือข้อความเดียว
 *
 * @param array<int, string> $remarkParts หมายเหตุแต่ละแถว
 * @return string ข้อความรวมคั่นด้วย /
 */
function pickup_prep_api_join_remarks(array $remarkParts)
{
    $unique = array();
    foreach ($remarkParts as $part) {
        $t = trim((string) $part);
        if ($t !== '') {
            $unique[$t] = true;
        }
    }
    if ($unique === array()) {
        return '';
    }

    return implode(' / ', array_keys($unique));
}

/**
 * โหลดรายการรอจ่ายทั้งหมดแล้วจัดกลุ่มตาม pre_id
 *
 * @param mysqli $conn connection
 * @return array{summary: array, documents: array} โครงสร้างสำหรับ JSON
 */
function pickup_prep_api_fetch_list($conn)
{
    $sql = 'SELECT ps.`pre_running`, ps.`pre_id`, ps.`pre_date`, ps.`pre_by`, ps.`pre_type`,
                   ps.`pre_product`, ps.`pre_amount`, ps.`pre_remarks`, ps.`pre_check`,
                   p.`part_name`
            FROM `pre_stock` ps
            INNER JOIN `part` p ON ps.`pre_part` = p.`part_id`
            WHERE ps.`pre_status` = ?
            ORDER BY ps.`pre_date` DESC, ps.`pre_id` DESC, ps.`pre_running` ASC';

    $statusPre = 'pre';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        error_log('pickup_prep_api list prepare: ' . mysqli_error($conn));
        pickup_prep_api_json(array('ok' => false, 'message' => 'ไม่สามารถโหลดรายการได้'), 500);
    }

    mysqli_stmt_bind_param($stmt, 's', $statusPre);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('pickup_prep_api list execute: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        pickup_prep_api_json(array('ok' => false, 'message' => 'ไม่สามารถโหลดรายการได้'), 500);
    }

    mysqli_stmt_bind_result(
        $stmt,
        $preRunning,
        $preId,
        $preDate,
        $preBy,
        $preType,
        $preProduct,
        $preAmount,
        $preRemarks,
        $preCheck,
        $partName
    );

    $documents = array();
    $totalLines = 0;
    $totalPrepared = 0;

    while (mysqli_stmt_fetch($stmt)) {
        $docId = (string) $preId;
        if (!isset($documents[$docId])) {
            $documents[$docId] = array(
                'pre_id' => $docId,
                'pre_date' => (string) $preDate,
                'pre_by' => (string) $preBy,
                'pre_type' => (string) $preType,
                'remarks_parts' => array(),
                'lines' => array(),
            );
        }

        $remarkLine = trim((string) $preRemarks);
        if ($remarkLine !== '') {
            $documents[$docId]['remarks_parts'][] = $remarkLine;
        }

        $isPrepared = (strtoupper(trim((string) $preCheck)) === 'YES');
        if ($isPrepared) {
            $totalPrepared++;
        }
        $totalLines++;

        $documents[$docId]['lines'][] = array(
            'pre_running' => (int) $preRunning,
            'part_name' => (string) $partName,
            'pre_product' => (string) $preProduct,
            'pre_amount' => (string) $preAmount,
            'prepared' => $isPrepared,
        );
    }

    mysqli_stmt_close($stmt);

    $documentList = array();
    foreach ($documents as $doc) {
        $preparedCount = 0;
        foreach ($doc['lines'] as $line) {
            if (!empty($line['prepared'])) {
                $preparedCount++;
            }
        }
        $lineCount = count($doc['lines']);
        $remarksParts = isset($doc['remarks_parts']) ? $doc['remarks_parts'] : array();
        $documentList[] = array(
            'pre_id' => $doc['pre_id'],
            'pre_date' => $doc['pre_date'],
            'pre_by' => $doc['pre_by'],
            'pre_type' => $doc['pre_type'],
            'remarks' => pickup_prep_api_join_remarks($remarksParts),
            'progress' => array(
                'prepared' => $preparedCount,
                'total' => $lineCount,
            ),
            'lines' => $doc['lines'],
        );
    }

    return array(
        'summary' => array(
            'lines' => $totalLines,
            'prepared' => $totalPrepared,
            'pending' => $totalLines - $totalPrepared,
            'documents' => count($documentList),
        ),
        'documents' => $documentList,
    );
}

/**
 * อัปเดตสถานะจัดเตรียมของแถว pre_stock
 *
 * @param mysqli $conn connection
 * @param int $preRunning id แถว
 * @param bool $prepared true = YES, false = NO
 * @return bool true เมื่อมีแถวถูกอัปเดต
 */
function pickup_prep_api_set_prepared($conn, $preRunning, $prepared)
{
    if ($preRunning <= 0) {
        return false;
    }

    $checkValue = $prepared ? 'YES' : 'NO';
    $statusPre = 'pre';

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE `pre_stock` SET `pre_check` = ? WHERE `pre_running` = ? AND `pre_status` = ?'
    );
    if ($stmt === false) {
        error_log('pickup_prep_api set prepare: ' . mysqli_error($conn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'sis', $checkValue, $preRunning, $statusPre);
    $ok = mysqli_stmt_execute($stmt);
    $affected = $ok ? mysqli_stmt_affected_rows($stmt) : 0;
    mysqli_stmt_close($stmt);

    return ($affected > 0);
}

/**
 * อ่าน body JSON ของ POST
 *
 * @return array<string, mixed> ข้อมูลที่ decode แล้ว
 */
function pickup_prep_api_read_json_body()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return array();
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : array();
}

// ─ Main ──────────────────────────────────────────────────────────────────────

pickup_prep_api_require_token();

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
if ($action === '' && isset($_POST['action'])) {
    $action = trim((string) $_POST['action']);
}

if ($action === 'list') {
    $data = pickup_prep_api_fetch_list($connect);
    pickup_prep_api_json(array(
        'ok' => true,
        'summary' => $data['summary'],
        'documents' => $data['documents'],
    ));
}

if ($action === 'set_prepared') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        pickup_prep_api_json(array('ok' => false, 'message' => 'ใช้ POST เท่านั้น'), 405);
    }

    $body = pickup_prep_api_read_json_body();
    $preRunning = isset($body['pre_running']) ? (int) $body['pre_running'] : 0;
    $prepared = !empty($body['prepared']);

    if ($preRunning <= 0) {
        pickup_prep_api_json(array('ok' => false, 'message' => 'รหัสรายการไม่ถูกต้อง'), 400);
    }

    if (!pickup_prep_api_set_prepared($connect, $preRunning, $prepared)) {
        pickup_prep_api_json(array('ok' => false, 'message' => 'ไม่พบรายการหรือเอกสารไม่ใช่สถานะรอจ่าย'), 404);
    }

    pickup_prep_api_json(array(
        'ok' => true,
        'pre_running' => $preRunning,
        'prepared' => $prepared,
    ));
}

pickup_prep_api_json(array('ok' => false, 'message' => 'action ไม่รองรับ'), 400);
