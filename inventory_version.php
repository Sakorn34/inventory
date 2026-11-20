<?php
/**
 * ค่า release version ของระบบ inventory สำหรับแสดงบน navbar
 *
 * อัปเดตค่า INVENTORY_RELEASE_VERSION เมื่อ deploy ชุดใหม่ขึ้น production
 * ตัวอย่าง: require จาก title-h.php แล้ว echo ในมุมขวาบน
 */

if (!defined('INVENTORY_RELEASE_VERSION')) {
    define('INVENTORY_RELEASE_VERSION', '2026.11.20-r9');
}

if (!defined('INVENTORY_RELEASE_DATE')) {
    define('INVENTORY_RELEASE_DATE', '2026-11-20');
}

if (!defined('INVENTORY_APP_META_LABEL')) {
    define('INVENTORY_APP_META_LABEL', '© bitCOMBINE · Inventory');
}

/** วันที่เริ่มดูแล/พัฒนาระบบ (แสดงบน navbar) — อัปเดตเมื่อเปลี่ยนทีมดูแล */
if (!defined('INVENTORY_MAINTENANCE_SINCE_LABEL')) {
    define('INVENTORY_MAINTENANCE_SINCE_LABEL', 'Production · 3 พ.ย. 2569');
}

if (!defined('INVENTORY_MAINTENANCE_SINCE_DATE')) {
    define('INVENTORY_MAINTENANCE_SINCE_DATE', '2026-11-03');
}
