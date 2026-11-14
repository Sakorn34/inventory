<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=TIS-620" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link type="text/css" rel="stylesheet" href="css.css" />
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">  
<title>แก้ไขข้อมูล Part</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

<script type="text/javascript">

function save_job(){
//	document.getElementById("form_part").submit();
	
		if(document.getElementById("part_name").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_name").focus();
			return;
		}
		
		if(document.getElementById("part_amount").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_amount").focus();
			return;
		}

		if(document.getElementById("part_min_alert").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_min_alert").focus();
			return;
		}

		if(document.getElementById("part_price").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_price").focus();
			return;
		}

		if(document.getElementById("part_by").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_by").focus();
			return;
		}

			var part_name= document.getElementById("part_name").value; 	
			var part_leadtime= document.getElementById("part_leadtime").value; 
			var part_product = document.getElementById("part_product").value;
			var part_amount = document.getElementById("part_amount").value;
			var part_link = document.getElementById("part_link").value;
			var part_image = document.getElementById("part_image").value;
			var part_min_alert = document.getElementById("part_min_alert").value;
			var part_vendor = document.getElementById("part_vendor").value;
			var part_price = document.getElementById("part_price").value;
			var part_by = document.getElementById("part_by").value;
			var part_categories = document.getElementById("part_categories").value;
			var part_id = document.getElementById("part_id").value;
			
			var part_status_purchase = document.getElementById("part_status_purchase").value;
			var part_shipment = document.getElementById("part_shipment").value;
			var part_date_purchase = document.getElementById("part_date_purchase").value;
			var part_stock_no = document.getElementById("part_stock_no").value;
			var part_puchase_quantity = document.getElementById("part_puchase_quantity").value;
			var part_transport_company = document.getElementById("part_transport_company").value;
			var part_period_use = document.getElementById("part_period_use").value;
			var part_check_draft = document.getElementById("part_check_draft").value;
			var part_remarks = document.getElementById("part_remarks").value;
		//	alert("กรุณากรอก part_edit " + part_remarks);
			
			var data_update = "part_name="+part_name
											+"&part_leadtime="+part_leadtime
											+"&part_product="+part_product
											+"&part_amount="+part_amount
											+"&part_link="+part_link
											+"&part_image="+part_image
											+"&part_min_alert="+part_min_alert
											+"&part_vendor="+part_vendor
											+"&part_price="+part_price
											+"&part_by="+part_by
											+"&part_categories="+part_categories
											+"&part_id="+part_id
											+"&part_shipment="+part_shipment
											+"&part_status_purchase="+part_status_purchase
											+"&part_date_purchase="+part_date_purchase
											+"&part_stock_no="+part_stock_no
											+"&part_puchase_quantity="+part_puchase_quantity
											+"&part_transport_company="+part_transport_company
											+"&part_period_use="+part_period_use
											+"&part_check_draft="+part_check_draft
											+"&part_remarks="+part_remarks
											+"&status=update"
											+" ";
			//alert("Save : " +data_update);
			window.location="part_save.php?" +data_update;
					
	}

//Search ?????  Enduser / ????????
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

//select s/n spare 1
$(document.getElementById("pd_sp1")).ready(function(){
     $("select#pd_sp1").change(function(){ $.get("list_sn_spare.php",{ pd_sp1:$(this).val() },
		 function(data) { $("select#sn_sp1").html(data);  });
      }); 
});
//select s/n spare 2
$(document.getElementById("pd_sp2")).ready(function(){
     $("select#pd_sp2").change(function(){ $.get("list_sn_spare.php",{ pd_sp2:$(this).val() },
		 function(data) { $("select#sn_sp2").html(data);  });
      }); 
});
//select s/n spare 3
$(document.getElementById("pd_sp3")).ready(function(){
     $("select#pd_sp3").change(function(){ $.get("list_sn_spare.php",{ pd_sp3:$(this).val() },
		 function(data) { $("select#sn_sp3").html(data);  });
      }); 
});
//select s/n spare 4
$(document.getElementById("pd_sp4")).ready(function(){
     $("select#pd_sp4").change(function(){ $.get("list_sn_spare.php",{ pd_sp4:$(this).val() },
		 function(data) { $("select#sn_sp4").html(data);  });
      }); 
});
//select s/n spare 5
$(document.getElementById("pd_sp5")).ready(function(){
     $("select#pd_sp5").change(function(){ $.get("list_sn_spare.php",{ pd_sp5:$(this).val() },
		 function(data) { $("select#sn_sp5").html(data);  });
      }); 
});

