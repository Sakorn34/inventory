<?php
/**
 * โมดูลแจ้งเตือนสต็อกต่ำประจำวันสำหรับระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือ query รายการ part ที่จำนวนคงเหลือต่ำกว่า `part_min_alert`,
 * ส่งอีเมลแจ้งเตือนไปยังผู้ใช้ที่มี email ในตาราง `users`, และบันทึกผลการ run
 * เพื่อกันการส่งซ้ำในวันเดียวกัน โดยรองรับการเรียกผ่าน PHP CLI หรือไฟล์ endpoint
 * `low_stock_alert_url.php` ที่ให้ scheduler ภายนอกเรียกผ่าน URL เวลา 09:00 น.
 *
 * Flow สั้น:
 * 1. Scheduler เรียก `php low_stock_alert.php` หรือ URL endpoint พร้อม token
 * 2. สคริปต์ตรวจรายการสต็อกต่ำและผู้รับแจ้งเตือน
 * 3. ส่งอีเมลเมื่อพบรายการต่ำกว่าเกณฑ์ แล้วบันทึกสถานะลง `stock_low_alert_runs`
 *
 * ตัวอย่าง:
 * `php low_stock_alert.php --force` ใช้ทดสอบส่งซ้ำโดยไม่เช็ค log ของวันนี้
 */

// ─ Constants ────────────────────────────────────────────────────────────────

if (!defined('INVENTORY_LOW_STOCK_MAIL_FROM')) {
    $mailFrom = getenv('INVENTORY_LOW_STOCK_MAIL_FROM');
    define('INVENTORY_LOW_STOCK_MAIL_FROM', $mailFrom !== false ? $mailFrom : 'inventory@localhost.localdomain');
}

if (!defined('INVENTORY_LOW_STOCK_BASE_URL')) {
    $baseUrl = getenv('INVENTORY_LOW_STOCK_BASE_URL');
    define('INVENTORY_LOW_STOCK_BASE_URL', $baseUrl !== false ? rtrim($baseUrl, '/') : '');
}

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * ตรวจว่าสคริปต์ถูกเรียกจาก PHP CLI หรือไม่
 *
 * @return bool คืนค่า true เมื่อทำงานผ่าน CLI เพื่อปิดช่องทาง trigger ผ่าน web
 */
function inventory_alert_is_cli()
{
    return PHP_SAPI === 'cli';
}

/**
 * ตรวจว่าคอลัมน์ใช้งานได้จริงด้วย SELECT (ไม่พึ่ง information_schema)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $table ชื่อตาราง
 * @param string $column ชื่อคอลัมน์
 * @return bool คืน true เมื่อ SELECT คอลัมน์ได้
 */
