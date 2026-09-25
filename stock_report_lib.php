<?php
/**
 * ไลบรารีดึงข้อมูลรายงานการเบิกใช้ Stock รายเดือน
 *
 * อ่านจากตาราง log_stock เฉพาะ log_type = "เบิกผลิต"
 * parse วันที่จากรูปแบบ YYMMDD-HHMMSS ใน log_date
 * และคำนวณจำนวนเครื่องจาก log_amount ตาม log_part ที่เลือกใน filter
 *
 * ใช้ร่วมกับ stock_report.php, production_history.php สำหรับรายงานและประวัติเบิกผลิต
 */

// ─ Constants ──────────────────────────────────────────────────────────────────

define('STOCK_REPORT_MONTH_LABELS', 'ม.ค.,ก.พ.,มี.ค.,เม.ย.,พ.ค.,มิ.ย.,ก.ค.,ส.ค.,ก.ย.,ต.ค.,พ.ย.,ธ.ค.');
define('STOCK_REPORT_HISTORY_LIMIT', 500);

define('STOCK_REPORT_PRODUCTION_LOG_TYPE', 'เบิกผลิต');

// ─ Helpers ────────────────────────────────────────────────────────────────────

/**
 * แปลง log_date (YYMMDD-HHMMSS) เป็นปีและเดือน
 *
 * @param string $logDate ค่า log_date จากฐานข้อมูล
 * @return array|null คีย์ year, month หรือ null ถ้า parse ไม่ได้
 */
function stock_report_parse_log_date($logDate)
{
    $logDate = trim((string) $logDate);
    if (strlen($logDate) < 6) {
        return null;
    }

    $ymd = substr($logDate, 0, 6);
    if (!ctype_digit($ymd)) {
        return null;
    }

    $yy = (int) substr($ymd, 0, 2);
    $mm = (int) substr($ymd, 2, 2);
    if ($mm < 1 || $mm > 12) {
        return null;
    }

    return array(
        'year' => 2000 + $yy,
        'month' => $mm,
        'day' => (int) substr($ymd, 4, 2),
    );
}

/**
 * แปลง log_date เป็นข้อความวันที่-เวลาสำหรับแสดงผล
 *
 * @param string $logDate ค่า log_date รูปแบบ YYMMDD-HHMMSS
 * @return string ข้อความวันที่ เช่น 18/05/2569 13:26
 */
function stock_report_format_log_datetime($logDate)
{
    $parsed = stock_report_parse_log_date($logDate);
    if ($parsed === null) {
        return trim((string) $logDate);
    }

    $logDate = trim((string) $logDate);
    $timeText = '';
    $dashPos = strpos($logDate, '-');
    if ($dashPos !== false && strlen($logDate) >= $dashPos + 5) {
        $timeRaw = substr($logDate, $dashPos + 1);
        $hh = substr($timeRaw, 0, 2);
        $mi = substr($timeRaw, 2, 2);
        if (ctype_digit($hh) && ctype_digit($mi)) {
            $timeText = $hh . ':' . $mi;
        }
    }

    $day = isset($parsed['day']) ? (int) $parsed['day'] : 0;
    $beYear = (int) $parsed['year'] + 543;
    $dateText = sprintf('%02d/%02d/%d', $day, (int) $parsed['month'], $beYear);

    if ($timeText !== '') {
        return $dateText . ' ' . $timeText;
    }

    return $dateText;
}

/**
 * คืนรายชื่อเดือนภาษาไทย 12 เดือน
 *
 * @return array
 */
function stock_report_month_labels()
{
    return explode(',', STOCK_REPORT_MONTH_LABELS);
}

/**
 * สร้างเงื่อนไข SQL กรองเฉพาะรายการเบิกผลิต
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $alias alias ของตาราง log_stock
 * @return string เงื่อนไข WHERE สำหรับ log_type
 */
function stock_report_production_type_sql($conn, $alias = '')
{
    $type = db_escape($conn, STOCK_REPORT_PRODUCTION_LOG_TYPE);
    $column = ($alias !== '') ? $alias . '.`log_type`' : '`log_type`';

    return $column . " = '" . $type . "'";
}

