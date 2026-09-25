<?php
$path = __DIR__ . '/../stock_overview.php';
$c = file_get_contents($path);
if ($c === false || strlen($c) < 1000) {
    die("cannot read stock_overview.php\n");
}

$c = preg_replace('/<title>.*?<\/title>/u', '<title>ภาพรวม Stock</title>', $c, 1);

$c = preg_replace(
    '/<td colspan="7" align="center" valign="center" ><b class="ex">[^<]*<\/b><\/td>/u',
    '<td colspan="7" align="center" valign="center" ><b class="ex">ภาพรวมสต็อก</b></td>',
    $c,
    1
);

$c = preg_replace(
    '/<b class="ex">[^<]* Product : <\/b>/u',
    '<b class="ex">หมวด Product : </b>',
    $c,
    1
);

$c = preg_replace(
    '/<b class="ex">[^<]* : <\/b><\/td><td>\s*\n\s*<select class="custom-select" name="part_status"/su',
    "<b class=\"ex\">สถานะ : </b></td><td>\n\t<select class=\"custom-select\" name=\"part_status\"",
    $c,
    1
);

$statusSelect = '<select class="custom-select" name="part_status" id="part_status" required>
	<?php
	$statusList = array(\'\', \'ยังไม่ถึงจุดสั่ง\', \'ถึงจุดสั่งแล้ว\', \'ปกติ\');
	foreach ($statusList as $st) {
		$sel = ($getStatus === $st) ? \' selected="selected"\' : \'\';
		$label = ($st === \'\') ? \'-- All --\' : $st;
		echo \'<option value="\' . htmlspecialchars($st, ENT_QUOTES, \'UTF-8\') . \'"\' . $sel . \'>\'
			. htmlspecialchars($label, ENT_QUOTES, \'UTF-8\') . \'</option>\';
	}
	?>
    </select>';

$c = preg_replace(
    '/<select class="custom-select" name="part_status" id="part_status" required>.*?<\/select>/su',
    $statusSelect,
    $c,
    1
);

$tableHead = <<<'END'
     <tr  bgcolor="#E1E1E1">
    <th width="20"> [[[DIV]]]No. [[[/DIV]]]</th>
    <th width="50"> [[[DIV]]]หมวด Product[[[/DIV]]]</th>
    <th width="150"> [[[DIV]]]ชื่อ Part[[[/DIV]]]</th>
    <th width="60"> [[[DIV]]]จำนวนขั้นต่ำ Stock [[[/DIV]]]</th>
	<th width="50"> [[[DIV]]]จำนวนใน Stock [[[/DIV]]]</th>
	<th width="100"> [[[DIV]]]สถานะ[[[/DIV]]]</th>
    <th width="20"> [[[DIV]]]Edit[[[/DIV]]]</th>
  </tr>
END;
$tableHead = str_replace(array('[[[DIV]]]', '[[[/DIV]]]'), array('<div align="center">', '</div>'), $tableHead);

$c = preg_replace(
    '/<tr\s+bgcolor="#E1E1E1">[\s\S]*?<th width="20"> <div align="center">Edit<\/div><\/th>\s*<\/tr>/u',
    $tableHead,
    $c,
    1
);

$c = preg_replace('/<script[^>]*kit\.fontawesome\.com[^>]*>\s*<\/script>\s*/i', '', $c);
$c = preg_replace('/<script[^>]*jquery\.maskedinput[^>]*>\s*<\/script>\s*/i', '', $c);

if (strlen($c) < 1000) {
    die("refuse to write: content too short\n");
}

file_put_contents($path, $c);
echo 'OK len=' . strlen($c) . "\n";
