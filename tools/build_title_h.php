<?php
/** สร้าง title-h.php เป็น UTF-8 จาก Unicode escape */
$out = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'title-h.php';

$u = static function ($json) {
    return json_decode('"' . $json . '"');
};

$labels = array(
    'stock_overview' => $u('\u0e20\u0e32\u0e1e\u0e23\u0e27\u0e21') . ' Stock',
    'pickup_overview' => $u('\u0e14\u0e39\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e40\u0e1a\u0e34\u0e01\u0e02\u0e2d\u0e07'),
    'pre_pickup' => $u('\u0e40\u0e1a\u0e34\u0e01\u0e02\u0e2d\u0e07\u0e40\u0e1b\u0e47\u0e19\u0e40\u0e0b\u0e15'),
    'pickup_ma' => $u('\u0e40\u0e1a\u0e34\u0e01\u0e41\u0e22\u0e01\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23'),
    'stock_add' => $u('\u0e40\u0e1e\u0e34\u0e48\u0e21') . ' Stock',
    'part_add' => $u('\u0e40\u0e1e\u0e34\u0e48\u0e21') . ' Part',
    'setup' => $u('\u0e1b\u0e23\u0e30\u0e2a\u0e32\u0e19\u0e07\u0e32\u0e19'),
);

$q = static function ($s) {
    return str_replace(array('\\', "'"), array('\\\\', "\\'"), $s);
};

$content = <<<'PHP'
<?php
/**
 * แถบเมนูหลักด้านบน — include ในหน้าที่มี class navbar
 *
 * ใช้ร่วมกับ Font Awesome 4 (fa) จากหน้า parent
 */
$mnuStockOverview = '__MNU_STOCK__';
$mnuPickupOverview = '__MNU_PICKUP__';
$mnuPrePickup = '__MNU_PRE__';
$mnuPickupMa = '__MNU_MA__';
$mnuStockAdd = '__MNU_STOCK_ADD__';
$mnuPartAdd = '__MNU_PART_ADD__';
$mnuSetup = '__MNU_SETUP__';
$h = static function ($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
?>
<style type="text/css">
.style1 {
	font-size: 30px;
	font-weight: bold;
	margin-left: 2rem;
}
</style>
<table width="100%">
	<tr>
		<td valign="bottom"><span class="style1">Inventory</span></td>
		<td width="70%" align="center" valign="bottom">
			<div>
			<a href="./stock_overview.php">[ <i class="fa fa-star"></i> <?php echo $h($mnuStockOverview); ?> ]</a>
			<a href="./pickup_overview.php">[ <i class="fa fa-star"></i> <?php echo $h($mnuPickupOverview); ?> ]</a>
			<a href="./production_pre_pickup.php">[ <i class="fa fa-minus"></i> <?php echo $h($mnuPrePickup); ?> ]</a>
			<a href="./pickup_ma.php">[ <i class="fa fa-minus"></i> <?php echo $h($mnuPickupMa); ?> ]</a>
			<a href="./stock_add.php">[ <i class="fa fa-plus"></i> <?php echo $h($mnuStockAdd); ?> ]</a>
			<a href="./part_add.php">[ <i class="fa fa-plus"></i> <?php echo $h($mnuPartAdd); ?> ]</a>
			<a href="./setup_overview.php">[ <i class="fa fa-truck"></i> <?php echo $h($mnuSetup); ?> ]</a>
			</div>
		</td>
		<td width="10%" align="right" valign="bottom">
			<div style="margin-right:2rem;">
			<a href="/maintenance/index.php">[ <i class="fa fa-home"></i> Home ]</a>
			</div>
		</td>
	</tr>
</table>
<hr />

PHP;

$content = str_replace(
    array(
        '__MNU_STOCK__', '__MNU_PICKUP__', '__MNU_PRE__', '__MNU_MA__',
        '__MNU_STOCK_ADD__', '__MNU_PART_ADD__', '__MNU_SETUP__',
    ),
    array(
        $q($labels['stock_overview']),
        $q($labels['pickup_overview']),
        $q($labels['pre_pickup']),
        $q($labels['pickup_ma']),
        $q($labels['stock_add']),
        $q($labels['part_add']),
        $q($labels['setup']),
    ),
    $content
);

$content = str_replace(array('<motion', '</motion>'), array('<div', '</motion>'), $content);
$content = str_replace('</motion>', '</motion>', $content);
$content = str_replace('</motion>', '</div>', $content);

if (strlen($content) < 400) {
    die("refuse: too short\n");
}

$written = @file_put_contents($out, $content);
if ($written === false) {
    $out = 'D:/AppServ/www/inventory/title-h.php';
    file_put_contents($out, $content);
}
echo "OK len=" . strlen($content) . " -> " . $out . "\n";
