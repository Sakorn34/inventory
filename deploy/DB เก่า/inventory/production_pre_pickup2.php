<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');

	$strKeyword = null;

	if(isset($_POST["pre_product"]))
	{
    $strKeyword = $_POST["pre_product"];
    $strAmount = $_POST["pre_amount"];
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=TIS-620" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link type="text/css" rel="stylesheet" href="css.css" />
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css"> 

<title>Inventory</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
<script type="text/javascript">

function save_job(){
		
	if(document.getElementById("pre_product").value == "" 
	|| document.getElementById("pre_amount").value == ""
	|| document.getElementById("pre_by").value == "")
	{
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			return;
	}

	var data_update = "pre_date="+document.getElementById("pre_date").value
	    +"&pre_product="+document.getElementById("pre_product").value
		+"&pre_type=เบิกผลิต"
		+"&pre_amount="+document.getElementById("pre_amount").value
		+"&pre_by="+document.getElementById("pre_by").value
		+"&pre_remarks=";
	
//	alert(data_update);
	window.location="pre_pickup_save.php?"+data_update;
	}
	
//Search บริษัท  Enduser / ผู้ว่าจ้าง
$(document.getElementById("user_company")).ready(function(){
    $('.search-box-user input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result");
        if(inputVal.length){
            $.get("search_cusname.php", {term: inputVal}).done(function(data){
                resultDropdown.html(data);  // Display the returned data in browser
            });
        } else{  resultDropdown.empty(); }
	});
	
    // Set search input value on click of result item
    $(document).on("click", ".result p", function(){
        $(this).parents(".search-box-user").find('input[type="text"]').val($(this).text());
        $(this).parent(".result").empty();
    });
});

//Search บริษัท รปภ.
$(document.getElementById("security_company")).ready(function(){

    $('.search-box-sec input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result_sec");
        if(inputVal.length){
            $.get("search_cusname.php", {term: inputVal}).done(function(data){
                resultDropdown.html(data);  // Display the returned data in browser
            });
        } else{  resultDropdown.empty(); }
    });
    // Set search input value on click of result item
    $(document).on("click", ".result_sec p", function(){
        $(this).parents(".search-box-sec").find('input[type="text"]').val($(this).text());
        $(this).parent(".result_sec").empty();
    });
});


//-------------------------------------------- select product_name -------------------------------------
/*$(document.getElementById("pre_product")).ready(function(){
     $("#pre_product").change(function(){ 
		 $.get("list_production.php",{ product_name:$(this).val() },
		 function(data) { $("#result").html(data);  });
      }); 
});
*/
//-------------------------------------------- chkStock() -----------------------------------------------------

function chkStock() {
		var pick = parseInt(document.getElementById("amount_sp1").value);
		var stock =  parseInt(document.getElementById("stock_sp1").value);

		if(pick > stock && stock != null){
		  alert('ใส่ยอดเบิกเกินจำนวน stock ที่มี ');
		   document.getElementById('amount_sp1').value = '';
		}

}

//-------------------------------------------------------------------------------------------------


</script>
</head>
<style>
.content {
  max-width: 1200px;
  margin-top:100px;
  font-size: 16px; 
  color:#666666;
}

input[type="text"] {
  font-size: 16px;
  padding: 1px;
  border: 1px solid #C0C0C0;
  color:#FF9900;
}

textarea{
  font-size: 16px;
  padding: 1px;
  border: 1px solid #C0C0C0;
}

select {
  font-size: 16px;
  padding: 1px;
  border: 1px solid #C0C0C0;
}
table, td, th {
  		border: 0px solid black;
 }
 tr.spaceUnder>td {
		padding-bottom: 1em;
 }
 tr.spaceUnder05>td {
		padding-bottom: 0.5em;
 }
 tr.spaceTop>td {
		padding-top: 1em;
 }

.ex {
  border-radius: 10px;
  background:#FFCCCC;
  padding: 5px 10px 5px 10px;
   width:max-content;
  /*border: 3px solid #BADA55; */
}

