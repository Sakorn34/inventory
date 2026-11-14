<?
	//session_start();
	require "connect.php";
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
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

echo $search;
?>

<?php if(isset($search) ){?>
  <option value="">Select Model</option>
 <?php
$sql =  "SELECT * FROM part_list Where part_product like '%".$search."%'  ";
$exec = odbc_exec($connect,$sql) or die ("Error Query [".$sql."]");	
				
while($result=odbc_fetch_array($exec)){
?>
  <option value="<?=$result['part_name']?>"><?=$result['part_name']?></option>
<?php } ?>
<?php }else{ ?>
  <option value="">Not Found</option>
<?php } ?>

</body>
</html>
