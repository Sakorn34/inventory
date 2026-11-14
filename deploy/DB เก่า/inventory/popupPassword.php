<?
	//session_start();
require "connect.php";
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
$count_order = 0;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
</head>

<script>

function OpenPopup(intLine)
	{
		window.open('getData.php?Line='+intLine,'myPopup','width=650,height=200,toolbar=0, menubar=0,location=0,status=1,scrollbars=1,resizable=1,left=0,top=0');
	
  }


function save_job(){
  window.open('getData.php?Line='+intLine,'myPopup','width=650,height=200,toolbar=0, menubar=0,location=0,status=1,scrollbars=1,resizable=1,left=0,top=0');
	
    //var str = document.getElementById("pre_check").value;
    var count = document.getElementById("count_order").value;
    var ts_mtn_number = document.getElementById("ts_mtn_number").value;

    alert("Total : " + count);
    var data_row = "" ;
    if(count == 0){
      alert("ไม่ได้ข้อมูลให้บันทึก");
      return;
  }
  
  data_row = "count="+count + "&ts_mtn_number=" + ts_mtn_number;
  
  var a;
  var count_check = 0;
  for ( a = 1; a <= count; a++){
    var yes = document.getElementById('chk'+a);  
    var chk = "";
    if(yes.checked == true){
      chk = document.getElementById('chk'+a).value;  
      count_check++;
    }else{
      chk = "NO";
    }
    data_row += "&log_part="+document.getElementById('pre_part'+a).value 
              + "&log_product="+document.getElementById('pre_pd'+a).value 
              + "&log_amount="+document.getElementById('pre_amount'+a).value 
              + "&check="+chk+"@" ;
  }

  if(count_check == 0){
			alert("กรุณาติ๊กเลือกรายการที่ต้องการเบิกก่อน Save "+count_check);
			return;
  }  

  alert(data_row);
  
  window.location="save_log_stock.php?" + data_row;
  
}



</script>
<body>
<?
if (isset($_GET['pre_id'])) {
  $id2 = $_GET['pre_id'];
  $id_ma = $_GET['ts_mtn_number'];
}
//echo "List :  " . $arr_username[0] . ", " . $arr_username[1] . " and " . $arr_username[2] . "."; 
echo "<b>เลขเอกสารเบิกของ : " . $id2 . "<b><br>";
?>
<form id="form_add_log" name="form_add_log" method="post"  action="save_stock">
<table border="1" id="table_result" name="table_result">
  <tr>
    <th width="20"> <div align="center">No. </div></th>
    <th width="200"> <div align="center">Part name </div></th>
    <th width="100"> <div align="center">Amount </div></th>
    <th width="50"> <div align="center">Check </div></th>
  </tr>
<?php
$data1;
$data2;
if (isset($id2)) {
 // echo "Found records <br>";
  $sql = "SELECT * FROM pre_stock Where pre_id = '" . $id2 . "' ";
  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;


  while ($result = odbc_fetch_array($exec)) { ?>
  <tr>
    <td align="center" > 
    <div >
    <?php echo $num;?>
    <input type="hidden" id="<?="pre_id".$num;?>" name="<?="pre_id".$num;?>" value="<?=$num;?>">
    <input type="hidden" id="<?="pre_pd".$num;?>" name="<?="pre_pd".$num;?>" value="<?=$result["pre_product"];?>">
    </div>
    </td>
    <td align="center" ><p><?=$result["pre_part"]; ?></p>
    <input type="hidden" id="<?="pre_part".$num;?>" name="<?="pre_part".$num;?>" value="<?=$result["pre_part"];?>"></td>
    <td align="center" >
    <input style="text-align:right;" min="1" max="5" type="number" id="<?="pre_amount".$num;?>" 
    name="<?="pre_amount".$num;?>" value="<?=$result["pre_amount"];?>">
    </td>
    <td align="center" ><input type="checkbox" id="<?="chk".$num;?>" name="chk[]" value="YES"></td>
  </tr>

<?php 
$count_order = $num;
$data1 = $result["pre_part"];
$data2 = $result["pre_amount"];
$num++;
} ?>

<?php 
} else { ?>
  <td> Not Found </td>
<?php 
} ?>
  <tr>
    <p><input type="hidden" id="count_order" name="count_order" value="<?=$count_order;?>"></p>
    <p><input type="hidden" id="ts_mtn_number" name="ts_mtn_number" value="<?=$id_ma;?>"></p>
    </td>
  </tr>
  <tr>
    <td align="center" colspan="2" valign="center"><input type="button" onclick="javascript:goBack();" value="Go Back"></td>
    <td align="center" colspan="2" valign="center"><input type="button" onclick="javascript:save_job();" value="Save"></td>
  </tr>

</table>
</form>
</body>
</html>
