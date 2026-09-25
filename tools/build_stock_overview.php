<?php
/** สร้าง stock_overview.php UTF-8 จาก Unicode escape (สคริปต์เป็น ASCII) */
$out = 'D:/AppServ/www/inventory/stock_overview.php';

$u = static function ($json) {
    return json_decode('"' . $json . '"');
};

$statusNotYet = $u('\u0e22\u0e31\u0e07\u0e44\u0e21\u0e48\u0e16\u0e36\u0e07\u0e08\u0e38\u0e14\u0e2a\u0e31\u0e48\u0e07');
$statusReached = $u('\u0e16\u0e36\u0e07\u0e08\u0e38\u0e14\u0e2a\u0e31\u0e48\u0e07\u0e41\u0e25\u0e49\u0e27');
$statusNormal = $u('\u0e1b\u0e01\u0e15\u0e34');
$lblOverview = $u('\u0e20\u0e32\u0e1e\u0e23\u0e27\u0e21\u0e2a\u0e15\u0e47\u0e2d\u0e01');
$lblCategory = $u('\u0e2b\u0e21\u0e27\u0e14');
$lblStatus = $u('\u0e2a\u0e16\u0e32\u0e19\u0e30');
$lblPartName = $u('\u0e0a\u0e37\u0e48\u0e2d');
$lblMinStock = $u('\u0e08\u0e33\u0e19\u0e27\u0e19\u0e02\u0e31\u0e49\u0e19\u0e15\u0e48\u0e33');
$lblInStock = $u('\u0e08\u0e33\u0e19\u0e27\u0e19\u0e43\u0e19');
$titlePage = $u('\u0e20\u0e32\u0e1e\u0e23\u0e27\u0e21') . ' Stock';

$q = static function ($s) {
    return str_replace(array('\\', "'"), array('\\\\', "\\'"), $s);
};

