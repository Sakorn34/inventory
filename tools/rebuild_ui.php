<?php
/** Rebuild title-h.php + stock_overview.php (UTF-8) — ASCII source only */
$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);
$u = static function ($j) {
    return json_decode('"' . $j . '"');
};
$q = static function ($s) {
    return str_replace(array('\\', "'"), array('\\\\', "\\'"), $s);
};

$L = array(
    'statusNotYet' => $u('\u0e22\u0e31\u0e07\u0e44\u0e21\u0e48\u0e16\u0e36\u0e07\u0e08\u0e38\u0e14\u0e2a\u0e31\u0e48\u0e07'),
    'statusReached' => $u('\u0e16\u0e36\u0e07\u0e08\u0e38\u0e14\u0e2a\u0e31\u0e48\u0e07\u0e41\u0e25\u0e49\u0e27'),
    'statusNormal' => $u('\u0e1b\u0e01\u0e15\u0e34'),
    'lblOverview' => $u('\u0e20\u0e32\u0e1e\u0e23\u0e27\u0e21\u0e2a\u0e15\u0e47\u0e2d\u0e01'),
    'lblCategory' => $u('\u0e2b\u0e21\u0e27\u0e14'),
    'lblStatus' => $u('\u0e2a\u0e16\u0e32\u0e19\u0e30'),
    'lblPartName' => $u('\u0e0a\u0e37\u0e48\u0e2d'),
    'lblMinStock' => $u('\u0e08\u0e33\u0e19\u0e27\u0e19\u0e02\u0e31\u0e49\u0e19\u0e15\u0e48\u0e33'),
    'lblInStock' => $u('\u0e08\u0e33\u0e19\u0e27\u0e19\u0e43\u0e19'),
    'titlePage' => $u('\u0e20\u0e32\u0e1e\u0e23\u0e27\u0e21') . ' Stock',
    'desc' => $u('\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e08\u0e33\u0e19\u0e27\u0e19\u0e2a\u0e15\u0e47\u0e2d\u0e01 \u0e2a\u0e16\u0e32\u0e19\u0e30\u0e01\u0e32\u0e23\u0e2a\u0e31\u0e48\u0e07\u0e0b\u0e37\u0e49\u0e2d \u0e41\u0e25\u0e30\u0e41\u0e01\u0e49\u0e44\u0e02\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23 Part'),
    'filterHead' => $u('\u0e01\u0e23\u0e2d\u0e07\u0e02\u0e49\u0e2d\u0e21\u0e39\u0e25'),
    'tableHead' => $u('\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23 Part'),
    'btnShow' => $u('\u0e41\u0e2a\u0e14\u0e07\u0e1c\u0e25'),
    'btnReset' => $u('\u0e25\u0e49\u0e32\u0e07'),
    'allOpt' => $u('-- \u0e17\u0e31\u0e49\u0e07\u0e2b\u0e21\u0e14 --'),
    'editCol' => $u('\u0e41\u0e01\u0e49\u0e44\u0e02'),
    'empty' => $u('\u0e44\u0e21\u0e48\u0e1e\u0e1a\u0e02\u0e49\u0e2d\u0e21\u0e39\u0e25'),
    'editTitle' => $u('\u0e41\u0e01\u0e49\u0e44\u0e02 Part'),
    'navMenu' => $u('\u0e40\u0e21\u0e19\u0e39\u0e2b\u0e25\u0e31\u0e01'),
    'mnuStock' => $u('\u0e20\u0e32\u0e1e\u0e23\u0e27\u0e21') . ' Stock',
    'mnuPickup' => $u('\u0e14\u0e39\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e40\u0e1a\u0e34\u0e01\u0e02\u0e2d\u0e07'),
    'mnuPre' => $u('\u0e40\u0e1a\u0e34\u0e01\u0e02\u0e2d\u0e07\u0e40\u0e1b\u0e47\u0e19\u0e40\u0e0b\u0e15'),
    'mnuMa' => $u('\u0e40\u0e1a\u0e34\u0e01\u0e41\u0e22\u0e01\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23'),
    'mnuStockAdd' => $u('\u0e40\u0e1e\u0e34\u0e48\u0e21') . ' Stock',
    'mnuPartAdd' => $u('\u0e40\u0e1e\u0e34\u0e48\u0e21') . ' Part',
    'mnuSetup' => $u('\u0e1b\u0e23\u0e30\u0e2a\u0e32\u0e19\u0e07\u0e32\u0e19'),
);

