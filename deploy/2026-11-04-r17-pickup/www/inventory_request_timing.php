<?php
/**
 * คำนวณเวลา generate หน้า PHP สำหรับแสดงบน navbar
 *
 * ใช้ REQUEST_TIME_FLOAT จาก PHP (เริ่มต้น request จริง) ไม่พึ่ง connect.php บน production
 * ที่มักไม่ถูก deploy — หลีกเลี่ยงการ define INV_REQUEST_START ช่วง render navbar
 * ซึ่งทำให้ได้ 0 ms เสมอ
 *
 * ตัวอย่าง: require จาก title-h.php แล้วเรียก inv_page_elapsed_ms()
 */

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * คืนเวลาเริ่ม request เป็นวินาที (float)
 *
 * ลำดับ: REQUEST_TIME_FLOAT → INV_REQUEST_START (ถ้ามี) → microtime ปัจจุบัน
 *
 * @return float timestamp เริ่ม request
 */
function inv_request_start_timestamp()
{
    if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
        return (float) $_SERVER['REQUEST_TIME_FLOAT'];
    }
    if (defined('INV_REQUEST_START')) {
        return (float) INV_REQUEST_START;
    }

    return microtime(true);
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
