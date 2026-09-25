<?php
/**
 * migrate_mdb_to_mariadb.php — ย้ายข้อมูลจาก Access (.mdb) ไป MariaDB
 *
 * วัตถุประสงค์:
 *   รวมขั้นตอน export จาก _db/inventory.mdb และ import เข้า MariaDB
 *   ให้รันได้ทั้ง CLI และเบราว์เซอร์ (บน Windows ที่มี Access ODBC Driver)
 *
 * Flow:
 *   1) export  — อ่าน .mdb ผ่าน ODBC → database/inventory_data.sql
 *   2) import  — รัน inventory_schema_access.sql แล้ว inventory_data.sql
 *   3) all     — export แล้ว import ต่อเนื่อง
 *   4) status  — ตรวจสอบไฟล์ สิทธิ์ extension และจำนวนแถวใน MariaDB
 *   5) compare — เปรียบเทียบ part_amount ระหว่าง Access กับ MariaDB
 *
 * ตัวอย่าง CLI (รันจากโฟลเดอร์โปรเจกต์):
 *   php tools/migrate_mdb_to_mariadb.php compare
 *
 * ตัวอย่าง Web (Apache ชี้ document root มาที่โฟลเดอร์โปรเจกต์):
 *   http://localhost/inventory/tools/migrate_mdb_to_mariadb.php?key=YOUR_KEY&action=export
 */

// ─ Path bootstrap ─────────────────────────────────────────────────────────────

/**
 * โหลดการตั้งค่า path เพิ่มเติมจาก tools/migrate.config.php (ถ้ามี)
 *
 * @return string|null root ที่ override หรือ null
 */
function migrate_config_root()
{
    $configFile = __DIR__ . DIRECTORY_SEPARATOR . 'migrate.config.php';

    if (!file_exists($configFile)) {
        return null;
    }

    $MIGRATE_ROOT = null;
    include $configFile;

    if (!isset($MIGRATE_ROOT) || !is_string($MIGRATE_ROOT) || trim($MIGRATE_ROOT) === '') {
        return null;
    }

    return rtrim(str_replace('/', DIRECTORY_SEPARATOR, $MIGRATE_ROOT), DIRECTORY_SEPARATOR);
}

/**
 * หา path ไฟล์ .mdb ในโฟลเดอร์ _db
 *
 * @param string $root root โปรเจกต์
 * @return string|null
 */
