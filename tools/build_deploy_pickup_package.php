<?php
/**
 * สร้างแพ็กไฟล์ deploy โมดูลรายการเบิกของ (pickup) สำหรับ copy ขึ้น server production
 *
 * คัดลอก PHP ที่เปลี่ยนไปไว้ใน deploy/<ชื่อแพ็ก>/www/ พร้อม MANIFEST.txt และ DEPLOY.txt
 * อ้างอิงแนวทางเดียวกับการ deploy หน้า production — แยกชุดไฟล์ชัดเจน มี backup/rollback
 *
 * ตัวอย่าง: php tools/build_deploy_pickup_package.php 2026-11-03
 *          php tools/build_deploy_pickup_package.php 2026-11-03-r2
 *
 * สร้างเฉพาะโฟลเดอร์ deploy/<เวอร์ชัน>-pickup/ ไม่สร้างไฟล์ zip
 *
 * ค่าเริ่มต้น: incremental — คัดลอกเฉพาะไฟล์ที่ MD5 ต่างจาก deploy/LAST_UPLOADED.txt
 * --full : คัดลอกไฟล์ deploy ครบทุกตัว
 */

// ─ Constants ─────────────────────────────────────────────────────────────────

define('DEPLOY_PACKAGE_SLUG', 'pickup');
define('DEPLOY_PACKAGE_LABEL', 'Inventory — โมดูลเบิกของ (pickup + pickup_ma + UI ฟอนต์)');

/** @var string[] ไฟล์ PHP ที่ต้องวางทับที่ root โฟลเดอร์ inventory บน server */
$DEPLOY_WEB_ROOT_FILES = array(
    'pickup_overview.php',
    'pickup_history.php',
    'pickup_history_lib.php',
    'pickup_history_lines.php',
    'pickup_list_order.php',
    'pickup_list_order_detail.php',
    'pickup_list_order_edit.php',
    'pickup_ma.php',
    'pickup_set_formula.php',
    'formula_manage.php',
    'pre_pickup_save.php',
    'pickup_ma_save.php',
    'pickup_save_log.php',
    'stock_add.php',
    'stock_save.php',
    'stock_flash.php',
    'stock_overview.php',
    'search_cusname.php',
    'part_add.php',
    'part_save.php',
    'production_pre_pickup.php',
    'list_stock.php',
    'title-h.php',
    'inventory_version.php',
    'inventory_ui_fonts.php',
    'inventory_ui_head.php',
    'inventory_request_timing.php',
    'pickup_document_lines.php',
    'pickup_pre_stock_tech.php',
    'pickup_tech_parts.php',
    'pickup_tech_parts_save.php',
    'pickup_constants.php',
    'pickup_repair_part_product.php',
    'pickup_repair_formula_tech.php',
    'pickup_set_repair_tech.php',
    'pickup_ma_production_catalog.php',
    'pickup_prep_board.php',
    'pickup_prep_board_auth.php',
    'pickup_prep_board_pin.local.php',
    'pickup_prep_board_status.php',
    'pickup_prep_api.php',
    'pickup_lift_call_fragment.php',
    'inventory_config.php',
    'inventory_session_actor.php',
    'vendor_manage.php',
    'vendor_add.php',
    'vendor_edit.php',
    'vendor_save.php',
    'stock_add_history.php',
    'stock_log_edit.php',
    'stock_log_save.php',
);

/** @var string[] ไฟล์ใน path ย่อยใต้ inventory (เช่น css/) */
$DEPLOY_WEB_RELATIVE_PATHS = array(
    'css/inventory-ui.css',
    'css/pickup-prep.css',
    'js/pickup-lift-call.js',
    'images/pickup-lift-walk.gif',
    'images/pickup-lift-walk-1.gif',
    'images/pickup-lift-walk-2.gif',
    'images/pickup-lift-walk-3.gif',
    'images/pickup-lift-walk-4.gif',
    'images/pickup-lift-walk-5.gif',
    'images/pickup-lift-walk-6.gif',
    'images/pickup-lift-walk-7.gif',
);

/** @var string[] เอกสารอ้างอิง (ไม่บังคับ copy ขึ้น server) */
$DEPLOY_DOC_PATHS = array(
    'docs/deploy_summary_inventory_pickup.html',
    'docs/images/pickup-list-overview-example.png',
    'docs/images/pickup-detail-example.jpg',
);

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * สร้าง path แบบปลอดภัยและตรวจว่ามีไฟล์ต้นทาง
 *
 * @param string $projectRoot โฟลเดอร์รากโปรเจค
 * @param string $relativePath path จากราก
 * @return string path เต็มของไฟล์ต้นทาง
 */
