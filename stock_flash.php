<?php
/**
 * Flash หลังบันทึก stock (PRG) — แสดงครั้งเดียวบน stock_overview หรือ stock_add
 *
 * stock_save.php เรียก stock_flash_set ก่อน redirect
 * หน้าปลายทางเรียก stock_flash_consume() แล้วแสดงแถบข้อความ
 */

// ────────────────────────────────────────────────────────────────────────────────
// session
// ────────────────────────────────────────────────────────────────────────────────

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** @var string คีย์ session สำหรับ flash stock */
define('STOCK_FLASH_SESSION_KEY', 'stock_save_flash');

/**
 * เก็บข้อความ flash ไว้ใน session
 *
 * @param string $message ข้อความแจ้งผู้ใช้
 * @param string $type success หรือ error
 * @return void
 */
function stock_flash_set($message, $type)
{
    $safeType = ($type === 'success') ? 'success' : 'error';
    $_SESSION[STOCK_FLASH_SESSION_KEY] = array(
        'message' => (string) $message,
        'type' => $safeType,
    );
}

/**
 * อ่านและลบ flash ออกจาก session (แสดงได้ครั้งเดียว)
 *
 * @return array{message:string,type:string}
 */
function stock_flash_consume()
{
    if (empty($_SESSION[STOCK_FLASH_SESSION_KEY]) || !is_array($_SESSION[STOCK_FLASH_SESSION_KEY])) {
        return array('message' => '', 'type' => '');
    }

    $flash = $_SESSION[STOCK_FLASH_SESSION_KEY];
    unset($_SESSION[STOCK_FLASH_SESSION_KEY]);

    return array(
        'message' => isset($flash['message']) ? (string) $flash['message'] : '',
        'type' => isset($flash['type']) ? (string) $flash['type'] : '',
    );
}