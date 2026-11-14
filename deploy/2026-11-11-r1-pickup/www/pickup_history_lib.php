<?php
/**
 * ฟังก์ชันดึงประวัติการเบิกของจาก log_stock (ทุกประเภทที่ขึ้นต้นด้วย เบิก)
 *
 * ใช้ร่วมกับ pickup_history.php — 1 แถวต่อ 1 เลขเอกสาร (pre_id)
 *
 * ตัวอย่าง: require แล้วเรียก pickup_history_fetch_documents($connect, '', 0, 0);
 */

require_once __DIR__ . '/stock_report_lib.php';

// ─ Constants ─────────────────────────────────────────────────────────────────

if (!defined('PICKUP_HISTORY_DOC_LIMIT')) {
    define('PICKUP_HISTORY_DOC_LIMIT', 500);
}

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * เงื่อนไข SQL กรอง log_type เป็นรายการเบิก
 *
 * @param string $alias alias ตาราง เช่น ls
 * @return string เงื่อนไขสำหรับ WHERE
 */
function pickup_history_type_sql($alias = '')
{
    $column = ($alias !== '') ? $alias . '.`log_type`' : '`log_type`';

    return $column . " LIKE 'เบิก%'";
}

/**
 * ดึงปีที่มีข้อมูลประวัติเบิก
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array<int> รายการปี ค.ศ.
 */
function pickup_history_available_years($conn)
{
    $sql = "SELECT DISTINCT SUBSTRING(`log_date`, 1, 2) AS yy
            FROM `log_stock`
            WHERE " . pickup_history_type_sql() . "
              AND `log_date` IS NOT NULL AND `log_date` <> ''
              AND CHAR_LENGTH(`log_date`) >= 6
              AND `pre_id` IS NOT NULL AND `pre_id` <> ''
            ORDER BY yy DESC";
    $exec = db_exec($conn, $sql);
    if (!$exec) {
        return array();
    }

    $years = array();
    while ($row = db_fetch_array($exec)) {
        $yy = trim((string) $row['yy']);
        if ($yy !== '' && ctype_digit($yy)) {
            $years[] = 2000 + (int) $yy;
        }
    }

    return array_values(array_unique($years));
}

/**
 * รายการประเภทการเบิกที่มีใน log
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array<int, string> ชื่อ log_type
 */
function pickup_history_type_list($conn)
{
    $sql = "SELECT DISTINCT `log_type` AS type_name
            FROM `log_stock`
            WHERE " . pickup_history_type_sql() . "
              AND `log_type` IS NOT NULL AND `log_type` <> ''
              AND `pre_id` IS NOT NULL AND `pre_id` <> ''
            ORDER BY `log_type` ASC";
    $exec = db_exec($conn, $sql);
    if (!$exec) {
        return array();
    }

    $list = array();
    while ($row = db_fetch_array($exec)) {
        $list[] = (string) $row['type_name'];
    }

    return $list;
}

/**
 * ตรวจ log_type จาก request ให้ตรงกับรายการที่มี
 *
 * @param string $requested ค่าจาก GET
 * @param array<int, string> $available รายการที่มีจริง
 * @return string ค่าว่าง = ทุกประเภท
 */
function pickup_history_resolve_type($requested, array $available)
{
    $requested = trim((string) $requested);
    if ($requested === '') {
        return '';
    }

    foreach ($available as $typeName) {
        if ($typeName === $requested) {
            return $requested;
        }
    }

    return '';
}

/**
 * ดึงประวัติเอกสารเบิกที่จ่ายแล้ว (จาก log_stock) เรียงล่าสุดก่อน
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $logType กรองประเภท หรือค่าว่าง = ทั้งหมด
 * @param int $year ปี ค.ศ. หรือ 0 = ทุกปี
 * @param int $month เดือน 1–12 หรือ 0 = ทุกเดือน
 * @param int $limit จำนวนเอกสารสูงสุด
 * @return array<int, array{pre_id:string, log_date:string, log_date_display:string, log_type:string, log_by:string, part_count:int, total_qty:float}>
 */
