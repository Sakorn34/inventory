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
<meta http-equiv="Content-Type" content="text/html; charset=TIS-620" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link type="text/css" rel="stylesheet" href="css.css" />
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css"> 

<title>ระบบประสานงาน Setup</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
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

//Search ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝัท รป๏ฟฝ.
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
$(document.getElementById("pre_date")).ready(function(){
     $("#pre_date").change(function(){ 
		 $.get("setup_list_order.php",{ pre_date:$(this).val() },
		 function(data) { $("#result").html(data);  });
      }); 
});

function listSetup(){

    /*$tz_object = new DateTimeZone('Asia/Bangkok');

    $datetime = new DateTime();
    $datetime->setTimezone($tz_object);
    $datetime->format('Y\-m\-d\ h:i:s');*/
    $dateNow = pre_date.value;
    // alert($dateNow);
	 $.get("setup_list_order.php",{ pre_date:$dateNow },
	 function(data) { 
         $("#result").html(data); 
     });
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

<body onload="listSetup()" >
<div class="navbar"><?php require("title-h.php"); ?></div>

<div class="content">
<form id="form_production" name="form_production" method="post" action="save_stock">
<table id="table1" width="100%" >

<tr class="spaceUnder05">
<td colspan="4" align="center" valign="center" ><b class="ex">รายการประสานงาน</b></td>

<tr>
    <td width="200" align="right" valign="center"><b >วันที่ประสาน : </b></td>
	<td width="200"><input type="date" name="pre_date" id="pre_date" value="<?php echo date("Y-m-d"); ?>" /></td>
    <td></td>
    <td width="200" align="right" valign="center"><a href="./setup_add.php">[ <i class="fa fa-plus-circle" aria-hidden="true" style="color:#333333"> เพิ่มงานประสาน</i> ]</a></td>

   </tr>
</tr>
<tr>
<td align="right" valign="right"> </td> 
</tr>

<tr>
    <td colspan="4"><hr /></td>
   </tr>
   <tr>
    <td colspan="4"><div  id="result" name="result">result</div></td>
   </tr>
  <tr >
    <td colspan="4"><hr /></td>
   </tr>

</table>

<br /><br />
<div align="center"></div>
<div align="center"></div>
</form>
</div>
</body>
</html>
