<?php
/**
 * ตำแหน่งเก็บอะไหล่ในห้องคลัง (ชั้นวาง + ระดับ + หมายเหตุ)
 *
 * เก็บแยกในตาราง part_location (1 อะไหล่ = 1 ตำแหน่ง) ไม่แตะตาราง part
 * รหัสแสดงผล: "B-3" = ชั้นวาง B ระดับ 3 (1 = ล่างสุด) หมายเหตุใช้แยกของที่อยู่ระดับเดียวกัน
 */

if (!defined('PART_LOCATION_MAX_LEVEL')) {
    define('PART_LOCATION_MAX_LEVEL', 8);
}
if (!defined('PART_LOCATION_NOTE_MAX')) {
    define('PART_LOCATION_NOTE_MAX', 100);
}

/**
 * สร้างตาราง part_location ถ้ายังไม่มี (เรียกได้หลายครั้ง ทำจริงครั้งเดียวต่อ request)
 *
 * @param mysqli $conn
 * @return bool
 */
function part_location_db_ready($conn)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = ($conn instanceof mysqli) && mysqli_query(
        $conn,
        'CREATE TABLE IF NOT EXISTS `part_location` (
            `part_id` INT UNSIGNED NOT NULL,
            `shelf` VARCHAR(4) NOT NULL,
            `level` TINYINT UNSIGNED NOT NULL,
            `note` VARCHAR(100) NOT NULL DEFAULT \'\',
            `updated_by` VARCHAR(100) NOT NULL DEFAULT \'\',
            `updated_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`part_id`),
            KEY `idx_part_location_pos` (`shelf`, `level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    ) !== false;

    if (!$ready && $conn instanceof mysqli) {
        error_log('part_location_db_ready: ' . mysqli_error($conn));
    }

    return $ready;
}

/**
 * ตัดความยาวข้อความแบบ UTF-8
 */
function part_location_cut($text, $max)
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max, 'UTF-8');
    }

    return substr($text, 0, $max);
}

/**
 * ตรวจและจัดรูปค่าตำแหน่งที่รับเข้ามา
 *
 * @return array{ok: bool, clear?: bool, shelf?: string, level?: int, note?: string, message?: string}
 *   clear = true เมื่อชั้นและระดับว่างทั้งคู่ (หมายถึงลบตำแหน่ง)
 */
function part_location_normalize($shelf, $level, $note)
{
    $shelf = strtoupper(trim((string) $shelf));
    $levelText = trim((string) $level);
    $note = trim(preg_replace('/\s+/u', ' ', (string) $note));

    if ($shelf === '' && ($levelText === '' || $levelText === '0')) {
        return array('ok' => true, 'clear' => true);
    }
    if (!preg_match('/^[A-Z]{1,2}$/', $shelf)) {
        return array('ok' => false, 'message' => 'ชั้นวางต้องเป็นตัวอักษร A–Z');
    }
    if (!preg_match('/^\d{1,2}$/', $levelText)) {
        return array('ok' => false, 'message' => 'เลือกระดับชั้น 1–' . PART_LOCATION_MAX_LEVEL);
    }
    $levelNum = (int) $levelText;
    if ($levelNum < 1 || $levelNum > PART_LOCATION_MAX_LEVEL) {
        return array('ok' => false, 'message' => 'เลือกระดับชั้น 1–' . PART_LOCATION_MAX_LEVEL);
    }

    return array(
        'ok' => true,
        'clear' => false,
        'shelf' => $shelf,
        'level' => $levelNum,
        'note' => part_location_cut($note, PART_LOCATION_NOTE_MAX),
    );
}

/**
 * บันทึก/ลบตำแหน่งของอะไหล่ 1 รายการ
 *
 * @param mysqli $conn
 * @param int $partId
 * @param array $loc ผลจาก part_location_normalize() ที่ ok แล้ว
 * @param string $actor ผู้บันทึก
 * @return bool
 */
function part_location_save($conn, $partId, array $loc, $actor)
{
    $partId = (int) $partId;
    if ($partId <= 0 || !part_location_db_ready($conn)) {
        return false;
    }

    if (!empty($loc['clear'])) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM `part_location` WHERE `part_id` = ?');
        if ($stmt === false) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $partId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }

    $actor = part_location_cut(trim((string) $actor), 100);
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO `part_location` (`part_id`, `shelf`, `level`, `note`, `updated_by`, `updated_at`)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE `shelf` = VALUES(`shelf`), `level` = VALUES(`level`), `note` = VALUES(`note`),
                                 `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()'
    );
    if ($stmt === false) {
        error_log('part_location_save prepare: ' . mysqli_error($conn));
        return false;
    }
    $shelf = $loc['shelf'];
    $level = (int) $loc['level'];
    $note = $loc['note'];
    mysqli_stmt_bind_param($stmt, 'isiss', $partId, $shelf, $level, $note, $actor);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        error_log('part_location_save execute: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    return $ok;
}

/**
 * อ่านตำแหน่งของอะไหล่ 1 รายการ
 *
 * @return array{shelf: string, level: int, note: string}|null
 */
function part_location_get($conn, $partId)
{
    if (!part_location_db_ready($conn)) {
        return null;
    }
    $stmt = mysqli_prepare($conn, 'SELECT `shelf`, `level`, `note` FROM `part_location` WHERE `part_id` = ?');
    if ($stmt === false) {
        return null;
    }
    $partId = (int) $partId;
    mysqli_stmt_bind_param($stmt, 'i', $partId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $shelf, $level, $note);
    $row = null;
    if (mysqli_stmt_fetch($stmt)) {
        $row = array('shelf' => (string) $shelf, 'level' => (int) $level, 'note' => (string) $note);
    }
    mysqli_stmt_close($stmt);

    return $row;
}

/**
 * รหัสตำแหน่งสั้น เช่น "B-3" (ว่างถ้ายังไม่ระบุ)
 */
function part_location_code($shelf, $level)
{
    $shelf = trim((string) $shelf);
    $level = (int) $level;
    if ($shelf === '' || $level <= 0) {
        return '';
    }

    return $shelf . '-' . $level;
}

/**
 * ข้อความแสดงผลเต็ม เช่น "B-3 · กล่องฟ้า ซ้าย"
 */
function part_location_label($shelf, $level, $note)
{
    $code = part_location_code($shelf, $level);
    if ($code === '') {
        return '';
    }
    $note = trim((string) $note);

    return $note === '' ? $code : $code . ' · ' . $note;
}

/**
 * คีย์สำหรับเรียงตามทางเดิน (ไม่มีตำแหน่งไปท้ายสุด)
 */
function part_location_sort_key($shelf, $level)
{
    $shelf = trim((string) $shelf);
    if ($shelf === '' || (int) $level <= 0) {
        return 'ZZZ-99';
    }

    return str_pad($shelf, 2, ' ', STR_PAD_RIGHT) . '-' . str_pad((string) (int) $level, 2, '0', STR_PAD_LEFT);
}

/**
 * HTML ป้ายตำแหน่ง (หน้าคอม) — ใช้คลาส inv-loc ใน css/inventory-ui.css
 */
function part_location_badge_html($shelf, $level, $note)
{
    $code = part_location_code($shelf, $level);
    if ($code === '') {
        return '<span class="inv-loc inv-loc--none">ไม่ระบุ</span>';
    }
    $html = '<span class="inv-loc">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span>';
    $note = trim((string) $note);
    if ($note !== '') {
        $html .= ' <span class="inv-loc-note">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return $html;
}
