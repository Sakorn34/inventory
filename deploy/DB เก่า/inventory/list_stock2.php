<?
require "connect.php";

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

</head>

<body>
<?

$search = "";
if(isset($_GET['sn_sp1'])){
	$search = $_GET['sn_sp1'];

}else if(isset($_GET['sn_sp2'])){
	$search = $_GET['sn_sp2'];

}else if(isset($_GET['sn_sp3'])){
	$search = $_GET['sn_sp3'];

}else if(isset($_GET['sn_sp4'])){
	$search = $_GET['sn_sp4'];
	
}else if(isset($_GET['sn_sp5'])){
	$search = $_GET['sn_sp5'];
}


$sql = "SELECT * FROM part Where part_name LIKE '%".$search."'  ";
echo "<script>alert('$sql')</script>";
$exec = odbc_exec($connect,$sql) or die ("Error Query [".$sql."]");	
$result=odbc_fetch_array($exec);
?>
	<option value="<?=$result['part_amount']?>"><?=$result['part_amount']?></option>
	
<?php 

odbc_close($connect);
?>
</body>
</html>