// Add Product Maintenance
$(document).ready(function() {
    var max_fields = 10;
    var wrapper = $(".container_maintenance");
    var add_button = $(".add_form_field");

    var x = 1;
    $(add_button).click(function(e) {
        e.preventDefault();
        if (x < max_fields) {
            x++;
            $(wrapper).append('<div><input type="text" name="mytext[]"/><a href="#" class="delete">Delete</a></div>'); //add input box
        } else {
            alert('You Reached the limits')
        }
    });

    $(wrapper).on("click", ".delete", function(e) {
        e.preventDefault();
        $(this).parent('div').remove();
        x--;
    })
}); 

var r_spare = 0;
//Add ??????????????
function addSpare(mode) {


			if(r_spare >= 5 && mode ==  "add"){
				alert("เพิ่มได้สูงสุด 5 รายการ");
				return;
			}
			
			if(mode == "add"){

				if(r_spare >= 1 && (document.getElementById("pd_sp"+r_spare).value == "" || document.getElementById("sn_sp"+r_spare).value == "")){
					alert("กรุณากรอกข้อมูลก่อนหน้าให้ครบถ้วน");
					return;
				}
				r_spare += 1;

				if(r_spare >= 5){
					r_spare = 5;
				}
				//number_sp1
				document.getElementById("number_sp"+r_spare).style.display = '';
				//pd_sp1
				document.getElementById("pd_sp"+r_spare).style.display = '';
				document.getElementById("pd_sp"+r_spare).value = "";
				//sn_sp1
				document.getElementById("sn_sp"+r_spare).style.display = '';
				document.getElementById("sn_sp"+r_spare).value = ""; 
			
			}else{
				if(r_spare <= 0){
					r_spare = 0;
					return;
				}
				//number_sp1
				document.getElementById("number_sp"+r_spare).style.display = 'none';
				//pd_sp1
				document.getElementById("pd_sp"+r_spare).style.display = 'none';
				document.getElementById("pd_sp"+r_spare).value = "";
				//sn_sp1
				document.getElementById("sn_sp"+r_spare).style.display = 'none';
				document.getElementById("sn_sp"+r_spare).value = 0;
				if(r_spare >= 1){
					r_spare -= 1;
				}
			}	
}

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
<center>
<body>
<div class="navbar"><?php require("title-h.php"); ?></div>
<div class="content">
<!-- Code PHP  -->
	
<?php
$part_id = $_GET['part_id'];


$sqlPart = "SELECT * FROM part INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id] WHERE  part.[part_id] = ".$part_id;
$exePart = odbc_exec($connect, $sqlPart) or die("Error Query [" . $sqlPart . "]");
$resultPart = odbc_fetch_array($exePart);

$sqlCT = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories] = categories_product.[ct_id] WHERE  part.[part_id] = ".$part_id;
$exeCT = odbc_exec($connect, $sqlCT) or die("Error Query [" . $sqlCT . "]");
$resultCT = odbc_fetch_array($exeCT);

$array = array();
$sqlSP = "SELECT * FROM product";
$exeSP = odbc_exec($connect, $sqlSP) or die("Error Query [" . $sqlSP . "]");
while ($resultSP = odbc_fetch_array($exeSP)) {
    array_push($array, $resultSP['pd_name']);
}
$arrLength = count($array);
	//		odbc_close($connect);

$arrayVD = array();
$sqlVD = "SELECT * FROM tbl_vendor ORDER BY vname ASC";
$exeVD = odbc_exec($connect, $sqlVD) or die("Error Query [" . $sqlVD . "]");
while ($resultVD = odbc_fetch_array($exeVD)) {
    array_push($arrayVD, $resultVD['vname']);
}
$arrLengthVD = count($arrayVD);
//echo $arrayVD;
?>