function pickup_history_fetch_documents($conn, $logType = '', $year = 0, $month = 0, $limit = 0)
{
    $limit = (int) $limit;
    if ($limit <= 0) {
        $limit = (int) PICKUP_HISTORY_DOC_LIMIT;
    }
    $limit = max(1, min(2000, $limit));
    $year = (int) $year;
    $month = (int) $month;
    if ($month < 1 || $month > 12) {
        $month = 0;
    }

    $sql = "SELECT ls.`pre_id` AS pre_id,
                   MIN(ls.`log_date`) AS log_date,
                   MIN(ls.`log_type`) AS log_type,
                   MIN(ls.`log_by`) AS log_by,
                   COUNT(DISTINCT ls.`log_part`) AS part_count,
                   SUM(ls.`log_amount`) AS total_qty
            FROM `log_stock` ls
            WHERE " . pickup_history_type_sql('ls') . "
              AND ls.`log_date` IS NOT NULL AND ls.`log_date` <> ''
              AND CHAR_LENGTH(ls.`log_date`) >= 6
              AND ls.`pre_id` IS NOT NULL AND ls.`pre_id` <> ''";

    if ($logType !== '') {
        $sql .= " AND ls.`log_type` = '" . db_escape($conn, $logType) . "'";
    }

    if ($year >= 2000 && $year <= 2100) {
        $yy = str_pad((string) ($year - 2000), 2, '0', STR_PAD_LEFT);
        $sql .= " AND SUBSTRING(ls.`log_date`, 1, 2) = '" . db_escape($conn, $yy) . "'";
    }

    if ($month > 0) {
        $mm = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $sql .= " AND SUBSTRING(ls.`log_date`, 3, 2) = '" . db_escape($conn, $mm) . "'";
    }

    $sql .= " GROUP BY ls.`pre_id`
              ORDER BY MIN(ls.`log_date`) DESC, ls.`pre_id` DESC
              LIMIT " . $limit;

    $exec = db_exec($conn, $sql);
    if (!$exec) {
        return array();
    }

    $rows = array();
    while ($row = db_fetch_array($exec)) {
        $rows[] = array(
            'pre_id' => (string) $row['pre_id'],
            'log_date' => (string) $row['log_date'],
            'log_date_display' => stock_report_format_log_datetime((string) $row['log_date']),
            'log_type' => (string) $row['log_type'],
            'log_by' => (string) $row['log_by'],
            'part_count' => (int) $row['part_count'],
            'total_qty' => (float) $row['total_qty'],
        );
    }

    return $rows;
}

/**
 * ดึงแถวอะไหล่ของเอกสารที่จ่ายแล้วจาก log_stock
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $preId เลขเอกสาร
 * @return array<int, array{part_id:string, part_name:string, product:string, amount:string, remarks:string}>
 */
function pickup_history_fetch_lines($conn, $preId)
{
    $preId = trim((string) $preId);
    if ($preId === '' || !preg_match('/^[0-9A-Za-z-]{1,32}$/', $preId)) {
        return array();
    }

    $sql = 'SELECT ls.`log_part`, ls.`log_product`, ls.`log_amount`, ls.`log_remarks`,
                   p.`part_name`
            FROM `log_stock` ls
            LEFT JOIN `part` p ON p.`part_id` = ls.`log_part`
            WHERE ls.`pre_id` = ? AND ls.`log_type` LIKE ?
            ORDER BY p.`part_name` ASC, ls.`log_product` ASC, ls.`log_part` ASC';

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        error_log('pickup_history_fetch_lines prepare: ' . mysqli_error($conn));

        return array();
    }

    $typeLike = 'เบิก%';
    mysqli_stmt_bind_param($stmt, 'ss', $preId, $typeLike);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return array();
    }

    mysqli_stmt_bind_result($stmt, $logPart, $logProduct, $logAmount, $logRemarks, $partName);

    $lines = array();
    while (mysqli_stmt_fetch($stmt)) {
        $name = trim((string) $partName);
        if ($name === '') {
            $name = 'Part #' . (string) $logPart;
        }
        $lines[] = array(
            'part_id' => (string) $logPart,
            'part_name' => $name,
            'product' => (string) $logProduct,
            'amount' => (string) $logAmount,
            'remarks' => trim((string) $logRemarks),
        );
    }

    mysqli_stmt_close($stmt);

    return $lines;
}
