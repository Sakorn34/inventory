<?php
/**
 * หน้ารายละเอียดเอกสารเบิกของที่ยังรอคลังจ่าย
 *
 * รับเลขเอกสารจากเมนูรายการเบิก แสดงรายการ part ที่รอเบิก เพิ่ม/ลบอะไหล่ในเอกสาร
 * และส่งต่อไปบันทึก log เมื่อผู้ใช้ยืนยันรหัสผ่านแล้ว ใช้ prepared statement กับ
 * pre_id จาก URL เพื่อกัน SQL Injection และ escape output ทุกจุดที่ render
 *
 * ตัวอย่างการใช้งาน: pickup_list_order_detail.php?pre_id=260518-132656
 */
require "connect.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

header("Content-Type: text/html; charset=UTF-8");
header('X-Content-Type-Options: nosniff');
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

/**
 * คืนค่า CSRF token สำหรับเพิ่ม/ลบอะไหล่ในเอกสารนี้
 *
 * @return string token ปัจจุบันของ session
 */
function pickup_list_order_detail_csrf_token()
{
    if (empty($_SESSION['pickup_list_order_detail_csrf'])) {
        $_SESSION['pickup_list_order_detail_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pickup_list_order_detail_csrf'];
}

// ─ Request State ─────────────────────────────────────────────────────────────

$count_order = 0;
$pre_type = "";
$id2 = isset($_GET['pre_id']) ? trim((string) $_GET['pre_id']) : '';
$id_ma = isset($_GET['ts_mtn_number']) ? trim((string) $_GET['ts_mtn_number']) : '';
$pre_remarks = isset($_GET['pre_remarks']) ? trim((string) $_GET['pre_remarks']) : '';
$rows = array();
$query_error = '';
$csrf_token = pickup_list_order_detail_csrf_token();

if ($id2 !== '') {
    $sql = "SELECT pre_stock.pre_running, pre_stock.pre_product, pre_stock.pre_part, pre_stock.pre_type,
                   pre_stock.pre_amount, pre_stock.pre_remarks, pre_stock.pre_status, part.part_name
            FROM pre_stock
            INNER JOIN part ON pre_stock.pre_part = part.part_id
            WHERE pre_stock.pre_id = ? AND pre_stock.pre_status = 'pre'
            ORDER BY pre_stock.pre_running ASC";
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
                $row_pre_running,
                $row_pre_product,
                $row_pre_part,
                $row_pre_type,
                $row_pre_amount,
                $row_pre_remarks,
                $row_pre_status,
                $row_part_name
            );

            while (mysqli_stmt_fetch($stmt)) {
                $rows[] = array(
                    'pre_running' => $row_pre_running,
                    'pre_product' => $row_pre_product,
                    'pre_part' => $row_pre_part,
                    'pre_type' => $row_pre_type,
                    'pre_amount' => $row_pre_amount,
                    'pre_remarks' => $row_pre_remarks,
                    'pre_status' => $row_pre_status,
                    'part_name' => $row_part_name,
                );
            }
        }

        mysqli_stmt_close($stmt);
    }
}

$can_edit = ($id2 !== '' && $query_error === '' && !empty($rows));

