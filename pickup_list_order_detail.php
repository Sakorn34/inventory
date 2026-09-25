<?php
/**
 * หน้ารายละเอียดเอกสารเบิกของ
 *
 * รับเลขเอกสารจากเมนูรายการเบิก แสดงรายการ part ที่รอเบิก และส่งต่อไปบันทึก log
 * เมื่อผู้ใช้ยืนยันรหัสผ่านแล้ว จุดสำคัญคือใช้ prepared statement กับ pre_id จาก URL
 * เพื่อกัน SQL Injection และ escape output ทุกจุดที่ render กลับไปยัง HTML
 *
 * ตัวอย่างการใช้งาน: pickup_list_order_detail.php?pre_id=260518-132656
 */
require "connect.php";
header("Content-Type: text/html; charset=UTF-8");
setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * escape ค่าก่อน render ใน HTML attribute หรือ text node
 *
 * @param mixed $value ค่าที่ต้องการแสดงผล
 * @return string ข้อความที่ปลอดภัยสำหรับ HTML
 */
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ─ Request State ─────────────────────────────────────────────────────────────

$count_order = 0;
$pre_type = "";
$id2 = isset($_GET['pre_id']) ? trim((string) $_GET['pre_id']) : '';
$id_ma = isset($_GET['ts_mtn_number']) ? trim((string) $_GET['ts_mtn_number']) : '';
$pre_remarks = isset($_GET['pre_remarks']) ? trim((string) $_GET['pre_remarks']) : '';
$rows = array();
$query_error = '';

if ($id2 !== '') {
    $sql = "SELECT pre_stock.pre_product, pre_stock.pre_part, pre_stock.pre_type,
                   pre_stock.pre_amount, pre_stock.pre_remarks, part.part_name
            FROM pre_stock
            INNER JOIN part ON pre_stock.pre_part = part.part_id
            WHERE pre_stock.pre_id = ?";
    $stmt = mysqli_prepare($connect, $sql);

    if ($stmt === false) {
        $query_error = 'ไม่สามารถเตรียมคำสั่งค้นหารายการเบิกได้';
        error_log('inventory SQL prepare error: ' . mysqli_error($connect));
    } else {
        mysqli_stmt_bind_param($stmt, 's', $id2);

        if (!mysqli_stmt_execute($stmt)) {
            $query_error = 'ไม่สามารถค้นหารายการเบิกได้';
            error_log('inventory SQL execute error: ' . mysqli_stmt_error($stmt));
        } else {
            mysqli_stmt_bind_result(
                $stmt,
                $row_pre_product,
                $row_pre_part,
                $row_pre_type,
                $row_pre_amount,
                $row_pre_remarks,
                $row_part_name
            );

            while (mysqli_stmt_fetch($stmt)) {
                $rows[] = array(
                    'pre_product' => $row_pre_product,
                    'pre_part' => $row_pre_part,
                    'pre_type' => $row_pre_type,
                    'pre_amount' => $row_pre_amount,
                    'pre_remarks' => $row_pre_remarks,
                    'part_name' => $row_part_name,
                );
            }
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

<script type="text/javascript">

/**
 * ตรวจรหัสผ่านและส่งรายการเบิกที่ผู้ใช้เลือกไปบันทึก log
 *
 * parameters: ไม่มี รับค่าจาก form ในหน้า
 * return value: ไม่มี ใช้ redirect ไป pickup_save_log.php เมื่อข้อมูลครบ
 * signals/events: เรียกจากปุ่มบันทึก และใช้ AJAX callback หลังตรวจรหัสผ่าน
 */
function save_job(){

    var x;
    var password = prompt("กรุณาใส่รหัสผ่าน", "");
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
                      alert("ไม่พบรายการสำหรับบันทึก");
                      return;
                    }
                
                    data_row = "count=" + encodeURIComponent(count)
                    + "&ts_mtn_number=" + encodeURIComponent(ts_mtn_number)
                    + "&log_by=" + encodeURIComponent(log_by)
                    + "&pre_id=" + encodeURIComponent(pre_id)
                    + "&pre_type=" + encodeURIComponent(pre_type);
                    
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

                      data_row += "&log_part"+a+"="+encodeURIComponent(document.getElementById('pre_part'+a).value)
                                + "&log_product"+a+"="+encodeURIComponent(document.getElementById('pre_pd'+a).value)
                                + "&log_amount"+a+"="+encodeURIComponent(document.getElementById('pre_amount'+a).value)
								+ "&log_remarks"+a+"="+encodeURIComponent(document.getElementById('pre_remarks'+a).value)
                                + "&check"+a+"="+encodeURIComponent(chk) ;
                    }
					
					

                    if(count_check == 0){
                        alert("กรุณาเลือกรายการที่ต้องการเบิกก่อน Save "+count_check);
                        return;

                    }else{
                   //     alert("data row : "+data_row);
                        window.location="pickup_save_log.php?" + data_row;
                    }

                }else if (response == "") {
                    password_check = false;
                    alert("รหัสผ่านไม่ถูกต้อง กรุณาใส่ใหม่อีกครั้ง")
                    return;
                }
            }
        })
    }  
}

