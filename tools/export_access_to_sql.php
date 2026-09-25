<?php
/**
 * Export ข้อมูลจาก Microsoft Access (.mdb) เป็นไฟล์ SQL สำหรับ MariaDB
 *
 * รันบน Windows ที่มี Microsoft Access Driver (*.mdb) และ PHP ODBC:
 *   php tools/export_access_to_sql.php
 *
 * ผลลัพธ์: database/inventory_data.sql
 */

// ─ การตั้งค่า ─────────────────────────────────────────────────────────────────

$PROJECT_ROOT = dirname(__DIR__);
$MDB_PATH = $PROJECT_ROOT . DIRECTORY_SEPARATOR . '_db' . DIRECTORY_SEPARATOR . 'inventory.mdb';
$OUTPUT_FILE = $PROJECT_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'inventory_data.sql';
$DB_NAME = 'biton_inventory';

/** ลำดับตาราง (parent ก่อน child) */
$TABLE_ORDER = array(
    'users',
    'employee',
    'categories_product',
    'product',
    'tbl_vendor',
    'part',
    'production_formula',
    'formula_name',
    'pre_stock',
    'log_stock',
    'log_part',
    'setup_log',
    'setup_pd',
    'part_list',
    'part_raw',
    'Product_Name',
    'Spare',
    'Transac',
    'Transac_repair',
    'Transac_spare',
);

/** ตารางที่ไม่ export (ว่างหรือซ้ำ) */
$IGNORE_TABLES = array('vendor');

/** ตารางระบบ Access ที่ไม่ export */
$SKIP_TABLES = array(
    'MSysAccessObjects', 'MSysACEs', 'MSysObjects', 'MSysQueries',
    'MSysRelationships', 'MSysAccessXML', 'MSysNavPaneGroupCategories',
    'MSysNavPaneGroups', 'MSysNavPaneObjects', 'MSysNavPaneSections',
    'MSysPermissions', 'MSysProperties', 'MSysQueries', 'MSysResources',
    'MSysRowCols', 'MSysTables', 'MSysUserAccounts', 'MSysUserPermissions',
    'MSysUsers', 'MSysWebUserRoles', 'MSysWebUsers',
);

// ─ helpers ───────────────────────────────────────────────────────────────────

/**
 * escape ค่าสำหรับ INSERT SQL
 *
 * @param mixed $value
 * @return string
 */
/**
 * แปลง encoding จาก Access (TIS-620) เป็น UTF-8
 *
 * @param string $str
 * @return string
 */
function to_utf8($str)
{
    if ($str === '' || $str === null) {
        return $str;
    }
    if (function_exists('mb_check_encoding') && mb_check_encoding($str, 'UTF-8')) {
        return $str;
    }
    if (function_exists('iconv')) {
        $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $str);
        if ($converted !== false) {
            return $converted;
        }
        $converted = @iconv('Windows-874', 'UTF-8//IGNORE', $str);
        if ($converted !== false) {
            return $converted;
        }
    }
    return $str;
}

function sql_value($value)
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $str = to_utf8((string) $value);
    $str = str_replace(array('\\', "\0", "\n", "\r", "'", '"'), array('\\\\', '', "\n", "\r", "''", '\"'), $str);

    return "'" . $str . "'";
}

/**
 * ดึงรายชื่อตารางจาก Access
 *
 * @param resource $conn ODBC connection
 * @return array
 */
function get_access_tables($conn)
{
    $tables = array();
    $result = odbc_tables($conn);

    if (!$result) {
        return $tables;
    }

    while ($row = odbc_fetch_array($result)) {
        if (!isset($row['TABLE_TYPE']) || $row['TABLE_TYPE'] !== 'TABLE') {
            continue;
        }
        if (!isset($row['TABLE_NAME'])) {
            continue;
        }
        $name = $row['TABLE_NAME'];
        if (strpos($name, 'MSys') === 0) {
            continue;
        }
        $tables[] = $name;
    }

    return $tables;
}

/**
 * export ตารางเดียวเป็น INSERT statements
 *
 * @param resource $conn
 * @param string $table
 * @return array [sql_lines, row_count]
 */
