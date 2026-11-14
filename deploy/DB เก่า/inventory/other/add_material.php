<?
require "connect.php";
//header("Content-type : text/html; charset=utf-8");
setlocale(LC_TIME, 'tha');

$start = strtotime('00:00'); 
$end = strtotime('23:59'); 
$yymmdd = date("Y/m/d");
$hhmm = date('H:m');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="content-Type" content="text/html; charset=TIS-620">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link type="text/css" rel="stylesheet" href="css.css" />
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">  
<title>งานซ่อม</title>

<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
<script type="text/javascript">

function save_job(){
   document.getElementById("form1").submit();
			var lp_date= document.getElementById("lp_date").value;
			var lp_product = document.getElementById("lp_product").value;
			var lp_type = document.getElementById("lp_type").value;
			var lp_amount = document.getElementById("lp_amount").value;
			var lp_user = document.getElementById("lp_user").value;
		
			var data_update = "lp_date="+lp_date
											+"&lp_product="+lp_product
											+"&lp_type="+lp_type
											+"&lp_amount="+lp_amount
											+"&lp_user="+lp_user+" ";

			window.location="add_part.php?"+data_update;
}
</script>
</head>

<body>
<?php
	date ('Y/m/d', strtotime($time));
	date ('Y/m/d' , mktime($int-time));
	date ('H:i:s',strtotime($time));
	$time1= date("H:i:s");
	$year = date("Y");//+543;
	$date1 = date("Y/m/d" , strtotime("-4 week"));
	$date2 = date("Y/m/d");
	

?>
<div class="navbar"><?php require("title-h.php");?></div>

<div class="content">
<?

	if($connect)
	{
		echo "Database Connected.";
	}
	else
	{
		echo "Database Connect Failed.";
	}

	
?>
<form id="form1" name="form1" method="post" action="" enctype="multipart/form-data"  onsubmit='javascript:return save_job();'>
<table id="table1" width="90%" align="center" >
  <tr>
    <td style="font-size:20px; text-align:right;"><b>วันนำเข้า : </b></td><td><input type="date" name="lp_date" id="lp_date" value="<?php echo date("Y-m-d"); ?>" style="width:auto;" /></td>
    <tr><td style="font-size:20px; text-align:right;"><b>Part :</b></td><td>
	
	<?php
		$sql_a =  "SELECT *  FROM part_raw order by part_id ASC";
		$exea = odbc_exec($connect,$sql_a) or die ("Error Query [".$sql_a."]");			
	?>
	<select id="lp_product" name="lp_product" style="width:auto;">
	<option value="">Select part</option>
	<?php while($result_a = odbc_fetch_array($exea)){ ?>
	
	<option value="<?=$result_a['part_name'];?>"><?=$result_a['part_name'];?></option><? } ?>
	<? odbc_close($connect);?>
	</select>

	<!--<input type="text"  name="lp_product" id="lp_product" style="width:auto;" />  -->  </td></tr>
	
	
    <tr><td style="font-size:20px; text-align:right;"><b> &nbsp; &nbsp; type :</b></td><td>
	   <select class="custom-select" name="lp_type" id="lp_type">
	  	<option value="เบิกผลิต">เบิกผลิต</option>
		<option value="เบิกซ่อม">เบิกซ่อม</option>
        <option value="นำเข้า">นำเข้า</option>
       </select>	 </td></tr>
    <tr><td style="font-size:20px; text-align:right;"><b> &nbsp; &nbsp; จำนวน :</b></td><td><input type="text"  name="lp_amount" id="lp_amount" style="width:auto;" /></td></tr>
	<tr><td style="font-size:20px; text-align:right;"><b>ผู้บันทึก : </b> </td><td>
      <select class="custom-select" name="lp_user" id="lp_user">
	  	<option value="none">ผู้บันทึก..</option>
        <option value="NOI">NOI</option>
        <option value="NI">NI</option>
        <option value="PLAM">PLAM</option>
        <option value="AO">AO</option>
        <option value="MAX">Max</option>
        <option value="DAY">DAY</option>
        <option value="NUT">NUT</option>
        <option value="BOY">BOY</option>
        <option value="NA-MO">NA-MO</option>
       </select>	   
	   </td></tr>
    	<tr><td ></td><td style="font-size:24px; text-align:left; color:#009900"><p><a href="javascript: save_job();"><i class="fa fa-save"> Save</i></a></p></td></tr>
  </tr>
</table>
<br /><br />
</form>


</div>
</body>
</html>