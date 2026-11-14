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
<style>
.content {
  max-width: 1200px;
  margin-top:100px;
  font-size: 16px; 
  color:#666666;
}

.paddingResult {
  padding: 15px;
}

</style>
<body>
<?
$search = "";
if (isset($_GET['pre_date'])) {
  $search = $_GET['pre_date'];
} ?>
<table width="100%" border="1" id="table_result" name="table_result">
  <tr bgcolor="#E1E1E1">
    <th width="20"> <div align="center">No.</div></th>
	<th width="50"> <div align="center">วันที่เบิก</div></th>
    <th width="50"> <div align="center">เลขเอกสาร</div></th>
    <th width="20"> <div align="center">ประเภท</div></th>
    <th width="20"> <div align="center">ผู้ขอเบิก</div></th>
  </tr>
<?php

if (isset($search)) {
 /* echo '<script>alert(". $search . ")</script>';*/
  $sql = "SELECT  pre_id, pre_by, pre_type, ts_mtn_number ,pre_date
  
  FROM pre_stock  WHERE pre_status = 'pre'  
  
  GROUP BY pre_id, pre_by, pre_type, ts_mtn_number,pre_date ";
  
  
  //  28/12/2021 ตัด where วันที่ออก อยากให้แสดงรายการค้างทั้งหมด
  // pre_date  = '" . $search . "'  AND 
  
  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;
  while ($result = odbc_fetch_array($exec)){
    $id =  "./pickup_list_order_detail.php?pre_id=" .$result['pre_id']. "&pre_remarks=".$result['pre_remarks']."&ts_mtn_number=". $result['ts_mtn_number'];  //"&pre_type=".$result['pre_type'].
	
//	echo $result["pre_type"];
    ?>
  <tr class="paddingResult">
    <td><div align="center"><?=$num; ?></div></td>
	<td><div align="center"> <?=$result["pre_date"];?> </td>
    <td><div align="center"><u><a href=<?=$id;?> ><?=$result["pre_id"];?></a></u></td>
    <td><div align="center"> <?=$result["pre_type"];?> </td>
    <td><div align="center"> <?=$result["pre_by"];?> </td>
  </tr>

<?php 
$num++;
} ?>
<?php 
} else { ?>
  <option value="">Not Found</option>
<?php 
} ?>
</table>
</body>
</html>