function deploy_pickup_resolve_source($projectRoot, $relativePath)
{
    $full = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($full)) {
        fwrite(STDERR, "ไม่พบไฟล์: {$relativePath}\n");
        exit(1);
    }

    return $full;
}

/**
 * คัดลอกไฟล์ไปปลายทาง พร้อมสร้างโฟลเดอร์แม่
 *
 * @param string $source path ต้นทาง
 * @param string $dest path ปลายทาง
 * @return void
 */
function deploy_pickup_copy_file($source, $dest)
{
    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "สร้างโฟลเดอร์ไม่ได้: {$dir}\n");
        exit(1);
    }
    if (!copy($source, $dest)) {
        fwrite(STDERR, "copy ไม่สำเร็จ: {$source}\n");
        exit(1);
    }
}

/**
 * เขียน MANIFEST.txt สำหรับตรวจหลัง upload
 *
 * @param string $manifestPath path ไฟล์ manifest
 * @param array<int, array{rel: string, bytes: int, md5: string, mtime: string}> $rows
 * @return void
 */
function deploy_pickup_write_manifest($manifestPath, array $rows)
{
    $lines = array(
        '# MANIFEST — ' . DEPLOY_PACKAGE_LABEL,
        '# สร้างเมื่อ: ' . date('Y-m-d H:i:s'),
        '# ใช้เปรียบเทียบ MD5 บน server หลัง copy (เช่น ผ่าน check_server_files.php)',
        '',
        'relative_path | bytes | md5 | modified',
        str_repeat('-', 72),
    );

    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s | %d | %s | %s',
            $row['rel'],
            $row['bytes'],
            $row['md5'],
            $row['mtime']
        );
    }

    file_put_contents($manifestPath, implode("\n", $lines) . "\n");
}

/**
 * โหลด MD5 baseline จาก deploy/LAST_UPLOADED.txt
 *
 * @param string $deployDir path deploy/
 * @return array{package: string, paths: array<string, string>}|null
 */
function deploy_pickup_load_last_uploaded($deployDir)
{
    $path = $deployDir . DIRECTORY_SEPARATOR . 'LAST_UPLOADED.txt';
    if (!is_file($path)) {
        return null;
    }
    $package = '';
    $paths = array();
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            if (strpos($line, 'package=') === 0) {
                $package = trim(substr($line, 8));
            }
            continue;
        }
        if (strpos($line, 'path |') === 0 || strpos($line, '---') === 0) {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) >= 2 && preg_match('/^[a-f0-9]{32}$/', strtolower($parts[1]))) {
            $paths[$parts[0]] = strtolower($parts[1]);
        }
    }
    if ($paths === array()) {
        return null;
    }

    return array('package' => $package, 'paths' => $paths);
}

/**
 * สร้างแถว manifest จากไฟล์ต้นทาง
 *
 * @param string $projectRoot รากโปรเจค
 * @param string $relativePath path จากราก (เช่น pickup_ma.php)
 * @param string $manifestRel path ใน manifest (เช่น www/pickup_ma.php)
 * @return array{rel: string, bytes: int, md5: string, mtime: string}
 */
/**
 * ตรวจว่ามีไฟล์ต้นทางใน workspace หรือไม่
 *
 * @param string $projectRoot รากโปรเจค
 * @param string $relativePath path จากราก
 * @return bool
 */
function deploy_pickup_source_exists($projectRoot, $relativePath)
{
    $full = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    return is_file($full);
}

function deploy_pickup_manifest_row($projectRoot, $relativePath, $manifestRel)
{
    $source = deploy_pickup_resolve_source($projectRoot, $relativePath);

    return array(
        'rel' => $manifestRel,
        'bytes' => (int) filesize($source),
        'md5' => md5_file($source),
        'mtime' => date('Y-m-d H:i:s', filemtime($source)),
    );
}

/**
 * เขียนคู่มือ deploy ภาษาไทย
 *
 * @param string $deployTxtPath path ไฟล์ DEPLOY.txt
 * @param string $packageDir ชื่อโฟลเดอร์แพ็ก
 * @param string $mode full|incremental
 * @param string $baselinePackage แพ็ก baseline บน server (incremental)
 * @param string[] $webFiles รายการ path ไฟล์ที่อยู่ใน www/ ของแพ็กนี้
 * @return void
 */
