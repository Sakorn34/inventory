<?php
/**
 * split_inventory_data.php — แยก inventory_data.sql เป็นหลายไฟล์สำหรับ import บน hosting
 *
 * วัตถุประสงค์:
 *   phpMyAdmin มักจำกัด upload 2 MB — ไฟล์ data รวม ~7 MB
 *   สคริปต์นี้แยกตามตาราง และแบ่งตารางใหญ่ (pre_stock, log_stock) เป็นชิ้นละไม่เกิน max bytes
 *
 * Flow:
 *   1. อ่าน database/inventory_data.sql
 *   2. เขียนไฟล์ลง database/data_parts/
 *   3. สร้าง IMPORT_ORDER.txt ระบุลำดับ import
 *
 * ตัวอย่าง:
 *   php tools/split_inventory_data.php
 *   php tools/split_inventory_data.php 1200000
 *
 * หมายเหตุ: ถ้า PHP อ่าน OneDrive ไม่ได้ ใช้:
 *   python database/split_inventory_data.py
 */

// ─ Constants ──────────────────────────────────────────────────────────────────

define('SPLIT_ROOT', dirname(__DIR__));
define('SPLIT_SOURCE', SPLIT_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'inventory_data.sql');
define('SPLIT_OUTPUT_DIR', SPLIT_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'data_parts');
define('SPLIT_DEFAULT_MAX_BYTES', 1500000);

date_default_timezone_set('Asia/Bangkok');

// ─ Helpers ────────────────────────────────────────────────────────────────────

/**
 * ตรวจว่ารันจาก CLI หรือไม่
 *
 * @return bool
 */
function split_is_cli()
{
    return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
}

/**
 * แสดงข้อความ
 *
 * @param string $message ข้อความ
 * @return void
 */
function split_log($message)
{
    echo rtrim($message) . "\n";
}

/**
 * ดึง preamble มาตรฐานสำหรับทุกไฟล์ย่อย
 *
 * @return string
 */
function split_preamble()
{
    return implode("\n", array(
        'SET NAMES utf8mb4;',
        'SET FOREIGN_KEY_CHECKS = 0;',
        'SET SQL_MODE = \'NO_AUTO_VALUE_ON_ZERO\';',
        '',
        'USE `biton_inventory`;',
        '',
    ));
}

/**
 * แยกคำสั่ง SQL สมบูรณ์จากเนื้อหาตาราง
 *
 * @param string $content เนื้อหาของ section (ไม่รวม comment หัวตาราง)
 * @return array รายการ SQL statement
 */
