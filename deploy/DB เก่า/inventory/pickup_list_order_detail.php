<?
	//session_start();
require "connect.php";
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
$count_order = 0;
$pre_type = "";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />

<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

<script type="text/javascript">

function save_job(){

    var x;
    var password = prompt("กรุณาใส่รหัส", "");
    //var arr_passwd = document.getElementById("arr_pass").value;
    var log_by = "";
    if (password != null) {

      //alert("password : "+ password);
      $.ajax({
            url: 'check_password.php',
            type: 'post',
            data: {
                'password_check': 1,
                'password': password
            },
            success: function(response) {
                  //alert("response =" +response)
                if (response != '') {
                    password_check = true;
                    log_by = response;
                    //alert("บันทึกโดย : "+log_by);

                    //var str = document.getElementById("pre_check").value;
                    var count = document.getElementById("count_order").value;
                    var ts_mtn_number = document.getElementById("ts_mtn_number").value;
                    var pre_id =  document.getElementById("pre_id").value;
                    var pre_type = document.getElementById("pre_type").value;
       //             var pre_remarks = document.getElementById("pre_remarks").value;

                    var data_row = "" ;
                    if(count == 0){
                      alert("ไม่ได้ข้อมูลให้บันทึก");
                      return;
                    }
                
                    data_row = "count=" + count +"&ts_mtn_number=" + ts_mtn_number 
                    + "&log_by="+log_by+ "&pre_id="+pre_id + "&pre_type="+pre_type;
                    
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

                      data_row += "&log_part"+a+"="+document.getElementById('pre_part'+a).value 
                                + "&log_product"+a+"="+document.getElementById('pre_pd'+a).value 
                                + "&log_amount"+a+"="+document.getElementById('pre_amount'+a).value 
								+ "&log_remarks"+a+"="+document.getElementById('pre_remarks'+a).value 
                                + "&check"+a+"="+chk ;
                    }
					
					

                    if(count_check == 0){
                        alert("กรุณาติ๊กเลือกรายการที่ต้องการเบิกก่อน Save "+count_check);
                        return;

                    }else{
                   //     alert("data row : "+data_row);
                        window.location="pickup_save_log.php?" + data_row;
                    }

                }else if (response == "") {
                    password_check = false;
                    alert("รหัสผ่านไม่ถูกต้อง กรุณาใส่รหัสอีกครั้ง")
                    return;
                }
            }
        })
    }  
}

</script>
</head>

<style>


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

table, th, td {
  border: 0px solid black;
  border-collapse: collapse;
}

th, td {
  padding: 15px;
}

</style>
<style type="text/css">
.even {
    background-color: #F0F0F0  !important;  //#E1E1E1
}
.odd {
    background-color: #FFFFFF  !important;
}
</style>
<center>
<?
if (isset($_GET['pre_id'])) {
  $id2 = $_GET['pre_id'];
  $id_ma = $_GET['ts_mtn_number'];
  $pre_remarks = $_GET['pre_remarks'];
//    $pre_type = $_GET['pre_type'];
 // echo "pre_type = ".$pre_type;

}
//echo "List :  " . $arr_password[0] . ", " . $arr_password[1] . " and " . $arr_password[2] . "."; 
//echo "Count : " . count($arr_password);

echo "<b>เลขเอกสารเบิกของ : " . $id2 . "<b><br>";
?>

<form id="form_add_log" name="form_add_log" method="post" >
<table border="1" id="table_result" name="table_result" width="100%">
  <tr bgcolor="#E1E1E1">
    <th width="10"> <div align="center">No. </div></th>
    <th width="200"> <div align="center">ชื่อ Part</div></th>
    <th width="100"> <div align="center">จำนวน</div></th>
    <th width="30"> <div align="center">Check</div></th>
	<th width="30"> <div align="center">หมายเหตุ</div></th>
  </tr>
<?php
$data1;
$data2;
$styles = array('even','odd');

if (isset($id2)) {
 // echo "Found records <br>";
  $sql = "SELECT  * FROM pre_stock  
  INNER JOIN part ON pre_stock.[pre_part]= part.[part_id]  Where  pre_stock.[pre_id] = '" . $id2 . "'  ";
  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;

  while ($result = odbc_fetch_array($exec)) { ?>
  <tr class="<?php echo $styles[$num % 2]; ?>">
    <td align="center" > 
    <div >
    <?php echo $num; ?>
    <input type="hidden" id="<?= "pre_id" . $num; ?>" name="<?= "pre_id" . $num; ?>" value="<?= $num; ?>">
    <input type="hidden" id="<?= "pre_pd" . $num; ?>" name="<?= "pre_pd" . $num; ?>" value="<?= $result["pre_product"]; ?>">
    </div>
    </td>
    <td align="left" ><?= $result["part_name"]; ?>
    <input type="hidden" id="<?= "pre_part" . $num; ?>" name="<?= "pre_part" . $num; ?>" value="<?= $result["pre_part"]; ?>"></td>
    <td align="center" >
    <input style="text-align:right;" min="1" max="999" type="number" id="<?= "pre_amount" . $num; ?>"  name="<?= "pre_amount" . $num; ?>" value="<?= $result["pre_amount"]; ?>">
    </td>
    <td align="center" ><input type="checkbox" id="<?= "chk" . $num; ?>" name="chk[]" value="YES"></td>
	    <td align="center" >
		<textarea rows="1" cols="20"  id="<?= "pre_remarks" . $num; ?>"  name="<?= "pre_remarks" . $num; ?>" > <?= $result["pre_remarks"]; ?></textarea>

    </td>
  </tr>

<?php 
$count_order = $num;
$data1 = $result["pre_part"];
$data2 = $result["pre_amount"];
$pre_type = $result["pre_type"];
$num++;
} ?>

<?php 
} else { ?>
  <td> Not Found </td>
<?php 
} ?>
  <tr>
    <p><input type="hidden" id="pre_id" name="pre_id" value="<?= $id2; ?>" /></p>
    <p><input type="hidden" id="count_order" name="count_order" value="<?= $count_order; ?>" /></p>
    <p><input type="hidden" id="ts_mtn_number" name="ts_mtn_number" value="<?= $id_ma; ?>" /></p>
    <p><input type="hidden" id="pre_type" name="pre_type" value="<?=$pre_type; ?>" /></p>
   <!-- <p><input type="hidden" id="pre_remarks" name="pre_remarks" value="" /></p>  -->
    </td>
  </tr>
  <tr>
    <td align="center" colspan="2" valign="center"><input class="buttonBack" type="button" 
    onclick="document.location.href='./pickup_overview.php'" value="Go Back"></td>
    
    <td align="center" colspan="2" valign="center"><input class="buttonSave" type="submit" 
    onclick="javascript:save_job();" value="Save"></td>
  </tr>


</table>
</form>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="script.js"></script>
</center>
</html>