$products = array();
$default_product_id = 0;
$document_product_name = !empty($rows) ? (string) $rows[0]['pre_product'] : '';
$productStmt = mysqli_prepare($connect, 'SELECT `pd_id`, `pd_name` FROM `product` ORDER BY `pd_name` ASC');
if ($productStmt !== false) {
    mysqli_stmt_execute($productStmt);
    mysqli_stmt_bind_result($productStmt, $row_pd_id, $row_pd_name);
    while (mysqli_stmt_fetch($productStmt)) {
        $pdId = (int) $row_pd_id;
        $pdName = (string) $row_pd_name;
        $products[] = array(
            'pd_id' => $pdId,
            'pd_name' => $pdName,
        );
        if ($default_product_id === 0 && $document_product_name !== '' && $pdName === $document_product_name) {
            $default_product_id = $pdId;
        }
    }
    mysqli_stmt_close($productStmt);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<?php
if (!defined('INVENTORY_UI_CSS')) {
    define('INVENTORY_UI_CSS', true);
}
?>
<link rel="stylesheet" href="css/inventory-ui.css">

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

/**
 * โหลดหน้าเอกสารเดิมอีกครั้งหลังเพิ่มหรือลบอะไหล่
 *
 * parameters: ไม่มี อ่าน pre_id และ ts_mtn_number จาก hidden field
 * return value: ไม่มี ใช้ redirect กลับหน้าเดิม หรือไปภาพรวมเมื่อเอกสารว่าง
 * signals/events: เรียกหลัง AJAX เพิ่ม/ลบสำเร็จ
 */
function reload_document(goOverview) {
    if (goOverview) {
        window.location = './pickup_overview.php';
        return;
    }

    var preId = document.getElementById('pre_id').value;
    var tsMtn = document.getElementById('ts_mtn_number').value;
    var url = 'pickup_list_order_detail.php?pre_id=' + encodeURIComponent(preId);
    if (tsMtn) {
        url += '&ts_mtn_number=' + encodeURIComponent(tsMtn);
    }
    window.location = url;
}

/**
 * ส่งคำสั่งเพิ่มหรือลบอะไหล่ไปยัง endpoint พร้อม CSRF token
 *
 * @param object payload ข้อมูล POST ที่ต้องการส่ง
 * @param function onSuccess callback เมื่อบันทึกสำเร็จ
 * return value: ไม่มี ใช้ AJAX
 * signals/events: เรียกจาก add_part และ delete_part
 */
function post_edit(payload, onSuccess) {
    $.ajax({
        url: 'pickup_list_order_edit.php',
        type: 'post',
        dataType: 'json',
        data: payload,
        success: function(response) {
            if (response && response.ok) {
                alert(response.message || 'บันทึกเรียบร้อยแล้ว');
                onSuccess(response);
                return;
            }
            alert((response && response.message) ? response.message : 'บันทึกไม่สำเร็จ');
        },
        error: function(xhr) {
            var message = 'บันทึกไม่สำเร็จ';
            try {
                var response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    message = response.message;
                }
            } catch (e) {}
            alert(message);
        }
    });
}

/**
 * แสดงแจ้งเตือนด้านบนหน้า (สำเร็จ / ไม่สำเร็จ / error)
 *
 * @param string message ข้อความที่ต้องการแสดง
 * @param string type ชนิดแจ้งเตือน success หรือ error
 * return value: ไม่มี
 * signals/events: เรียกหลังบันทึกแก้ไขเอกสาร
 */
function show_pickup_notice(message, type) {
    var box = document.getElementById('pickup_notice');
    if (!box) {
        alert(message);
        return;
    }

    box.className = 'pickup-notice pickup-notice--' + type + ' is-visible';
    box.textContent = message;

    if (show_pickup_notice._timer) {
        clearTimeout(show_pickup_notice._timer);
    }
    show_pickup_notice._timer = setTimeout(function() {
        box.classList.remove('is-visible');
    }, 6000);
}

/**
 * normalize จำนวนเพื่อเปรียบเทียบว่าแก้ไขหรือไม่
 *
 * @param mixed value ค่าจำนวนจาก input
 * @return string จำนวนรูปแบบทศนิยม 2 ตำแหน่ง
 */
function normalize_edit_amount(value) {
    var num = parseFloat(String(value).replace(',', ''));
    if (isNaN(num)) {
        return '';
    }

    return num.toFixed(2);
}

/**
 * normalize หมายเหตุเพื่อเปรียบเทียบว่าแก้ไขหรือไม่
 *
 * @param mixed value ข้อความหมายเหตุ
 * @return string ข้อความที่ trim แล้ว
 */
function normalize_edit_remarks(value) {
    return String(value || '').trim();
}

/**
 * ตรวจว่าแถวรายการที่ n มีการแก้จำนวนหรือหมายเหตุหรือไม่
 *
 * @param number rowIndex ลำดับแถวในตาราง
 * @return boolean true เมื่อมีการแก้ไข
 */
function document_row_is_dirty(rowIndex) {
    var amountEl = document.getElementById('pre_amount' + rowIndex);
    var remarksEl = document.getElementById('pre_remarks' + rowIndex);
    if (!amountEl || !remarksEl) {
        return false;
    }

    var originalAmount = amountEl.getAttribute('data-original-amount');
    var originalRemarks = remarksEl.getAttribute('data-original-remarks');

    return normalize_edit_amount(amountEl.value) !== normalize_edit_amount(originalAmount)
        || normalize_edit_remarks(remarksEl.value) !== normalize_edit_remarks(originalRemarks);
}