/**
 * ดึงปีที่มีข้อมูลเบิกจาก log_stock
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการปี เรียงจากมากไปน้อย
 */
function stock_report_available_years($conn)
{
    $sql = "SELECT DISTINCT SUBSTRING(`log_date`, 1, 2) AS yy
            FROM `log_stock`
            WHERE " . stock_report_production_type_sql($conn) . "
              AND `log_date` IS NOT NULL AND `log_date` <> ''
              AND CHAR_LENGTH(`log_date`) >= 6
              AND `pre_id` IS NOT NULL AND `pre_id` <> ''
              AND `log_part` IS NOT NULL AND `log_part` <> ''
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
 * ดึงรายชื่อ log_part ที่มีใน log เบิกผลิต
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายชื่อ log_part เรียงตามชื่อ
 */
function stock_report_log_part_list($conn)
{
    $sql = "SELECT DISTINCT `log_part` AS name
            FROM `log_stock`
            WHERE " . stock_report_production_type_sql($conn) . "
              AND `log_part` IS NOT NULL AND `log_part` <> ''
              AND `pre_id` IS NOT NULL AND `pre_id` <> ''
            ORDER BY `log_part` ASC";
    $exec = db_exec($conn, $sql);
    if (!$exec) {
        return array();
    }

    $list = array();
    while ($row = db_fetch_array($exec)) {
        $list[] = (string) $row['name'];
    }

    return $list;
}

/**
 * ตรวจสอบ log_part ที่เลือกจาก filter ให้ตรงกับรายการที่มีจริง
 *
 * @param string $requested ค่าจาก request (หรือ All/ว่าง = ทั้งหมด)
 * @param array $available รายชื่อ log_part ที่มีในระบบ
 * @return string log_part ที่ใช้กรอง หรือค่าว่าง = ทั้งหมด
 */
function stock_report_resolve_log_part($requested, array $available)
{
    $requested = trim((string) $requested);
    if ($requested === '' || $requested === 'All') {
        return '';
    }

    foreach ($available as $name) {
        if ((string) $name === $requested) {
            return $requested;
        }
    }

    return '';
}

/**
 * ดึงยอดเบิกผลิตต่อ pre_id รายเดือน (อิง log_part + log_amount)
 *
 * จำนวนเครื่องต่อใบ = SUM(log_amount) ของ log_part ที่กรอง (มักมี 1 แถวต่อ pre_id)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array $years ปีที่ต้องการ
 * @param string $logPart กรอง log_part หรือค่าว่าง = ทั้งหมด
 * @return array แถว yy, mm, log_part_name, pre_id, prod_qty
 */
function stock_report_fetch_pre_id_monthly_qty($conn, array $years, $logPart = '')
{
    if (count($years) === 0) {
        return array();
    }

    $yyFilters = array();
    foreach ($years as $year) {
        $yy = str_pad((string) ((int) $year - 2000), 2, '0', STR_PAD_LEFT);
        $yyFilters[] = "'" . db_escape($conn, $yy) . "'";
    }

    $sql = "SELECT SUBSTRING(ls.`log_date`, 1, 2) AS yy,
                   SUBSTRING(ls.`log_date`, 3, 2) AS mm,
                   ls.`log_part` AS log_part_name,
                   ls.`pre_id` AS pre_id,
                   SUM(ls.`log_amount`) AS prod_qty
            FROM `log_stock` ls
            WHERE " . stock_report_production_type_sql($conn, 'ls') . "
              AND ls.`log_date` IS NOT NULL AND ls.`log_date` <> ''
              AND CHAR_LENGTH(ls.`log_date`) >= 6
              AND SUBSTRING(ls.`log_date`, 1, 2) IN (" . implode(',', $yyFilters) . ")
              AND SUBSTRING(ls.`log_date`, 3, 2) BETWEEN '01' AND '12'
              AND ls.`pre_id` IS NOT NULL AND ls.`pre_id` <> ''
              AND ls.`log_part` IS NOT NULL AND ls.`log_part` <> ''";

    if ($logPart !== '') {
        $sql .= " AND ls.`log_part` = '" . db_escape($conn, $logPart) . "'";
    }

    $sql .= ' GROUP BY yy, mm, log_part_name, pre_id ORDER BY yy, mm, log_part_name, pre_id';

    $exec = db_exec($conn, $sql);
    if (!$exec) {
        return array();
    }

    $rows = array();
    while ($row = db_fetch_array($exec)) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * ดึงประวัติการเบิกผลิตรายใบ (1 แถวต่อ 1 pre_id) เรียงจากล่าสุด
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $logPart กรอง log_part หรือค่าว่าง = ทั้งหมด
 * @param int $limit จำนวนแถวสูงสุด
 * @param int $year ปี ค.ศ. หรือ 0 = ทุกปี
 * @param int $month เดือน 1-12 หรือ 0 = ทุกเดือน
 * @return array แถว pre_id, log_date, log_part, prod_qty, log_by
 */
function stock_report_fetch_production_history($conn, $logPart = '', $limit = 0, $year = 0, $month = 0)
{
    $limit = (int) $limit;
    if ($limit <= 0) {
        $limit = (int) STOCK_REPORT_HISTORY_LIMIT;
    }
    $limit = max(1, min(2000, $limit));
    $year = (int) $year;
    $month = (int) $month;
    if ($month < 1 || $month > 12) {
        $month = 0;
    }

    $sql = "SELECT ls.`pre_id` AS pre_id,
                   MIN(ls.`log_date`) AS log_date,
                   ls.`log_part` AS log_part_name,
                   MIN(ls.`log_by`) AS log_by,
                   SUM(ls.`log_amount`) AS prod_qty
            FROM `log_stock` ls
            WHERE " . stock_report_production_type_sql($conn, 'ls') . "
              AND ls.`log_date` IS NOT NULL AND ls.`log_date` <> ''
              AND CHAR_LENGTH(ls.`log_date`) >= 6
              AND ls.`pre_id` IS NOT NULL AND ls.`pre_id` <> ''
              AND ls.`log_part` IS NOT NULL AND ls.`log_part` <> ''";

    if ($logPart !== '') {
        $sql .= " AND ls.`log_part` = '" . db_escape($conn, $logPart) . "'";
    }

    if ($year >= 2000 && $year <= 2100) {
        $yy = str_pad((string) ($year - 2000), 2, '0', STR_PAD_LEFT);
        $sql .= " AND SUBSTRING(ls.`log_date`, 1, 2) = '" . db_escape($conn, $yy) . "'";
    }

    if ($month > 0) {
        $mm = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $sql .= " AND SUBSTRING(ls.`log_date`, 3, 2) = '" . db_escape($conn, $mm) . "'";
    }

    $sql .= " GROUP BY ls.`pre_id`, ls.`log_part`
              ORDER BY MIN(ls.`log_date`) DESC
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
            'log_part' => (string) $row['log_part_name'],
            'log_by' => (string) $row['log_by'],
            'prod_qty' => (float) $row['prod_qty'],
        );
    }

    return $rows;
}