</script>
<style type="text/css">
body {
  background: #f5f7fb;
  color: #1f2937;
  font-family: Tahoma, Arial, sans-serif;
  margin: 0;
}

.page-shell {
  margin: 24px auto;
  max-width: 1180px;
  padding: 0 20px;
}

.document-title {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 18px;
  text-align: center;
}

.pickup-card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.pickup-table {
  border-collapse: collapse;
  width: 100%;
}

.pickup-table th {
  background: #e5e7eb;
  color: #111827;
  font-size: 15px;
  padding: 14px 12px;
  text-align: center;
}

.pickup-table td {
  border-top: 1px solid #eef2f7;
  padding: 14px 12px;
  vertical-align: middle;
}

.even {
  background-color: #f9fafb;
}

.odd {
  background-color: #ffffff;
}

.amount-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 7px 8px;
  text-align: right;
  width: 90px;
}

.remarks-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  min-height: 34px;
  padding: 7px 8px;
  width: 95%;
}

.actions-row td {
  background: #ffffff;
  padding: 22px 12px;
}

.buttonBack,
.buttonSave {
  border: none;
  border-radius: 8px;
  color: white;
  cursor: pointer;
  font-size: 16px;
  min-width: 120px;
  padding: 12px 28px;
}

.buttonBack {
  background-color: #ef4444;
}

.buttonSave {
  background-color: #22c55e;
}

.buttonBack:hover {
  background-color: #dc2626;
}

.buttonSave:hover {
  background-color: #16a34a;
}
</style>
</head>
<body>
<div class="page-shell">
<?php
//echo "List :  " . $arr_password[0] . ", " . $arr_password[1] . " and " . $arr_password[2] . ".";
//echo "Count : " . count($arr_password);

echo '<p class="document-title">เลขเอกสารเบิกของ : ' . h($id2) . '</p>';

if ($id2 === '') {
    echo '<p style="color:red;">ไม่พบเลขเอกสารเบิกของ</p>';
} elseif ($query_error !== '') {
    echo '<p style="color:red;">' . h($query_error) . '</p>';
}
?>

<form id="form_add_log" name="form_add_log" method="post" >
<div class="pickup-card">
<table class="pickup-table" id="table_result" name="table_result">
  <tr>
    <th width="10"> <div align="center">No. </div></th>
    <th width="200"> <div align="center">รายการ Part</div></th>
    <th width="100"> <div align="center">จำนวน</div></th>
    <th width="30"> <div align="center">เลือก</div></th>
	<th width="30"> <div align="center">หมายเหตุ</div></th>
  </tr>
<?php
$styles = array('even','odd');
$num = 1;

if (!empty($rows)) {
  foreach ($rows as $result) { ?>
  <tr class="<?php echo $styles[$num % 2]; ?>">
    <td align="center" > 
    <div >
    <?php echo (int) $num; ?>
    <input type="hidden" id="<?= "pre_id" . $num; ?>" name="<?= "pre_id" . $num; ?>" value="<?= $num; ?>">
    <input type="hidden" id="<?= "pre_pd" . $num; ?>" name="<?= "pre_pd" . $num; ?>" value="<?= h($result["pre_product"]); ?>">
    </div>
    </td>
    <td align="left" ><?= h($result["part_name"]); ?>
    <input type="hidden" id="<?= "pre_part" . $num; ?>" name="<?= "pre_part" . $num; ?>" value="<?= h($result["pre_part"]); ?>"></td>
    <td align="center" >
    <input class="amount-input" min="1" max="999" type="number" id="<?= "pre_amount" . $num; ?>"  name="<?= "pre_amount" . $num; ?>" value="<?= h($result["pre_amount"]); ?>">
    </td>
    <td align="center" ><input type="checkbox" id="<?= "chk" . $num; ?>" name="chk[]" value="YES"></td>
	    <td align="center" >
		<textarea class="remarks-input" rows="1" id="<?= "pre_remarks" . $num; ?>"  name="<?= "pre_remarks" . $num; ?>" ><?= h($result["pre_remarks"]); ?></textarea>

    </td>
  </tr>

<?php 
$count_order = $num;
$pre_type = $result["pre_type"];
$num++;
} ?>

<?php 
} else { ?>
  <tr><td colspan="5" align="center">Not Found</td></tr>
<?php 
} ?>
  <tr class="actions-row">
    <td align="center" colspan="2" valign="center"><input class="buttonBack" type="button" 
    onclick="document.location.href='./pickup_overview.php'" value="ย้อนกลับ"></td>
    
    <td align="center" colspan="3" valign="center"><input class="buttonSave" type="button" 
    onclick="javascript:save_job();" value="บันทึก"></td>
  </tr>


</table>
</div>
<input type="hidden" id="pre_id" name="pre_id" value="<?= h($id2); ?>" />
<input type="hidden" id="count_order" name="count_order" value="<?= (int) $count_order; ?>" />
<input type="hidden" id="ts_mtn_number" name="ts_mtn_number" value="<?= h($id_ma); ?>" />
<input type="hidden" id="pre_type" name="pre_type" value="<?= h($pre_type); ?>" />
</form>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="script.js"></script>
</div>
</body>
</html>