/**
 * ตรวจว่ามีการแก้ไขรายการในตารางอย่างน้อย 1 แถวหรือไม่
 *
 * parameters: ไม่มี
 * @return boolean true เมื่อมีการแก้ไข
 */
function document_has_unsaved_edits() {
    var count = parseInt(document.getElementById('count_order').value, 10);
    if (!count) {
        return false;
    }

    for (var i = 1; i <= count; i++) {
        if (document_row_is_dirty(i)) {
            return true;
        }
    }

    return false;
}

/**
 * แสดงหรือซ่อนปุ่มบันทึกแก้ไขเอกสารตามสถานะการแก้ไข
 *
 * parameters: ไม่มี
 * return value: ไม่มี
 * signals/events: เรียกเมื่อผู้ใช้แก้ input ในตาราง
 */
function update_save_document_button() {
    var button = document.getElementById('btn_save_document_edits');
    if (!button) {
        return;
    }

    if (document_has_unsaved_edits()) {
        button.classList.remove('is-hidden');
    } else {
        button.classList.add('is-hidden');
    }
}

/**
 * อัปเดตค่า baseline หลังบันทึกแก้ไขสำเร็จ
 *
 * parameters: ไม่มี
 * return value: ไม่มี
 * signals/events: เรียกหลัง save_document สำเร็จ
 */
function sync_document_edit_baselines() {
    var count = parseInt(document.getElementById('count_order').value, 10);
    if (!count) {
        return;
    }

    for (var i = 1; i <= count; i++) {
        var amountEl = document.getElementById('pre_amount' + i);
        var remarksEl = document.getElementById('pre_remarks' + i);
        if (!amountEl || !remarksEl) {
            continue;
        }
        amountEl.setAttribute('data-original-amount', amountEl.value);
        remarksEl.setAttribute('data-original-remarks', remarksEl.value);
    }
}

/**
 * เปิด popup ฟอร์มเพิ่มอะไหล่แบบเดียวกับหน้าเบิกแยกรายการ
 *
 * parameters: ไม่มี
 * return value: ไม่มี
 * signals/events: เรียกจากปุ่มเพิ่มอะไหล่ในตาราง
 */
function open_add_part_modal() {
    var modal = document.getElementById('add_part_modal');
    if (!modal) {
        return;
    }
    reset_add_part_modal();
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
}

/**
 * ปิด popup ฟอร์มเพิ่มอะไหล่
 *
 * parameters: ไม่มี
 * return value: ไม่มี
 * signals/events: เรียกจากปุ่มยกเลิกหรือคลิกพื้นหลัง
 */
function close_add_part_modal() {
    var modal = document.getElementById('add_part_modal');
    if (!modal) {
        return;
    }
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
}

/**
 * รีเซ็ตค่าในฟอร์ม popup ก่อนแสดง
 *
 * parameters: ไม่มี
 * return value: ไม่มี
 * signals/events: เรียกตอนเปิด modal
 */
function reset_add_part_modal() {
    var pd = document.getElementById('modal_pd_sp');
    var sn = document.getElementById('modal_sn_sp');
    if (pd) {
        if (typeof PICKUP_DOC_DEFAULT_PD !== 'undefined' && PICKUP_DOC_DEFAULT_PD > 0) {
            pd.value = String(PICKUP_DOC_DEFAULT_PD);
            load_modal_parts();
        } else {
            pd.value = '';
        }
    }
    if (sn) {
        sn.innerHTML = '<option value="">--เลือก part--</option>';
    }
    document.getElementById('modal_stock_display').textContent = '-';
    document.getElementById('modal_add_amount').value = '';
    document.getElementById('modal_add_remarks').value = '';
}

/**
 * โหลดรายการ Part ตาม Product ที่เลือกใน popup
 *
 * parameters: ไม่มี อ่านค่าจาก modal_pd_sp
 * return value: ไม่มี
 * signals/events: เรียกเมื่อเปลี่ยน Product หรือตอนเปิด modal
 */
function load_modal_parts() {
    var productId = document.getElementById('modal_pd_sp').value;
    var sn = document.getElementById('modal_sn_sp');
    document.getElementById('modal_stock_display').textContent = '-';
    if (!productId) {
        sn.innerHTML = '<option value="">--เลือก part--</option>';
        return;
    }
    $.get('list_part.php', { pd_sp1: productId }, function(data) {
        sn.innerHTML = data;
    });
}

