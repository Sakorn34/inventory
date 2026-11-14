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
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
</head>

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

    alert(days);

    //document.getElementById('result').value = days + ' ' + hrs_d + ':' + mnts + ':' + secs;
}

</script>


<body onLoad="CheckTime()">
<?

$DateTime1 = '2020-02-26 18:25:45';
$DateTime2 = '03/26/20 18:30:00';


if (isset($_GET['pre_date'])) {
  $search = $_GET['pre_date'];
} ?>

<table width="100%" border="1" id="table_result" name="table_result">
  <tr>
    <th width="20"> <div align="center">No. </div></th>
    <th width="100"> <div align="center">part_product </div></th>
    <th width="100"> <div align="center">part_name </div></th>
    <th width="100"> <div align="center">part_amount </div></th>
    <th width="50"> <div align="center">part_min_alert </div></th>
    <th width="50"> <div align="center">info </div></th>
  </tr>
<?php

if (isset($search)) {
 // echo '<script>alert(". $search . ")</script>';
  $sql = "SELECT * FROM part ";
  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;
  while ($result = odbc_fetch_array($exec)){
    $id =  "./pickup_list_order_detail.php?pre_id=" .$result["pre_id"]. "&ts_mtn_number="
    . $result["ts_mtn_number"]."&pre_type=".$result["pre_type"]."&pre_remarks=".$result["pre_remarks"];
    ?>
  <tr>
    <td><div align="center"><?=$num; ?></div></td>
    <td><div align="center"> <?=$result["part_product"] ?> </td>
    <td><div align="center"><a href=<?=$id?> ><?=$result["part_name"];?></a></td>
    <td><div align="center"><a href=<?=$id?> ><?=$result["part_amount"];?></a></td>
    <td><div align="center"> <?=$result["part_min_alert"] ?> </td>
    <td><div align="center"> <a target = '_blank' href="setup_detail.php?setup_id=<?= $result['setup_id'];?>" ><i class="fa fa-info-circle" aria-hidden="true"> info</i></a></td>
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
</html>
