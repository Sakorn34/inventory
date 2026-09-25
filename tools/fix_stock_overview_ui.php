<?php
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'stock_overview.php';
$c = file_get_contents($path);

$c = preg_replace('/<title>.*?<\/title>/u', '<title>ภาพรวม Stock</title>', $c, 1);
$c = str_replace('charset=TIS-620', 'charset=UTF-8', $c);

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

$tableHead = '<tr  bgcolor="#E1E1E1">
    <th width="20"> <div align="center">No. </div></th>
    <th width="50"> <div align="center">หมวด Product</div></th>
    <th width="150"> <div align="center">ชื่อ Part</div></th>
    <th width="60"> <div align="center">จำนวนขั้นต่ำ Stock </div></th>
	<th width="50"> <div align="center">จำนวนใน Stock </div></th>
	<th width="100"> <div align="center">สถานะ</div></th>
    <th width="20"> <div align="center">Edit</div></th>
  </tr>';

$c = preg_replace(
    '/<tr\s+bgcolor="#E1E1E1">[\s\S]*?<th width="20"> <div align="center">Edit<\/div><\/th>\s*<\/tr>/u',
    $tableHead,
    $c,
    1
);

file_put_contents($path, $c);
echo "done\n";
