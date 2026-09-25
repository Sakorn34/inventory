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

// ─ Menu Labels ───────────────────────────────────────────────────────────────

$mnuStockOverview = 'ภาพรวม Stock';
$mnuStockReport = 'รายงาน Stock';
$mnuProductionHistory = 'ดูประวัติการเบิกผลิต';
$mnuPickupOverview = 'ดูรายการเบิกของ';
$mnuPrePickup = 'เบิกของเป็นเซต';
$mnuPickupMa = 'เบิกแยกรายการ';
$mnuStockAdd = 'เพิ่ม Stock';
$mnuPartAdd = 'เพิ่ม Part';
$mnuProduct = 'เพิ่ม Product';
$mnuFormula = 'จัดชุดรายการเบิก';
$mnuSetup = 'ประสานงาน';

// ─ Render Helpers ────────────────────────────────────────────────────────────

$h = static function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
if (!defined('INVENTORY_UI_CSS')) { define('INVENTORY_UI_CSS', true); echo '<link rel="stylesheet" href="css/inventory-ui.css">' . "\n"; }
$invScript = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
$navActive = static function ($files) use ($invScript) { return in_array($invScript, (array) $files, true) ? ' is-active' : ''; };
?>

<!-- ─ Navbar ─────────────────────────────────────────────────────────────── -->
<header class="inv-navbar"><div class="inv-navbar__inner">
<a href="./stock_overview.php" class="inv-navbar__brand"><i class="fa fa-cubes"></i><span>Inventory</span></a>
<nav class="inv-navbar__links" aria-label="เมนูหลัก">
<a href="./stock_overview.php" class="inv-nav-link<?php echo $navActive('stock_overview.php'); ?>"><i class="fa fa-star"></i><?php echo $h($mnuStockOverview); ?></a>
<a href="./stock_report.php" class="inv-nav-link<?php echo $navActive('stock_report.php'); ?>"><i class="fa fa-bar-chart"></i><?php echo $h($mnuStockReport); ?></a>
<a href="./production_history.php" class="inv-nav-link<?php echo $navActive('production_history.php'); ?>"><i class="fa fa-history"></i><?php echo $h($mnuProductionHistory); ?></a>
<a href="./pickup_overview.php" class="inv-nav-link<?php echo $navActive('pickup_overview.php'); ?>"><i class="fa fa-list-alt"></i><?php echo $h($mnuPickupOverview); ?></a>
<a href="./production_pre_pickup.php" class="inv-nav-link<?php echo $navActive(array('production_pre_pickup.php','production_pre_pickup2.php')); ?>"><i class="fa fa-minus-circle"></i><?php echo $h($mnuPrePickup); ?></a>
<a href="./pickup_ma.php" class="inv-nav-link<?php echo $navActive(array('pickup_ma.php','pickup_ma3.php')); ?>"><i class="fa fa-minus"></i><?php echo $h($mnuPickupMa); ?></a>
<a href="./stock_add.php" class="inv-nav-link<?php echo $navActive('stock_add.php'); ?>"><i class="fa fa-plus-circle"></i><?php echo $h($mnuStockAdd); ?></a>
<a href="./part_add.php" class="inv-nav-link<?php echo $navActive('part_add.php'); ?>"><i class="fa fa-plus"></i><?php echo $h($mnuPartAdd); ?></a>
<a href="./product_manage.php" class="inv-nav-link<?php echo $navActive('product_manage.php'); ?>"><i class="fa fa-tags"></i><?php echo $h($mnuProduct); ?></a>
<a href="./formula_manage.php" class="inv-nav-link<?php echo $navActive('formula_manage.php'); ?>"><i class="fa fa-sitemap"></i><?php echo $h($mnuFormula); ?></a>
<a href="./setup_overview.php" class="inv-nav-link<?php echo $navActive(array('setup_overview.php','setup_add.php','setup_detail.php')); ?>"><i class="fa fa-truck"></i><?php echo $h($mnuSetup); ?></a>
</nav>
<div class="inv-navbar__home"><a href="/maintenance/index.php" class="inv-nav-link inv-nav-link--home"><i class="fa fa-home"></i> Home</a></div>
</div></header>