function inventory_alert_table_column_usable($conn, $table, $column)
{
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columnSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($tableSafe === '' || $columnSafe === '') {
        return false;
    }

    $result = mysqli_query($conn, 'SELECT `' . $columnSafe . '` FROM `' . $tableSafe . '` LIMIT 1');
    if ($result instanceof mysqli_result) {
        mysqli_free_result($result);

        return true;
    }

    $result = mysqli_query($conn, 'SELECT * FROM `' . $tableSafe . '` LIMIT 1');
    if ($result instanceof mysqli_result) {
        $fields = mysqli_fetch_fields($result);
        mysqli_free_result($result);
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (isset($field->name) && strcasecmp($field->name, $column) === 0) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * อ่านชื่อฐานข้อมูลที่ connection ใช้อยู่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return string ชื่อ database หรือ string ว่าง
 */
function inventory_alert_current_database($conn)
{
    $result = mysqli_query($conn, 'SELECT DATABASE() AS `db_name`');
    if (!$result instanceof mysqli_result) {
        return '';
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return isset($row['db_name']) ? (string) $row['db_name'] : '';
}

/**
 * ตรวจว่าตาราง query ได้
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $table ชื่อตาราง
 * @return bool คืน true เมื่อ SELECT จากตารางได้
 */
function inventory_alert_table_usable($conn, $table)
{
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $result = mysqli_query($conn, 'SELECT 1 FROM `' . $tableSafe . '` LIMIT 1');
    if ($result instanceof mysqli_result) {
        mysqli_free_result($result);

        return true;
    }

    return false;
}

/**
 * ยืนยันว่ามีคอลัมน์แล้ว (ไม่พยายาม ALTER — เหมาะกับ cron/URL บน shared hosting)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $table ชื่อตาราง
 * @param string $column ชื่อคอลัมน์
 * @return void ไม่มี return value
 */
function inventory_alert_require_column($conn, $table, $column)
{
    if (inventory_alert_table_column_exists($conn, $table, $column)) {
        return;
    }

    $dbName = inventory_alert_current_database($conn);
    $dbHint = $dbName !== '' ? ' (DB: ' . $dbName . ')' : '';

    throw new RuntimeException(
        'ไม่พบคอลัมน์ ' . $column . ' ในตาราง ' . $table . $dbHint
        . ' — ตรวจว่า connect.php ชี้ DB เดียวกับ phpMyAdmin แล้วรัน database/migrations/20250604_part_line_alert.sql'
    );
}

/**
 * ตรวจว่ามีคอลัมน์ในตาราง (ลอง SELECT ก่อน แล้ว fallback SHOW COLUMNS)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $table ชื่อตาราง
 * @param string $column ชื่อคอลัมน์
 * @return bool คืน true เมื่อพบคอลัมน์
 */
function inventory_alert_table_column_exists($conn, $table, $column)
{
    if (inventory_alert_table_column_usable($conn, $table, $column)) {
        return true;
    }

    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $columnEsc = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableSafe}` LIKE '" . $columnEsc . "'");
    if ($result instanceof mysqli_result) {
        $exists = mysqli_num_rows($result) > 0;
        mysqli_free_result($result);

        return $exists;
    }

    return false;
}

/**
 * เพิ่มคอลัมน์เมื่อยังไม่มี (รองรับ hosting ที่ไม่มีสิทธิ์ information_schema)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $table ชื่อตาราง
 * @param string $column ชื่อคอลัมน์
 * @param string $definition นิยามคอลัมน์หลังชื่อ (เช่น TINYINT(1) NOT NULL DEFAULT 0)
 * @param string|null $afterColumn วางหลังคอลัมน์นี้เมื่อระบุ
 * @return void ไม่มี return value
 */
function inventory_alert_ensure_column($conn, $table, $column, $definition, $afterColumn = null)
{
    if (inventory_alert_table_column_exists($conn, $table, $column)) {
        return;
    }

    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columnSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $attempts = array();

    if ($afterColumn !== null && $afterColumn !== '') {
        $afterSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $afterColumn);
        if ($afterSafe !== '') {
            $attempts[] = "ALTER TABLE `{$tableSafe}` ADD COLUMN IF NOT EXISTS `{$columnSafe}` {$definition} AFTER `{$afterSafe}`";
        }
    }
    $attempts[] = "ALTER TABLE `{$tableSafe}` ADD COLUMN IF NOT EXISTS `{$columnSafe}` {$definition}";
    $attempts[] = "ALTER TABLE `{$tableSafe}` ADD COLUMN `{$columnSafe}` {$definition}";

    $lastErrno = 0;
    $lastError = '';
    foreach ($attempts as $sql) {
        if (mysqli_query($conn, $sql)) {
            if (inventory_alert_table_column_usable($conn, $table, $column)) {
                return;
            }
        }

        $lastErrno = mysqli_errno($conn);
        $lastError = mysqli_error($conn);
        if ($lastErrno === 1060 || inventory_alert_table_column_usable($conn, $table, $column)) {
            return;
        }
    }

    throw new RuntimeException(sprintf(
        'เพิ่มคอลัมน์ %s ไม่สำเร็จ (errno=%d): %s — รัน database/migrations/20250604_part_line_alert.sql ใน phpMyAdmin',
        $columnSafe,
        $lastErrno,
        $lastError !== '' ? $lastError : 'user DB อาจไม่มีสิทธิ์ ALTER TABLE'
    ));
}

/**
 * รันคำสั่ง DDL (CREATE/ALTER) ที่ mysqli_prepare ไม่รองรับ
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL
 * @return void ไม่มี return value
 */
function inventory_alert_exec_ddl($conn, $sql)
{
    if (!mysqli_query($conn, $sql)) {
        $error = mysqli_error($conn);
        if ($error === '') {
            $error = 'errno=' . mysqli_errno($conn);
        }
        throw new RuntimeException('execute DDL ไม่สำเร็จ: ' . $error);
    }
}

/**
 * อ่านข้อความ error จาก mysqli connection
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return string ข้อความ error
 */
function inventory_alert_mysqli_error($conn)
{
    $error = mysqli_error($conn);
    if ($error !== '') {
        return $error;
    }

    $errno = mysqli_errno($conn);

    return $errno !== 0 ? 'errno=' . $errno : 'unknown';
}

/**
 * เตรียมและ execute SQL ด้วย mysqli prepared statement
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL ที่ใช้ placeholder เมื่อมี input
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind เข้า statement
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function inventory_alert_execute($conn, $sql, $types = '', $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('เตรียม SQL ไม่สำเร็จ: ' . inventory_alert_mysqli_error($conn));
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
        throw new RuntimeException('execute SQL ไม่สำเร็จ: ' . $error);
    }

    return $stmt;
}

/**
 * ดึงผลลัพธ์ทั้งหมดจาก prepared statement เป็น associative array
 *
 * @param mysqli_stmt $stmt statement ที่ execute แล้ว
 * @return array รายการแถวข้อมูลจากฐานข้อมูล
 */
function inventory_alert_fetch_all($stmt)
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
 * สร้างตาราง log สำหรับบันทึกผลการแจ้งเตือนรายวันเมื่อยังไม่มีตาราง
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return void ไม่มี return value
 */
function inventory_alert_ensure_log_table($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `stock_low_alert_runs` (
        `alert_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `run_date` DATE NOT NULL,
        `run_at` DATETIME NOT NULL,
        `status` VARCHAR(30) NOT NULL,
        `item_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `recipient_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `message` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`alert_id`),
        UNIQUE KEY `uk_stock_low_alert_run_date` (`run_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    inventory_alert_exec_ddl($conn, $sql);
}

/**
 * ตรวจว่าเคย run สำเร็จสำหรับวันที่กำหนดแล้วหรือไม่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $runDate วันที่รูปแบบ Y-m-d
 * @return bool คืน true เมื่อวันนี้เคยส่งหรือบันทึกว่าไม่มีรายการแล้ว
 */
function inventory_alert_has_successful_run($conn, $runDate)
{
    $sql = "SELECT `alert_id`
        FROM `stock_low_alert_runs`
        WHERE `run_date` = ? AND `status` IN ('sent', 'no_low_stock')
        LIMIT 1";
    $stmt = inventory_alert_execute($conn, $sql, 's', array($runDate));
    $rows = inventory_alert_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return count($rows) > 0;
}

/**
 * ดึงรายการ part ที่จำนวนคงเหลือต่ำกว่าเกณฑ์ `part_min_alert`
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ part ที่ต้องแจ้งเตือน
 */
function inventory_alert_get_low_stock_parts($conn)
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
        WHERE p.`part_min_alert` > 0
            AND p.`part_amount` < p.`part_min_alert`
        ORDER BY c.`ct_name` ASC, p.`part_name` ASC";
    $stmt = inventory_alert_execute($conn, $sql);
    $rows = inventory_alert_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * ดึงและ validate email ผู้รับแจ้งเตือนจากตาราง users
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ email ที่ผ่านการ validate แล้ว
 */
function inventory_alert_get_recipients($conn)
{
    $sql = "SELECT `email`
        FROM `users`
        WHERE `email` IS NOT NULL AND `email` <> ''
        ORDER BY `user_id` ASC";
    $stmt = inventory_alert_execute($conn, $sql);
    $rows = inventory_alert_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    $recipients = array();
    foreach ($rows as $row) {
        $email = trim((string) $row['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $email;
        }
    }

    return array_values(array_unique($recipients));
}

/**
 * format ตัวเลขสำหรับแสดงในเนื้อหาแจ้งเตือน
 *
 * @param mixed $value ค่าจำนวนจากฐานข้อมูล
 * @return string ตัวเลขที่ตัดศูนย์ท้ายเพื่ออ่านง่าย
 */
function inventory_alert_format_quantity($value)
{
    $number = number_format((float) $value, 2, '.', '');

    return rtrim(rtrim($number, '0'), '.');
}

/**
 * สร้าง subject และ body อีเมลแจ้งเตือน
 *
 * @param array $parts รายการ part ที่ต่ำกว่าเกณฑ์
 * @param string $runDate วันที่ run รูปแบบ Y-m-d
 * @return array คืนค่า subject และ body สำหรับส่งอีเมล
 */
function inventory_alert_build_mail($parts, $runDate)
{
    $subject = '[Inventory] แจ้งเตือนสต็อกต่ำกว่าเกณฑ์ ' . $runDate;
    $lines = array(
        'ระบบ Inventory พบรายการสต็อกต่ำกว่าเกณฑ์ที่กำหนด',
        'วันที่ตรวจสอบ: ' . $runDate,
        'จำนวนรายการ: ' . count($parts),
        '',
    );

    foreach ($parts as $index => $part) {
        $lines[] = ($index + 1) . '. ' . $part['part_name']
            . ' | หมวด: ' . ($part['ct_name'] !== '' ? $part['ct_name'] : '-')
            . ' | คงเหลือ: ' . inventory_alert_format_quantity($part['part_amount'])
            . ' | ขั้นต่ำ: ' . inventory_alert_format_quantity($part['part_min_alert'])
            . ' | สถานะสั่งซื้อ: ' . ($part['part_status_purchase'] !== '' ? $part['part_status_purchase'] : '-');
    }

    if (INVENTORY_LOW_STOCK_BASE_URL !== '') {
        $lines[] = '';
        $lines[] = 'ดูภาพรวม: ' . INVENTORY_LOW_STOCK_BASE_URL . '/stock_overview.php';
    }

    $lines[] = '';
    $lines[] = 'ข้อความนี้ส่งโดย scheduler เวลา 09:00 น.';

    return array($subject, implode("\r\n", $lines));
}

/**
 * เข้ารหัส subject อีเมลให้รองรับภาษาไทย
 *
 * @param string $subject subject ต้นฉบับ
 * @return string subject ที่พร้อมส่งผ่าน mail()
 */
function inventory_alert_encode_subject($subject)
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }

    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

/**
 * ส่งอีเมลแจ้งเตือนโดย validate header เพื่อลดความเสี่ยง header injection
 *
 * @param array $recipients รายการ email ผู้รับ
 * @param string $subject หัวข้ออีเมล
 * @param string $body เนื้อหาอีเมลแบบ text/plain
 * @return bool คืน true เมื่อ mail() รับงานส่งสำเร็จ
 */
function inventory_alert_send_mail($recipients, $subject, $body)
{
    $safeRecipients = array();
    foreach ($recipients as $recipient) {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $safeRecipients[] = $recipient;
        }
    }

    if (count($safeRecipients) === 0) {
        return false;
    }

    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Content-Type-Options: nosniff',
    );

    $from = str_replace(array("\r", "\n"), '', (string) INVENTORY_LOW_STOCK_MAIL_FROM);
    if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'From: Inventory Alert <' . $from . '>';
    }

    return mail(
        implode(',', $safeRecipients),
        inventory_alert_encode_subject($subject),
        $body,
        implode("\r\n", $headers)
    );
}

/**
 * บันทึกสถานะการ run เพื่อ audit และกันการส่งซ้ำรายวัน
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $runDate วันที่ run รูปแบบ Y-m-d
 * @param string $status สถานะ เช่น sent, skipped, failed, no_low_stock
 * @param int $itemCount จำนวนรายการ part ที่พบ
 * @param int $recipientCount จำนวนผู้รับแจ้งเตือน
 * @param string $message ข้อความสรุปผล
 * @return void ไม่มี return value
 */
function inventory_alert_write_run_log($conn, $runDate, $status, $itemCount, $recipientCount, $message)
{
    $message = function_exists('mb_substr')
        ? mb_substr((string) $message, 0, 255, 'UTF-8')
        : substr((string) $message, 0, 255);
    $sql = "INSERT INTO `stock_low_alert_runs`
            (`run_date`, `run_at`, `status`, `item_count`, `recipient_count`, `message`)
        VALUES (?, NOW(), ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `run_at` = VALUES(`run_at`),
            `status` = VALUES(`status`),
            `item_count` = VALUES(`item_count`),
            `recipient_count` = VALUES(`recipient_count`),
            `message` = VALUES(`message`)";

    $stmt = inventory_alert_execute($conn, $sql, 'ssiis', array(
        $runDate,
        $status,
        (int) $itemCount,
        (int) $recipientCount,
        $message,
    ));
    mysqli_stmt_close($stmt);
}

// ─ Main Class ────────────────────────────────────────────────────────────────

/**
 * run กระบวนการแจ้งเตือนสต็อกต่ำหนึ่งครั้ง
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param bool $force ส่งซ้ำได้แม้วันนี้เคย run สำเร็จแล้ว
 * @return array ผลลัพธ์การทำงานพร้อม status และ message
 */
function inventory_alert_run($conn, $force = false)
{
    $runDate = date('Y-m-d');
    inventory_alert_ensure_log_table($conn);

    if (!$force && inventory_alert_has_successful_run($conn, $runDate)) {
        return array('status' => 'skipped', 'message' => 'วันนี้เคย run สำเร็จแล้ว', 'item_count' => 0, 'recipient_count' => 0);
    }

    $parts = inventory_alert_get_low_stock_parts($conn);
    if (count($parts) === 0) {
        inventory_alert_write_run_log($conn, $runDate, 'no_low_stock', 0, 0, 'ไม่พบรายการสต็อกต่ำกว่าเกณฑ์');
        return array('status' => 'no_low_stock', 'message' => 'ไม่พบรายการสต็อกต่ำกว่าเกณฑ์', 'item_count' => 0, 'recipient_count' => 0);
    }

    $recipients = inventory_alert_get_recipients($conn);
    if (count($recipients) === 0) {
        inventory_alert_write_run_log($conn, $runDate, 'failed', count($parts), 0, 'ไม่พบ email ผู้รับแจ้งเตือนที่ถูกต้อง');
        return array('status' => 'failed', 'message' => 'ไม่พบ email ผู้รับแจ้งเตือนที่ถูกต้อง', 'item_count' => count($parts), 'recipient_count' => 0);
    }

    list($subject, $body) = inventory_alert_build_mail($parts, $runDate);
    if (!inventory_alert_send_mail($recipients, $subject, $body)) {
        inventory_alert_write_run_log($conn, $runDate, 'failed', count($parts), count($recipients), 'ส่งอีเมลไม่สำเร็จ');
        return array('status' => 'failed', 'message' => 'ส่งอีเมลไม่สำเร็จ', 'item_count' => count($parts), 'recipient_count' => count($recipients));
    }

    inventory_alert_write_run_log($conn, $runDate, 'sent', count($parts), count($recipients), 'ส่งอีเมลแจ้งเตือนสำเร็จ');

    return array('status' => 'sent', 'message' => 'ส่งอีเมลแจ้งเตือนสำเร็จ', 'item_count' => count($parts), 'recipient_count' => count($recipients));
}

// ─ Standalone Utilities ─────────────────────────────────────────────────────

/**
 * อ่าน flag จาก CLI argument สำหรับการทดสอบ
 *
 * @param array $argv argument จาก CLI
 * @return bool คืน true เมื่อมี --force
 */
function inventory_alert_parse_force_flag($argv)
{
    return in_array('--force', $argv, true);
}

/**
 * จุดเริ่มต้นของสคริปต์สำหรับ scheduler
 *
 * @param mysqli $connect connection ฐานข้อมูลจาก connect.php
 * @param array $argv argument จาก CLI
 * @return int exit code สำหรับ scheduler
 */
function inventory_alert_main($connect, $argv)
{
    if (!inventory_alert_is_cli()) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        http_response_code(403);
        echo "อนุญาตให้ run ผ่าน PHP CLI เท่านั้น\n";
        return 1;
    }

    try {
        $result = inventory_alert_run($connect, inventory_alert_parse_force_flag($argv));
        echo '[' . $result['status'] . '] ' . $result['message']
            . ' | items=' . $result['item_count']
            . ' | recipients=' . $result['recipient_count'] . PHP_EOL;

        return $result['status'] === 'failed' ? 1 : 0;
    } catch (Exception $e) {
        error_log('inventory low stock alert error: ' . $e->getMessage());
        echo "[failed] เกิดข้อผิดพลาดระหว่างแจ้งเตือนสต็อกต่ำ\n";

        return 1;
    }
}

if (realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') === __FILE__) {
    require_once __DIR__ . '/connect.php';
    exit(inventory_alert_main(inventory_db_connection(), isset($argv) ? $argv : array()));
}