/**
 * แสดงจำนวน stock ของ Part ที่เลือกใน popup
 *
 * parameters: ไม่มี อ่านค่าจาก modal_sn_sp
 * return value: ไม่มี
 * signals/events: เรียกเมื่อเปลี่ยน Part
 */
function load_modal_stock() {
    var partId = document.getElementById('modal_sn_sp').value;
    var display = document.getElementById('modal_stock_display');
    if (!partId) {
        display.textContent = '-';
        return;
    }
    $.get('list_stock.php', { sn_sp1: partId }, function(data) {
        var tmp = document.createElement('select');
        tmp.innerHTML = data;
        var stock = tmp.options.length ? tmp.options[0].value : '';
        display.textContent = (stock === '' || stock === 'none') ? '-' : stock;
    });
}

/**
 * บันทึกจำนวนและหมายเหตุของทุกแถวลงเอกสารรอจ่าย (ไม่ตัดสต็อก)
 *
 * parameters: ไม่มี อ่านค่าจากตารางในหน้า
 * return value: ไม่มี โหลดหน้าใหม่เมื่อบันทึกสำเร็จ
 * signals/events: เรียกจากปุ่มบันทึกแก้ไขเอกสาร
 */
function save_document_edits() {
    var count = parseInt(document.getElementById('count_order').value, 10);
    if (!count) {
        show_pickup_notice('ไม่พบรายการสำหรับบันทึก', 'error');
        return;
    }

    if (!document_has_unsaved_edits()) {
        update_save_document_button();
        return;
    }

    var payload = {
        action: 'save_document',
        csrf_token: document.getElementById('csrf_token').value,
        pre_id: document.getElementById('pre_id').value,
        count: count
    };

    for (var i = 1; i <= count; i++) {
        payload['pre_running' + i] = document.getElementById('pre_running' + i).value;
        payload['pre_amount' + i] = document.getElementById('pre_amount' + i).value;
        payload['pre_remarks' + i] = document.getElementById('pre_remarks' + i).value;
    }

    var saveButton = document.getElementById('btn_save_document_edits');
    if (saveButton) {
        saveButton.disabled = true;
    }

    $.ajax({
        url: 'pickup_list_order_edit.php',
        type: 'post',
        dataType: 'json',
        data: payload,
        success: function(response) {
            if (response && response.ok) {
                sync_document_edit_baselines();
                update_save_document_button();
                show_pickup_notice(response.message || 'บันทึกแก้ไขเอกสารสำเร็จ', 'success');
                return;
            }
            show_pickup_notice(
                (response && response.message) ? response.message : 'บันทึกแก้ไขเอกสารไม่สำเร็จ',
                'error'
            );
        },
        error: function(xhr) {
            var message = 'เกิดข้อผิดพลาด ไม่สามารถบันทึกแก้ไขเอกสารได้';
            try {
                var response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    message = response.message;
                }
            } catch (e) {}
            show_pickup_notice(message, 'error');
        },
        complete: function() {
            if (saveButton) {
                saveButton.disabled = false;
            }
        }
    });
}

/**
 * บันทึกรายการจาก popup เข้าเอกสารเบิกปัจจุบัน
 *
 * parameters: ไม่มี อ่านค่าจากฟอร์มใน modal
 * return value: ไม่มี โหลดหน้าใหม่เมื่อเพิ่มสำเร็จ
 * signals/events: เรียกจากปุ่มบันทึกรายการใน modal
 */
function submit_add_part_modal() {
    var partId = document.getElementById('modal_sn_sp').value;
    var amount = document.getElementById('modal_add_amount').value;
    var remarks = document.getElementById('modal_add_remarks').value;

    if (!document.getElementById('modal_pd_sp').value) {
        alert('กรุณาเลือก Product');
        return;
    }
    if (!partId) {
        alert('กรุณาเลือก Part');
        return;
    }
    if (!amount || Number(amount) <= 0) {
        alert('กรุณากรอกจำนวนเบิก');
        document.getElementById('modal_add_amount').focus();
        return;
    }

    post_edit({
        action: 'add',
        csrf_token: document.getElementById('csrf_token').value,
        pre_id: document.getElementById('pre_id').value,
        part_id: partId,
        amount: amount,
        remarks: remarks
    }, function() {
        close_add_part_modal();
        reload_document(false);
    });
}

