<?
require("connect.php");
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

<title>รายละเอียด</title>
<!--
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
-->
<script src="https://code.jquery.com/jquery-3.5.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.8.0"></script>


<script type="text/javascript">

    $(document).ready(function() {
            showGraph();
    });

    function showGraph(){
        {
            $.post("sum_part.php", function(data) {

            console.log(data);
            let part = ["Display 7","กล่อง Plus","กล้อง 525","แผง EXP","Switch On-Off"];
            let amount = ["10","20","15","50","30"];

            for (let i in data) {
              //  part.push(data[i].part_name);
             //   amount.push(data[i].part_amount);
               // alert("run"+i);
            }

            
            let chartdata = {

            labels: part,
            datasets: [{
                    label: 'จำนวน Part ',
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255,99,132,1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    hoverBackgroundColor: '#CCCCCC',
                    hoverBorderColor: '#49e2ff',
                    data: amount
                    }]
            };

            let graphTarget = $('#graphCanvas');
            let barGraph = new Chart(graphTarget, {
                    type: 'bar',
                    data: chartdata
                })
                        
            })      
        } 
    }
	
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
		 $.get("list_order_pickup.php",{ pre_date:$(this).val() },
		 function(data) { $("#result").html(data);  });
      }); 
});

function listDateNow(){

    /*$tz_object = new DateTimeZone('Asia/Bangkok');

    $datetime = new DateTime();
    $datetime->setTimezone($tz_object);
    $datetime->format('Y\-m\-d\ h:i:s');*/
    $dateNow = pre_date.value;
   //  alert($dateNow);
	 $.get("list_order_pickup.php",{ pre_date:$dateNow },
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

body {
    width: 100%;
    margin: 3rem auto;
}

.graphCanvas{
    width: 200px;
    height: auto;
}
</style>

<body >
<div class="navbar"><?php require("title-h.php"); ?></div>

<div class="content">
<form id="form_production" name="form_production" method="post" action="save_stock">
<table id="table1" width="100%" >

<tr class="spaceUnder05">
<td colspan="4" align="center" valign="center" ><b class="ex">สรุปภาพรวม</b></td>

<tr>
    <td width="200" align="right" valign="center"><b class="ex">Product : </b></td>
	<td width="200"><select class="custom-select" name="product_name" id="product_name" required>
		<option value="">-- Select --</option>
		<option value="bitVisitor-Plus">bitVisitor-Plus</option>
		<option value="bitVisitor-M">bitVisitor-M</option>
		<option value="bitSupply">bitSupply</option>
		<option value="bitScan">bitScan</option>
		<option value="">Max</option>
		<option value="DAY">DAY</option>
		<option value="NUT">NUT</option>
		<option value="BOY">BOY</option>
		<option value="NA-MO">NA-MO</option>
    </select> </td>
    <td>	<td  align="right" valign="center"></td><td>
	</td></td>
    <td></td>

   </tr>
</tr>
<tr>
<td align="right" valign="center"> </td> 
</tr>

<tr>
    <td colspan="4"><hr /></td>
   </tr>
   <tr>
    <td colspan="4">
    <div id="chart-container" width="400px">
        <canvas id="graphCanvas"></canvas>
</div>
     </td>
   </tr>

</table>

<br /><br />
<div align="center"></div>
<div align="center"></div>
</form>
</div>

</body>
</html>