$body = <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link type="text/css" rel="stylesheet" href="css.css" />
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<title><?php echo htmlspecialchars($titlePage, ENT_QUOTES, 'UTF-8'); ?></title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
<script type="text/javascript">
function sort_product(){
	var product=document.getElementById("product_name").value;
	var part_status=document.getElementById("part_status").value;
	if(product=="All"){product="";}
	if(part_status=="All"){part_status="";}
	if(product!=""&&part_status!=""){
		window.location="stock_overview.php?product="+encodeURIComponent(product)+"&part_status="+encodeURIComponent(part_status);
	}else if(product!=""&&part_status==""){
		window.location="stock_overview.php?product="+encodeURIComponent(product);
	}else if(product==""&&part_status!=""){
		window.location="stock_overview.php?part_status="+encodeURIComponent(part_status);
	}else{window.location="stock_overview.php";}
}
</script>
</head>
<style>
.even{background-color:#F0F0F0!important}.odd{background-color:#FFF!important}
.content{max-width:1200px;margin-top:100px;font-size:16px;color:#666}
.ex{border-radius:10px;background:#FFCCCC;padding:5px 10px;width:max-content}
.navbar{background-color:#E9E9E9;overflow:hidden;position:fixed;top:0;width:100%}
</style>
<?php
$styles=array('even','odd');$search='';
$getData=isset($_GET['product'])?trim((string)$_GET['product']):'';
$getStatus=isset($_GET['part_status'])?trim((string)$_GET['part_status']):'';
$getDataSql=($getData!==''&&$getData!=='All')?(int)$getData:0;
$getStatusSql=db_escape($connect,$getStatus);
if($getData!==''||$getStatus!==''){
 if($getData!==''&&$getStatus!==''){
  $sql="SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_categories]=".$getDataSql." AND part.[part_status_purchase]='".$getStatusSql."' ORDER BY part.[part_name] ASC";
 }elseif($getData!==''&&$getStatus===''){
  $sql="SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_categories]=".$getDataSql." ORDER BY part.[part_name] ASC";
 }elseif($getData===''&&$getStatus!==''){
  $sql="SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_status_purchase]='".$getStatusSql."' ORDER BY part.[part_name] ASC";
 }else{
  $sql="SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] ORDER BY part.[part_categories],part.[part_name] ASC";
 }
}else{
 $sql="SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] ORDER BY part.[part_categories],part.[part_name] ASC";
}
$array=array('All');$array2=array('All');
$exeSP=db_exec($connect,"SELECT * FROM categories_product")or die("Error categories_product");
while($resultSP=db_fetch_array($exeSP)){array_push($array,$resultSP['ct_name']);array_push($array2,$resultSP['ct_id']);}
$arrLength=count($array);$statusList=array('',$statusNotYet,$statusReached,$statusNormal);
?>
<center><body>
<div class="navbar"><?php require("title-h.php");?></div>
<div class="content">
<table id="table1" width="100%">
<tr class="spaceUnder05"><td colspan="7" align="center"><b class="ex"><?php echo htmlspecialchars($lblOverview,ENT_QUOTES,'UTF-8');?></b></td></tr>
<tr>
<td align="right"><b class="ex"><?php echo htmlspecialchars($lblCategory,ENT_QUOTES,'UTF-8');?> Product : </b></td>
<td><select id="product_name" name="product_name"><?php for($a=0;$a<$arrLength;$a++){ $sel=($getData==$array2[$a]||($getData===''&&$array2[$a]==='All'))?' selected="selected"':''; echo '<option value="'.htmlspecialchars((string)$array2[$a],ENT_QUOTES,'UTF-8').'"'.$sel.'>'.htmlspecialchars((string)$array[$a],ENT_QUOTES,'UTF-8').'</option>'; } ?></select></td>
<td align="right"><b class="ex"><?php echo htmlspecialchars($lblStatus,ENT_QUOTES,'UTF-8');?> : </b></td>
<td><select name="part_status" id="part_status"><?php foreach($statusList as $st){ $sel=($getStatus===$st)?' selected="selected"':''; $label=($st==='')?'-- All --':$st; echo '<option value="'.htmlspecialchars($st,ENT_QUOTES,'UTF-8').'"'.$sel.'>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</option>'; } ?></select>
<input type="button" value="OK" onclick="sort_product();"></td>
</tr>
<tr><td colspan="7"><hr></td></tr>
<tr><td colspan="7">
<table width="100%"><tr bgcolor="#E1E1E1">
<th><div align="center">No.</div></th>
<th><div align="center"><?php echo htmlspecialchars($lblCategory,ENT_QUOTES,'UTF-8');?> Product</div></th>
<th><div align="center"><?php echo htmlspecialchars($lblPartName,ENT_QUOTES,'UTF-8');?> Part</div></th>
<th><div align="center"><?php echo htmlspecialchars($lblMinStock,ENT_QUOTES,'UTF-8');?> Stock</div></th>
<th><div align="center"><?php echo htmlspecialchars($lblInStock,ENT_QUOTES,'UTF-8');?> Stock</div></th>
<th><div align="center"><?php echo htmlspecialchars($lblStatus,ENT_QUOTES,'UTF-8');?></div></th>
<th><div align="center">Edit</div></th>
</tr>
<?php if(isset($search)){$exec=db_exec($connect,$sql)or die("Error Query");$num=1;while($result=db_fetch_array($exec)){
 $rs=$result['part_amount']-$result['part_min_alert'];$ps=(string)$result['part_status_purchase'];
 if(($rs<=0&&$ps==='')||$ps===$statusNotYet){$sc='<span style="color:red">'.$result['part_amount'].'</span>';$ss='<span style="color:red">'.htmlspecialchars($ps,ENT_QUOTES,'UTF-8').'</span>';}
 elseif($rs<=0&&$ps!==''){$sc='<span style="color:red">'.$result['part_amount'].'</span>';$ss=htmlspecialchars($ps,ENT_QUOTES,'UTF-8').' ('.htmlspecialchars((string)$result['part_date_purchase'],ENT_QUOTES,'UTF-8').')';}
 else{$sc=$result['part_amount'];$ss=htmlspecialchars($ps,ENT_QUOTES,'UTF-8');}
 $pid=(int)$result['part_id']; ?>
<tr class="<?php echo $styles[$num%2];?>">
<td align="center"><?php echo $num;?></td>
<td align="center"><?php echo htmlspecialchars((string)$result['ct_name'],ENT_QUOTES,'UTF-8');?></td>
<td><a target="_blank" href="part_edit.php?part_id=<?php echo $pid;?>"><?php echo htmlspecialchars((string)$result['part_name'],ENT_QUOTES,'UTF-8');?></a></td>
<td align="center"><?php echo htmlspecialchars((string)$result['part_min_alert'],ENT_QUOTES,'UTF-8');?></td>
<td align="center"><?php echo $sc;?></td>
<td align="center"><?php echo $ss;?></td>
<td align="center"><a target="_blank" href="part_edit.php?part_id=<?php echo $pid;?>"><i class="fa fa-edit"></i></a></td>
</tr><?php $num++;}}else{?><tr><td colspan="7">Not Found</td></tr><?php }?>
</table></td></tr></table></div></body></center></html>
HTML;

$body = str_replace(array('<motion', '</motion>'), array('<div', '</div>'), $body);

$head = "<?php\nrequire_once __DIR__ . '/connect.php';\nrequire_once __DIR__ . '/function.php';\n"
    . "header('Content-Type: text/html; charset=UTF-8');\n"
    . "@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');\n"
    . "\$statusNotYet = '" . $q($statusNotYet) . "';\n"
    . "\$statusReached = '" . $q($statusReached) . "';\n"
    . "\$statusNormal = '" . $q($statusNormal) . "';\n"
    . "\$lblOverview = '" . $q($lblOverview) . "';\n"
    . "\$lblCategory = '" . $q($lblCategory) . "';\n"
    . "\$lblStatus = '" . $q($lblStatus) . "';\n"
    . "\$lblPartName = '" . $q($lblPartName) . "';\n"
    . "\$lblMinStock = '" . $q($lblMinStock) . "';\n"
    . "\$lblInStock = '" . $q($lblInStock) . "';\n"
    . "\$titlePage = '" . $q($titlePage) . "';\n"
    . "?>\n";

$content = $head . $body;
if (strlen($content) < 5000) {
    die("refuse: too short\n");
}
file_put_contents($out, $content);
echo "OK len=" . strlen($content) . " -> " . $out . "\n";