/**
 * ลบอะไหล่หนึ่งรายการออกจากเอกสารเบิกปัจจุบัน
 *
 * @param number preRunning id แถวใน pre_stock
 * @param string partName ชื่ออะไหล่สำหรับยืนยันก่อนลบ
 * return value: ไม่มี โหลดหน้าใหม่เมื่อลบสำเร็จ
 * signals/events: เรียกจากปุ่มลบในแต่ละแถว
 */
function delete_part(preRunning, partName) {
    if (!confirm('ยืนยันลบอะไหล่ "' + partName + '" ออกจากเอกสารนี้?')) {
        return;
    }

    post_edit({
        action: 'delete',
        csrf_token: document.getElementById('csrf_token').value,
        pre_id: document.getElementById('pre_id').value,
        pre_running: preRunning
    }, function(response) {
        reload_document(!!response.empty);
    });
}

var PICKUP_DOC_DEFAULT_PD = <?= (int) $default_product_id; ?>;

</script>
<style type="text/css">
body.inv-app {
  margin: 0;
}

.amount-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-family: inherit;
  font-size: inherit;
  padding: 7px 8px;
  text-align: right;
  width: 90px;
}

.remarks-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-family: inherit;
  font-size: inherit;
  min-height: 34px;
  padding: 7px 8px;
  width: 95%;
}

.pickup-toolbar {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}

.pickup-toolbar .buttonBack,
.pickup-toolbar .buttonAdd,
.pickup-toolbar .buttonSaveEdit,
.pickup-toolbar .buttonSave {
  min-width: 0;
  padding: 11px 20px;
}

.buttonBack,
.buttonSave,
.buttonSaveEdit,
.buttonAdd,
.buttonDelete {
  border: none;
  border-radius: 8px;
  color: white;
  cursor: pointer;
  font-family: inherit;
  font-size: inherit;
  font-weight: 600;
  min-width: 120px;
  padding: 12px 28px;
}

.buttonBack {
  background-color: #ef4444;
}

.buttonSave {
  background-color: #22c55e;
}

.buttonSaveEdit {
  background-color: #2563eb;
}

.buttonSaveEdit.is-hidden {
  display: none;
}

.pickup-notice {
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
  display: none;
  font-family: inherit;
  font-size: inherit;
  font-weight: 600;
  left: 50%;
  max-width: 560px;
  padding: 12px 18px;
  position: fixed;
  top: 16px;
  transform: translateX(-50%);
  width: calc(100% - 32px);
  z-index: 2500;
}

.pickup-notice.is-visible {
  display: block;
}

.pickup-notice--success {
  background: #dcfce7;
  border: 1px solid #86efac;
  color: #166534;
}

.pickup-notice--error {
  background: #fee2e2;
  border: 1px solid #fca5a5;
  color: #991b1b;
}

.buttonAdd {
  background-color: #0d9488;
  min-width: 110px;
  padding: 10px 18px;
}

.buttonDelete {
  background-color: #ef4444;
  min-width: 64px;
  padding: 8px 12px;
}

.buttonBack:hover,
.buttonDelete:hover {
  background-color: #dc2626;
}

.buttonSave:hover {
  background-color: #16a34a;
}

.buttonSaveEdit:hover {
  background-color: #1d4ed8;
}

.buttonAdd:hover {
  background-color: #0f766e;
}

.pickup-modal {
  align-items: center;
  display: none;
  inset: 0;
  justify-content: center;
  position: fixed;
  z-index: 2000;
}

.pickup-modal__backdrop {
  background: rgba(15, 23, 42, 0.45);
  inset: 0;
  position: absolute;
}

.pickup-modal__panel {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
  max-height: 90vh;
  max-width: 560px;
  overflow: auto;
  padding: 22px 24px 20px;
  position: relative;
  width: calc(100% - 32px);
  z-index: 1;
}

.pickup-modal__title {
  font-family: inherit;
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0 0 6px;
  text-align: center;
}

.pickup-modal__subtitle {
  color: #64748b;
  font-family: inherit;
  font-size: 0.9rem;
  margin: 0 0 16px;
  text-align: center;
}

.pickup-modal__form {
  border-collapse: collapse;
  width: 100%;
}

.pickup-modal__form td {
  padding: 8px 6px;
  vertical-align: middle;
}

.pickup-modal__form td:first-child {
  color: #334155;
  font-weight: 600;
  text-align: right;
  white-space: nowrap;
  width: 130px;
}