function migrate_find_mdb($root)
{
    $dbDir = $root . DIRECTORY_SEPARATOR . '_db';
    $names = array('inventory.mdb', 'Inventory.mdb');

    foreach ($names as $name) {
        $path = $dbDir . DIRECTORY_SEPARATOR . $name;
        if (file_exists($path) && is_readable($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * คืน path โปรเจกต์และไฟล์ที่ใช้ migration
 *
 * ใช้ root จากตำแหน่งสคริปต์ (OneDrive) หรือ migrate.config.php / INVENTORY_ROOT
 *
 * @return array root, mdb, schema, data
 */
function migrate_paths()
{
    static $paths = null;

    if ($paths !== null) {
        return $paths;
    }

    $root = migrate_config_root();

    if ($root === null) {
        $envRoot = getenv('INVENTORY_ROOT');
        if ($envRoot !== false && trim($envRoot) !== '') {
            $root = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $envRoot), DIRECTORY_SEPARATOR);
        }
    }

    if ($root === null) {
        $root = dirname(__DIR__);
    }

    $mdb = migrate_find_mdb($root);
    if ($mdb === null) {
        $mdb = $root . DIRECTORY_SEPARATOR . '_db' . DIRECTORY_SEPARATOR . 'inventory.mdb';
    }

    $paths = array(
        'root' => $root,
        'mdb' => $mdb,
        'schema' => $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'inventory_schema_access.sql',
        'data' => $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'inventory_data.sql',
    );

    return $paths;
}

define('MIGRATE_ROOT', migrate_paths()['root']);
define('MIGRATE_MDB', migrate_paths()['mdb']);
define('MIGRATE_SCHEMA', migrate_paths()['schema']);
define('MIGRATE_DATA', migrate_paths()['data']);
define('MIGRATE_DATA_PARTS', MIGRATE_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'data_parts');

/** รหัสยืนยันเมื่อรันผ่านเบราว์เซอร์ — เปลี่ยนค่านี้ก่อนใช้งานจริง */
define('MIGRATE_WEB_KEY', '');

/** ลำดับตารางตอน export (parent ก่อน child) */
define('MIGRATE_TABLE_ORDER', 'users,categories_product,product,tbl_vendor,part,formula_name,production_formula,pre_stock,log_stock,setup_log,setup_pd');

/** ตารางที่ข้าม */
define('MIGRATE_IGNORE_TABLES', 'vendor');

date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ────────────────────────────────────────────────────────────────────

/**
 * ตรวจว่ารันจาก CLI หรือไม่
 *
 * @return bool
 */
function migrate_is_cli()
{
    return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
}

/**
 * แสดงข้อความ (CLI = echo, Web = HTML)
 *
 * @param string $message ข้อความ
 * @param string $type     info|ok|err|warn
 * @return void
 */
function migrate_log($message, $type = 'info')
{
    $line = rtrim($message) . "\n";

    if (migrate_is_cli()) {
        if ($type === 'err') {
            fwrite(STDERR, $line);
        } else {
            echo $line;
        }
        return;
    }

    $class = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    $text = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<div class="log ' . $class . '">' . nl2br($text) . '</div>';
}

/**
 * แปลง encoding จาก Access (TIS-620 / Windows-874) เป็น UTF-8
 *
 * @param mixed $str ค่าต้นฉบับ
 * @return mixed
 */
function migrate_to_utf8($str)
{
    if ($str === '' || $str === null) {
        return $str;
    }

    if (!is_string($str)) {
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

/**
 * แปลงค่าเป็น MySQL literal สำหรับ INSERT
 *
 * @param mixed $value ค่าจาก ODBC
 * @return string
 */
function migrate_sql_value($value)
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

    $str = migrate_to_utf8((string) $value);
    $str = str_replace(
        array('\\', "\0", "\n", "\r", "'", '"'),
        array('\\\\', '', "\n", "\r", "''", '\"'),
        $str
    );

    return "'" . $str . "'";
}

/**
 * escape ชื่อ identifier สำหรับ SQL
 *
 * @param string $name ชื่อตาราง/คอลัมน์
 * @return string
 */
function migrate_quote_ident($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * ดึงรายชื่อตารางจาก Access
 *
 * @param resource $conn ODBC connection
 * @return array
 */
function migrate_get_access_tables($conn)
{
    $tables = array();
    $result = odbc_tables($conn);

    if (!$result) {
        return $tables;
    }

    while ($row = odbc_fetch_array($result)) {
        if (!isset($row['TABLE_TYPE'], $row['TABLE_NAME']) || $row['TABLE_TYPE'] !== 'TABLE') {
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
 * export ตารางเดียวจาก Access เป็น batch INSERT
 *
 * @param resource $conn       ODBC connection
 * @param string   $table      ชื่อตาราง
 * @param int      $batch_size จำนวนแถวต่อ INSERT
 * @return array [sql_lines, row_count]
 */
function migrate_export_table($conn, $table, $batch_size = 100)
{
    $lines = array();
    $safeTable = migrate_quote_ident($table);
    $sql = 'SELECT * FROM [' . str_replace(']', ']]', $table) . ']';
    $result = @odbc_exec($conn, $sql);

    if (!$result) {
        return array(array('-- ERROR [' . $table . ']: ' . odbc_errormsg($conn)), 0);
    }

    $colCount = odbc_num_fields($result);
    $columns = array();
    $fieldNames = array();

    for ($i = 1; $i <= $colCount; $i++) {
        $fieldNames[] = odbc_field_name($result, $i);
        $columns[] = migrate_quote_ident($fieldNames[$i - 1]);
    }

    $colList = implode(', ', $columns);
    $rowCount = 0;
    $batch = array();

    while ($row = odbc_fetch_array($result)) {
        $values = array();
        foreach ($fieldNames as $fieldName) {
            $val = isset($row[$fieldName]) ? $row[$fieldName] : null;
            $values[] = migrate_sql_value($val);
        }
        $batch[] = '  (' . implode(', ', $values) . ')';
        $rowCount++;

        if (count($batch) >= $batch_size) {
            $lines[] = 'INSERT INTO ' . $safeTable . ' (' . $colList . ') VALUES';
            $lines[] = implode(",\n", $batch) . ';';
            $lines[] = '';
            $batch = array();
        }
    }

    if (!empty($batch)) {
        $lines[] = 'INSERT INTO ' . $safeTable . ' (' . $colList . ') VALUES';
        $lines[] = implode(",\n", $batch) . ';';
        $lines[] = '';
    }

    return array($lines, $rowCount);
}

/**
 * จัดลำดับตารางก่อน export
 *
 * @param array $foundTables ตารางที่พบใน MDB
 * @return array
 */
function migrate_order_tables($foundTables)
{
    $order = array_filter(array_map('trim', explode(',', MIGRATE_TABLE_ORDER)));
    $ignore = array_filter(array_map('trim', explode(',', MIGRATE_IGNORE_TABLES)));
    $ordered = array();

    foreach ($order as $table) {
        if (in_array($table, $foundTables, true)) {
            $ordered[] = $table;
        }
    }

    foreach ($foundTables as $table) {
        if (!in_array($table, $ordered, true) && !in_array($table, $ignore, true)) {
            $ordered[] = $table;
        }
    }

    return $ordered;
}

/**
 * Export ข้อมูลจาก Access เป็นไฟล์ inventory_data.sql
 *
 * @return array สรุปผล ['ok'=>bool, 'rows'=>int, 'file'=>string, 'messages'=>array]
 */
function migrate_action_export()
{
    $messages = array();

    if (!function_exists('odbc_connect')) {
        return array('ok' => false, 'rows' => 0, 'file' => '', 'messages' => array('PHP extension odbc ไม่พร้อม — เปิดใน php.ini แล้ว restart Apache'));
    }

    if (!file_exists(MIGRATE_MDB) || !is_readable(MIGRATE_MDB)) {
        $hints = array(
            'ไม่พบหรือเปิดไฟล์ MDB ไม่ได้: ' . MIGRATE_MDB,
            'Project root: ' . MIGRATE_ROOT,
        );

        if (stripos(MIGRATE_ROOT, 'OneDrive') !== false) {
            $hints[] = 'โฟลเดอร์อยู่บน OneDrive — คลิกขวาโฟลเดอร์ _db → Always keep on this device';
            $hints[] = 'หรือสร้าง tools/migrate.config.php ชี้ path ที่ PHP อ่านได้ (ดู migrate.config.example.php)';
        }

        return array('ok' => false, 'rows' => 0, 'file' => '', 'messages' => $hints);
    }

    $conn = @odbc_connect('Driver={Microsoft Access Driver (*.mdb)};Dbq=' . MIGRATE_MDB, '', '');

    if (!$conn) {
        return array(
            'ok' => false,
            'rows' => 0,
            'file' => '',
            'messages' => array(
                'เชื่อมต่อ Access ไม่ได้: ' . odbc_errormsg(),
                'ติดตั้ง Microsoft Access Database Engine ODBC Driver',
            ),
        );
    }

    $foundTables = migrate_get_access_tables($conn);
    $ordered = migrate_order_tables($foundTables);
    $messages[] = 'พบ ' . count($foundTables) . ' ตารางใน MDB';
    $messages[] = 'Export ไปที่: ' . MIGRATE_DATA;

    $out = array();
    $out[] = '-- =============================================================================';
    $out[] = '-- inventory_data.sql — generated by migrate_mdb_to_mariadb.php';
    $out[] = '-- Source : ' . MIGRATE_MDB;
    $out[] = '-- Date   : ' . date('Y-m-d H:i:s');
    $out[] = '-- ขั้นตอน: รัน inventory_schema_access.sql ก่อน แล้วค่อย import ไฟล์นี้';
    $out[] = '-- =============================================================================';
    $out[] = '';
    $out[] = 'SET NAMES utf8mb4;';
    $out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $out[] = 'SET SQL_MODE = \'NO_AUTO_VALUE_ON_ZERO\';';
    $out[] = '';
    $out[] = 'USE `biton_inventory`;';
    $out[] = '';

    $totalRows = 0;

    foreach ($ordered as $table) {
        $messages[] = 'Export: ' . $table . ' ...';
        $out[] = '-- ─ ' . $table . ' ───────────────────────────────────────────────────────';
        $out[] = '';
        list($lines, $count) = migrate_export_table($conn, $table);
        $out = array_merge($out, $lines);
        $totalRows += $count;
        $messages[count($messages) - 1] .= ' ' . number_format($count) . ' rows';
    }

    $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    $out[] = '-- END — total rows exported: ' . number_format($totalRows);

    odbc_close($conn);

    $dir = dirname(MIGRATE_DATA);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return array('ok' => false, 'rows' => 0, 'file' => '', 'messages' => array('สร้างโฟลเดอร์ database ไม่ได้'));
    }

    $content = implode("\n", $out);
    if (file_put_contents(MIGRATE_DATA, $content) === false) {
        return array('ok' => false, 'rows' => 0, 'file' => '', 'messages' => array('เขียนไฟล์ไม่ได้: ' . MIGRATE_DATA));
    }

    $messages[] = 'สำเร็จ: ' . number_format($totalRows) . ' แถว, ขนาด ' . number_format(filesize(MIGRATE_DATA)) . ' bytes';

    return array(
        'ok' => true,
        'rows' => $totalRows,
        'file' => MIGRATE_DATA,
        'messages' => $messages,
    );
}

/**
 * แยกคำสั่ง SQL จากไฟล์ (รองรับ INSERT หลายบรรทัด)
 *
 * @param string $file path ไฟล์ .sql
 * @return array รายการคำสั่ง SQL
 */
function migrate_read_sql_statements($file)
{
    $content = file_get_contents($file);
    if ($content === false) {
        return array();
    }

    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }

    $statements = array();
    $buffer = '';

    foreach (preg_split('/\R/', $content) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || strpos($trimmed, '--') === 0) {
            continue;
        }

        $buffer .= $line . "\n";

        if (substr(rtrim($line), -1) === ';') {
            $stmt = trim($buffer);
            $stmt = rtrim($stmt, ";\n\r\t ");
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

/**
 * รันไฟล์ SQL ผ่าน mysqli ทีละคำสั่ง
 *
 * @param mysqli $conn การเชื่อมต่อ
 * @param string $file path ไฟล์ .sql
 * @param array  $messages ข้อความ log (pass by reference)
 * @return bool
 */
function migrate_execute_sql_file($conn, $file, &$messages)
{
    if (!file_exists($file)) {
        $messages[] = 'ไม่พบไฟล์: ' . $file;
        return false;
    }

    $statements = migrate_read_sql_statements($file);
    $messages[] = basename($file) . ': ' . count($statements) . ' คำสั่ง';

    $done = 0;
    $failed = 0;

    foreach ($statements as $sql) {
        if (!mysqli_query($conn, $sql)) {
            $failed++;
            $err = mysqli_error($conn);
            $preview = mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 120);
            $messages[] = 'ERROR: ' . $err . ' | ' . $preview . '...';
            if ($failed >= 5) {
                $messages[] = 'หยุด import — error เกิน 5 ครั้ง';
                return false;
            }
            continue;
        }
        $done++;
    }

    $messages[] = basename($file) . ': สำเร็จ ' . $done . ' คำสั่ง' . ($failed ? ', ล้มเหลว ' . $failed : '');

    return $failed === 0;
}

/**
 * Import schema + data เข้า MariaDB
 *
 * @return array สรุปผล
 */
function migrate_action_import()
{
    $messages = array();

    if (!extension_loaded('mysqli')) {
        return array('ok' => false, 'messages' => array('PHP extension mysqli ไม่พร้อม'));
    }

    set_time_limit(0);
    ini_set('memory_limit', '512M');

    require MIGRATE_ROOT . DIRECTORY_SEPARATOR . 'connect.php';

    $messages[] = 'เชื่อมต่อ MariaDB: ' . $DB_HOST . ' / ' . $DB_NAME;

    if (!migrate_execute_sql_file($connect, MIGRATE_SCHEMA, $messages)) {
        db_close($connect);
        return array('ok' => false, 'messages' => $messages);
    }

    if (!migrate_execute_sql_file($connect, MIGRATE_DATA, $messages)) {
        db_close($connect);
        return array('ok' => false, 'messages' => $messages);
    }

    $messages[] = '--- จำนวนแถวหลัง import ---';
    $tables = array('users', 'categories_product', 'product', 'tbl_vendor', 'part', 'formula_name', 'production_formula', 'pre_stock', 'log_stock', 'setup_log', 'setup_pd');

    foreach ($tables as $table) {
        $safe = migrate_quote_ident($table);
        $res = mysqli_query($connect, 'SELECT COUNT(*) AS c FROM ' . $safe);
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $messages[] = $table . ': ' . number_format((int) $row['c']) . ' rows';
            mysqli_free_result($res);
        }
    }

    db_close($connect);
    $messages[] = 'Import เสร็จสมบูรณ์';

    return array('ok' => true, 'messages' => $messages);
}

/**
 * รายการไฟล์ SQL ย่อยใน data_parts เรียงตามชื่อ
 *
 * @return array path ไฟล์ .sql
 */
function migrate_list_data_part_files()
{
    if (!is_dir(MIGRATE_DATA_PARTS)) {
        return array();
    }

    $files = glob(MIGRATE_DATA_PARTS . DIRECTORY_SEPARATOR . '*.sql');
    if (!$files) {
        return array();
    }

    sort($files, SORT_NATURAL);

    return $files;
}

/**
 * Import schema + ไฟล์ data แยกชิ้น (database/data_parts/)
 *
 * @return array สรุปผล
 */
function migrate_action_import_parts()
{
    $messages = array();

    if (!extension_loaded('mysqli')) {
        return array('ok' => false, 'messages' => array('PHP extension mysqli ไม่พร้อม'));
    }

    $partFiles = migrate_list_data_part_files();
    if (empty($partFiles)) {
        return array(
            'ok' => false,
            'messages' => array(
                'ไม่พบไฟล์ใน ' . MIGRATE_DATA_PARTS,
                'รัน: python database/split_inventory_data.py',
            ),
        );
    }

    set_time_limit(0);
    ini_set('memory_limit', '512M');

    require MIGRATE_ROOT . DIRECTORY_SEPARATOR . 'connect.php';

    $messages[] = 'เชื่อมต่อ MariaDB: ' . $DB_HOST . ' / ' . $DB_NAME;
    $messages[] = 'import แบบแยกไฟล์: ' . count($partFiles) . ' ไฟล์';

    if (!migrate_execute_sql_file($connect, MIGRATE_SCHEMA, $messages)) {
        db_close($connect);
        return array('ok' => false, 'messages' => $messages);
    }

    foreach ($partFiles as $file) {
        $messages[] = '--- ' . basename($file) . ' ---';
        if (!migrate_execute_sql_file($connect, $file, $messages)) {
            db_close($connect);
            return array('ok' => false, 'messages' => $messages);
        }
    }

    $messages[] = '--- จำนวนแถวหลัง import ---';
    $tables = array('users', 'categories_product', 'product', 'tbl_vendor', 'part', 'formula_name', 'production_formula', 'pre_stock', 'log_stock', 'setup_log', 'setup_pd');

    foreach ($tables as $table) {
        $safe = migrate_quote_ident($table);
        $res = mysqli_query($connect, 'SELECT COUNT(*) AS c FROM ' . $safe);
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $messages[] = $table . ': ' . number_format((int) $row['c']) . ' rows';
            mysqli_free_result($res);
        }
    }

    db_close($connect);
    $messages[] = 'Import แยกไฟล์เสร็จสมบูรณ์';

    return array('ok' => true, 'messages' => $messages);
}

/**
 * แปลงค่าจำนวน stock เป็นตัวเลขสำหรับเปรียบเทียบ
 *
 * @param mixed $value ค่าจาก ODBC/mysqli
 * @return float
 */
function migrate_amount_float($value)
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    return (float) str_replace(',', '', (string) $value);
}

/**
 * ดึงข้อมูล part จาก Access เป็น array จัด index ด้วย part_id
 *
 * @param resource $conn ODBC connection
 * @return array|array{ok:bool,messages:array} แผนที่ part_id => row หรือ error
 */
function migrate_fetch_access_parts($conn)
{
    $parts = array();
    $result = @odbc_exec($conn, 'SELECT part_id, part_name, part_amount, part_min_alert FROM part ORDER BY part_id');

    if (!$result) {
        return array(
            'ok' => false,
            'messages' => array('อ่านตาราง part จาก Access ไม่ได้: ' . odbc_errormsg($conn)),
        );
    }

    while ($row = odbc_fetch_array($result)) {
        if (!isset($row['part_id'])) {
            continue;
        }
        $id = (int) $row['part_id'];
        $parts[$id] = array(
            'part_id' => $id,
            'part_name' => isset($row['part_name']) ? migrate_to_utf8($row['part_name']) : '',
            'part_amount' => migrate_amount_float(isset($row['part_amount']) ? $row['part_amount'] : 0),
            'part_min_alert' => migrate_amount_float(isset($row['part_min_alert']) ? $row['part_min_alert'] : 0),
        );
    }

    return $parts;
}

/**
 * ดึงข้อมูล part จาก MariaDB เป็น array จัด index ด้วย part_id
 *
 * @param mysqli $conn การเชื่อมต่อ MariaDB
 * @return array|array{ok:bool,messages:array}
 */
function migrate_fetch_mariadb_parts($conn)
{
    $parts = array();
    $result = mysqli_query($conn, 'SELECT part_id, part_name, part_amount, part_min_alert FROM part ORDER BY part_id');

    if (!$result) {
        return array(
            'ok' => false,
            'messages' => array('อ่านตาราง part จาก MariaDB ไม่ได้: ' . mysqli_error($conn)),
        );
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $id = (int) $row['part_id'];
        $parts[$id] = array(
            'part_id' => $id,
            'part_name' => isset($row['part_name']) ? $row['part_name'] : '',
            'part_amount' => migrate_amount_float($row['part_amount']),
            'part_min_alert' => migrate_amount_float($row['part_min_alert']),
        );
    }

    mysqli_free_result($result);

    return $parts;
}

/**
 * เขียนรายงานเปรียบเทียบเป็น CSV
 *
 * @param string $file     path ไฟล์ output
 * @param array  $sections กลุ่มข้อมูล mismatch / access_only / mariadb_only
 * @return bool
 */
function migrate_write_compare_csv($file, $sections)
{
    $lines = array();
    $lines[] = 'section,part_id,part_name,access_amount,mariadb_amount,diff,access_min,mariadb_min';

    foreach ($sections as $section => $rows) {
        foreach ($rows as $row) {
            $lines[] = implode(',', array(
                $section,
                (int) $row['part_id'],
                '"' . str_replace('"', '""', $row['part_name']) . '"',
                isset($row['access_amount']) ? $row['access_amount'] : '',
                isset($row['mariadb_amount']) ? $row['mariadb_amount'] : '',
                isset($row['diff']) ? $row['diff'] : '',
                isset($row['access_min']) ? $row['access_min'] : '',
                isset($row['mariadb_min']) ? $row['mariadb_min'] : '',
            ));
        }
    }

    return file_put_contents($file, implode("\n", $lines) . "\n") !== false;
}

/**
 * แสดงตารางเปรียบเทียบบน Web
 *
 * @param array $sections กลุ่มข้อมูลที่ไม่ตรง
 * @return void
 */
function migrate_render_compare_table($sections)
{
    if (migrate_is_cli()) {
        return;
    }

    $titles = array(
        'mismatch' => 'จำนวนไม่ตรง (part_id เดียวกัน)',
        'access_only' => 'มีใน Access แต่ไม่มีใน MariaDB',
        'mariadb_only' => 'มีใน MariaDB แต่ไม่มีใน Access',
    );

    foreach ($sections as $key => $rows) {
        if (empty($rows)) {
            continue;
        }

        $title = isset($titles[$key]) ? $titles[$key] : $key;
        echo '<h3 style="margin:1rem 0 .5rem;font-size:1rem">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' (' . count($rows) . ')</h3>';
        echo '<table style="width:100%;border-collapse:collapse;font-size:.85rem;margin-bottom:1rem">';
        echo '<tr style="background:#eef2f8"><th style="border:1px solid #dde3ef;padding:.35rem">ID</th>';
        echo '<th style="border:1px solid #dde3ef;padding:.35rem">ชื่อ Part</th>';
        echo '<th style="border:1px solid #dde3ef;padding:.35rem">Access</th>';
        echo '<th style="border:1px solid #dde3ef;padding:.35rem">MariaDB</th>';
        echo '<th style="border:1px solid #dde3ef;padding:.35rem">ต่าง</th></tr>';

        foreach ($rows as $row) {
            $diff = isset($row['diff']) ? $row['diff'] : '-';
            echo '<tr>';
            echo '<td style="border:1px solid #dde3ef;padding:.35rem">' . (int) $row['part_id'] . '</td>';
            echo '<td style="border:1px solid #dde3ef;padding:.35rem">' . htmlspecialchars($row['part_name'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td style="border:1px solid #dde3ef;padding:.35rem;text-align:right">' . htmlspecialchars((string) (isset($row['access_amount']) ? $row['access_amount'] : '-'), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td style="border:1px solid #dde3ef;padding:.35rem;text-align:right">' . htmlspecialchars((string) (isset($row['mariadb_amount']) ? $row['mariadb_amount'] : '-'), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td style="border:1px solid #dde3ef;padding:.35rem;text-align:right;color:#b00020">' . htmlspecialchars((string) $diff, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}

/**
 * เปรียบเทียบ part_amount ระหว่าง Access (.mdb) กับ MariaDB
 *
 * @return array สรุปผล พร้อม sections สำหรับรายงาน
 */
function migrate_action_compare()
{
    $messages = array();
    $sections = array(
        'mismatch' => array(),
        'access_only' => array(),
        'mariadb_only' => array(),
    );

    if (!function_exists('odbc_connect')) {
        return array('ok' => false, 'messages' => array('PHP extension odbc ไม่พร้อม'), 'sections' => $sections);
    }

    if (!extension_loaded('mysqli')) {
        return array('ok' => false, 'messages' => array('PHP extension mysqli ไม่พร้อม'), 'sections' => $sections);
    }

    if (!file_exists(MIGRATE_MDB) || !is_readable(MIGRATE_MDB)) {
        return array(
            'ok' => false,
            'messages' => array('ไม่พบหรือเปิดไฟล์ MDB ไม่ได้: ' . MIGRATE_MDB),
            'sections' => $sections,
        );
    }

    $connAccess = @odbc_connect('Driver={Microsoft Access Driver (*.mdb)};Dbq=' . MIGRATE_MDB, '', '');
    if (!$connAccess) {
        return array(
            'ok' => false,
            'messages' => array('เชื่อมต่อ Access ไม่ได้: ' . odbc_errormsg()),
            'sections' => $sections,
        );
    }

    $accessParts = migrate_fetch_access_parts($connAccess);
    odbc_close($connAccess);

    if (isset($accessParts['ok']) && $accessParts['ok'] === false) {
        return array('ok' => false, 'messages' => $accessParts['messages'], 'sections' => $sections);
    }

    $cfg = migrate_load_db_config();
    if ($cfg['user'] === '' || $cfg['name'] === '') {
        return array('ok' => false, 'messages' => array('ไม่พบการตั้งค่า MariaDB ใน connect.php'), 'sections' => $sections);
    }

    $connMaria = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
    if (!$connMaria) {
        return array(
            'ok' => false,
            'messages' => array('เชื่อมต่อ MariaDB ไม่ได้: ' . mysqli_connect_error()),
            'sections' => $sections,
        );
    }

    mysqli_set_charset($connMaria, 'utf8mb4');
    $mariaParts = migrate_fetch_mariadb_parts($connMaria);
    mysqli_close($connMaria);

    if (isset($mariaParts['ok']) && $mariaParts['ok'] === false) {
        return array('ok' => false, 'messages' => $mariaParts['messages'], 'sections' => $sections);
    }

    $messages[] = 'Access part: ' . count($accessParts) . ' รายการ';
    $messages[] = 'MariaDB part: ' . count($mariaParts) . ' รายการ';

    $matchCount = 0;
    $allIds = array_unique(array_merge(array_keys($accessParts), array_keys($mariaParts)));
    sort($allIds, SORT_NUMERIC);

    foreach ($allIds as $partId) {
        $inAccess = isset($accessParts[$partId]);
        $inMaria = isset($mariaParts[$partId]);

        if ($inAccess && !$inMaria) {
            $a = $accessParts[$partId];
            $sections['access_only'][] = array(
                'part_id' => $partId,
                'part_name' => $a['part_name'],
                'access_amount' => $a['part_amount'],
                'access_min' => $a['part_min_alert'],
            );
            continue;
        }

        if (!$inAccess && $inMaria) {
            $m = $mariaParts[$partId];
            $sections['mariadb_only'][] = array(
                'part_id' => $partId,
                'part_name' => $m['part_name'],
                'mariadb_amount' => $m['part_amount'],
                'mariadb_min' => $m['part_min_alert'],
            );
            continue;
        }

        $a = $accessParts[$partId];
        $m = $mariaParts[$partId];
        $diff = round($m['part_amount'] - $a['part_amount'], 2);

        if (abs($diff) < 0.001) {
            $matchCount++;
            continue;
        }

        $sections['mismatch'][] = array(
            'part_id' => $partId,
            'part_name' => $a['part_name'] !== '' ? $a['part_name'] : $m['part_name'],
            'access_amount' => $a['part_amount'],
            'mariadb_amount' => $m['part_amount'],
            'diff' => $diff,
            'access_min' => $a['part_min_alert'],
            'mariadb_min' => $m['part_min_alert'],
        );
    }

    $reportFile = MIGRATE_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'compare_part_report.csv';
    if (migrate_write_compare_csv($reportFile, $sections)) {
        $messages[] = 'รายงาน CSV: ' . $reportFile;
    }

    $messages[] = '--- สรุป ---';
    $messages[] = 'ตรงกัน: ' . $matchCount . ' รายการ';
    $messages[] = 'จำนวนไม่ตรง: ' . count($sections['mismatch']) . ' รายการ';
    $messages[] = 'มีใน Access เท่านั้น: ' . count($sections['access_only']) . ' รายการ';
    $messages[] = 'มีใน MariaDB เท่านั้น: ' . count($sections['mariadb_only']) . ' รายการ';

    if (!empty($sections['mismatch'])) {
        $messages[] = '--- ตัวอย่างที่ไม่ตรง (สูงสุด 15 รายการ) ---';
        $limit = 0;
        foreach ($sections['mismatch'] as $row) {
            $messages[] = sprintf(
                '#%d %s | Access=%s MariaDB=%s (ต่าง %s)',
                $row['part_id'],
                $row['part_name'],
                $row['access_amount'],
                $row['mariadb_amount'],
                $row['diff']
            );
            $limit++;
            if ($limit >= 15) {
                if (count($sections['mismatch']) > 15) {
                    $messages[] = '... และอีก ' . (count($sections['mismatch']) - 15) . ' รายการ (ดูใน CSV)';
                }
                break;
            }
        }
    }

    $hasIssue = !empty($sections['mismatch']) || !empty($sections['access_only']) || !empty($sections['mariadb_only']);

    return array(
        'ok' => true,
        'has_issue' => $hasIssue,
        'messages' => $messages,
        'sections' => $sections,
    );
}

/**
 * ตรวจสอบสถานะไฟล์ extension และฐานข้อมูล
 *
 * @return array สรุปผล
 */
function migrate_action_status()
{
    $messages = array();

    $messages[] = 'Project root: ' . MIGRATE_ROOT;
    $messages[] = 'MDB path: ' . MIGRATE_MDB;
    $messages[] = 'PHP ' . PHP_VERSION;
    $messages[] = 'ODBC: ' . (extension_loaded('odbc') ? 'OK' : 'MISSING');
    $messages[] = 'mysqli: ' . (extension_loaded('mysqli') ? 'OK' : 'MISSING');
    $messages[] = 'MDB: ' . (file_exists(MIGRATE_MDB) && is_readable(MIGRATE_MDB) ? 'OK' : 'NOT FOUND / ไม่สามารถอ่านได้');
    $messages[] = 'Schema: ' . (file_exists(MIGRATE_SCHEMA) ? 'OK — ' . number_format(filesize(MIGRATE_SCHEMA)) . ' bytes' : 'NOT FOUND');
    $messages[] = 'Data SQL: ' . (file_exists(MIGRATE_DATA) ? 'OK — ' . number_format(filesize(MIGRATE_DATA)) . ' bytes' : 'NOT FOUND (รัน export ก่อน)');
    $partCount = count(migrate_list_data_part_files());
    $messages[] = 'Data parts: ' . ($partCount ? $partCount . ' ไฟล์ใน database/data_parts/' : 'NOT FOUND (รัน split_inventory_data.py)');

    if (extension_loaded('mysqli')) {
        $cfg = migrate_load_db_config();
        if ($cfg['user'] !== '' && $cfg['name'] !== '') {
            $messages[] = 'MariaDB config: ' . $cfg['host'] . ' / ' . $cfg['name'];
            $connect = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);

            if ($connect) {
                mysqli_set_charset($connect, 'utf8mb4');
                $res = mysqli_query($connect, 'SHOW TABLES');
                if ($res) {
                    $count = 0;
                    while ($row = mysqli_fetch_array($res)) {
                        $count++;
                    }
                    mysqli_free_result($res);
                    $messages[] = 'MariaDB: เชื่อมต่อได้ — ' . $count . ' ตาราง';
                } else {
                    $messages[] = 'MariaDB: SHOW TABLES ล้มเหลว — ' . mysqli_error($connect);
                }
                mysqli_close($connect);
            } else {
                $messages[] = 'MariaDB: เชื่อมต่อไม่ได้ — ' . mysqli_connect_error();
            }
        }
    }

    return array('ok' => true, 'messages' => $messages);
}

/**
 * โหลดค่า DB จาก connect.php โดยไม่ require (หลีกเลี่ยง die เมื่อเชื่อมต่อไม่ได้)
 *
 * @return array host, user, pass, name
 */
function migrate_load_db_config()
{
    $config = array(
        'host' => 'localhost',
        'user' => '',
        'pass' => '',
        'name' => '',
    );
    $file = MIGRATE_ROOT . DIRECTORY_SEPARATOR . 'connect.php';

    if (!file_exists($file)) {
        return $config;
    }

    $content = file_get_contents($file);
    $map = array(
        'host' => 'DB_HOST',
        'user' => 'DB_USER',
        'pass' => 'DB_PASS',
        'name' => 'DB_NAME',
    );

    foreach ($map as $key => $var) {
        if (preg_match('/\$' . $var . '\s*=\s*[\'"]([^\'"]*)[\'"]\s*;/', $content, $m)) {
            $config[$key] = $m[1];
        }
    }

    return $config;
}

/**
 * ตรวจว่ารัน export/compare จาก Access ได้หรือไม่
 *
 * @return bool
 */
function migrate_can_access_mdb()
{
    return function_exists('odbc_connect')
        && file_exists(MIGRATE_MDB)
        && is_readable(MIGRATE_MDB);
}

/**
 * ตรวจสอบสิทธิ์รันผ่านเว็บ (ใช้รหัส MIGRATE_WEB_KEY — เปลี่ยนก่อนอัปโหลด hosting)
 *
 * @param string|null $key คีย์จาก query/post
 * @return bool
 */
function migrate_web_authorized($key)
{
    if (migrate_is_cli()) {
        return true;
    }

    return is_string($key) && $key !== '' && hash_equals(MIGRATE_WEB_KEY, $key);
}

/**
 * แสดงหน้า Web UI
 *
 * @param string $action action ที่เลือก
 * @param bool   $authorized ผ่านการยืนยันหรือไม่
 * @return void
 */
function migrate_render_web($action, $authorized)
{
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
    echo '<title>ย้ายข้อมูล Access → MariaDB</title>';
    echo '<style>
        body{font-family:Segoe UI,Tahoma,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;background:#f5f7fb;color:#1a1a1a}
        h1{font-size:1.35rem}
        .card{background:#fff;border:1px solid #dde3ef;border-radius:8px;padding:1rem 1.25rem;margin:1rem 0}
        .actions a,.actions button{display:inline-block;margin:.25rem .5rem .25rem 0;padding:.5rem 1rem;border-radius:6px;text-decoration:none;border:1px solid #3b6cb7;background:#3b6cb7;color:#fff;cursor:pointer;font-size:.95rem}
        .actions a.secondary{background:#fff;color:#3b6cb7}
        .log{font-family:Consolas,monospace;font-size:.88rem;padding:.35rem 0;border-bottom:1px solid #eef1f6}
        .log.ok{color:#0a7a36}.log.err{color:#b00020}.log.warn{color:#9a6700}
        input[type=password]{padding:.45rem .6rem;width:220px}
        code{background:#eef2f8;padding:.1rem .35rem;border-radius:4px}
    </style></head><body>';
    echo '<h1>ย้ายข้อมูล Access (.mdb) → MariaDB</h1>';
    echo '<div class="card"><p>รันบน <strong>Windows + Access ODBC Driver</strong> สำหรับ export<br>';
    echo 'Import ใช้ค่าจาก <code>connect.php</code></p></div>';

    if (!$authorized) {
        echo '<div class="card"><form method="get">';
        echo '<label>รหัสยืนยัน: <input type="password" name="key" required></label> ';
        echo '<button type="submit">เข้าใช้งาน</button>';
        echo '<p style="font-size:.85rem;color:#666">เปลี่ยน MIGRATE_WEB_KEY ในไฟล์นี้ก่อนใช้บน hosting แล้วลบไฟล์หลัง import เสร็จ</p>';
        echo '</form></div></body></html>';
        return;
    }

    $key = isset($_GET['key']) ? rawurlencode($_GET['key']) : '';
    $q = $key !== '' ? '&key=' . $key : '';
    $canMdb = migrate_can_access_mdb();

    echo '<div class="card actions">';
    echo '<a href="?action=status' . $q . '">status</a>';
    echo '<a href="?action=import' . $q . '">import</a>';
    echo '<a href="?action=import-parts' . $q . '">import-parts</a>';
    if ($canMdb) {
        echo '<a href="?action=export' . $q . '">export</a>';
        echo '<a href="?action=all' . $q . '">all (export+import)</a>';
        echo '<a href="?action=compare' . $q . '" class="secondary">compare</a>';
    } else {
        echo '<span style="font-size:.85rem;color:#666;margin-left:.5rem">export/compare ใช้ได้เฉพาะ Windows ที่มี Access ODBC</span>';
    }
    echo '</div>';

    if ($action !== '') {
        echo '<div class="card">';
        migrate_run_action($action);
        echo '</div>';
    }

    echo '</body></html>';
}

/**
 * รัน action ที่เลือก
 *
 * @param string $action export|import|all|status|compare
 * @return array สรุปผล
 */
function migrate_run_action($action)
{
    $action = strtolower(trim($action));
    $result = array('ok' => false, 'messages' => array('ไม่รู้จัก action: ' . $action));

    switch ($action) {
        case 'export':
            $result = migrate_action_export();
            break;
        case 'import':
            $result = migrate_action_import();
            break;
        case 'import-parts':
            $result = migrate_action_import_parts();
            break;
        case 'all':
            $export = migrate_action_export();
            foreach ($export['messages'] as $msg) {
                migrate_log($msg, $export['ok'] ? 'info' : 'err');
            }
            if (!$export['ok']) {
                return $export;
            }
            migrate_log('--- เริ่ม import ---', 'info');
            $result = migrate_action_import();
            break;
        case 'compare':
            $result = migrate_action_compare();
            break;
        case 'status':
            $result = migrate_action_status();
            break;
    }

    $type = 'info';
    if (empty($result['ok'])) {
        $type = 'err';
    } elseif (!empty($result['has_issue'])) {
        $type = 'warn';
    } elseif (!empty($result['ok'])) {
        $type = 'ok';
    }

    foreach ($result['messages'] as $msg) {
        migrate_log($msg, $type);
    }

    if ($action === 'compare' && !empty($result['sections'])) {
        migrate_render_compare_table($result['sections']);
    }

    return $result;
}

// ─ Main ───────────────────────────────────────────────────────────────────────

if (migrate_is_cli()) {
    $action = isset($argv[1]) ? $argv[1] : 'status';
    $result = migrate_run_action($action);
    if (empty($result['ok'])) {
        exit(1);
    }
    if ($action === 'compare' && !empty($result['has_issue'])) {
        exit(2);
    }
    exit(0);
}

$key = isset($_REQUEST['key']) ? (string) $_REQUEST['key'] : '';
$authorized = migrate_web_authorized($key);
$action = $authorized && isset($_GET['action']) ? (string) $_GET['action'] : '';

migrate_render_web($action, $authorized);
