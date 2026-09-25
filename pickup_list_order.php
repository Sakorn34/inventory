<?php
/**
 * fragment ตารางรายการเบิกของที่ยังรอดำเนินการ
 *
 * ใช้โดย `pickup_overview.php` ผ่าน AJAX เพื่อแสดงเอกสารเบิกตามวันที่ และรับคำสั่งลบ
 * เอกสารที่ยังมีสถานะ `pre` เท่านั้น ปุ่มลบส่งคำขอแบบ POST พร้อม CSRF token เพื่อกัน
 * การลบจาก URL หรือ request ปลอม
 *
 * ตัวอย่างการใช้งาน: $.get("pickup_list_order.php", { pre_date: "2026-05-18" })
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/connect.php';
header("Content-Type: text/html; charset=UTF-8");
header('X-Content-Type-Options: nosniff');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

// ─ Helpers ───────────────────────────────────────────────────────────────────

/**
 * escape ค่าก่อน render กลับไปยัง HTML
 *
 * @param mixed $value ค่าที่ต้องแสดงผลบนหน้าเว็บ
 * @return string ข้อความที่ปลอดภัยสำหรับ HTML
 */
function pickup_list_order_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * คืนค่า CSRF token สำหรับปุ่มลบใน fragment นี้
 *
 * @param void
 * @return string token ปัจจุบันของ session
 */