<form id="form_part" name="form_part" method="get" action="">
<table id="table1" width="100%" >
   <tr class="spaceUnder05">
      	<td colspan="4" align="center" valign="center" ><b class="ex">เพิ่มรายชื่อ part</b></td>
   </tr>
  <tr><td><br></td></tr>
  <tr> 
  <td align="right" valign="center"> ชื่อ Part : </td> <td > <input type="text" name="part_name" id="part_name" value="<?=$resultPart['part_name']; ?>"> </td> 
      <td align="right" valign="center"> Stock No. : </td>
   <td ><input type="text" name="part_stock_no" id="part_stock_no" value="<?=$resultPart['part_stock_no']; ?>">   </td>

  </tr>
  <tr> <td align="right" valign="center"> Product : </td> <td > <p> <input type="text" name="part_product" id="part_product" value="<?=$resultPart['pd_name']; ?>" ></p>
				
<? 
$arrayCt = array();
$arrayCt_id = array();
$sqlCt = "SELECT * FROM categories_product";
$exeCt = odbc_exec($connect, $sqlCt) or die("Error Query [" . $sqlCt . "]");
while ($resultCt = odbc_fetch_array($exeCt)) {
    array_push($arrayCt, $resultCt['ct_name']);
	array_push($arrayCt_id, $resultCt['ct_id']);
}
$arrLengthCt = count($arrayCt);
				
?>
  </td> 
  <td align="right" valign="center"> Categories : </td> <td > <p><input type="text" name="part_categories" id="part_categories" value="<?=$resultCT['ct_name']; ?>" ></p>
  </td> 
  </tr>
  <tr> <td align="right" valign="center"> จำนวนใน Stock : </td> 
    <td > <input type="number" name="part_amount" id="part_amount"  value="<?=$resultPart['part_amount']; ?>" > </td> 
  <td align="right" valign="center"> รูปภาพ : </td> <td > <input type="text" name="part_image" id="part_image" value="<?=$resultPart['part_image']; ?>" > </td></tr>
    <tr>
    <td colspan="4" ><hr /></td>
   </tr>
  <tr> <td align="right" valign="center"> แจ้งเตือนเมื่อต่ำกว่า : </td> 
  <td > <input type="number" name="part_min_alert" id="part_min_alert"  value="<?=$resultPart['part_min_alert']; ?>" > </td>
  <td align="right" valign="center"> ชื่อ Vendor : </td> <td > <p><select id="part_vendor" name="part_vendor"  >
	 			<option value="<?=$resultPart['part_vendor']; ?>" ><?=$resultPart['part_vendor']; ?></option>
				<option value="" >-- Select --</option>
					  <? for ($a = 0; $a <= $arrLengthVD; $a++) { ?>
							<option value="<?= $arrayVD[$a]; ?>"><?= $arrayVD[$a]; ?></option>
					 <?
  				  } ?>
	 			</select></p>
  </td> 
  <tr>
   <tr> <td align="right" valign="center"> จำนวนการสั่ง/ครั้ง : </td> 
  <td > <input type="number" name="part_puchase_quantity" id="part_puchase_quantity"  value="<?=$resultPart['part_puchase_quantity']; ?>" > </td>
  <td align="right" valign="center"> Link สั่งซื้อ : </td> <td > <input type="text" name="part_link" id="part_link"  value="<?=$resultPart['part_link']; ?>"> </td> </tr>
  
  </tr>
  <tr>
    <td align="right" valign="center"> ราคา : </td> <td > <input type="text" name="part_price" id="part_price"  value="<?=$resultPart['part_price']; ?>"> </td> 
	  <td align="right" valign="center"> การจัดส่ง : </td> <td > <select class="custom-select" name="part_shipment" id="part_shipment" required>
		<option value="<?=$resultPart['part_shipment']; ?>"><?=$resultPart['part_shipment']; ?></option>
		<option value="ในประเทศ">ในประเทศ</option>
		<option value="ต่างประเทศ">ต่างประเทศ</option>
    </select></td> 
  </tr>
  <tr>
      <td align="right" valign="center"> ระยะเวลารอสินค้า (วัน) : </td> <td > <input type="number" name="part_leadtime" id="part_leadtime" value="<?=$resultPart['part_leadtime']; ?>"> </td>
	  
	      <td align="right" valign="center"> บริษัทขนส่ง : </td> <td > <input type="text" name="part_transport_company" id="part_transport_company" value="<?=$resultPart['part_transport_company']; ?>"> </td>
  </tr>
  
  <tr>
      <td align="right" valign="center"> ระยะเวลาใช้งาน (วัน) : </td> <td > <input type="number" name="part_period_use" id="part_period_use" value="<?=$resultPart['part_period_use']; ?>"> </td> 

        <td align="right" valign="center"> ส่งดราฟให้ตรวจก่อน : </td> <td > <input type="checkbox" name="part_check_draft" id="part_check_draft" value="Yes"  <?php if( !empty($resultPart['part_check_draft'])) { ?> checked="checked" <?php } ?> ></td>  
  </tr>
  <tr>
          <td align="right" valign="center"> หมายเหตุ : </td> <td > <input type="text" name="part_remarks" id="part_remarks" value="<?=$resultPart['part_remarks']; ?>"> </td>  
    <td align="right" valign="center"> สถานะสั่งซื้อ : </td> <td > 
  <select class="custom-select" name="part_status_purchase" id="part_status_purchase" >
		<option value="<?=$resultPart['part_status_purchase']; ?>"><?=$resultPart['part_status_purchase']; ?></option>
		<option value="ยังไม่ได้สั่งซื้อ">ยังไม่ได้สั่งซื้อ</option>
		<option value="สั่งซื้อแล้ว">สั่งซื้อแล้ว</option>
		<option value="ยกเลิกใช้">ยกเลิกใช้</option>
    </select></td> 
  </tr>
  
  <tr> 
