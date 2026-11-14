<?
require("connect.php");
include("function.php");

header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
//$date = date("Y-m-d");
//echo $date . "<br>";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<meta http-equiv="Content-Type" content="text/html; charset=TIS-620" />

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link type="text/css" rel="stylesheet" href="css.css" />
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css"> 

<title>ภาพรวม Stock</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script type="text/javascript">
	
//Search   Enduser / 
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

//Search ????? ??.
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


//select product_name
$(document.getElementById("product_name")).ready(function(){
     $("#product_name").change(function(){ 
		 $.get("stock_overview_list.php",{ product_name:$(this).val() },
		 function(data) { $("#result").html(data);  });
      }); 
});

/*
function listSetup(){

    $dateNow = "all";
//     alert(data);
	 $.get("stock_overview_list.php",{ product:$dateNow },
	 function(data) { 
         $("#result").html(data); 
     });
}
*/

function sort_product(){

 	var product  = document.getElementById("product_name").value;
	var part_status  = document.getElementById("part_status").value;
	
		//alert("product = " + product + " part_status = " + part_status);
		
	if(product == "All" ){
		product = "";
	}
	
			
	if(part_status == "All" ){
		part_status = "";
	}
		
	if( product != "" && part_status != ""){
		//alert("if 1 =" + product + " part_status =" + part_status);

		window.location = "stock_overview.php?product="+product+"&part_status="+part_status;
		
	}else if( product != "" && part_status == ""){
		//alert("if 2 = " + product + " part_status =" + part_status);
		
		window.location = "stock_overview.php?product="+product;
		
	}else if( product == "" && part_status != ""){
		//alert("if 3 =" + product + " part_status =" + part_status);
		
 	window.location = "stock_overview.php?part_status="+part_status;
		
	}else{
		//alert("if 4 =" + product + " part_status =" + part_status);
		
		window.location = "stock_overview.php";
	}	
	
	
}
</script>
</head>
<style>