function deploy_pickup_write_deploy_txt($deployTxtPath, $packageDir, $mode, $baselinePackage, array $webFiles)
{
    $fileLines = '';
    foreach ($webFiles as $f) {
        $fileLines .= "  - {$f}\n";
    }
    $modeNote = $mode === 'incremental'
        ? "【โหมด incremental】 อัปโหลดเฉพาะไฟล์ในรายการนี้ (baseline บน server: {$baselinePackage})\n\n"
        : "【โหมด full】 อัปโหลดครบทุกไฟล์ในรายการ\n\n";

    $text = <<<TXT
================================================================================
  {$packageDir}
  Inventory — Deploy โมดูลรายการเบิก / แก้เอกสารรอคลัง
================================================================================

{$modeNote}【ไม่มีการเปลี่ยน DB】 ใช้ตาราง pre_stock เดิม ไม่ต้องรัน SQL

【ไฟล์ที่ต้องวางทับ】 (จากโฟลเดอร์ www/ ไปที่โฟลเดอร์ inventory บน server)
{$fileLines}
【อย่าแก้ / อย่าทับ】
  - connect.php, inventory_config.local.php, pickup_log.php

--------------------------------------------------------------------------------
ขั้นตอน deploy (แนะนำ)
--------------------------------------------------------------------------------
1) แจ้งคลัง / เลือกช่วงที่ใช้งานน้อย
2) Backup ไฟล์เดิมบน server (ต่อท้าย .bak.YYYYMMDD) ตามรายการด้านบน
   (ไฟล์ใหม่ที่ยังไม่มี ข้าม backup)
3) Upload / copy จาก www/ ขึ้น server
4) ตรวจ MD5 กับ MANIFEST.txt (หรือเปิดหน้ารายการเบิกทดสอบ)
5) ทดสอบตาม docs/deploy_summary_inventory_pickup.html ข้อ 5

--------------------------------------------------------------------------------
ทดสอบเร็ว (5 นาที)
--------------------------------------------------------------------------------
[ ] เปิด pickup_overview.php — ตารางรายการ + คอลัมน์หมายเหตุ
[ ] เข้าเอกสาร pre — เพิ่ม/ลบ part, บันทึกแก้ไขเอกสาร (สต็อกยังไม่ลด)
[ ] ยืนยันจ่ายของ 1 รายการทดลอง — flow เดิมยังทำงาน

--------------------------------------------------------------------------------
Rollback
--------------------------------------------------------------------------------
นำไฟล์ .bak.YYYYMMDD กลับมา rename ทับไฟล์ปัจจุบัน
ลบ pickup_list_order_edit.php ถ้าต้องการย้อนกลับแบบไม่มีฟีเจอร์ใหม่

--------------------------------------------------------------------------------
เอกสารอ้างอิง
--------------------------------------------------------------------------------
โฟลเดอร์ docs/ ในแพ็กนี้ — สรุปฟีเจอร์และภาพตัวอย่าง (ไม่ต้อง copy ขึ้น server)

================================================================================
TXT;

    file_put_contents($deployTxtPath, $text);
}

/**
 * ลบไฟล์ .zip ใน deploy/ (ไม่ใช้ zip สำหรับส่ง server)
 *
 * @param string $deployDir path โฟลเดอร์ deploy
 * @return void
 */
function deploy_pickup_remove_zip_files($deployDir)
{
    if (!is_dir($deployDir)) {
        return;
    }
    $pattern = $deployDir . DIRECTORY_SEPARATOR . '*.zip';
    $files = glob($pattern);
    if ($files === false) {
        return;
    }
    foreach ($files as $zipPath) {
        if (is_file($zipPath)) {
            unlink($zipPath);
        }
    }
}

/**
 * ลบโฟลเดอร์แบบ recursive สำหรับ rebuild แพ็ก
 *
 * @param string $dir path โฟลเดอร์ที่จะลบ
 * @return void
 */
