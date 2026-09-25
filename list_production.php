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
if (isset($_GET['product_name'])) {
  $search = $_GET['product_name'];
} ?>
<table width="100%" border="1" id="table_result" name="table_result">
  <tr>
    <th width="20"> <div align="center">No. </div></th>
    <th width="200"> <div align="left">Part name </div></th>
    <th width="200"> <div align="left">จำนวน Stock </div></th>
    <th width="200"> <div align="left">จำนวนขั้นต่ำที่ต้องมี </div></th>
  </tr>
<?php
if (isset($search)) {
/*
  $sqlPickup = "SELECT count(*) FROM log_stock ";  //Where log_product  like '%" . $result["part_name"] . "%' 
  $execPickup = db_exec($connect, $sqlPickup) or die("Error Query [" . $sqlPickup . "]");

  echo $execPickup;*/
  
  $sql = "SELECT * FROM part WHERE part_product LIKE '%" . $search . "%'  ";
  $exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;
  while ($result = db_fetch_array($exec)) {
    ?>
<tr>
    <td><div align="center"><?php echo $num; ?></div></td>
    <td><?= $result["part_name"]; ?></td>
    <td><?= $result["part_amount"]; ?></td>
    <td><?= $result["part_min_alert"]; ?></td>

  </tr>

<?php 
$num++;
  } ?>

<?php 
} else { ?>
  <option value="">Not Found</option>
<?php 
}


db_close($connect);?>

</body>
</html>

