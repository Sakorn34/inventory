<?
	//session_start();
require "connect.php";
include("function.php");

header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');




?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

</head>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script>

function CheckTime(){
    //Get datetime from input
    datetime1 = '2020-02-26 18:25:45';
    datetime2 = '2020-02-26 18:35:45';
  
    //Set date time format
    var startDate = new Date(datetime1);
    var endDate   = new Date(datetime2);
    var seconds = (endDate.getTime() - startDate.getTime()) / 1000;
  
    //Calculate time
    var days = Math.floor(seconds / (3600*24));
    var hrs_d   = Math.floor((seconds - (days * (3600*24))) / 3600);
    var hrs   = Math.floor(seconds / 3600);
    var mnts = Math.floor((seconds - (hrs * 3600)) / 60);
    var secs = seconds - (hrs * 3600) - (mnts * 60);
  
    //Add 0 if one digit
    if(hrs_d<10) hrs_d = "0" + hrs_d;
    if(mnts<10) mnts = "0" + mnts;
    if(secs<10) secs = "0" + secs;

  //  alert(days);

    //document.getElementById('result').value = days + ' ' + hrs_d + ':' + mnts + ':' + secs;
}


</script>
<style type="text/css">
.even {
    background-color: #F0F0F0  !important;
}
.odd {
    background-color:  #FFFFFF !important;
}
.buttonBack {
  background-color: #f44336; /* Back */
  border: none;
  color: white;
  padding: 15px 32px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 16px;
}
.buttonSave {
  background-color: #4CAF50; /* Green */
  border: none;
  color: white;
  padding: 15px 32px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 16px;
}
</style>

<?
$styles = array('even','odd');

$DateTime1 = '2020-02-26 18:25:45';
$DateTime2 = '03/26/20 18:30:00';
$getData = '';
$search = '';

if (isset($_GET['product_name'])) {
  $getData = $_GET['product_name'];
   echo "ผลลัพท์ =". $getData;
   
   if($getData == "all" || $getData == "" ){
   		$search = " GROUP BY  part.[part_id], part.[part_product], part.[part_amount],part.[part_min_alert], part.[part_name],production_formula.[pd_id] "; //  ORDER BY part_product DESC,  part_name DESC ";
   }else{
   		$search =  " WHERE production_formula.[pd_id]  =  '$getData'  GROUP BY  part.[part_id], part.[part_product], part.[part_amount],part.[part_min_alert], part.[part_name], production_formula.[pd_id]"; // ORDER BY part_product DESC ,  part_name DESC 
   }
   
}else{

	$search = " GROUP BY  part.[part_id], part.[part_product], part.[part_amount],part.[part_min_alert], part.[part_name],production_formula.[pd_id] ";
}
?>

<center>
<body onLoad="CheckTime()">

<table width="100%" border="1" id="table_result" name="table_result">
  <tr  bgcolor="#E1E1E1">
    <th width="20"> <div align="center">No. </div></th>
    <th width="100"> <div align="center">Product </div></th>
    <th width="100"> <div align="center">ชื่อ Part</div></th>

    <th width="100"> <div align="center">จำนวนขั้นต่ำใน Stock </div></th>
	<th width="50"> <div align="center">จำนวนใน Stock </div></th>
	<th width="50"> <div align="center">สถานะ</div></th>
    <th width="50"> <div align="center">Edit</div></th>
  </tr>
<?php

if (isset($search)) {

echo $search;
//  $sql = "SELECT * FROM part ".$search;

 $sql = "SELECT part.[part_id], part.[part_product], part.[part_amount],part.[part_min_alert], part.[part_name], production_formula.[pd_id] FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  ".$search ; 
  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;
  while ($result = odbc_fetch_array($exec)){
    $id =  "./pickup_list_order_detail.php?pre_id=" .$result["pre_id"]. "&ts_mtn_number="
    . $result["ts_mtn_number"]."&pre_type=".$result["pre_type"]."&pre_remarks=".$result["pre_remarks"];
	
	$stockColor = "";
	$resultStock = $result["part_amount"] - $result["part_min_alert"];
	if($resultStock <= 0){
		$stockColor =  "<span style=color:red;text-align:center;>".$result["part_amount"]."</span>";
	
	}else{
		$stockColor =  "<span style=color:black;text-align:center;>".$result["part_amount"]."</span>";
	}
	

    ?>
  <tr  class="<?php echo $styles[$num % 2]; ?>">
    <td><div align="center"><?=$num; ?></div></td>
    <td><div align="center"> <?=$result["part_product"] ?> </td>
    <td><div align="center"><a href=<?=$id?> ><?=$result["part_name"];?></a></td>

    <td><div align="center"> <?=$result["part_min_alert"] ?> </td>
	<td><div align="center"><?=$stockColor;?></td>
	<td><div align="center"><?=$result["part_status_purchase"] ;?></td>
    <td><div align="center"> <a target = '_blank' href="part_edit.php?setup_id=<?= $result['setup_id'];?>" ><i class="fas fa-edit" ></i></a></td>
  </tr>

	<?php 
	$num++;
	} ?>
<?php 
} else { ?>
  <option value="">Not Found</option>
<?php 
} ?>

</body>
</center>
</html>
