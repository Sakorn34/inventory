<?php
/**
 * โหลดฟอนต์ Sarabun จาก Google Fonts สำหรับ inventory UI
 *
 * เรียกครั้งเดียวต่อหน้า (กัน duplicate link) — ควรอยู่ใน <head> ก่อน inventory-ui.css
 */

if (!defined('INVENTORY_UI_FONTS')) {
    define('INVENTORY_UI_FONTS', true);
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&amp;display=swap">' . "\n";
}