<td></td><td>
<td align="right" valign="center"> วันที่สั่งซื้อ : </td> <td ><input type="date" name="part_date_purchase" id="part_date_purchase" value="<?php echo date("Y-m-d"); ?>" /></td> 
  </tr>
  <tr> 

    
  </tr>
  </tr>
  
  <tr>

  </tr>

  <!-- add ?????????   -->
  <tr>
    <td colspan="4" ><hr /></td>
   </tr>
   <tr>
   </tr>
   <tr>
    <td width="25%" align="center">
	  </td>
    <td width="25%">
	
	</td>
    <td width="25%" align="center">
	<!-- serial ????????? -->
<!--	<p><select class="custom-select" name="sn_sp1" id="sn_sp1" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp2" id="sn_sp2" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp3" id="sn_sp3" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp4" id="sn_sp4" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp5" id="sn_sp5" style="display:none;  width:200px"><option value="">S/N</option></select></p>  -->
	</td>
    <td width="25%"></td>
  </tr>
  <tr>
  <td  align="right" valign="center"> ผู้บันทึก : </td> <td > <select class="custom-select" name="part_by" id="part_by">
  	<option value="<?=$resultPart['part_by']; ?>"><?=$resultPart['part_by']; ?></option>
    <option value="NOI">NOI</option>
    <option value="NI">NI</option>
    <option value="PLAM">PLAM</option>
    <option value="AO">AO</option>
    <option value="MAX">Max</option>
    <option value="DAY">DAY</option>
    <option value="NUT">NUT</option>
    <option value="BOY">BOY</option>
    </select> </td> 
    <td  align="right" valign="center" ><p><button type="button" onclick="javascript: save_job();"><i class="fa fa-save">SAVE</i></button> <i><a><p><a href="javascript: save_job();"></td><input type="hidden" name="part_id" id="part_id"  value="<?=$_GET['part_id']; ?>"> 
   </tr>

</table>
<br />
<div align="center"></div>
<div align="center"></div>
</form>
</div>
</body>
</center>
</html>