function split_extract_statements($content)
{
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
                $statements[] = $stmt . ';';
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
 * แยก section ตารางเป็นชิ้นตามขนาดไฟล์
 *
 * @param string $tableComment comment หัวตาราง เช่น -- ─ pre_stock ...
 * @param array  $statements  คำสั่ง SQL ในตาราง
 * @param int    $maxBytes    ขนาดสูงสุดต่อไฟล์
 * @return array รายการ chunk ['content'=>string,'bytes'=>int]
 */
function split_chunk_statements($tableComment, $statements, $maxBytes)
{
    $chunks = array();
    $current = $tableComment . "\n\n";
    $currentBytes = strlen($current);

    foreach ($statements as $sql) {
        $add = $sql . "\n\n";
        $addBytes = strlen($add);

        if ($currentBytes + $addBytes > $maxBytes && $currentBytes > strlen($tableComment) + 2) {
            $chunks[] = array('content' => rtrim($current) . "\n", 'bytes' => $currentBytes);
            $current = $tableComment . " (ต่อ)\n\n";
            $currentBytes = strlen($current);
        }

        $current .= $add;
        $currentBytes += $addBytes;
    }

    if (trim($current) !== '') {
        $chunks[] = array('content' => rtrim($current) . "\n", 'bytes' => $currentBytes);
    }

    return $chunks;
}

/**
 * แยก inventory_data.sql เป็นหลายไฟล์
 *
 * @param int $maxBytes ขนาดสูงสุดต่อไฟล์ (bytes)
 * @return array สรุปผล
 */
function split_run($maxBytes)
{
    if (!file_exists(SPLIT_SOURCE)) {
        return array('ok' => false, 'messages' => array('ไม่พบไฟล์: ' . SPLIT_SOURCE));
    }

    $raw = file_get_contents(SPLIT_SOURCE);
    if ($raw === false) {
        return array('ok' => false, 'messages' => array('อ่านไฟล์ไม่ได้'));
    }

    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }

    if (!is_dir(SPLIT_OUTPUT_DIR) && !mkdir(SPLIT_OUTPUT_DIR, 0755, true)) {
        return array('ok' => false, 'messages' => array('สร้างโฟลเดอร์ output ไม่ได้: ' . SPLIT_OUTPUT_DIR));
    }

    $footer = "SET FOREIGN_KEY_CHECKS = 1;\n";
    $lines = preg_split('/\R/', $raw);
    $sections = array();
    $currentName = '_preamble';
    $currentLines = array();

    foreach ($lines as $line) {
        if (preg_match('/^-- ─ (.+?) ─/', $line, $m)) {
            if (!empty($currentLines)) {
                $sections[] = array('name' => $currentName, 'body' => implode("\n", $currentLines));
            }
            $currentName = trim($m[1]);
            $currentLines = array($line);
            continue;
        }

        if (strpos(trim($line), 'SET FOREIGN_KEY_CHECKS = 1') === 0) {
            if (!empty($currentLines)) {
                $sections[] = array('name' => $currentName, 'body' => implode("\n", $currentLines));
                $currentLines = array();
            }
            continue;
        }

        if (strpos(trim($line), '-- END') === 0) {
            continue;
        }

        $currentLines[] = $line;
    }

    if (!empty($currentLines)) {
        $sections[] = array('name' => $currentName, 'body' => implode("\n", $currentLines));
    }

    $preamble = split_preamble();
    $written = array();
    $seq = 0;

    foreach ($sections as $section) {
        $name = $section['name'];
        $body = trim($section['body']);

        if ($name === '_preamble') {
            continue;
        }

        $tableComment = '';
        if (preg_match('/^(-- ─ .+)$/m', $body, $cm)) {
            $tableComment = $cm[1];
            $body = trim(preg_replace('/^-- ─ .+\R/m', '', $body, 1));
        }

        $statements = split_extract_statements($body);
        if (empty($statements)) {
            continue;
        }

        $sectionBytes = strlen($body);

        if ($sectionBytes <= $maxBytes) {
            $seq++;
            $fileName = sprintf('%02d_%s.sql', $seq, split_safe_filename($name));
            $content = $preamble . $tableComment . "\n\n" . implode("\n\n", $statements) . "\n";
            $path = SPLIT_OUTPUT_DIR . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($path, $content);
            $written[] = array('file' => $fileName, 'bytes' => strlen($content), 'table' => $name);
            continue;
        }

        $chunks = split_chunk_statements($tableComment, $statements, $maxBytes - strlen($preamble));
        $part = 0;
        foreach ($chunks as $chunk) {
            $part++;
            $seq++;
            $fileName = sprintf('%02d_%s_part%02d.sql', $seq, split_safe_filename($name), $part);
            $content = $preamble . $chunk['content'];
            $path = SPLIT_OUTPUT_DIR . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($path, $content);
            $written[] = array('file' => $fileName, 'bytes' => strlen($content), 'table' => $name . ' part ' . $part);
        }
    }

    $seq++;
    $footerFile = sprintf('%02d_footer.sql', $seq);
    file_put_contents(SPLIT_OUTPUT_DIR . DIRECTORY_SEPARATOR . $footerFile, $preamble . $footer);
    $written[] = array('file' => $footerFile, 'bytes' => strlen($preamble . $footer), 'table' => 'footer');

    $orderLines = array(
        '# ลำดับ import ใน phpMyAdmin (หลัง inventory_schema_access.sql)',
        '# สร้างเมื่อ: ' . date('Y-m-d H:i:s'),
        '# แหล่ง: ' . basename(SPLIT_SOURCE),
        '',
    );
    foreach ($written as $w) {
        $orderLines[] = $w['file'] . '  (' . number_format($w['bytes']) . ' bytes)  — ' . $w['table'];
    }
    file_put_contents(SPLIT_OUTPUT_DIR . DIRECTORY_SEPARATOR . 'IMPORT_ORDER.txt', implode("\n", $orderLines) . "\n");

    $messages = array(
        'แยกไฟล์สำเร็จ: ' . count($written) . ' ไฟล์',
        'โฟลเดอร์: ' . SPLIT_OUTPUT_DIR,
        'max ต่อไฟล์: ' . number_format($maxBytes) . ' bytes',
        'ดูลำดับ import: database/data_parts/IMPORT_ORDER.txt',
    );
    foreach ($written as $w) {
        $messages[] = $w['file'] . ' — ' . number_format($w['bytes']) . ' bytes';
    }

    return array('ok' => true, 'messages' => $messages, 'files' => $written);
}

/**
 * แปลงชื่อตารางเป็นชื่อไฟล์ที่ปลอดภัย
 *
 * @param string $name ชื่อ section
 * @return string
 */
function split_safe_filename($name)
{
    $name = preg_replace('/\s*\(.+\)\s*$/', '', $name);
    $name = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $name);
    $name = trim($name, '_');

    if ($name === '') {
        $name = 'section';
    }

    return strtolower($name);
}

// ─ Main ───────────────────────────────────────────────────────────────────────

if (!split_is_cli()) {
    header('Content-Type: text/plain; charset=UTF-8');
}

$maxBytes = SPLIT_DEFAULT_MAX_BYTES;
if (split_is_cli() && isset($argv[1]) && ctype_digit((string) $argv[1])) {
    $maxBytes = (int) $argv[1];
}

$result = split_run($maxBytes);

foreach ($result['messages'] as $msg) {
    split_log($msg);
}

exit(!empty($result['ok']) ? 0 : 1);
