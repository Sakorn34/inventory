<?php
/**
 * โมดูลแจ้งเตือนสต็อกต่ำผ่าน Line รายวัน
 *
 * ขอบเขต: query เฉพาะ part ที่เปิด `part_line_alert` และคงเหลือต่ำกว่า `part_min_alert`,
 * ส่งข้อความรวมหนึ่งครั้งต่อวันผ่าน LINE Messaging API และบันทึก log กันการส่งซ้ำ
 *
 * Flow สั้น:
 * 1. Scheduler เรียก `php line_stock_alert.php` หรือ `line_stock_alert_url.php?token=...`
 * 2. ตรวจรายการที่ต้องแจ้งและค่า LINE_CHANNEL_ACCESS_TOKEN + LINE_TO_ID
 * 3. ส่ง Line เมื่อมีรายการ แล้วบันทึก `line_stock_alert_runs`
 *
 * ตัวอย่าง: `php line_stock_alert.php --force`
 */

require_once __DIR__ . '/low_stock_alert.php';
require_once __DIR__ . '/line_messaging.php';

// ─ Constants ────────────────────────────────────────────────────────────────

if (!defined('INVENTORY_LINE_STOCK_BASE_URL')) {
    $baseUrl = getenv('INVENTORY_LOW_STOCK_BASE_URL');
    define('INVENTORY_LINE_STOCK_BASE_URL', $baseUrl !== false ? rtrim((string) $baseUrl, '/') : '');
}

// ─ Schema ───────────────────────────────────────────────────────────────────

/**
 * เตรียมคอลัมน์ part_line_alert และตาราง log เมื่อยังไม่มี
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return void ไม่มี return value
 */