function pickup_list_order_csrf_token()
{
    if (empty($_SESSION['pickup_list_order_csrf'])) {
        $_SESSION['pickup_list_order_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pickup_list_order_csrf'];
}

// ─ Delete Action ─────────────────────────────────────────────────────────────

$message = '';
$message_type = '';
$search = isset($_REQUEST['pre_date']) ? trim((string) $_REQUEST['pre_date']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pre_id'])) {
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $deletePreId = trim((string) $_POST['delete_pre_id']);

    if (!hash_equals(pickup_list_order_csrf_token(), $csrfToken)) {
        http_response_code(400);
        $message = 'ไม่สามารถลบได้ เนื่องจาก session ไม่ถูกต้อง กรุณาโหลดหน้าใหม่';
        $message_type = 'error';
    } elseif ($deletePreId === '') {
        $message = 'ไม่พบเลขเอกสารที่ต้องการลบ';
        $message_type = 'error';
    } else {
        $deleteSql = "DELETE FROM pre_stock WHERE pre_id = ? AND pre_status = 'pre'";
        $deleteStmt = mysqli_prepare($connect, $deleteSql);

        if ($deleteStmt === false) {
            $message = 'ไม่สามารถเตรียมคำสั่งลบรายการได้';
            $message_type = 'error';
            error_log('inventory delete prepare error: ' . mysqli_error($connect));
        } else {
            mysqli_stmt_bind_param($deleteStmt, 's', $deletePreId);

            if (mysqli_stmt_execute($deleteStmt)) {
                $deletedRows = mysqli_stmt_affected_rows($deleteStmt);
                $message = $deletedRows > 0
                    ? 'ลบเอกสาร ' . $deletePreId . ' เรียบร้อยแล้ว'
                    : 'ไม่พบรายการที่ลบได้ หรือเอกสารนี้ไม่ได้อยู่ในสถานะรอเบิก';
                $message_type = $deletedRows > 0 ? 'success' : 'error';
            } else {
                $message = 'ลบรายการไม่สำเร็จ';
                $message_type = 'error';
                error_log('inventory delete execute error: ' . mysqli_stmt_error($deleteStmt));
            }

            mysqli_stmt_close($deleteStmt);
        }
    }
}

$csrf_token = pickup_list_order_csrf_token();
?>
<style>
.content {
  max-width: 1200px;
  margin-top:100px;
  font-size: 16px; 
  color:#666666;
}

.paddingResult {
  padding: 15px;
}

.pickup-list-table {
  border-collapse: collapse;
  width: 100%;
}

.pickup-list-table th,
.pickup-list-table td {
  border: 0;
  padding: 6px 10px;
}

.pickup-list-table th {
  background: #e1e1e1;
}

.pickup-list-table tr:not(:first-child) {
  border-bottom: 1px solid #eeeeee;
}

.delete-button {
  background: #dc2626;
  border: 0;
  border-radius: 4px;
  color: #ffffff;
  cursor: pointer;
  padding: 4px 10px;
}

.delete-button:hover {
  background: #b91c1c;
}

.pickup-list-message {
  border-radius: 6px;
  margin: 8px auto 12px;
  max-width: 760px;
  padding: 8px 12px;
  text-align: center;
}

.pickup-list-message.success {
  background: #dcfce7;
  color: #166534;
}

.pickup-list-message.error {
  background: #fee2e2;
  color: #991b1b;
}

</style>
<?php
if ($message !== '') { ?>
<div class="pickup-list-message <?php echo pickup_list_order_h($message_type); ?>">
  <?php echo pickup_list_order_h($message); ?>
</div>
<?php } ?>
<table class="pickup-list-table" id="table_result" name="table_result">
  <tr>
    <th width="20"> <div align="center">No.</div></th>
	<th width="50"> <div align="center">วันที่เบิก</div></th>
    <th width="50"> <div align="center">เลขเอกสาร</div></th>
    <th width="20"> <div align="center">ประเภท</div></th>
    <th width="20"> <div align="center">ผู้เบิก</div></th>
    <th width="20"> <div align="center">ลบ</div></th>
  </tr>
<?php

if (isset($search)) {
 /* echo '<script>alert(". $search . ")</script>';*/
  $sql = "SELECT  pre_id, pre_by, pre_type, ts_mtn_number ,pre_date
  
  FROM pre_stock  WHERE pre_status = 'pre'  
  
  GROUP BY pre_id, pre_by, pre_type, ts_mtn_number,pre_date ";
  
  
  //  28/12/2021 ตัด where วันที่ออก ถ้าค้างให้แสดงรายการค้างทั้งหมด
  // pre_date  = '" . $search . "'  AND 
  
  $exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;
  while ($result = db_fetch_array($exec)){
    $remarks = isset($result['pre_remarks']) ? $result['pre_remarks'] : '';
    $id = './pickup_list_order_detail.php?pre_id=' . urlencode($result['pre_id'])
        . '&pre_remarks=' . urlencode($remarks)
        . '&ts_mtn_number=' . urlencode($result['ts_mtn_number']);
	
//	echo $result["pre_type"];
    ?>
  <tr class="paddingResult">
    <td><div align="center"><?php echo (int) $num; ?></div></td>
	<td><div align="center"><?php echo pickup_list_order_h($result["pre_date"]); ?></div></td>
    <td><div align="center"><u><a href="<?php echo pickup_list_order_h($id); ?>"><?php echo pickup_list_order_h($result["pre_id"]); ?></a></u></div></td>
    <td><div align="center"><?php echo pickup_list_order_h($result["pre_type"]); ?></div></td>
    <td><div align="center"><?php echo pickup_list_order_h($result["pre_by"]); ?></div></td>
    <td>
      <div align="center">
        <form class="delete-pickup-form" method="post" action="pickup_list_order.php" onsubmit="return confirm('ยืนยันลบเอกสาร <?php echo pickup_list_order_h($result["pre_id"]); ?> ?');">
          <input type="hidden" name="delete_pre_id" value="<?php echo pickup_list_order_h($result["pre_id"]); ?>" />
          <input type="hidden" name="pre_date" value="<?php echo pickup_list_order_h($search); ?>" />
          <input type="hidden" name="csrf_token" value="<?php echo pickup_list_order_h($csrf_token); ?>" />
          <button class="delete-button" type="submit" data-pre-id="<?php echo pickup_list_order_h($result["pre_id"]); ?>">ลบ</button>
        </form>
      </div>
    </td>
  </tr>

<?php 
$num++;
} ?>
<?php 
} else { ?>
  <option value="">Not Found</option>
<?php 
} ?>
</table>

