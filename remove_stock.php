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
	
				window.location="save_stock.php?"+data_update;
				
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
//Add รายการเครื่องสำรอง
function addSpare(mode) {


			if(r_spare >= 5 && mode ==  "add"){
				alert("เพิ่มรายการสำรองได้สูงสุด 5 รายการ");
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

<body>
<div class="navbar"><?php require("title-h.php");?></div>

<div class="content">
<form id="form1" name="form1" method="get" action="">
<table id="table1" width="100%" >
   <tr class="spaceUnder05">
      	<td colspan="2"><b class="ex">บริษัท รปภ..</b></td>
   </tr>
  <tr class="spaceUnder">
	<td colspan="2" >
	 <div class="search-box-sec">
        <input type="text" autocomplete="off" placeholder="Search security company" name="ts_security_com" id="ts_security_com" style="width:400px;" />
        <div class="result_sec"></div>
    </div>	</td>
  </tr>
  <tr>
    <td colspan="4"><hr /></td>
   </tr>
   <tr>
    <td colspan="4"><p class="ex2"><b>ข้อมูลเครื่องสำรอง  &nbsp; &nbsp; 
	<a href='javascript:addSpare("del");'><i class="fa fa-minus-circle" aria-hidden="true" style="color:#FF0000"> Del </i></a> &nbsp; &nbsp;  
	<a href='javascript:addSpare("add");'><i class="fa fa-plus-circle" aria-hidden="true" style="color:#339900"> Add </i></a></b></p></td>
   </tr>
  <tr>
    <td width="25%"><div align="center"><b>รายการที่</b></div></td>
    <td width="25%"><div align="left"><b>สินค้า/Product</b></div></td>
    <td width="25%"><div align="center"><b>Part</b></div></td>
    <td width="25%"></td>
  </tr>
   <tr>
    <td width="25%" align="center">
	  <p><label id="number_sp1" style="display:none;">1</label></p>
	  <p><label id="number_sp2" style="display:none;">2</label></p>
	  <p><label id="number_sp3" style="display:none;">3</label></p>
	  <p><label id="number_sp4" style="display:none;">4</label></p>
	  <p><label id="number_sp5" style="display:none;">5</label></p>
	  </td>
    <td width="25%">
	
	 <?php
			$array = array();
			$sqlSP =  "SELECT * FROM product";
			$exeSP = db_exec($connect,$sqlSP) or die ("Error Query [".$sqlSP."]");
			while($resultSP = db_fetch_array($exeSP)){ 
				array_push($array, $resultSP['pd_name']);
			}
			$arrLength = count($array);
	 ?>
	 <p><select class="custom-select" id="pd_sp1" name="pd_sp1"  style="display:none">
	 			<option value="">Product</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp2" name="pd_sp2"  style="display:none">
	 			<option value="">Product</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp3" name="pd_sp3"  style="display:none">
	 			<option value="">Product</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp4" name="pd_sp4"  style="display:none">
	 			<option value="">Product</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	 <p><select class="custom-select" id="pd_sp5" name="pd_sp5"  style="display:none">
	 			<option value="">Product</option>
					  <? for($a=0;$a <= $arrLength;$a++){?>
							<option value="<?= $array[$a]; ?>"><?= $array[$a]; ?></option>
					 <? } ?>
	 			</select></p>
	<?php db_close($connect); ?>	
	
	</td>
    <td width="25%" align="center">
	<!-- serial เครื่องสำรอง -->
	<p><select class="custom-select" name="sn_sp1" id="sn_sp1" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp2" id="sn_sp2" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp3" id="sn_sp3" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp4" id="sn_sp4" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	<p><select class="custom-select" name="sn_sp5" id="sn_sp5" style="display:none;  width:200px"><option value="">S/N</option></select></p>
	</td>
    <td width="25%"></td>
  </tr>
  <tr>
    <td colspan="4"><hr /><p><a href="javascript: save_job();"><i class="fa fa-save"> Save</i></a></p></td>
   </tr>

</table>
<br /><br />
<div align="center"></div>
<div align="center"></div>
</form>
</div>
</body>
</html>