/**
 * ตรวจสอบปีที่เลือกจาก filter ประวัติให้ตรงกับข้อมูลที่มีจริง
 *
 * @param int $requested ปีจาก request (0 = ทุกปี)
 * @param array $available ปีที่มีในฐานข้อมูล
 * @return int ปีที่ใช้กรอง หรือ 0 = ทุกปี
 */
function stock_report_resolve_history_year($requested, array $available)
{
    $requested = (int) $requested;
    if ($requested < 2000 || $requested > 2100) {
        return 0;
    }

    foreach ($available as $year) {
        if ((int) $year === $requested) {
            return $requested;
        }
    }

    return 0;
}

/**
 * ตรวจสอบเดือนที่เลือกจาก filter ประวัติ
 *
 * @param int $requested เดือนจาก request (0 = ทุกเดือน)
 * @return int เดือน 1-12 หรือ 0 = ทุกเดือน
 */
function stock_report_resolve_history_month($requested)
{
    $requested = (int) $requested;
    if ($requested >= 1 && $requested <= 12) {
        return $requested;
    }

    return 0;
}

/**
 * สร้างข้อความสรุปช่วงเวลาที่กรองในหน้าประวัติ
 *
 * @param int $year ปี ค.ศ. หรือ 0
 * @param int $month เดือน 1-12 หรือ 0
 * @param string $lblAll ข้อความเมื่อไม่ได้กรอง
 * @return string ข้อความช่วงเวลา
 */
