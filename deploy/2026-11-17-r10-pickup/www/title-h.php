<?php
/**
 * โมดูลแถบนำทางหลักของระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือ render เมนูหลักที่ใช้ร่วมกันทุกหน้า กำหนดชื่อเมนู
 * active state จากชื่อไฟล์ปัจจุบัน และโหลด stylesheet กลางหนึ่งครั้ง
 *
 * Flow สั้น: หน้า PHP เรียก `require("title-h.php")` แล้วได้ navbar พร้อมลิงก์
 * ไปยัง workflow หลัก เช่น Stock, เบิกของ, Part, Formula และ Setup
 */

require_once __DIR__ . '/inventory_request_timing.php';

// ─ Menu Labels ───────────────────────────────────────────────────────────────

$mnuStockOverview = 'ภาพรวม Stock';
$mnuStockReport = 'รายงาน Stock';
$mnuProductionHistory = 'ดูประวัติการเบิกผลิต';
$mnuPickupOverview = 'ดูรายการเบิกของ';
$mnuPickupCreate = 'เบิกของ';
$mnuStockAdd = 'เพิ่ม Stock';
$mnuPartAdd = 'เพิ่ม Part';
$mnuProduct = 'เพิ่ม Product';
$mnuFormula = 'จัดชุดรายการเบิก';
$mnuSetup = 'ประสานงาน';
$mnuVendor = 'Vendor';

// ─ Render Helpers ────────────────────────────────────────────────────────────

$h = static function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
require_once __DIR__ . '/inventory_ui_head.php';
$invScript = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
$navActive = static function ($files) use ($invScript) { return in_array($invScript, (array) $files, true) ? ' is-active' : ''; };

if (!defined('INVENTORY_RELEASE_VERSION')) {
    require_once __DIR__ . '/inventory_version.php';
}
$invReleaseVersion = defined('INVENTORY_RELEASE_VERSION') ? (string) INVENTORY_RELEASE_VERSION : '';
$invAppMetaLabel = defined('INVENTORY_APP_META_LABEL') ? (string) INVENTORY_APP_META_LABEL : '';
$invMaintenanceSince = defined('INVENTORY_MAINTENANCE_SINCE_LABEL') ? (string) INVENTORY_MAINTENANCE_SINCE_LABEL : '';

$invPageMs = (string) inv_page_elapsed_ms();
?>

<!-- ─ Navbar ─────────────────────────────────────────────────────────────── -->
<header class="inv-navbar">
<div class="inv-navbar__inner">
<a href="./stock_overview.php" class="inv-navbar__brand"><i class="fa fa-cubes"></i><span>Inventory</span></a>
<nav class="inv-navbar__links" aria-label="เมนูหลัก">
<a href="./stock_overview.php" class="inv-nav-link<?php echo $navActive('stock_overview.php'); ?>"><i class="fa fa-star"></i><?php echo $h($mnuStockOverview); ?></a>
<a href="./stock_report.php" class="inv-nav-link<?php echo $navActive('stock_report.php'); ?>"><i class="fa fa-bar-chart"></i><?php echo $h($mnuStockReport); ?></a>
<a href="./production_history.php" class="inv-nav-link<?php echo $navActive('production_history.php'); ?>"><i class="fa fa-history"></i><?php echo $h($mnuProductionHistory); ?></a>
<a href="./pickup_overview.php" class="inv-nav-link<?php echo $navActive('pickup_overview.php'); ?>"><i class="fa fa-list-alt"></i><?php echo $h($mnuPickupOverview); ?></a>
<a href="./pickup_ma.php" class="inv-nav-link<?php echo $navActive(array('pickup_ma.php','pickup_ma3.php','production_pre_pickup.php','production_pre_pickup2.php')); ?>"><i class="fa fa-minus-circle"></i><?php echo $h($mnuPickupCreate); ?></a>
<a href="./stock_add.php" class="inv-nav-link<?php echo $navActive('stock_add.php'); ?>"><i class="fa fa-plus-circle"></i><?php echo $h($mnuStockAdd); ?></a>
<a href="./part_add.php" class="inv-nav-link<?php echo $navActive('part_add.php'); ?>"><i class="fa fa-plus"></i><?php echo $h($mnuPartAdd); ?></a>
<a href="./product_manage.php" class="inv-nav-link<?php echo $navActive('product_manage.php'); ?>"><i class="fa fa-tags"></i><?php echo $h($mnuProduct); ?></a>
<a href="./formula_manage.php" class="inv-nav-link<?php echo $navActive('formula_manage.php'); ?>"><i class="fa fa-sitemap"></i><?php echo $h($mnuFormula); ?></a>
<?php if (false): /* ซ่อนเมนูประสานงาน — ไม่ใช้งานแล้ว */ ?>
<a href="./setup_overview.php" class="inv-nav-link<?php echo $navActive(array('setup_overview.php','setup_add.php','setup_detail.php')); ?>"><i class="fa fa-truck"></i><?php echo $h($mnuSetup); ?></a>
<?php endif; ?>
<a href="./vendor_manage.php" class="inv-nav-link<?php echo $navActive(array('vendor_manage.php','vendor_add.php','vendor_edit.php')); ?>"><i class="fa fa-building-o"></i><?php echo $h($mnuVendor); ?></a>
</nav>
<?php if ($invAppMetaLabel !== '' || $invReleaseVersion !== '' || $invMaintenanceSince !== '') { ?>
<aside class="inv-navbar__meta" aria-label="ข้อมูลเวอร์ชันระบบ">
<?php if ($invAppMetaLabel !== '') { ?>
<p class="inv-navbar__meta-line"><?php echo $h($invAppMetaLabel); ?></p>
<?php } ?>
<?php if ($invMaintenanceSince !== '') { ?>
<p class="inv-navbar__meta-line inv-navbar__meta-line--since"><?php echo $h($invMaintenanceSince); ?></p>
<?php } ?>
<?php if ($invReleaseVersion !== '') { ?>
<p class="inv-navbar__meta-line inv-navbar__meta-line--version">v<?php echo $h($invReleaseVersion); ?></p>
<?php } ?>
<?php if ($invPageMs !== '') { ?>
<p class="inv-navbar__meta-line inv-navbar__meta-line--detail">หน้า <?php echo $h($invPageMs); ?> ms</p>
<?php } ?>
</aside>
<?php } ?>
</div></header>