function deploy_pickup_remove_tree($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deploy_pickup_remove_tree($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * คืนชื่อโฟลเดอร์แพ็ก deploy ตามเวอร์ชันที่ระบุ
 *
 * @param array $argv อาร์กิวเมนต์ CLI
 * @return string เช่น 2026-11-03-pickup
 */
function deploy_pickup_resolve_package_id(array $argv)
{
    $version = date('Y-m-d');
    if (isset($argv[1]) && trim((string) $argv[1]) !== '' && strpos((string) $argv[1], '--') !== 0) {
        $version = preg_replace('/[^0-9A-Za-z._-]/', '', (string) $argv[1]);
    }
    if ($version === '') {
        $version = date('Y-m-d-His');
    }

    return $version . '-' . DEPLOY_PACKAGE_SLUG;
}

/**
 * ตรวจว่า CLI มี flag หรือไม่
 *
 * @param array $argv อาร์กิวเมนต์ CLI
 * @param string $flag เช่น --full
 * @return bool
 */
function deploy_pickup_argv_has_flag(array $argv, $flag)
{
    foreach ($argv as $arg) {
        if ((string) $arg === $flag) {
            return true;
        }
    }

    return false;
}

// ─ Main ──────────────────────────────────────────────────────────────────────

$cliArgv = isset($argv) ? $argv : array();
$fullBuild = deploy_pickup_argv_has_flag($cliArgv, '--full');
$deployPackageId = deploy_pickup_resolve_package_id($cliArgv);
$projectRoot = dirname(__DIR__);
$deployDir = $projectRoot . DIRECTORY_SEPARATOR . 'deploy';
deploy_pickup_remove_zip_files($deployDir);

$baseline = deploy_pickup_load_last_uploaded($deployDir);
$incremental = !$fullBuild && $baseline !== null;
$baselinePackage = $baseline !== null ? $baseline['package'] : '';

/** @var array<string, string> $allWebRel path จากราก => manifest rel */
$allWebRel = array();
foreach ($DEPLOY_WEB_ROOT_FILES as $basename) {
    $allWebRel[$basename] = 'www/' . $basename;
}
foreach ($DEPLOY_WEB_RELATIVE_PATHS as $relativePath) {
    $allWebRel[$relativePath] = 'www/' . str_replace('\\', '/', $relativePath);
}

$toCopy = array();
foreach ($allWebRel as $relPath => $manifestRel) {
    if (!deploy_pickup_source_exists($projectRoot, $relPath)) {
        if ($incremental && isset($baseline['paths'][$relPath])) {
            continue;
        }
        fwrite(STDERR, "ไม่พบไฟล์: {$relPath}\n");
        exit(1);
    }
    $row = deploy_pickup_manifest_row($projectRoot, $relPath, $manifestRel);
    if (!$incremental) {
        $toCopy[$relPath] = $row;
        continue;
    }
    $prevMd5 = isset($baseline['paths'][$relPath]) ? $baseline['paths'][$relPath] : null;
    if ($prevMd5 === null || $prevMd5 !== strtolower($row['md5'])) {
        $toCopy[$relPath] = $row;
    }
}

if ($toCopy === array()) {
    echo "ไม่มีไฟล์ deploy เปลี่ยนหลัง baseline";
    if ($baselinePackage !== '') {
        echo " ({$baselinePackage})";
    }
    echo "\n";
    echo "ถ้าต้องการแพ็กครบ: php tools/build_deploy_pickup_package.php <เวอร์ชัน> --full\n";
    exit(0);
}

$packageRoot = $deployDir . DIRECTORY_SEPARATOR . $deployPackageId;
$wwwDir = $packageRoot . DIRECTORY_SEPARATOR . 'www';
$docsDir = $packageRoot . DIRECTORY_SEPARATOR . 'docs';

if (is_dir($packageRoot)) {
    deploy_pickup_remove_tree($packageRoot);
}
mkdir($wwwDir, 0755, true);
mkdir($docsDir, 0755, true);

$manifestRows = array();
$deployWebList = array();

foreach ($toCopy as $relPath => $row) {
    $source = deploy_pickup_resolve_source($projectRoot, $relPath);
    $dest = $wwwDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    deploy_pickup_copy_file($source, $dest);
    $manifestRows[] = $row;
    $deployWebList[] = str_replace('\\', '/', $relPath);
}

if ($fullBuild || !$incremental) {
    foreach ($DEPLOY_DOC_PATHS as $docRel) {
        $source = deploy_pickup_resolve_source($projectRoot, $docRel);
        $dest = $packageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $docRel);
        deploy_pickup_copy_file($source, $dest);
    }
}

$buildMode = $incremental ? 'incremental' : 'full';
deploy_pickup_write_manifest($packageRoot . DIRECTORY_SEPARATOR . 'MANIFEST.txt', $manifestRows);
deploy_pickup_write_deploy_txt(
    $packageRoot . DIRECTORY_SEPARATOR . 'DEPLOY.txt',
    $deployPackageId,
    $buildMode,
    $baselinePackage,
    $deployWebList
);

$versionFile = $packageRoot . DIRECTORY_SEPARATOR . 'VERSION.txt';
file_put_contents(
    $versionFile,
    "package=" . $deployPackageId . "\n"
    . "label=" . DEPLOY_PACKAGE_LABEL . "\n"
    . "built=" . date('c') . "\n"
    . "mode=" . $buildMode . "\n"
    . "baseline=" . $baselinePackage . "\n"
    . "web_files=" . count($manifestRows) . "\n"
);

echo "สร้างแพ็ก deploy เรียบร้อย: {$packageRoot}\n";
echo "package_id={$deployPackageId}\n";
echo "mode={$buildMode} web_files=" . count($manifestRows) . "\n";
if ($incremental) {
    echo "baseline={$baselinePackage}\n";
}
echo "ขั้นถัดไป: copy จาก www/ ในโฟลเดอร์แพ็กขึ้น server ตาม DEPLOY.txt\n";