function stock_report_history_period_label($year, $month, $lblAll)
{
    $year = (int) $year;
    $month = (int) $month;
    $parts = array();

    if ($month >= 1 && $month <= 12) {
        $labels = stock_report_month_labels();
        $parts[] = $labels[$month - 1];
    }

    if ($year >= 2000 && $year <= 2100) {
        $parts[] = (string) $year;
    }

    if (count($parts) === 0) {
        return (string) $lblAll;
    }

    return implode(' ', $parts);
}

/**
 * สรุปยอดรวมจากรายการประวัติเบิกผลิต
 *
 * @param array $rows ผลลัพธ์จาก stock_report_fetch_production_history
 * @return array คีย์ total_docs, total_qty
 */
function stock_report_summarize_history(array $rows)
{
    $totalDocs = count($rows);
    $totalQty = 0.0;

    foreach ($rows as $row) {
        $totalQty += (float) $row['prod_qty'];
    }

    return array(
        'total_docs' => $totalDocs,
        'total_qty' => $totalQty,
    );
}

/**
 * สร้างยอดรายเดือนและ breakdown tooltip จาก pre_id + log_amount
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array $years ปีที่ต้องการ
 * @param string $logPart กรอง log_part หรือค่าว่าง = ทั้งหมด
 * @return array คีย์ usage, breakdown
 */
function stock_report_build_pre_id_metrics($conn, array $years, $logPart = '')
{
    $monthLabels = stock_report_month_labels();
    $series = array();
    $txSeries = array();
    $yearTotals = array();
    $txCounts = array();
    $breakdown = array();

    foreach ($years as $year) {
        $year = (int) $year;
        $yearKey = (string) $year;
        $series[$year] = array_fill(0, 12, 0.0);
        $txSeries[$year] = array_fill(0, 12, 0);
        $yearTotals[$year] = 0.0;
        $txCounts[$year] = 0;
        $breakdown[$yearKey] = array();
        for ($m = 0; $m < 12; $m++) {
            $breakdown[$yearKey][$m] = array();
        }
    }

    if (count($years) === 0) {
        return array(
            'usage' => array(
                'months' => $monthLabels,
                'years' => array(),
                'series' => array(),
                'txSeries' => array(),
                'yearTotals' => array(),
                'txCounts' => array(),
            ),
            'breakdown' => $breakdown,
        );
    }

    $rawRows = stock_report_fetch_pre_id_monthly_qty($conn, $years, $logPart);
    $monthProductPreIds = array();

    foreach ($rawRows as $row) {
        $year = 2000 + (int) $row['yy'];
        $month = (int) $row['mm'];
        if ($month < 1 || $month > 12 || !isset($series[$year])) {
            continue;
        }

        $monthIdx = $month - 1;
        $partName = (string) $row['log_part_name'];
        $preId = (string) $row['pre_id'];
        $prodQty = (float) $row['prod_qty'];

        $series[$year][$monthIdx] += $prodQty;
        $yearTotals[$year] += $prodQty;

        $yearKey = (string) $year;
        if (!isset($breakdown[$yearKey][$monthIdx][$partName])) {
            $breakdown[$yearKey][$monthIdx][$partName] = array(
                'qty' => 0.0,
                'tx' => 0,
            );
        }

        $breakdown[$yearKey][$monthIdx][$partName]['qty'] += $prodQty;
        $breakdown[$yearKey][$monthIdx][$partName]['tx']++;

        $monthKey = $year . '|' . $month;
        if (!isset($monthProductPreIds[$monthKey])) {
            $monthProductPreIds[$monthKey] = array();
        }
        $monthProductPreIds[$monthKey][$preId] = true;
    }

    foreach ($monthProductPreIds as $monthKey => $preIdMap) {
        $parts = explode('|', $monthKey);
        $year = (int) $parts[0];
        $month = (int) $parts[1];
        if (!isset($txSeries[$year]) || $month < 1 || $month > 12) {
            continue;
        }
        $tx = count($preIdMap);
        $txSeries[$year][$month - 1] = $tx;
        $txCounts[$year] += $tx;
    }

    krsort($series);
    krsort($txSeries);
    krsort($yearTotals);
    krsort($txCounts);

    return array(
        'usage' => array(
            'months' => $monthLabels,
            'years' => array_keys($series),
            'series' => $series,
            'txSeries' => $txSeries,
            'yearTotals' => $yearTotals,
            'txCounts' => $txCounts,
        ),
        'breakdown' => $breakdown,
    );
}

