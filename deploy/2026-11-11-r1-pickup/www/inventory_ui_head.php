<?php
/**
 * ส่วนหัว UI กลาง — ฟอนต์ Sarabun + stylesheet inventory-ui.css
 *
 * ใช้ใน <head> ของหน้า inv-app หรับให้ title-h.php เรียกถ้ายังไม่ได้โหลด
 * โหลด inventory_request_timing ต้น head เพื่อยึดเวลา request จริงสำหรับ navbar
 */

require_once __DIR__ . '/inventory_request_timing.php';
require_once __DIR__ . '/inventory_ui_fonts.php';

/**
 * ใช้ INVENTORY_UI_CSS_LINK_EMITTED แทน INVENTORY_UI_CSS อย่างเดียว
 * (หน้าเก่าบางไฟล์ define INVENTORY_UI_CSS ก่อน require ไฟล์นี้ ทำให้ไม่โหลด CSS)
 */
if (!defined('INVENTORY_UI_CSS_LINK_EMITTED')) {
    define('INVENTORY_UI_CSS_LINK_EMITTED', true);
    if (!defined('INVENTORY_UI_CSS')) {
        define('INVENTORY_UI_CSS', true);
    }
    echo '<link rel="stylesheet" href="css/inventory-ui.css">' . "\n";
}
