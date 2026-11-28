<?php
/**
 * โมดูล favicon กลางของระบบ inventory
 *
 * สร้าง `<link rel="icon">` ชี้ไปที่ `images/inventory.png` และ inject เข้า `<head>`
 * อัตโนมัติผ่าน output buffer สำหรับหน้า HTML ที่โหลด connect.php ก่อน render
 *
 * Flow สั้น: require connect.php → ลงทะเบียน buffer → เมื่อ script จบ buffer จะแทรก favicon
 * ใน `<head>` ถ้ายังไม่มี tag icon อยู่
 *
 * ตัวอย่าง: เปิด stock_overview.php แล้ว browser แสดงไอคอนจาก images/inventory.png
 */

// ─ Constants ────────────────────────────────────────────────────────────────

if (!defined('INVENTORY_FAVICON_FILE')) {
    define('INVENTORY_FAVICON_FILE', 'images/inventory.png');
}

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * คำนวณ URL ของ favicon ให้ชี้ไปที่โฟลเดอร์ inventory ไม่ว่าหน้าจะอยู่ subdirectory ใด
 *
 * @return string path สำหรับใส่ใน href เช่น /inventory/images/inventory.png
 */
function inventory_favicon_href()
{
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    $scriptDir = rtrim($scriptDir, '/');

    $subDirs = array('/other', '/register', '/chart', '/tools');
    foreach ($subDirs as $suffix) {
        if ($suffix !== '' && strlen($scriptDir) >= strlen($suffix)
            && substr($scriptDir, -strlen($suffix)) === $suffix) {
            $scriptDir = substr($scriptDir, 0, -strlen($suffix));
            break;
        }
    }

    $scriptDir = rtrim($scriptDir, '/');
    $file = INVENTORY_FAVICON_FILE;

    if ($scriptDir === '' || $scriptDir === '.') {
        return $file;
    }

    return $scriptDir . '/' . $file;
}

/**
 * สร้าง HTML tag ของ favicon พร้อม escape ค่า href
 *
 * @return string `<link rel="icon" ...>` หนึ่งบรรทัด
 */
function inventory_favicon_link_tag()
{
    $href = inventory_favicon_href();

    return '<link rel="icon" type="image/png" href="'
        . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * echo tag favicon สำหรับเรียกใช้ใน `<head>` โดยตรง (ถ้าต้องการ)
 *
 * @return void
 */
function inventory_echo_favicon_link()
{
    echo inventory_favicon_link_tag() . "\n";
}

/**
 * inject favicon เข้า `<head>` ของ HTML ถ้ายังไม่มี icon link
 *
 * @param string $html เนื้อหา HTML ทั้งหมดที่ buffer สะสม
 * @return string HTML หลังแทรก favicon หรือคืนค่าเดิมถ้าไม่ต้องแก้
 */
function inventory_inject_favicon_into_head($html)
{
    if (!is_string($html) || stripos($html, '<head') === false) {
        return $html;
    }

    if (stripos($html, 'rel="icon"') !== false || stripos($html, "rel='icon'") !== false) {
        return $html;
    }

    $tag = inventory_favicon_link_tag();
    $count = 0;
    $updated = preg_replace('/<head(\s[^>]*)?>/i', '$0' . "\n" . $tag, $html, 1, $count);

    return $count > 0 ? $updated : $html;
}

/**
 * ลงทะเบียน output buffer เพื่อแทรก favicon อัตโนมัติ (ครั้งเดียวต่อ request)
 *
 * @return void
 */
function inventory_register_favicon_output_buffer()
{
    if (defined('INVENTORY_FAVICON_BUFFER')) {
        return;
    }

    define('INVENTORY_FAVICON_BUFFER', true);
    ob_start('inventory_inject_favicon_into_head');
}