/**
 * รวมยอดเบิกผลิตรายเดือนแยกตามปี (อิง pre_id + log_amount)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array $years ปีที่ต้องการ (เช่น [2024, 2025])
 * @param string $logPart กรอง log_part หรือค่าว่าง = ทั้งหมด
 * @return array โครงสร้าง years, months, series, totals
 */
function stock_report_monthly_usage($conn, array $years, $logPart = '')
{
    $metrics = stock_report_build_pre_id_metrics($conn, $years, $logPart);

    return $metrics['usage'];
}

/**
 * ดึงยอดเบิกผลิตรายเดือนแยกตาม log_part สำหรับ tooltip
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param array $years ปีที่ต้องการ
 * @param string $logPart กรอง log_part หรือค่าว่าง = ทุก part
 * @return array [ปี][เดือน0-11][log_part] => qty, tx
 */
function stock_report_monthly_part_breakdown($conn, array $years, $logPart = '')
{
    $metrics = stock_report_build_pre_id_metrics($conn, $years, $logPart);

    return $metrics['breakdown'];
}

/**
 * กรองและจัดเรียงปีที่ผู้ใช้เลือกให้ถูกต้อง
 *
 * @param array $requested ปีจาก request
 * @param array $available ปีที่มีในฐานข้อมูล
 * @return array ปีที่เลือกได้จริง เรียงจากมากไปน้อย
 */
function stock_report_resolve_years(array $requested, array $available)
{
    $availableMap = array();
    foreach ($available as $y) {
        $availableMap[(int) $y] = true;
    }

    $picked = array();
    foreach ($requested as $y) {
        $y = (int) $y;
        if ($y >= 2000 && $y <= 2100 && isset($availableMap[$y])) {
            $picked[$y] = true;
        }
    }

    if (count($picked) === 0 && count($available) > 0) {
        $picked[(int) $available[0]] = true;
        if (isset($available[1])) {
            $picked[(int) $available[1]] = true;
        }
    }

    $years = array_keys($picked);
    rsort($years, SORT_NUMERIC);

    return $years;
}

/**
 * เลือกปีเดียวสำหรับรายงานแยก Product
 *
 * @param int $requested ปีจาก request
 * @param array $available ปีที่มีในฐานข้อมูล
 * @return int ปีที่ใช้แสดงผล
 */
function stock_report_resolve_year($requested, array $available)
{
    $requested = (int) $requested;
    if ($requested >= 2000 && $requested <= 2100) {
        foreach ($available as $y) {
            if ((int) $y === $requested) {
                return $requested;
            }
        }
    }
    if (count($available) > 0) {
        return (int) $available[0];
    }

    return (int) date('Y');
}

/**
 * ดึง log_part ที่มียอดเบิกสูงสุดในปีที่กำหนด
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $year ปี ค.ศ.
 * @param int $limit จำนวนสูงสุด
 * @return array รายชื่อ log_part
 */
function stock_report_top_log_parts_by_year($conn, $year, $limit = 8)
{
    $limit = max(1, min(20, (int) $limit));
    $rows = stock_report_fetch_pre_id_monthly_qty($conn, array((int) $year), '');
    $totals = array();

    foreach ($rows as $row) {
        $name = (string) $row['log_part_name'];
        if (!isset($totals[$name])) {
            $totals[$name] = 0.0;
        }
        $totals[$name] += (float) $row['prod_qty'];
    }

    arsort($totals);
    $list = array();
    foreach ($totals as $name => $qty) {
        if ($qty <= 0) {
            continue;
        }
        $list[] = $name;
        if (count($list) >= $limit) {
            break;
        }
    }

    return $list;
}