.even {
    background-color: #F0F0F0  !important;
}
.odd {
    background-color:  #FFFFFF !important;
}
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
  		border:  0px solid black;
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
 tr.paddingResult{
 		padding: 5px;
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
<?
$styles = array('even','odd');

$DateTime1 = '2020-02-26 18:25:45';
$DateTime2 = '03/26/20 18:30:00';
$search = '';
$getData = $_GET['product'];
$getStatus = $_GET['part_status'];

//echo "<br><br><br><br><br>ผลลัพท์ getData =". $_GET['product']."<br>";
//echo "ผลลัพท์ getStatus =". $_GET['part_status']."<br>";


if (isset($getData) || isset($getStatus)) {
  
  
   if( $getData != "" && $getStatus  != "" ){
   
    //echo "<script>alert('received!')";
   		$sql =  "SELECT * FROM part  INNER JOIN categories_product ON part.[part_categories] = categories_product.[ct_id] WHERE part.[part_categories]  =  ".$getData."  
		AND  part.[part_status_purchase] = '".$getStatus ."' ORDER BY part.[part_name]  ASC"; 
		
   }else if($getData != "" &&  $getStatus  == "" ){
    //echo "<script>alert('received!')";
   		$sql =  "SELECT * FROM part  INNER JOIN categories_product ON part.[part_categories] = categories_product.[ct_id] WHERE part.[part_categories]  =  ".$getData."  ORDER BY part.[part_name]  ASC"; 

   }else if($getData == "" &&  $getStatus  != "" ){
    //echo "<script>alert('received!')";
   		$sql =  "SELECT * FROM part  INNER JOIN categories_product ON part.[part_categories] = categories_product.[ct_id] WHERE part.[part_status_purchase]  =  '".$getStatus."'  ORDER BY part.[part_name]  ASC"; 

   }else{
   //echo "<script>alert('received!')";
   		$sql = "SELECT * FROM part  INNER JOIN categories_product ON part.[part_categories] = categories_product.[ct_id] ORDER BY part.[part_categories], part.[part_name]  ASC  "; //  ORDER BY part_product DESC,  part_name DESC ";
   }

   
}else{
//GROUP BY  part.[part_id], part.[part_product], part.[part_amount],part.[part_min_alert], part.[part_name],production_formula.[pd_id]
    //echo "All Start";
	$sql = "SELECT * FROM part  INNER JOIN categories_product ON part.[part_categories] = categories_product.[ct_id] ORDER BY part.[part_categories], part.[part_name] ASC " ; 
}


$array = array();
$array2 = array();
$sqlSP = "SELECT * FROM categories_product ";
array_push($array2, "All");
array_push($array, "All");
$exeSP = odbc_exec($connect, $sqlSP) or die("Error Query [" . $sqlSP . "]");
while ($resultSP = odbc_fetch_array($exeSP)) {
    array_push($array, $resultSP['ct_name']);
	array_push($array2, $resultSP['ct_id']);
	
}

$arrLength = count($array);

?>
<center>
<body >
<div class="navbar"><?php require("title-h.php"); ?></div>

<div class="content">
<!-- <form id="form_production" name="form_production" method="post" > -->

<table id="table1" width="100%" >

<tr class="spaceUnder05">
<td colspan="7" align="center" valign="center" ><b class="ex">สรุปภาพรวม</b></td>

<tr>
    <td width="200" align="right" valign="center"><b class="ex">หมวด Product : </b></td>
	<td width="100" ><p><select id="product_name" name="product_name" > <!--  onchange="javascript: sort_product(this.value);"  -->
<? if (isset($getData)) {  
	
	$ct_id;
	$ct_name;

	$sqlID = "SELECT * FROM categories_product  WHERE ct_id = ".$getData." ";

	$exeID = odbc_exec($connect, $sqlID) or die("Error Query [" . $sqlID . "]");
	while ($resultID = odbc_fetch_array($exeID)) {
		$ct_name = $resultID['ct_name'];
		$ct_id = $resultID['ct_id'];
	}

?>
<? } ?>
	<? for ($a = 0; $a < $arrLength; $a++) {  
	
			if($getData == $array2[$a] || ($getData == "" && $array2[$a] == "All") ){
		?>
		
			<option value="<?= $array2[$a]; ?>" selected="selected"><?= $array[$a]; ?></option>
	<?	}else{  ?>
			<option value="<?= $array2[$a]; ?>"><?= $array[$a]; ?></option>
					
	<?	} 
			
		 } ?>
	</select><p> </td>
	  	<td  align="right" valign="center" width="100"><b class="ex">สถานะ : </b></td><td>
	<select class="custom-select" name="part_status" id="part_status" required>
	<? if($getStatus == ""){?>
			<option value="" selected="selected" >-- All --</option>
			<option value="ยังไม่ได้สั่งซื้อ">ยังไม่ได้สั่งซื้อ</option>
			<option value="สั่งซื้อแล้ว">สั่งซื้อแล้ว</option>
			<option value="ปกติ">ปกติ</option>
	
	<? }else if($getStatus == "ยังไม่ได้สั่งซื้อ"){ ?>
			<option value="" >-- All --</option>
			<option value="ยังไม่ได้สั่งซื้อ" selected="selected">ยังไม่ได้สั่งซื้อ</option>
			<option value="สั่งซื้อแล้ว">สั่งซื้อแล้ว</option>
			<option value="ปกติ">ปกติ</option>
	
	<? }else if($getStatus == "สั่งซื้อแล้ว"){ ?>
			<option value="" >-- All --</option>
			<option value="ยังไม่ได้สั่งซื้อ">ยังไม่ได้สั่งซื้อ</option>
			<option value="สั่งซื้อแล้ว" selected="selected">สั่งซื้อแล้ว</option>
			<option value="ปกติ">ปกติ</option>
	
	<? }else if($getStatus == "ปกติ"){ ?>
			<option value="" >-- All --</option>
			<option value="ยังไม่ได้สั่งซื้อ">ยังไม่ได้สั่งซื้อ</option>
			<option value="สั่งซื้อแล้ว">สั่งซื้อแล้ว</option>
			<option value="ปกติ" selected="selected">ปกติ</option>
	
	<? } ?>
	
    </select> <input type="submit" value="OK" onclick="javascript:sort_product();">
	</td>

   </tr>
</tr>
<tr>
<td align="right" valign="right"> </td> 
</tr>

<tr>
    <td colspan="7"><hr /></td>
   </tr>
   <tr>
   <td colspan="7">
   <table width="100%" >
     <tr  bgcolor="#E1E1E1">
    <th width="20"> <div align="center">No. </div></th>
    <th width="50"> <div align="center">หมวด Product</div></th>
    <th width="150"> <div align="center">ชื่อ Part</div></th>

    <th width="60"> <div align="center">จำนวนขั้นต่ำใน Stock </div></th>
	<th width="50"> <div align="center">จำนวนใน Stock </div></th>
	<th width="100"> <div align="center">สถานะ</div></th>
    <th width="20"> <div align="center">Edit</div></th>
  </tr>
  <?php

if (isset($search)) {

echo $search;
//  $sql = "SELECT * FROM part ".$search;

 //$sql = "SELECT part.[part_id], part.[part_product], part.[part_amount],part.[part_min_alert], part.[part_name], production_formula.[pd_id] FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  ".$search ; 
 // $sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  ".$search ; 
  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;
  while ($result = odbc_fetch_array($exec)){
  /*  $id =  "./pickup_list_order_detail.php?pre_id=" .$result["pre_id"]. "&ts_mtn_number="
    . $result["ts_mtn_number"]."&pre_type=".$result["pre_type"]."&pre_remarks=".$result["pre_remarks"];*/
	
	$stockColor = "";
	$statusStock = "";
	$resultStock = $result["part_amount"] - $result["part_min_alert"];
	if($resultStock <= 0  &&  $result['part_status_purchase'] == "" || $result['part_status_purchase'] == "ยังไม่ได้สั่งซื้อ" ){
		$stockColor =  "<span style=color:red;text-align:center;>".$result["part_amount"]."</span>";
		$statusStock = "<span style=color:red;text-align:center;>".$result["part_status_purchase"]."</span>";
	}else if($resultStock <= 0  &&  $result['part_status_purchase'] != ""){
		$stockColor =  "<span style=color:red;text-align:center;>".$result["part_amount"]."</span>";
		$statusStock = $result['part_status_purchase']." (".$result['part_date_purchase'].")";
	}else{
		$stockColor =  "<span style=color:black;text-align:center;>".$result["part_amount"]."</span>";
		$statusStock = $result['part_status_purchase'];
	}
	

    ?>
  <tr  class="<?php echo $styles[$num % 2]; ?>">
    <td><div align="center"><?=$num; ?></div></td>
	<td><div align="center"><?=$result["ct_name"];?></div></td>
    <td><div align="left"><a target = '_blank' href="part_edit.php?part_id=<?= $result['part_id'];?>" ><?=$result["part_name"];?></a></td>

    <td><div align="center"> <?=$result["part_min_alert"] ?> </td>
	<td><div align="center"><?=$stockColor;?></td>
	<td><div align="center"><?=$statusStock;?></td>
    <td><div align="center"> <a target = '_blank' href="part_edit.php?part_id=<?= $result['part_id'];?>" ><i class="fas fa-edit" ></i></a></td>
  </tr>

	<?php 
	$num++;
	} ?>
<?php 
} else { ?>
  <option value="">Not Found</option>
<?php 
} ?>
</td>
  </table>
  <!---  <td colspan="7"><div  id="result" name="result">result</div></td> --->
   </tr>
  <tr >
    <td colspan="7"><hr /></td>
   </tr>

</table>

<br /><br />
<div align="center"></div>
<div align="center"></div>
<!-- </form> -->
</div>
</body>
</center>
</html>