$titleH = '<?php
$mnuStockOverview = \'' . $q($L['mnuStock']) . '\';
$mnuPickupOverview = \'' . $q($L['mnuPickup']) . '\';
$mnuPrePickup = \'' . $q($L['mnuPre']) . '\';
$mnuPickupMa = \'' . $q($L['mnuMa']) . '\';
$mnuStockAdd = \'' . $q($L['mnuStockAdd']) . '\';
$mnuPartAdd = \'' . $q($L['mnuPartAdd']) . '\';
$mnuSetup = \'' . $q($L['mnuSetup']) . '\';
$h = static function ($s) { return htmlspecialchars($s, ENT_QUOTES, \'UTF-8\'); };
if (!defined(\'INVENTORY_UI_CSS\')) { define(\'INVENTORY_UI_CSS\', true); echo \'<link rel="stylesheet" href="css/inventory-ui.css">\' . "\\n"; }
$invScript = isset($_SERVER[\'SCRIPT_NAME\']) ? basename($_SERVER[\'SCRIPT_NAME\']) : \'\';
$navActive = static function ($files) use ($invScript) { return in_array($invScript, (array) $files, true) ? \' is-active\' : \'\'; };
?>
<header class="inv-navbar"><motion class="inv-navbar__inner">
<a href="./stock_overview.php" class="inv-navbar__brand"><i class="fa fa-cubes"></i><span>Inventory</span></a>
<nav class="inv-navbar__links" aria-label="' . $q($L['navMenu']) . '">
<a href="./stock_overview.php" class="inv-nav-link<?php echo $navActive(\'stock_overview.php\'); ?>"><i class="fa fa-star"></i><?php echo $h($mnuStockOverview); ?></a>
<a href="./pickup_overview.php" class="inv-nav-link<?php echo $navActive(\'pickup_overview.php\'); ?>"><i class="fa fa-list-alt"></i><?php echo $h($mnuPickupOverview); ?></a>
<a href="./production_pre_pickup.php" class="inv-nav-link<?php echo $navActive(array(\'production_pre_pickup.php\',\'production_pre_pickup2.php\')); ?>"><i class="fa fa-minus-circle"></i><?php echo $h($mnuPrePickup); ?></a>
<a href="./pickup_ma.php" class="inv-nav-link<?php echo $navActive(array(\'pickup_ma.php\',\'pickup_ma3.php\')); ?>"><i class="fa fa-minus"></i><?php echo $h($mnuPickupMa); ?></a>
<a href="./stock_add.php" class="inv-nav-link<?php echo $navActive(\'stock_add.php\'); ?>"><i class="fa fa-plus-circle"></i><?php echo $h($mnuStockAdd); ?></a>
<a href="./part_add.php" class="inv-nav-link<?php echo $navActive(\'part_add.php\'); ?>"><i class="fa fa-plus"></i><?php echo $h($mnuPartAdd); ?></a>
<a href="./setup_overview.php" class="inv-nav-link<?php echo $navActive(array(\'setup_overview.php\',\'setup_add.php\',\'setup_detail.php\')); ?>"><i class="fa fa-truck"></i><?php echo $h($mnuSetup); ?></a>
</nav>
<div class="inv-navbar__home"><a href="/maintenance/index.php" class="inv-nav-link inv-nav-link--home"><i class="fa fa-home"></i> Home</a></div>
</div></header>
';
$titleH = str_replace(array('<motion ', '</motion>'), array('<div ', '</div>'), $titleH);

$vars = '';
foreach (array(
    'statusNotYet', 'statusReached', 'statusNormal', 'lblOverview', 'lblCategory', 'lblStatus',
    'lblPartName', 'lblMinStock', 'lblInStock', 'titlePage', 'lblDesc', 'lblFilter', 'lblTable',
    'lblBtnShow', 'lblBtnReset', 'lblAllOpt', 'lblEditCol', 'lblEmpty', 'lblEditTitle',
) as $name) {
    $key = ($name === 'lblDesc') ? 'desc' : (($name === 'lblFilter') ? 'filterHead' : (($name === 'lblTable') ? 'tableHead' : (($name === 'lblBtnShow') ? 'btnShow' : (($name === 'lblBtnReset') ? 'btnReset' : (($name === 'lblAllOpt') ? 'allOpt' : (($name === 'lblEditCol') ? 'editCol' : (($name === 'lblEmpty') ? 'empty' : (($name === 'lblEditTitle') ? 'editTitle' : $name))))))));
    $vars .= '$' . $name . ' = \'' . $q($L[$key]) . "';\n";
}

$stockHead = '<?php
require_once __DIR__ . \'/connect.php\';
require_once __DIR__ . \'/function.php\';
header(\'Content-Type: text/html; charset=UTF-8\');
@setlocale(LC_TIME, \'th_TH.UTF-8\', \'th_TH\', \'tha\');
' . $vars . '$h = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, \'UTF-8\'); };
$search = \'\';
$getData = isset($_GET[\'product\']) ? trim((string) $_GET[\'product\']) : \'\';
$getStatus = isset($_GET[\'part_status\']) ? trim((string) $_GET[\'part_status\']) : \'\';
$getDataSql = ($getData !== \'\' && $getData !== \'All\') ? (int) $getData : 0;
$getStatusSql = db_escape($connect, $getStatus);
if ($getData !== \'\' || $getStatus !== \'\') {
    if ($getData !== \'\' && $getStatus !== \'\') {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_categories]=$getDataSql AND part.[part_status_purchase]=\'$getStatusSql\' ORDER BY part.[part_name] ASC";
    } elseif ($getData !== \'\' && $getStatus === \'\') {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_categories]=$getDataSql ORDER BY part.[part_name] ASC";
    } elseif ($getData === \'\' && $getStatus !== \'\') {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_status_purchase]=\'$getStatusSql\' ORDER BY part.[part_name] ASC";
    } else {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] ORDER BY part.[part_categories],part.[part_name] ASC";
    }
} else {
    $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] ORDER BY part.[part_categories],part.[part_name] ASC";
}
$array = array(\'All\'); $array2 = array(\'All\');
$exeSP = db_exec($connect, \'SELECT * FROM categories_product\') or die(\'Error categories_product\');
while ($resultSP = db_fetch_array($exeSP)) { $array[] = $resultSP[\'ct_name\']; $array2[] = $resultSP[\'ct_id\']; }
$arrLength = count($array);
$statusList = array(\'\', $statusNotYet, $statusReached, $statusNormal);
$statusBadgeClass = static function ($status) use ($statusNotYet, $statusReached, $statusNormal) {
    if ($status === \'\' || $status === $statusNotYet) { return \'inv-badge--danger\'; }
    if ($status === $statusReached) { return \'inv-badge--warn\'; }
    if ($status === $statusNormal) { return \'inv-badge--success\'; }
    return \'inv-badge--muted\';
};
$dash = "\xE2\x80\x94";
?>';

$bodyPath = __DIR__ . DIRECTORY_SEPARATOR . 'stock_overview_body.html';
$stockHtml = @file_get_contents($bodyPath);
if ($stockHtml === false || strlen($stockHtml) < 500) {
  $bodyPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'stock_overview_body.html';
  $stockHtml = @file_get_contents($bodyPath);
}
if ($stockHtml === false || strlen($stockHtml) < 500) {
    die("missing stock_overview_body.html\n");
}
$stockHtml = str_replace('</motion></motion>', '</div>', $stockHtml);
$stockHtml = str_replace('</motion>', '</div>', $stockHtml);

$stock = $stockHead . "\n" . $stockHtml;
$w1 = @file_put_contents($root . DIRECTORY_SEPARATOR . 'title-h.php', $titleH);
$w2 = @file_put_contents($root . DIRECTORY_SEPARATOR . 'stock_overview.php', $stock);
if ($w1 === false || $w2 === false) {
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inventory_ui';
    if (!is_dir($tmp)) {
        mkdir($tmp, 0777, true);
    }
    file_put_contents($tmp . DIRECTORY_SEPARATOR . 'title-h.php', $titleH);
    file_put_contents($tmp . DIRECTORY_SEPARATOR . 'stock_overview.php', $stock);
    echo "TMP=$tmp\n";
} else {
    echo "OK root=$root title=" . strlen($titleH) . " stock=" . strlen($stock) . "\n";
}
