<?php
/**
 * Endpoint บันทึกรายการเบิก "จากคลังอะไหล่ช่าง" (โหมดคลังช่าง ของหน้า pickup_ma.php)
 *
 * ขอบเขต: รับรายการเบิกแบบ POST (tp_pd*, tp_amount*, tp_sn*) ตรวจ input,
 * แล้วเบิกผ่าน StockService ของแอป parts (biton_tech_parts) ทีละบรรทัด —
 * แต่ละบรรทัดสร้างใบเบิก OUT-... ของตัวเองและตัดสต็อกทันที (เหมือนเบิกในแอป parts)
 *
 * Flow:
 * 1. pickup_ma.php โหมด "คลังช่าง" submit ฟอร์มมาที่ไฟล์นี้
 * 2. validate ทุก field — จำนวนบรรทัดไม่เกิน 10
 * 3. เบิกทีละบรรทัด (fail บรรทัดไหนรายงานบรรทัดนั้น — บรรทัดก่อนหน้าตัดไปแล้วจริง)
 * 4. แสดงผลลัพธ์ต่อบรรทัด + เลขที่เอกสาร พร้อมลิงก์กลับหน้าเบิกของรวม
 *
 * ไม่เข้าคิว pre_stock — การเบิกคลังช่างตัดสต็อกทันที ประวัติดูที่แอป parts
 */

require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/pickup_tech_parts.php';
require_once __DIR__ . '/inventory_request_timing.php';
require_once __DIR__ . '/inventory_ui_fonts.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
date_default_timezone_set('Asia/Bangkok');

if (!defined('INVENTORY_UI_CSS_LINK_EMITTED')) {
    define('INVENTORY_UI_CSS_LINK_EMITTED', true);
}

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render HTML
 *
 * @param mixed $value ค่าที่ต้องแสดงผลบนหน้าเว็บ
 * @return string ข้อความที่ผ่าน htmlspecialchars แล้ว
 */
function pickup_tp_save_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * อ่านค่า int ที่ต้องมากกว่า 0 จาก request
 *
 * @param mixed $value ค่าจาก request
 * @return int ค่า int ที่ valid หรือ 0 เมื่อไม่ถูกต้อง
 */