function inventory_line_alert_ensure_schema($conn)
{
    if (!($conn instanceof mysqli)) {
        throw new RuntimeException('การเชื่อมต่อฐานข้อมูลไม่ถูกต้อง');
    }

    inventory_alert_ensure_column(
        $conn,
        'part',
        'part_line_alert',
        "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = แจ้ง LINE Messaging API เมื่อ part_amount < part_min_alert'",
        'part_min_alert'
    );

    if (inventory_alert_table_usable($conn, 'line_stock_alert_runs')) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `line_stock_alert_runs` (
        `alert_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `run_date` DATE NOT NULL,
        `run_at` DATETIME NOT NULL,
        `status` VARCHAR(30) NOT NULL,
        `item_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `message` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`alert_id`),
        UNIQUE KEY `uk_line_stock_alert_run_date` (`run_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    inventory_alert_exec_ddl($conn, $sql);

    if (!inventory_alert_table_usable($conn, 'line_stock_alert_runs')) {
        $dbName = inventory_alert_current_database($conn);
        $dbHint = $dbName !== '' ? ' (DB: ' . $dbName . ')' : '';
        throw new RuntimeException(
            'ไม่พบตาราง line_stock_alert_runs' . $dbHint
            . ' — รัน database/migrations/20250604_part_line_alert.sql ใน phpMyAdmin'
        );
    }
}

// ─ Query & notify ───────────────────────────────────────────────────────────

/**
 * ตรวจว่าวันนี้เคยส่ง Line สำเร็จหรือบันทึกว่าไม่มีรายการแล้วหรือไม่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $runDate วันที่รูปแบบ Y-m-d
 * @return bool คืน true เมื่อไม่ควรส่งซ้ำ
 */
function inventory_line_alert_has_successful_run($conn, $runDate)
{
    $sql = "SELECT `alert_id`
        FROM `line_stock_alert_runs`
        WHERE `run_date` = ? AND `status` IN ('sent', 'no_low_stock')
        LIMIT 1";
    $stmt = inventory_alert_execute($conn, $sql, 's', array($runDate));
    $rows = inventory_alert_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return count($rows) > 0;
}

/**
 * ดึง part ที่เปิดแจ้ง Line และสต็อกต่ำกว่าเกณฑ์
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ part ที่ต้องแจ้งเตือน
 */
function inventory_line_alert_get_low_stock_parts($conn)
{
    $sql = "SELECT
            p.`part_id`,
            p.`part_name`,
            p.`part_amount`,
            p.`part_min_alert`,
            p.`part_status_purchase`,
            COALESCE(c.`ct_name`, '') AS `ct_name`
        FROM `part` p
        LEFT JOIN `categories_product` c ON p.`part_categories` = c.`ct_id`
        WHERE p.`part_line_alert` = 1
            AND p.`part_min_alert` > 0
            AND p.`part_amount` < p.`part_min_alert`
        ORDER BY c.`ct_name` ASC, p.`part_name` ASC";
    $stmt = inventory_alert_execute($conn, $sql);
    $rows = inventory_alert_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * สร้างข้อความ Line สำหรับรายการสต็อกต่ำ
 *
 * @param array $parts รายการ part
 * @param string $runDate วันที่ตรวจสอบ Y-m-d
 * @return string ข้อความที่พร้อมส่ง
 */
function inventory_line_alert_build_message($parts, $runDate)
{
    $lines = array(
        '[Inventory] สต็อกต่ำกว่าเกณฑ์',
        'วันที่: ' . $runDate,
        'จำนวน: ' . count($parts) . ' รายการ',
        '',
    );

    foreach ($parts as $index => $part) {
        $lines[] = ($index + 1) . '. ' . $part['part_name']
            . ' | ' . ($part['ct_name'] !== '' ? $part['ct_name'] : '-')
            . ' | คงเหลือ ' . inventory_alert_format_quantity($part['part_amount'])
            . ' / ขั้นต่ำ ' . inventory_alert_format_quantity($part['part_min_alert']);
    }

    if (INVENTORY_LINE_STOCK_BASE_URL !== '') {
        $lines[] = '';
        $lines[] = INVENTORY_LINE_STOCK_BASE_URL . '/stock_overview.php';
    }

    return line_messaging_truncate_text(implode("\n", $lines));
}

/**
 * ส่งข้อความผ่าน LINE Messaging API
 *
 * @param string $message ข้อความที่ต้องการส่ง
 * @return bool คืน true เมื่อส่งสำเร็จ
 */
function inventory_line_alert_send_notify($message)
{
    return line_messaging_push_text($message, 'inventory LINE stock alert');
}

/**
 * บันทึกผลการ run แจ้งเตือน Line รายวัน
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $runDate วันที่ run
 * @param string $status สถานะ sent, skipped, failed, no_low_stock
 * @param int $itemCount จำนวนรายการ
 * @param string $message ข้อความสรุป
 * @return void ไม่มี return value
 */
function inventory_line_alert_write_run_log($conn, $runDate, $status, $itemCount, $message)
{
    $message = function_exists('mb_substr')
        ? mb_substr((string) $message, 0, 255, 'UTF-8')
        : substr((string) $message, 0, 255);
    $sql = "INSERT INTO `line_stock_alert_runs`
            (`run_date`, `run_at`, `status`, `item_count`, `message`)
        VALUES (?, NOW(), ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `run_at` = VALUES(`run_at`),
            `status` = VALUES(`status`),
            `item_count` = VALUES(`item_count`),
            `message` = VALUES(`message`)";

    $stmt = inventory_alert_execute($conn, $sql, 'ssis', array(
        $runDate,
        $status,
        (int) $itemCount,
        $message,
    ));
    mysqli_stmt_close($stmt);
}

/**
 * run กระบวนการแจ้งเตือน Line สต็อกต่ำหนึ่งครั้งต่อวัน
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param bool $force ข้ามการเช็ค log วันนี้เมื่อ true
 * @return array ผลลัพธ์ status, message, item_count
 */
function inventory_line_alert_run($conn, $force = false)
{
    $runDate = date('Y-m-d');
    inventory_line_alert_ensure_schema($conn);

    if (!$force && inventory_line_alert_has_successful_run($conn, $runDate)) {
        return array('status' => 'skipped', 'message' => 'วันนี้เคยส่ง Line แล้ว', 'item_count' => 0);
    }

    if (!line_messaging_is_configured()) {
        $configMsg = line_messaging_config_error_message();
        inventory_line_alert_write_run_log($conn, $runDate, 'failed', 0, $configMsg);
        return array('status' => 'failed', 'message' => $configMsg, 'item_count' => 0);
    }

    $parts = inventory_line_alert_get_low_stock_parts($conn);
    if (count($parts) === 0) {
        inventory_line_alert_write_run_log($conn, $runDate, 'no_low_stock', 0, 'ไม่มีรายการที่ต้องแจ้ง Line');
        return array('status' => 'no_low_stock', 'message' => 'ไม่มีรายการที่ต้องแจ้ง Line', 'item_count' => 0);
    }

    $message = inventory_line_alert_build_message($parts, $runDate);
    if (!inventory_line_alert_send_notify($message)) {
        inventory_line_alert_write_run_log($conn, $runDate, 'failed', count($parts), 'ส่ง Line ไม่สำเร็จ');
        return array('status' => 'failed', 'message' => 'ส่ง Line ไม่สำเร็จ', 'item_count' => count($parts));
    }

    inventory_line_alert_write_run_log($conn, $runDate, 'sent', count($parts), 'ส่ง Line แจ้งเตือนสำเร็จ');

    return array(
        'status' => 'sent',
        'message' => 'ส่ง Line แจ้งเตือนสำเร็จ',
        'item_count' => count($parts),
    );
}

/**
 * จุดเริ่มต้นสำหรับ PHP CLI
 *
 * @param mysqli $connect connection จาก connect.php
 * @param array $argv argument CLI
 * @return int exit code
 */
function inventory_line_alert_main($connect, $argv)
{
    if (!inventory_alert_is_cli()) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        http_response_code(403);
        echo "อนุญาตให้ run ผ่าน PHP CLI เท่านั้น\n";
        return 1;
    }

    try {
        $result = inventory_line_alert_run($connect, inventory_alert_parse_force_flag($argv));
        echo '[' . $result['status'] . '] ' . $result['message']
            . ' | items=' . $result['item_count'] . PHP_EOL;

        return $result['status'] === 'failed' ? 1 : 0;
    } catch (Exception $e) {
        error_log('inventory line stock alert error: ' . $e->getMessage());
        echo "[failed] เกิดข้อผิดพลาดระหว่างแจ้งเตือน Line\n";
        return 1;
    }
}

if (realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') === __FILE__) {
    require_once __DIR__ . '/connect.php';
    exit(inventory_line_alert_main(inventory_db_connection(), isset($argv) ? $argv : array()));
}