.pickup-modal__form select,
.pickup-modal__form input[type="number"],
.pickup-modal__form textarea {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  box-sizing: border-box;
  font-family: inherit;
  font-size: inherit;
  padding: 8px 10px;
  width: 100%;
}

.pickup-modal__form textarea {
  min-height: 72px;
  resize: vertical;
}

.pickup-modal__stock {
  color: #0f766e;
  font-weight: 700;
}

.pickup-modal__actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  margin-top: 18px;
}
</style>
</head>
<body class="inv-app">
<?php require __DIR__ . '/title-h.php'; ?>
<main class="inv-main">
<div id="pickup_notice" class="pickup-notice" role="alert" aria-live="polite"></div>
<?php
echo '<header class="inv-page-header"><h1 class="inv-page-title">เลขเอกสารเบิกของ : ' . h($id2) . '</h1></header>';

if ($id2 === '') {
    echo '<p style="color:red;">ไม่พบเลขเอกสารเบิกของ</p>';
} elseif ($query_error !== '') {
    echo '<p style="color:red;">' . h($query_error) . '</p>';
}
?>

<form id="form_add_log" name="form_add_log" method="post" >
<section class="inv-card">
  <div class="inv-card__head"><i class="fa fa-list"></i> รายการ Part รอจ่าย</div>
  <div class="inv-card__body inv-card__body--flush">
    <div class="inv-table-wrap">
<table class="inv-table pickup-doc-table" id="table_result" name="table_result">
  <thead>
  <tr>
    <th class="inv-col-num">No.</th>
    <th>รายการ Part</th>
    <th class="inv-col-num">จำนวน</th>
    <th class="inv-col-num">เลือก</th>
    <th>หมายเหตุ</th>
    <th class="inv-col-actions">ลบ</th>
  </tr>
  </thead>
  <tbody>
<?php
$num = 1;

if (!empty($rows)) {
  foreach ($rows as $result) { ?>
  <tr>
    <td class="inv-col-num">
    <?php echo (int) $num; ?>
    <input type="hidden" id="<?= "pre_id" . $num; ?>" name="<?= "pre_id" . $num; ?>" value="<?= $num; ?>">
    <input type="hidden" id="<?= "pre_running" . $num; ?>" name="<?= "pre_running" . $num; ?>" value="<?= (int) $result['pre_running']; ?>">
    <input type="hidden" id="<?= "pre_pd" . $num; ?>" name="<?= "pre_pd" . $num; ?>" value="<?= h($result["pre_product"]); ?>">
    </td>
    <td><?= h($result["part_name"]); ?>
    <input type="hidden" id="<?= "pre_part" . $num; ?>" name="<?= "pre_part" . $num; ?>" value="<?= h($result["pre_part"]); ?>"></td>
    <td class="inv-col-num">
    <input class="amount-input js-doc-edit-field" min="1" max="999" type="number" id="<?= "pre_amount" . $num; ?>" name="<?= "pre_amount" . $num; ?>" value="<?= h($result["pre_amount"]); ?>" data-original-amount="<?= h($result["pre_amount"]); ?>">
    </td>
    <td class="inv-col-num"><input type="checkbox" id="<?= "chk" . $num; ?>" name="chk[]" value="YES"></td>
    <td>
    <textarea class="remarks-input js-doc-edit-field" rows="1" id="<?= "pre_remarks" . $num; ?>" name="<?= "pre_remarks" . $num; ?>" data-original-remarks="<?= h($result["pre_remarks"]); ?>"><?= h($result["pre_remarks"]); ?></textarea>

    </td>
    <td class="inv-col-actions">
      <button type="button" class="buttonDelete js-delete-part" data-running="<?= (int) $result['pre_running']; ?>" data-name="<?= h($result['part_name']); ?>">ลบ</button>
    </td>
  </tr>

<?php 
$count_order = $num;
$pre_type = $result["pre_type"];
$num++;
} ?>

<?php 
} else { ?>
  <tr><td colspan="6" class="pickup-list-empty">ไม่พบรายการรอเบิกสำหรับเอกสารนี้</td></tr>
<?php 
} ?>
  </tbody>
