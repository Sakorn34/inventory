<?php
require "connect.php";
header("Content-type : text/html; charset=TIS-620");
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
<title>�����</title>

<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
<script  src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
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
//Add ��¡������ͧ���ͧ
function addSpare(mode) {

        alert("mode : " + mode);
			if(r_spare >= 5 && mode ==  "add"){
				alert("������¡�����ͧ���٧�ش 5 ��¡��");
				return;
			}
			
			if(mode == "add"){

				if(r_spare >= 1 && (document.getElementById("pd_sp"+r_spare).value == "" || document.getElementById("sn_sp"+r_spare).value == "")){
					alert("��سҡ�͡�����š�͹˹�����ú��ǹ");
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

<form id="form1" name="form1" method="post" action="" enctype="multipart/form-data"  onsubmit='javascript:return save_job();'>

<table id="table1" width="90%" align="center" >
<tr>
<td style="font-size:24px; text-align:right; color:#009900" >�����</td>
<td style="font-size:24px; text-align:right; color:#009900"><a href="./overview.php">[ <i class="fa fa-minus">������ª��� Part ]</i></a>
</td>
</tr>
<tr>
<td style="font-size:20px; text-align:right;"><b>�ѹ������� : </b></td>
<td><input type="date" name="lp_date" id="lp_date" value="<?php echo date("Y-m-d"); ?>" style="width:auto;" /></td>
<tr>
<td style="font-size:20px; text-align:right;"><b>Product :</b>
</td>
<td>
	<?php 
		$sql_pd =  "SELECT *  FROM part_list order by part_id ASC";
		$exe_pd = db_exec($connect, $sql_pd) or die ("Error Query [".$sql_pd."]");  ?>
	    <select id="lp_product" name="lp_product" style="width:auto;">
		<option value="">---Select product---</option>
		<?php while($result_pd = db_fetch_array($exe_pd)){ ?>
		<option value="<?=$result_pd['part_name'];?>"><?=$result_pd['part_name'];?></option><?php } ?>
     	</select>
</td>
</tr>
<tr>
<td style="font-size:20px; text-align:right;"><b>Part : </b></td>
<td>	
	<select id="lp_part" name="lp_part" style="width:auto;">
	<option value="" >---Select part----</option>
	</select>
</td>
</tr>
<tr>
<td style="font-size:20px; text-align:right;"><b>���ѹ�֡ : </b>
</td>
<td>
    <select class="custom-select" name="lp_user" id="lp_user">
  	<option value="none">���ѹ�֡..</option>
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
<tr>
<td>
</td>
</tr>
<tbody id="tbody"></tbody>
<tr><td></td><td>
<button type="button" onclick="addItem();">Add Item</button></td></tr>
<a href='javascript:addSpare("del");'><i class="fa fa-minus-circle" aria-hidden="true" style="color:#FF0000"> Del </i></a> &nbsp; &nbsp;  
<a href='javascript:addSpare("add");'><i class="fa fa-plus-circle" aria-hidden="true" style="color:#339900"> Add </i></a></b></p></td>
<tr>
<td>
</td>
</tr>
<tr>
<td>
</td>
<td style="height:100px; vertical-align:center;  text-align:left;"><p><a href="javascript: save_job();"><i class="fa fa-save"> Save</i></a></p>
</td>
</tr>
</table>
<br /><br />
</form>
		<?php db_close($connect);?>
</div>

</body>
</html>