function pickup_tp_save_positive_int($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * อ่านข้อความจาก request พร้อม trim และจำกัดความยาว
 *
 * @param mixed $value ค่าจาก request
 * @param int $maxLength ความยาวสูงสุด
 * @return string ข้อความที่ normalize แล้ว
 */
function pickup_tp_save_text($value, $maxLength)
{
    $text = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

/**
 * validate จำนวนเบิกให้เป็นตัวเลขบวก (รองรับทศนิยม เช่น 0.5 ตามกติกาคลังช่าง)
 *
 * @param mixed $value จำนวนจาก request
 * @return float จำนวนที่ใช้บันทึก
 */
function pickup_tp_save_amount($value)
{
    $amount = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount <= 0) {
        throw new RuntimeException('จำนวนเบิกต้องเป็นตัวเลขมากกว่า 0');
    }

    return (float) $amount;
}

/**
 * หาชื่อ/รหัสอะไหล่จาก id (เพื่อแสดงผลรวมถึงหน้าผลลัพธ์)
 *
 * @param PDO $pdo connection คลังช่าง
 * @param int $productId id ของ products
 * @return array<string,string>|null null เมื่อไม่พบ
 */
function pickup_tp_save_find_product($pdo, $productId)
{
    $stmt = $pdo->prepare('SELECT code, name, unit FROM products WHERE id = ? LIMIT 1');
    $stmt->execute(array($productId));
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * แถวผลลัพธ์ HTML ของหนึ่งบรรทัด
 *
 * @param array<string,mixed> $r ผลของบรรทัดนี้
 * @return string HTML แถว
 */
function pickup_tp_save_result_row($r)
{
    $ok = !empty($r['ok']);
    $statusHtml = $ok
        ? '<span class="tp-res tp-res--ok">สำเร็จ</span>'
        : '<span class="tp-res tp-res--fail">ไม่สำเร็จ</span>';
    $docHtml = $ok
        ? pickup_tp_save_h($r['doc_no'])
        : '<span class="tp-res-error">' . pickup_tp_save_h($r['error']) . '</span>';

    return '<tr class="' . ($ok ? 'tp-row--ok' : 'tp-row--fail') . '">'
        . '<td class="tp-cell-num">' . (int) $r['no'] . '</td>'
        . '<td>' . pickup_tp_save_h($r['label']) . '</td>'
        . '<td class="tp-cell-num">' . pickup_tp_save_h(qty_fmt($r['qty'])) . ' ' . pickup_tp_save_h($r['unit']) . '</td>'
        . '<td>' . $statusHtml . '</td>'
        . '<td>' . $docHtml . '</td>'
        . '</tr>';
}

// ─ Main Flow ─────────────────────────────────────────────────────────────────

$results = array();
$fatalError = '';
$okCount = 0;

try {
    $actor = inventory_actor_name_for_save(100);
} catch (Throwable $e) {
    $actor = '';
    $fatalError = $e->getMessage();
}

if ($fatalError === '') {
    try {
        $pdo = pickup_tech_parts_db();
        if (!($pdo instanceof PDO)) {
            throw new RuntimeException(pickup_tech_parts_error() !== ''
                ? pickup_tech_parts_error()
                : 'ระบบคลังช่างยังไม่พร้อมใช้งาน');
        }

        $count = pickup_tp_save_positive_int(isset($_POST['tp_count']) ? $_POST['tp_count'] : 0);
        if ($count <= 0 || $count > 10) {
            throw new RuntimeException('จำนวนรายการเบิกไม่ถูกต้อง (1–10 รายการ)');
        }
        $purpose = pickup_tp_save_text(isset($_POST['tp_purpose']) ? $_POST['tp_purpose'] : '', 100);
        $detailRemarks = pickup_tp_save_text(isset($_POST['tp_remarks']) ? $_POST['tp_remarks'] : '', 5000);
        if ($purpose === '' || !in_array($purpose, pickup_tech_parts_purpose_types(), true)) {
            throw new RuntimeException('กรุณาเลือกวัตถุประสงค์การเบิก');
        }
        $remarks = pickup_tech_parts_compose_stock_note($purpose, $detailRemarks);

        for ($i = 1; $i <= $count; $i++) {
            $rawPid = isset($_POST['tp_pd' . $i]) ? $_POST['tp_pd' . $i] : '';
            $rawAmt = isset($_POST['tp_amount' . $i]) ? $_POST['tp_amount' . $i] : '';
            $sn = pickup_tp_save_text(isset($_POST['tp_sn' . $i]) ? $_POST['tp_sn' . $i] : '', 200);

            $row = array(
                'no'     => $i,
                'label'  => 'รายการที่ ' . $i,
                'qty'    => 0,
                'unit'   => '',
                'ok'     => false,
                'doc_no' => '',
                'error'  => '',
            );

            try {
                $pid = pickup_tp_save_positive_int($rawPid);
                if ($pid <= 0) {
                    throw new RuntimeException('ข้อมูลรายการที่ ' . $i . ' ไม่ครบถ้วน');
                }
                $product = pickup_tp_save_find_product($pdo, $pid);
                if ($product === null) {
                    throw new RuntimeException('ไม่พบอะไหล่ในคลังช่าง (id=' . $pid . ')');
                }
                $row['label'] = trim((string) $product['code']) !== ''
                    ? $product['code'] . ' — ' . $product['name']
                    : $product['name'];
                $row['unit'] = (string) $product['unit'];
                $row['qty'] = pickup_tp_save_amount($rawAmt);

                $docNo = pickup_tech_parts_stock_out($pid, $row['qty'], $remarks, $actor, $sn);
                $row['ok'] = true;
                $row['doc_no'] = $docNo;
                $okCount++;
            } catch (Throwable $lineEx) {
                $row['error'] = $lineEx->getMessage() !== '' ? $lineEx->getMessage() : 'เบิกไม่สำเร็จ';
                error_log('[pickup_tech_parts_save] line ' . $i . ': ' . $lineEx->getMessage());
            }

            $results[] = $row;
        }
    } catch (Throwable $e) {
        $fatalError = $e->getMessage();
    }
}

$responseFormat = isset($_POST['tp_response_format']) ? trim((string) $_POST['tp_response_format']) : '';
if ($responseFormat === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    $totalLines = count($results);
    $payload = array(
        'ok'       => $fatalError === '' && $totalLines > 0 && $okCount === $totalLines,
        'ok_count' => (int) $okCount,
        'total'    => $totalLines,
        'results'  => array(),
    );
    foreach ($results as $row) {
        $payload['results'][] = array(
            'no'     => (int) $row['no'],
            'ok'     => !empty($row['ok']),
            'doc_no' => !empty($row['ok']) ? (string) $row['doc_no'] : '',
            'error'  => !empty($row['ok']) ? '' : (string) $row['error'],
        );
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/inventory_ui_head.php'; ?>
    <title>บันทึกเบิกอะไหล่จากคลังช่าง</title>
    <style>
        .tp-result-card { max-width: 860px; margin: 32px auto; background: #fff; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08); padding: 24px 28px; }
        .tp-result-card h2 { margin: 0 0 4px; font-size: 20px; }
        .tp-result-summary { color: #64748b; font-size: 14px; margin-bottom: 16px; }
        .tp-res--ok { color: #15803d; font-weight: 600; }
        .tp-res--fail { color: #b91c1c; font-weight: 600; }
        .tp-res-error { color: #b91c1c; }
        .tp-row--fail { background: #fef2f2; }
        .tp-cell-num { text-align: center; }
        .tp-actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
        .tp-actions a { text-decoration: none; }
        .tp-actor { font-size: 13px; color: #475569; margin-top: 10px; }
    </style>
</head>
<body class="inv-app">
    <div class="tp-result-card">
        <?php if ($fatalError !== '') { ?>
            <h2>บันทึกไม่สำเร็จ</h2>
            <p class="tp-res-error"><?php echo pickup_tp_save_h($fatalError); ?></p>
        <?php } else { ?>
            <?php $allOk = $okCount === count($results); ?>
            <h2><?php echo $allOk ? 'บันทึกสำเร็จ' : 'บันทึกบางส่วน'; ?></h2>
            <p class="tp-result-summary">
                เบิกจากคลังอะไหล่ช่าง — สำเร็จ <?php echo (int) $okCount; ?>/<?php echo count($results); ?> รายการ
                <?php if (!$allOk) { ?>
                    (รายการก่อนหน้าที่สำเร็จถูกตัดสต็อกไปแล้ว ต้องเบิกเพิ่มใหม่เฉพาะรายการที่ไม่สำเร็จ)
                <?php } ?>
            </p>
            <table class="inv-table" width="100%">
                <thead>
                    <tr>
                        <th class="tp-cell-num">ลำดับ</th>
                        <th>อะไหล่คลังช่าง</th>
                        <th class="tp-cell-num">จำนวนเบิก</th>
                        <th>สถานะ</th>
                        <th>เลขที่เอกสาร / สาเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r) { echo pickup_tp_save_result_row($r); } ?>
                </tbody>
            </table>
            <p class="tp-actor">ผู้เบิก: <?php echo pickup_tp_save_h($actor); ?> — ประวัติการเบิกดูได้ที่แอป Parts (คลังช่าง)</p>
        <?php } ?>
        <div class="tp-actions">
            <a class="inv-btn inv-btn--primary" href="pickup_ma.php?mode=tech">กลับไปหน้าเบิกของรวม (โหมดคลังช่าง)</a>
            <a class="inv-btn inv-btn--ghost" href="pickup_overview.php">ไปหน้ารายการเบิกของ</a>
        </div>
    </div>
</body>
</html>
