<?php
/**
 * คำนวณเวลา generate หน้า PHP สำหรับแสดงบน navbar
 *
 * ยึดเวลาเริ่ม request จาก REQUEST_TIME_FLOAT / REQUEST_TIME ของ PHP
 * หลีกเลี่ยงการ define INV_REQUEST_START ช่วง render navbar หรือใน inventory_ui_head
 * ซึ่งทำให้บน production เห็นแค่ 2–3 ms ทั้งที่หน้าจริงใช้เวลานานกว่า
 *
 * ตัวอย่าง: require จาก inventory_ui_head.php หรือ title-h.php แล้วเรียก inv_page_elapsed_ms()
 */

// ─ Bootstrap ─────────────────────────────────────────────────────────────────

if (!defined('INV_PAGE_TIMING_START')) {
    if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
        define('INV_PAGE_TIMING_START', (float) $_SERVER['REQUEST_TIME_FLOAT']);
    } elseif (isset($_SERVER['REQUEST_TIME'])) {
        define('INV_PAGE_TIMING_START', (float) $_SERVER['REQUEST_TIME']);
    } elseif (defined('INV_REQUEST_START')) {
        define('INV_PAGE_TIMING_START', (float) INV_REQUEST_START);
    } else {
        define('INV_PAGE_TIMING_START', microtime(true));
    }
}

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * คืนเวลาเริ่ม request เป็นวินาที (float)
 *
 * @return float timestamp เริ่ม request
 */
function inv_request_start_timestamp()
{
    return (float) INV_PAGE_TIMING_START;
}

/**
 * คืนเวลาที่ใช้ generate หน้าจนถึงตอนเรียก เป็นหน่วย ms (จำนวนเต็ม ≥ 0)
 *
 * @return int มิลลิวินาที
 */
function inv_page_elapsed_ms()
{
    $elapsed = (microtime(true) - inv_request_start_timestamp()) * 1000;

    return max(0, (int) round($elapsed));
}
