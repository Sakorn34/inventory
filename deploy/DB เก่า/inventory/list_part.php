<?
require "connect.php";
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

</head>
<body>
<?


$search = "";
if(isset($_GET['pd_sp1'])){
	$search = $_GET['pd_sp1'];
}else if(isset($_GET['pd_sp2'])){
	$search = $_GET['pd_sp2'];
}else if(isset($_GET['pd_sp3'])){
	$search = $_GET['pd_sp3'];
}else if(isset($_GET['pd_sp4'])){
	$search = $_GET['pd_sp4'];
}else if(isset($_GET['pd_sp5'])){
	$search = $_GET['pd_sp5'];
}


?>

<?php if(!empty($search)){?>
  <option value="">Select Model</option>
 <?php
//	$sql =  "SELECT * FROM part Where part_product like '%".$search."%'  ";
	$sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $search . "  ORDER BY part.[part_name] ASC";
	$exec = odbc_exec($connect,$sql) or die ("Error Query [".$sql."]");	

	while($result=odbc_fetch_array($exec)){
		?>
		<option value="<?=$result['part_id']?>"><?=$result['part_name']?></option>
		<?php } ?>
		<?php }else{ ?>
		<option value="">Not Found</option>
<?php } 

odbc_close($connect);?>


</body>
</html>