</table>
    </div>
  </div>
  <div class="inv-card__footer">
    <div class="pickup-toolbar">
      <input class="buttonBack" type="button" onclick="document.location.href='./pickup_overview.php'" value="ย้อนกลับ" />
      <?php if ($can_edit) { ?>
        <button type="button" class="buttonAdd" onclick="open_add_part_modal();">+ เพิ่มอะไหล่</button>
        <button type="button" id="btn_save_document_edits" class="buttonSaveEdit is-hidden" onclick="save_document_edits();">บันทึกแก้ไขเอกสาร</button>
      <?php } ?>
      <?php if ($count_order > 0) { ?>
        <input class="buttonSave" type="button" onclick="javascript:save_job();" value="ยืนยันจ่ายของ" />
      <?php } ?>
    </div>
  </div>
</section>
<input type="hidden" id="pre_id" name="pre_id" value="<?= h($id2); ?>" />
<input type="hidden" id="count_order" name="count_order" value="<?= (int) $count_order; ?>" />
<input type="hidden" id="ts_mtn_number" name="ts_mtn_number" value="<?= h($id_ma); ?>" />
<input type="hidden" id="pre_type" name="pre_type" value="<?= h($pre_type); ?>" />
<input type="hidden" id="csrf_token" name="csrf_token" value="<?= h($csrf_token); ?>" />
</form>

<?php if ($can_edit) { ?>
<div id="add_part_modal" class="pickup-modal" aria-hidden="true" role="dialog" aria-labelledby="add_part_modal_title">
  <div class="pickup-modal__backdrop" onclick="close_add_part_modal();"></div>
  <div class="pickup-modal__panel">
    <h2 id="add_part_modal_title" class="pickup-modal__title">ลงทะเบียนรายการเบิก</h2>
    <p class="pickup-modal__subtitle">เลือก Product และ Part เหมือนตอนสร้างใบเบิก แล้วกดบันทึกรายการ</p>
    <table class="pickup-modal__form">
      <tr>
        <td>Product</td>
        <td>
          <select id="modal_pd_sp" name="modal_pd_sp">
            <option value="">-- เลือก Product --</option>
            <?php foreach ($products as $product) { ?>
              <option value="<?= (int) $product['pd_id']; ?>"<?= ((int) $product['pd_id'] === $default_product_id) ? ' selected="selected"' : ''; ?>>
                <?= h($product['pd_name']); ?>
              </option>
            <?php } ?>
          </select>
        </td>
      </tr>
      <tr>
        <td>Part</td>
        <td>
          <select id="modal_sn_sp" name="modal_sn_sp">
            <option value="">-- เลือก part --</option>
          </select>
        </td>
      </tr>
      <tr>
        <td>จำนวน Stock</td>
        <td><span id="modal_stock_display" class="pickup-modal__stock">-</span></td>
      </tr>
      <tr>
        <td>จำนวนเบิก</td>
        <td><input type="number" id="modal_add_amount" name="modal_add_amount" min="1" step="0.01" placeholder="จำนวน" /></td>
      </tr>
      <tr>
        <td>หมายเหตุ</td>
        <td><textarea id="modal_add_remarks" name="modal_add_remarks" maxlength="500" placeholder="หมายเหตุ (ถ้ามี)"></textarea></td>
      </tr>
    </table>
    <div class="pickup-modal__actions">
      <button type="button" class="buttonBack" onclick="close_add_part_modal();">ยกเลิก</button>
      <button type="button" class="buttonAdd" onclick="submit_add_part_modal();">บันทึกรายการ</button>
    </div>
  </div>
</div>
<?php } ?>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="script.js"></script>
<script type="text/javascript">
(function() {
    var table = document.getElementById('table_result');
    if (table) {
        table.addEventListener('click', function(event) {
            var button = event.target.closest ? event.target.closest('.js-delete-part') : null;
            if (!button) {
                return;
            }
            delete_part(button.getAttribute('data-running'), button.getAttribute('data-name'));
        });
    }

    var pdSelect = document.getElementById('modal_pd_sp');
    if (pdSelect) {
        pdSelect.addEventListener('change', load_modal_parts);
    }
    var snSelect = document.getElementById('modal_sn_sp');
    if (snSelect) {
        snSelect.addEventListener('change', load_modal_stock);
    }
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            close_add_part_modal();
        }
    });

    var editFields = document.querySelectorAll('.js-doc-edit-field');
    for (var f = 0; f < editFields.length; f++) {
        editFields[f].addEventListener('input', update_save_document_button);
        editFields[f].addEventListener('change', update_save_document_button);
    }
    update_save_document_button();
})();
</script>
</main>
</body>
</html>
