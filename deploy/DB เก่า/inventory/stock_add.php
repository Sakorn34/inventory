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

<title>เพิ่ม Stock</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

<script type="text/javascript">

function save_job(){
	  // document.getElementById("form1").submit();

	var count = document.getElementById("count_sp").value;
	if(count == 0){
		alert("ยังไม่ได้เพิ่มรายการ กรุณากด add ก่อนบันทึก " + count);
		return;
	}
	
	if(document.getElementById("vendor_com").value == "" ){ 
		alert("กรุณากรอกชื่อ vendor");
		document.getElementById("vendor_com").focus();
		return;
	}
	if(document.getElementById("log_by").value == "" ){ 
		alert("กรุณากรอกชื่อคนบันทึก");
		document.getElementById("log_by").focus();
		return;
	}
			
	var data_update = "log_date="+document.getElementById("log_date").value
	    +"&log_type="+document.getElementById("log_type").value
		+"&log_by="+document.getElementById("log_by").value
		+"&vendor_com="+document.getElementById("vendor_com").value
		+"&count_sp="+document.getElementById("count_sp").value;

	var data_row = "";

	for($a = 1; $a <= count ; $a++ ){
		
		if(count >= 1 && (document.getElementById("pd_sp"+$a).value == "" || document.getElementById("sn_sp"+$a).value == ""
					|| document.getElementById("amount_sp"+$a).value == "" || document.getElementById("price_sp"+$a).value == "")){
						alert("กรุณากรอกข้อมูลให้ครบถ้วน");
					return;
		}
		data_row +=	
		"&pd_sp"+$a+"="+document.getElementById("pd_sp"+$a+"").value
		+"&sn_sp"+$a+"="+document.getElementById("sn_sp"+$a+"").value
		+"&amount_sp"+$a+"="+document.getElementById("amount_sp"+$a+"").value
		+"&price_sp"+$a+"="+document.getElementById("price_sp"+$a+"").value
		+"&remarks_sp"+$a+"="+document.getElementById("remarks_sp"+$a+"").value
	}

	var sum_data = data_update + data_row;
	//alert(sum_data);
	window.location="stock_save.php?"+sum_data;
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

//select s/n spare 1
$(document.getElementById("pd_sp1")).ready(function(){
     $("select#pd_sp1").change(function(){ $.get("list_part.php",{ pd_sp1:$(this).val() },
		 function(data) { $("select#sn_sp1").html(data);  });
      }); 
});
//select s/n spare 2
$(document.getElementById("pd_sp2")).ready(function(){
     $("select#pd_sp2").change(function(){ $.get("list_part.php",{ pd_sp2:$(this).val() },
		 function(data) { $("select#sn_sp2").html(data);  });
      }); 
});
//select s/n spare 3
$(document.getElementById("pd_sp3")).ready(function(){
     $("select#pd_sp3").change(function(){ $.get("list_part.php",{ pd_sp3:$(this).val() },
		 function(data) { $("select#sn_sp3").html(data);  });
      }); 
});
//select s/n spare 4
$(document.getElementById("pd_sp4")).ready(function(){
     $("select#pd_sp4").change(function(){ $.get("list_part.php",{ pd_sp4:$(this).val() },
		 function(data) { $("select#sn_sp4").html(data);  });
      }); 
});
//select s/n spare 5
$(document.getElementById("pd_sp5")).ready(function(){
     $("select#pd_sp5").change(function(){ $.get("list_part.php",{ pd_sp5:$(this).val() },
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

function checkEmpty(){

	var count_sp= document.getElementById("count_sp").value;

	if(count_sp == 0){
		alert("ยังไม่ได้เพิ่มรายการ กรุณากด add ก่อนบันทึก " + count_sp);
		return;
	}
	
	if(count_sp != 0){
		alert("Debug1 n="+$n+"  count_sp = "+ count_sp);
		for ($n = 0; $n <= count_sp; $n++){
			alert("Debug2 n="+$n+"  count_sp = "+ count_sp);

		}
	/*	if(count_sp >= 1 && (document.getElementById("pd_sp"+r_spare).value == "" || document.getElementById("sn_sp"+r_spare).value == ""
				|| document.getElementById("amount_sp"+r_spare).value == "")){
					alert("กรุณากรอกข้อมูลก่อนหน้าให้ครบถ้วน");
					return;
				}*/
		
	}

}

var r_spare = 0;
//Add รายการเครื่องสำรอง
function addSpare(mode) {

			if(r_spare >= 5 && mode ==  "add"){
				alert("เพิ่มรายการสำรองได้สูงสุด 5 รายการ");
				return;
			}

			$('#log_type').val("นำเข้า");
			
			if(mode == "add"){
				//check fields ที่ไม่ได้ป้อนข้อมูล
				if(r_spare >= 1 && (document.getElementById("pd_sp"+r_spare).value == "" 
				|| document.getElementById("sn_sp"+r_spare).value == ""
				|| document.getElementById("amount_sp"+r_spare).value == ""
				|| document.getElementById("price_sp"+r_spare).value == "")){
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
				//amount_sp1
				document.getElementById("amount_sp"+r_spare).style.display = '';
				document.getElementById("amount_sp"+r_spare).value = ""; 
				//price_sp1
				document.getElementById("price_sp"+r_spare).style.display = '';
				document.getElementById("price_sp"+r_spare).value = ""; 
				
				//remarks_sp1
				document.getElementById("remarks_sp"+r_spare).style.display = '';
				document.getElementById("remarks_sp"+r_spare).value = ""; 

				//count row
				$('#count_sp').val(r_spare);
			
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
				//amount_sp1
				document.getElementById("amount_sp"+r_spare).style.display = 'none';
				document.getElementById("amount_sp"+r_spare).value = ""; 
				
				//price_sp1
				document.getElementById("price_sp"+r_spare).style.display = 'none';
				document.getElementById("price_sp"+r_spare).value = ""; 
				
				//remarks_sp1
				document.getElementById("remarks_sp"+r_spare).style.display = 'none';
				document.getElementById("remarks_sp"+r_spare).value = ""; 
				
				if(r_spare >= 1){
					r_spare -= 1;
				}

				//count row
				$('#count_sp').val(r_spare);
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
<div class="navbar"><?php require("title-h.php");?></div>
<div class="content">
<?
/*
$arrayVD = array();
$sqlVD = "SELECT vname FROM tbl_vendor vname LIKE '%".iconv( 'UTF-8', 'TIS-620', $_REQUEST["term"])."%'";
$exeVD = odbc_exec($connect, $sqlVD) or die("Error Query [" . $sqlVD . "]");
while ($resultVD = odbc_fetch_array($exeVD)) {
    array_push($arrayVD, $resultVD['vname']);
}
$arrLengthVD = count($arrayVD);
*/
?>
<form id="form_add_stock" name="form_add_stock" method="post" action="save_stock">
<table id="table1" width="100%" >

  <tr  class="spaceUnder05">
  	<td colspan="6" align="center" valign="center" ><b class="ex">เพิ่ม Stock</b></td>
   </tr>
   <tr  class="spaceUnder05">
    <td  align="right" valign="center"><b class="ex">วันนำเข้า : </b></td><td><input type="date" name="log_date" id="log_date" value="<?php echo date("Y-m-d"); ?>" style="width:auto;" /></td>
	<td align="right" valign="center"><b class="ex">ชื่อ Vendor : </b></td>
    <td  valign="center"><p><div class="search-box-user"><input type="text" autocomplete="off" placeholder="Search vandor company" name="vendor_com" id="vendor_com" style="width:400px;"  required />
	<div class="result"></div></div>
    </p></td>
	<td width="10%">&nbsp;  </td>
<td  width="10%">&nbsp;  </td>
   </tr>
  <tr>
    <td colspan="7"><hr /></td>
   </tr>
   <tr>
    <td colspan="7"><p class="ex2"><b>รายการของนำเข้า  &nbsp; &nbsp; 
	<a href='javascript:addSpare("del");'><i class="fa fa-minus-circle" aria-hidden="true" style="color:#FF0000"> Del </i></a> &nbsp; &nbsp;  
	<a href='javascript:addSpare("add");'><i class="fa fa-plus-circle" aria-hidden="true" style="color:#339900"> Add </i></a></b></p></td>
   </tr>
  <tr> <td colspan="7">
  <table id="table2" width="100%"  >
    <td width="5%"><div align="center"><b>No.</b></div></td>
    <td width="25%"><div align="left"><b>สินค้า/Product</b></div></td>
    <td width="25%"><div align="left"><b>Part</b></div></td>
    <td width="10%"><div align="center"><b>จำนวน</b></div></td>
	<td width="10%"><div align="center"><b>ราคา</b></div></td>
	<td width="10%"><div align="center"><b>หมายเหตุ</b></div></td>
  </tr>
   <tr>
    <td width="5%" align="center">
	  <p><label id="number_sp1" style="display:none;">1</label></p>
	  <p><label id="number_sp2" style="display:none;">2</label></p>
	  <p><label id="number_sp3" style="display:none;">3</label></p>
	  <p><label id="number_sp4" style="display:none;">4</label></p>
	  <p><label id="number_sp5" style="display:none;">5</label></p>
	  
	  </td>
    <td width="25%">
	
	 <?php
			$array = array();
			$array2 = array();
			$sqlSP =  "SELECT * FROM product";
			$exeSP = odbc_exec($connect,$sqlSP) or die ("Error Query [".$sqlSP."]");
			while($resultSP = odbc_fetch_array($exeSP)){ 
				array_push($array, $resultSP['pd_name']);
				array_push($array2, $resultSP['pd_id']);
			}
			$arrLength = count($array);
	 ?>
	 <p><select class="custom-select" id="pd_sp1" name="pd_sp1"  style="display:none" >
	 			<option value="">--เลือก Product--</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array2[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp2" name="pd_sp2"  style="display:none" >
	 			<option value="">--เลือก Product--</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array2[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp3" name="pd_sp3"  style="display:none" >
	 			<option value="">--เลือก Product--</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array2[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp4" name="pd_sp4"  style="display:none" >
	 			<option value="">--เลือก Product--</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array2[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp5" name="pd_sp5"  style="display:none" >
	 			<option value="">--เลือก Product--</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array2[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	<?php odbc_close($connect); ?>	
	
	</td>
    <td width="25%" align="left">
	<!-- serial เครื่องสำรอง -->
	<p><select class="custom-select" name="sn_sp1" id="sn_sp1" style="display:none;  width:250px" ><option value="">--เลือก part--</option></select></p>
	<p><select class="custom-select" name="sn_sp2" id="sn_sp2" style="display:none;  width:250px" ><option value="">--เลือก part--</option></select></p>
	<p><select class="custom-select" name="sn_sp3" id="sn_sp3" style="display:none;  width:250px" ><option value="">--เลือก part--</option></select></p>
	<p><select class="custom-select" name="sn_sp4" id="sn_sp4" style="display:none;  width:250px" ><option value="">--เลือก part--</option></select></p>
	<p><select class="custom-select" name="sn_sp5" id="sn_sp5" style="display:none;  width:250px" ><option value="">--เลือก part--</option></select></p>
	</td>
    <td width="10%" align="center">
	<p><input type="number" name="amount_sp1" id="amount_sp1" min=0 style="display:none;  width:100px" ></p>
	<p><input type="number" name="amount_sp2" id="amount_sp2" min=0 style="display:none;  width:100px" ></p>
	<p><input type="number" name="amount_sp3" id="amount_sp3" min=0 style="display:none;  width:100px" ></p>
	<p><input type="number" name="amount_sp4" id="amount_sp4" min=0  style="display:none;  width:100px" ></p>
	<p><input type="number" name="amount_sp5" id="amount_sp5" min=0  style="display:none;  width:100px" ></p>
	</td>
	 <td width="10%" align="center">
	<p><input type="number" name="price_sp1" id="price_sp1" min=0  style="display:none;  width:100px" ></p>
	<p><input type="number" name="price_sp2" id="price_sp2" min=0 style="display:none;  width:100px" ></p>
	<p><input type="number" name="price_sp3" id="price_sp3" min=0  style="display:none;  width:100px" ></p>
	<p><input type="number" name="price_sp4" id="price_sp4" min=0 style="display:none;  width:100px" ></p>
	<p><input type="number" name="price_sp5" id="price_sp5" min=0 style="display:none;  width:100px" ></p>
	</td>
	<td width="25%" align="left">
	<p><input type="text" name="remarks_sp1" id="remarks_sp1" min=0  style="display:none;  width:300px" ></p>
	<p><input type="text" name="remarks_sp2" id="remarks_sp2" min=0 style="display:none;  width:300px" ></p>
	<p><input type="text" name="remarks_sp3" id="remarks_sp3" min=0  style="display:none;  width:300px" ></p>
	<p><input type="text" name="remarks_sp4" id="remarks_sp4" min=0 style="display:none;  width:300px" ></p>
	<p><input type="text" name="remarks_sp5" id="remarks_sp5" min=0 style="display:none;  width:300px" ></p>
	</td>

  </td>
  </table>
  <tr>
    <td colspan="6"><hr /></td>
   </tr>
  <tr>
  <p><input type="hidden" id="count_sp" name="count_sp"></p>
  <p><input type="hidden" id="log_type" name="log_type"></p>
  	<td  align="right" valign="center"><b class="ex">ผู้บันทึก : </b></td><td>
	<select class="custom-select" name="log_by" id="log_by" required>
		<option value="">-- Select --</option>
		<option value="NOI">NOI</option>
		<option value="NI">NI</option>
		<option value="PLAM">PLAM</option>
		<option value="AO">AO</option>
		<option value="MAX">Max</option>
		<option value="DAY">DAY</option>
		<option value="NUT">NUT</option>
		<option value="BOY">BOY</option>

    </select> 
	</td>
    <td  align="right" witdh="100%" valign="center" ><input type="button" value="Save" onclick="javascript:save_job();"></td>
   </tr>

</table>
<br /><br />
<div align="center"></div>
<div align="center"></div>
</form>
</div>
</body>
</center>
</html>