.ex2 {
  border-radius: 10px;
  background:#CCFFFF;
  padding: 5px 10px 5px 10px;
  width:max-content;
  /*border: 3px solid #BADA55; */
}

 /* The navigation bar */
.navbar {
  background-color:#E9E9E9;
  overflow: hidden;
  position: fixed; /* Set the navbar to fixed position */
  top: 0; /* Position the navbar at the top of the page */
  width: 100%; /* Full width */
}

</style>

<body>
<div class="navbar"><?php require("title-h.php"); ?></div>

<div class="content">
<form id="form_production" name="form_production" method="post" action="<?php echo $_SERVER['SCRIPT_NAME'];?>">
<table id="table1" width="100%" >

<tr class="spaceUnder05">
<td colspan="4" align="center" valign="center" ><b class="ex">เบิกของผลิต</b></td>

<tr>
    <td  align="right" valign="center"><b >วันเบิก : </b></td>
	<td><input type="date" name="pre_date" id="pre_date" value="<?php echo date("Y-m-d"); ?>" style="width:auto;" /></td>
	<td  align="right" valign="center"><b >ผู้เบิก : </b></td><td>
	<select class="custom-select" name="pre_by" id="pre_by" required>
		<option value="">-- Select --</option>
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
	</td>
   </tr>
</tr>

<?php
$array = array();
$sqlSP = "SELECT * FROM product";
$exeSP = odbc_exec($connect, $sqlSP) or die("Error Query [" . $sqlSP . "]");
while ($resultSP = odbc_fetch_array($exeSP)) {
	array_push($array, $resultSP['pd_name']);
}
$arrLength = count($array);

?>

<tr>
<td align="right" valign="center"> Product : </td> <td > <p><select id="pre_product" name="pre_product" >

       <?
       if(!empty($_POST["pre_product"])){ ?>
          <option value="<?=$resultSP['pd_name']?>"><?=$resultSP['pd_name']?></option>
        <?
       }else{
?>
          <option value="">-- Select --</option>
          <?
       }
?>
        <? for ($a = 0; $a <= $arrLength; $a++) { ?>
							<option value="<?= $array[$a]; ?>"><?= $array[$a]; ?></option>
				<? 	} ?>
        </select></p>

<td align="right" valign="center">
<p ><b>จำนวน : </b></p>
</td>
<td >
<input type="text" id="pre_amount" name="pre_amount" style="width: 7em"  value="<?php echo $strAmount;?>"> 
<input type="submit" value="Check"></td>
</tr>

<tr>
    <td colspan="4"><hr /></td>
   </tr>

  <tr>
    <th width="20"> <div align="center">No. </div></th>
    <th width="200"> <div align="left">Part name </div></th>
    <th width="200"> <div align="left">จำนวน Stock </div></th>
    <th width="200"> <div align="left">จำนวนขั้นต่ำที่ต้องมี </div></th>
  </tr>

<?

if (!empty($strKeyword)) {
	  
	  $sql = "SELECT * FROM part WHERE part_product LIKE '%" . $strKeyword . "%'  ";
	  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
	  $num = 1;
	  while ($result = odbc_fetch_array($exec)) {
		?>
			
			<tr>
			<td><div align="center"><?php echo $num; ?></div></td>
			<td><?= $result["part_name"]; ?></td>
			<td><?= $result["part_amount"]; ?></td>
			<td><?= $result["part_min_alert"]; ?></td>
		
		  </tr>
		
		<?php 
		$num++;
    } 
}?>
  <tr >
    <td colspan="4"><hr /></td>
   </tr>
  <tr>
  <p><input type="hidden" id="pre_type" name="pre_type"></p>
  
    <td colspan="4" align="center" valign="center" ><input type="button" value="Save" onclick="javascript:save_job();"></td>
   </tr>

</table>



<br /><br />
<div align="center"></div>
<div align="center"></div>
</form>
</div>
</body>
</html>