/**
 * รวมยอดเบิกรายเดือนแยกตาม Product ในปีที่กำหนด
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $year ปี ค.ศ.
 * @param array $products รายชื่อ product ที่ต้องการ
 * @return array โครงสร้าง months, products, series, txSeries, productTotals
 */
function stock_report_monthly_by_product($conn, $year, array $products)
{
    $monthLabels = stock_report_month_labels();
    $year = (int) $year;
    $series = array();
    $txSeries = array();
    $productTotals = array();
    $txCounts = array();

    foreach ($products as $product) {
        $product = (string) $product;
        $series[$product] = array_fill(0, 12, 0.0);
        $txSeries[$product] = array_fill(0, 12, 0);
        $productTotals[$product] = 0.0;
        $txCounts[$product] = 0;
    }

    if (count($products) === 0) {
        return array(
            'year' => $year,
            'months' => $monthLabels,
            'products' => array(),
            'series' => array(),
            'txSeries' => array(),
            'productTotals' => array(),
            'txCounts' => array(),
        );
    }

    $yy = str_pad((string) ($year - 2000), 2, '0', STR_PAD_LEFT);
    $yySql = db_escape($conn, $yy);
    $productFilters = array();
    foreach ($products as $product) {
        $productFilters[] = "'" . db_escape($conn, (string) $product) . "'";
    }

    $sql = "SELECT `log_product` AS product_name,
                   SUBSTRING(`log_date`, 3, 2) AS mm,
                   SUM(`log_amount`) AS total_qty,
                   COUNT(DISTINCT `pre_id`) AS tx_count
            FROM `log_stock`
            WHERE `log_type` LIKE 'เบิก%'
              AND `log_date` IS NOT NULL AND `log_date` <> ''
              AND CHAR_LENGTH(`log_date`) >= 6
              AND SUBSTRING(`log_date`, 1, 2) = '" . $yySql . "'
              AND SUBSTRING(`log_date`, 3, 2) BETWEEN '01' AND '12'
              AND `log_product` IN (" . implode(',', $productFilters) . ")
            GROUP BY `log_product`, mm
            ORDER BY `log_product`, mm";

    $exec = db_exec($conn, $sql);
    if ($exec) {
        while ($row = db_fetch_array($exec)) {
            $product = (string) $row['product_name'];
            $month = (int) $row['mm'];
            if (!isset($series[$product]) || $month < 1 || $month > 12) {
                continue;
            }
            $qty = (float) $row['total_qty'];
            $tx = (int) $row['tx_count'];
            $series[$product][$month - 1] = $qty;
            $txSeries[$product][$month - 1] = $tx;
            $productTotals[$product] += $qty;
            $txCounts[$product] += $tx;
        }
    }

    arsort($productTotals);

    return array(
        'year' => $year,
        'months' => $monthLabels,
        'products' => array_keys($productTotals),
        'series' => $series,
        'txSeries' => $txSeries,
        'productTotals' => $productTotals,
        'txCounts' => $txCounts,
    );
}

/**
 * กรองรายการ Product ที่เลือกจาก request
 *
 * @param array $requested ชื่อ product จาก request
 * @param array $available รายชื่อที่มีในระบบ
 * @param array $defaultProducts ค่าเริ่มต้นเมื่อไม่ได้เลือก
 * @return array product ที่ใช้แสดงผล
 */
function stock_report_resolve_products(array $requested, array $available, array $defaultProducts)
{
    $availableMap = array();
    foreach ($available as $name) {
        $availableMap[(string) $name] = true;
    }

    $picked = array();
    foreach ($requested as $name) {
        $name = trim((string) $name);
        if ($name !== '' && isset($availableMap[$name])) {
            $picked[$name] = true;
        }
    }

    if (count($picked) === 0) {
        foreach ($defaultProducts as $name) {
            $name = (string) $name;
            if (isset($availableMap[$name])) {
                $picked[$name] = true;
            }
        }
    }

    $products = array_keys($picked);
    sort($products, SORT_STRING);

    return $products;
}
