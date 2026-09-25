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
<title>เพิ่มเคสประสานงาน</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

<script type="text/javascript">

function save_job(){
//	document.getElementById("form_part").submit();
	
		if(document.getElementById("setup_site").value == ""){ 
			alert("กรุณากรอกข้อมูล setup_site ให้ครบถ้วน");
			document.getElementById("setup_site").focus();
			return;
		}

        var setup_site = document.getElementById("setup_site").value;
        var setup_security = document.getElementById("setup_security").value;
        var setup_po = document.getElementById("setup_po").value;
        var setup_product = document.getElementById("setup_product").value;

        var setup_name = document.getElementById("setup_name").value;
        var setup_amount = document.getElementById("setup_amount").value;
        var setup_user = document.getElementById("setup_user").value;
        var setup_tel = document.getElementById("setup_tel").value;
        var setup_email = document.getElementById("setup_email").value;
        var setup_status = document.getElementById("setup_status").value;
        var setup_remarks = document.getElementById("setup_remarks").value;
        var setup_name_update = document.getElementById("setup_name_update").value;
//----------------------------------Accessories-------------------------------------------//
        var setup_pd_plus = document.getElementById("setup_pd_plus").value;
        var setup_pd_m = document.getElementById("setup_pd_m").value;
        var setup_pd_scanner = document.getElementById("setup_pd_scanner").value;
        var setup_pd_scanwireless = document.getElementById("setup_pd_scanwireless").value;
        var setup_pd_supprint = document.getElementById("setup_pd_supprint").value;
        var setup_pd_suppaper = document.getElementById("setup_pd_suppaper").value;
        var setup_pd_printer = document.getElementById("setup_pd_printer").value;
        var setup_pd_cardcam = document.getElementById("setup_pd_cardcam").value;
        var setup_pd_smc = document.getElementById("setup_pd_smc").value;
        var setup_pd_magnetic = document.getElementById("setup_pd_magnetic").value;
        var setup_pd_indoor = document.getElementById("setup_pd_indoor").value;
        var setup_pd_outdoor = document.getElementById("setup_pd_outdoor").value;
        var setup_pd_a4 = document.getElementById("setup_pd_a4").value;
        var setup_pd_router = document.getElementById("setup_pd_router").value;
        var setup_pd_mobile = document.getElementById("setup_pd_mobile").value;
        var setup_pd_numeric = document.getElementById("setup_pd_numeric").value;
        var setup_pd_bitstampg = document.getElementById("setup_pd_bitstampg").value;
        var setup_pd_bitstampf = document.getElementById("setup_pd_bitstampf").value;
        var setup_pd_ewritter = document.getElementById("setup_pd_ewritter").value;
        var setup_pd_switchconfirm = document.getElementById("setup_pd_switchconfirm").value;
        var setup_pd_visitorcard = document.getElementById("setup_pd_visitorcard").value;
        var setup_pd_visitorrfid = document.getElementById("setup_pd_visitorrfid").value;

        var data_update = "setup_site="+setup_site
                                            +"&setup_security="+setup_security
                                            +"&setup_po="+setup_po
                                            +"&setup_product="+setup_product
                                            +"&setup_name="+setup_name
                                            +"&setup_amount="+setup_amount
                                            +"&setup_user="+setup_user
                                            +"&setup_tel="+setup_tel
                                            +"&setup_email="+setup_email
                                            +"&setup_status="+setup_status
                                            +"&setup_remarks="+setup_remarks
                                            +"&setup_name_update="+setup_name_update
                                            +"&setup_pd_plus="+setup_pd_plus
                                            +"&setup_pd_m="+setup_pd_m
                                            +"&setup_pd_scanner="+setup_pd_scanner
                                            +"&setup_pd_scanwireless="+setup_pd_scanwireless
                                            +"&setup_pd_supprint="+setup_pd_supprint
                                            +"&setup_pd_suppaper="+setup_pd_suppaper
                                            +"&setup_pd_printer="+setup_pd_printer
                                            +"&setup_pd_cardcam="+setup_pd_cardcam
                                            +"&setup_pd_smc="+setup_pd_smc
                                            +"&setup_pd_magnetic="+setup_pd_magnetic
                                            +"&setup_pd_indoor="+setup_pd_indoor
                                            +"&setup_pd_outdoor="+setup_pd_outdoor
                                            +"&setup_pd_a4="+setup_pd_a4
											+"&setup_pd_router="+setup_pd_router
											+"&setup_pd_mobile="+setup_pd_mobile
											+"&setup_pd_numeric="+setup_pd_numeric
											+"&setup_pd_bitstampg="+setup_pd_bitstampg
											+"&setup_pd_bitstampf="+setup_pd_bitstampf
											+"&setup_pd_ewritter="+setup_pd_ewritter
											+"&setup_pd_switchconfirm="+setup_pd_switchconfirm
											+"&setup_pd_visitorcard="+setup_pd_visitorcard
											+"&setup_pd_visitorrfid="+setup_pd_visitorrfid
											+" ";

		//alert("Save : " +data_update);
        //alert("กำลังบันทึกข้อมูล : " +data_update);
		window.location="setup_save.php?" +data_update;
			
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
  max-width: 300px;
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
	  	border: 1px solid black;
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

input[type='number']{
    width: 40px;
}

</style>

<body>
<div class="navbar"><?php require("title-h.php"); ?></div>

<div class="content">

<!-- Code PHP  -->

<?php
$array = array();
$sqlSP = "SELECT * FROM product";
$exeSP = db_exec($connect, $sqlSP) or die("Error Query [" . $sqlSP . "]");
while ($resultSP = db_fetch_array($exeSP)) {
    array_push($array, $resultSP['pd_name']);
}
$arrLength = count($array);
	//		db_close($connect);

$arrayVD = array();
$sqlVD = "SELECT * FROM tbl_vendor ORDER BY vname ASC";
$exeVD = db_exec($connect, $sqlVD) or die("Error Query [" . $sqlVD . "]");
while ($resultVD = db_fetch_array($exeVD)) {
    array_push($arrayVD, $resultVD['vname']);
}
$arrLengthVD = count($arrayVD);
echo $arrayVD;
?>

<form id="form_part" name="form_part" method="get" action="">
<table id="table1" width="100%" >
   <tr class="spaceUnder05">
      	<td colspan="4" align="center" valign="center" ><b class="ex">เพิ่มเคสประสานงาน</b></td>
   </tr>

  <tr> 
  <td align="right" valign="center" width="10%"> วันเปิด PO : </td> 

  <td><input type="date" name="setup_po" id="setup_po"> </td>
  
  <td align="right" valign="center" width="15%"> ระยะประสานงาน (วัน) : </td> 
  <td > </td>
  </tr>
  <tr> 
  <td align="right" valign="center"> หน่วยงาน : </td> <td > <input type="text" name="setup_site" id="setup_site"/> </td> 
  <td align="right" valign="center"> บ.รปภ. : </td> <td > <input type="text" name="setup_security" id="setup_security"/> </td>
  </tr>

  <tr> <td align="right" valign="center"> Product : </td> <td > <p>
  <select class="custom-select" name="setup_product" id="setup_product" required>
		<option value="">-- Select --</option>
		<option value="Plus">Plus</option>
		<option value="Plus+Paperless">Plus+Paperless</option>
		<option value="Plus+PR">Plus+PR</option>
		<option value="Plus+PR+Paperless">Plus+PR+Paperless</option>
        <option value="Plus (Retain)">Plus(Retain)</option>
		<option value="M">M</option>
		<option value="M+Paperless">M+Paperless</option>
        <option value="M (Retrain)">M (Retrain)</option>
        <option value="Lite">Lite</option>
        <option value="bitRegister">bitRegister</option>
        <option value="bitVehicle">bitVehicle</option>
        <option value="Realtime">Realtime</option>
        <option value="Paperless">Paperless</option>
    </select> </p>
  </td> 
  <td align="right" valign="center"> คนประสานงาน : </td> <td > <p>
  <select class="custom-select" name="setup_name" id="setup_name" required>
		<option value="">-- Select --</option>
		<option value="AO">AO</option>
		<option value="MAX">Max</option>
		<option value="DAY">DAY</option>
		<option value="NUT">NUT</option>
		<option value="BOY">BOY</option>
		<option value="NA-MO">NA-MO</option>
        <option value="NOI">NOI</option>
    </select> </p>
  </td> 
  </tr>

  <tr><td align="right" valign="center"> จำนวน : </td> <td > <input type="number" name="setup_amount" id="setup_amount" required/> ชุด</td>
   
  </tr>
  <tr>
  <td align="right" valign="center"> ชื่อผู้ติดต่อ : </td> <td > <input type="text" name="setup_user" id="setup_user" required/> </td>
  <td align="right" valign="center"> เบอร์โทร : </td> <td > <input type="text" name="setup_tel" id="setup_tel" required/> </td>
  </tr>
  <tr> 
  <td align="right" valign="center"> อีเมล์ : </td> <td > <input type="email" name="setup_email" id="setup_email" required/> </td> 
  </tr>
  <tr> 
  <td align="right" valign="center"> รายละเอียด : </td> <td colspan="3"> <textarea name="setup_remarks" id="setup_remarks" cols="90" rows="6"></textarea> </td> 
  </tr>

    <tr> 
  <td align="right" valign="center"> สถานะ : </td> <td > <p>
  <select class="custom-select" name="setup_status" id="setup_status" required>
		<option value="">-- Select --</option>
		<option value="รอประสานงาน">รอประสานงาน</option>
		<option value="อยู่ระหว่างออกแบบ">อยู่ระหว่างออกแบบ</option>
		<option value="รอ Approve">รอ Approve</option>
		<option value="พัก/ยกเลิก PO">พัก/ยกเลิก PO</option>
		<option value="รอส่งของ">รอส่งของ</option>
		<option value="นัดส่งของ">นัดส่งของ</option>
        <option value="ส่งของแล้ว">ส่งของแล้ว</option>
    </select> </p> 
  
  </td> 
  </tr>

  <tr>
  <td align="right" valign="center"> วันที่ update : </td> <td > <input type="date" name="setup_update" id="setup_update"/> </td>
  <td align="right" valign="center"> คน update : </td> <td > <p>
  <select class="custom-select" name="setup_name_update" id="setup_name_update" required>
		<option value="">-- Select --</option>
		<option value="AO">AO</option>
		<option value="MAX">Max</option>
		<option value="DAY">DAY</option>
		<option value="NUT">NUT</option>
		<option value="BOY">BOY</option>
		<option value="NA-MO">NA-MO</option>
        <option value="NOI">NOI</option>
    </select> </p> </td>
  <!-- add ?????????   -->
  <tr>
    <td colspan="4" ><hr /></td>
   </tr>
</table>

<table id="table1" width="100%" >
   <tr class="spaceUnder05">
      	<td colspan="5" align="center" valign="center" ><b class="ex">เพิ่มรายการอุปกรณ์</b></td>
   </tr>
   <tr> 
   <td align="right" valign="center">bitVisitor Plus : <input type="number" name="setup_pd_plus" id="setup_pd_plus" min="0" max="99" /></td>
   <td align="right" valign="center">bitVisitor M : <input type="number" name="setup_pd_m" id="setup_pd_m" min="0"  max="99"/></td>
   <td align="right" valign="center">Scanner มีสาย : <input type="number" name="setup_pd_scanner" id="setup_pd_scanner" min="0" max="99"/></td>
   <td align="right" valign="center">Scanner Wireless : <input type="number" name="setup_pd_scanwireless" id="setup_pd_scanwireless" min="0"  max="99"/></td>
   <td align="right" valign="center">bitSupply (Print) : <input type="number" name="setup_pd_supprint" id="setup_pd_supprint" min="0" max="99"/></td>
   </tr>
   <tr>
   
   <td align="right" valign="center">bitSupply (PPL)  : <input type="number" name="setup_pd_suppaper" id="setup_pd_suppaper" min="0" max="99"/></td>
   <td align="right" valign="center">Printer : <input type="number" name="setup_pd_printer" id="setup_pd_printer" min="0" max="99"/></td>
   <td align="right" valign="center">Cardcam : <input type="number" name="setup_pd_cardcam" id="setup_pd_cardcam" min="0" max="99"/></td>
   <td align="right" valign="center">Smart card : <input type="number" name="setup_pd_smc" id="setup_pd_smc" min="0" max="99"/></td>
   <td align="right" valign="center">Megnetic reader  : <input type="number" name="setup_pd_magnetic" id="setup_pd_magnetic" min="0" max="99"/></td>
   </tr>
   <tr>
   <td align="right" valign="center">Indoor camera : <input type="number" name="setup_pd_indoor" id="setup_pd_indoor" min="0" max="99"/></td>
   <td align="right" valign="center">Outdoor camera : <input type="number" name="setup_pd_outdoor" id="setup_pd_outdoor" min="0" max="99"/></td>
   <td align="right" valign="center">A4 Camera : <input type="number" name="setup_pd_a4" id="setup_pd_a4" min="0" max="99"/></td>
   <td align="right" valign="center">Router WiFi  : <input type="number" name="setup_pd_router" id="setup_pd_router" min="0" max="99"/></td>
   <td align="right" valign="center">มือถือ : <input type="number" name="setup_pd_mobile" id="setup_pd_mobile" min="0" max="99"/></td>
   </tr>
   <tr>
   <td align="right" valign="center">Numeric : <input type="number" name="setup_pd_numeric" id="setup_pd_numeric" min="0" max="99"/></td>
   <td align="right" valign="center">bitStamp G : <input type="number" name="setup_pd_bitstampg" id="setup_pd_bitstampg" min="0" max="99"/></td>
   <td align="right" valign="center">bitStamp F : <input type="number" name="setup_pd_bitstampf" id="setup_pd_bitstampf" min="0" max="99"/></td>
   <td align="right" valign="center">E-Writter  : <input type="number" name="setup_pd_ewritter" id="setup_pd_ewritter" min="0" max="99"/></td>
   <td align="right" valign="center">Switch confirm : <input type="number" name="setup_pd_switchconfirm" id="setup_pd_switchconfirm" min="0" max="99"/></td>
   </tr>
   <tr>
   <td align="right" valign="center">บัตรเคลือบร้อน : <input type="number" name="setup_pd_visitorcard" id="setup_pd_visitorcard" min="0" max="99"/></td>
   <td align="right" valign="center">บัตร RFiD : <input type="number" name="setup_pd_visitorrfid" id="setup_pd_visitorrfid" min="0" max="99"/></td>
   </tr>
  <tr>
    <td colspan="5"  align="center" valign="center" ><p><button type="button" onclick="javascript: save_job();"><i class="fa fa-save">SAVE</i></button> <i><a><p><a href="javascript: save_job();"></td>
   </tr>

</table>
<br />
<div align="center"></div>
<div align="center"></div>
</form>
</div>
</body>
</html>