function export_table($conn, $table)
{
    $lines = array();
    $safeTable = str_replace('`', '``', $table);
    $sql = 'SELECT * FROM [' . $table . ']';
    $result = @odbc_exec($conn, $sql);

    if (!$result) {
        return array(array('-- ERROR [' . $table . ']: ' . odbc_errormsg($conn)), 0);
    }

    $colCount = odbc_num_fields($result);
    $columns = array();

    for ($i = 1; $i <= $colCount; $i++) {
        $columns[] = '`' . str_replace('`', '``', odbc_field_name($result, $i)) . '`';
    }

    $colList = implode(', ', $columns);
    $rowCount = 0;

    while ($row = odbc_fetch_array($result)) {
        $values = array();
        foreach ($columns as $idx => $col) {
            $fieldName = trim($col, '`');
            $val = isset($row[$fieldName]) ? $row[$fieldName] : null;
            $values[] = sql_value($val);
        }
        $lines[] = 'INSERT INTO `' . $safeTable . '` (' . $colList . ') VALUES (' . implode(', ', $values) . ');';
        $rowCount++;
    }

    return array($lines, $rowCount);
}

// ─ main ──────────────────────────────────────────────────────────────────────

date_default_timezone_set('Asia/Bangkok');

if (!function_exists('odbc_connect')) {
    fwrite(STDERR, "PHP ODBC extension ไม่พร้อมใช้งาน\n");
    exit(1);
}

if (!file_exists($MDB_PATH)) {
    $alt = $PROJECT_ROOT . DIRECTORY_SEPARATOR . '_db' . DIRECTORY_SEPARATOR . 'Inventory.mdb';
    if (file_exists($alt)) {
        $MDB_PATH = $alt;
    } else {
        fwrite(STDERR, "ไม่พบไฟล์: $MDB_PATH\n");
        exit(1);
    }
}

echo "MDB: $MDB_PATH\n";

$conn = @odbc_connect('Driver={Microsoft Access Driver (*.mdb)};Dbq=' . $MDB_PATH, '', '');

if (!$conn) {
    fwrite(STDERR, "เชื่อมต่อ Access ไม่ได้: " . odbc_errormsg() . "\n");
    exit(1);
}

$foundTables = get_access_tables($conn);
$ordered = array();

foreach ($TABLE_ORDER as $t) {
    if (in_array($t, $foundTables, true)) {
        $ordered[] = $t;
    }
}

foreach ($foundTables as $t) {
    if (!in_array($t, $ordered, true) && !in_array($t, $SKIP_TABLES, true) && !in_array($t, $IGNORE_TABLES, true)) {
        $ordered[] = $t;
    }
}

$out = array();
$out[] = '-- =============================================================================';
$out[] = '-- ข้อมูลจาก Access: inventory.mdb';
$out[] = '-- สร้างเมื่อ: ' . date('Y-m-d H:i:s');
$out[] = '-- Import หลัง inventory.sql (โครงสร้างตาราง)';
$out[] = '-- =============================================================================';
$out[] = '';
$out[] = 'SET NAMES utf8mb4;';
$out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$out[] = 'USE `' . $DB_NAME . '`;';
$out[] = '';

$totalRows = 0;

foreach ($ordered as $table) {
    echo "Export: $table ... ";
    $out[] = '-- ─ ' . $table . ' ─';
    $out[] = 'DELETE FROM `' . str_replace('`', '``', $table) . '`;';
    list($lines, $count) = export_table($conn, $table);
    $out = array_merge($out, $lines);
    $out[] = '';
    $totalRows += $count;
    echo "$count rows\n";
}

$out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$out[] = '-- จบ export รวม ' . $totalRows . ' แถว';

$outDir = dirname($OUTPUT_FILE);
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$content = implode("\n", $out);

// BOM UTF-8 สำหรับ phpMyAdmin / Editor บน Windows
if (substr($content, 0, 3) !== "\xEF\xBB\xBF") {
    $content = "\xEF\xBB\xBF" . $content;
}

if (file_put_contents($OUTPUT_FILE, $content) === false) {
    fwrite(STDERR, "เขียนไฟล์ไม่ได้: $OUTPUT_FILE\n");
    exit(1);
}

$copyTo = $PROJECT_ROOT . DIRECTORY_SEPARATOR . 'inventory_data.sql';
@file_put_contents($copyTo, $content);

odbc_close($conn);

echo "\nสำเร็จ: $OUTPUT_FILE\n";
echo "ขนาด: " . number_format(filesize($OUTPUT_FILE)) . " bytes\n";
echo "รวม $totalRows แถว\n";
