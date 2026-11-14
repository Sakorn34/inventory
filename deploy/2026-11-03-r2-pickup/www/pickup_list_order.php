<?php
/**
 * fragment ตารางรายการเบิกของที่ยังรอดำเนินการ
 *
 * ใช้โดย `pickup_overview.php` ผ่าน AJAX เพื่อแสดงเอกสารเบิกตามวันที่ และรับคำสั่งลบ
 * เอกสารที่ยังมีสถานะ `pre` เท่านั้น ปุ่มลบส่งคำขอแบบ POST พร้อม CSRF token เพื่อกัน
 * การลบจาก URL หรือ request ปลอม
 *
 * ตารางใช้ layout แบบ A2: กว้างเต็มพื้นที่ผลลัพธ์ หมายเหตุชิดซ้าย คอลัมน์อื่นกึ่งกลาง
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

/**
 * รวมหมายเหตุของเอกสารเบิกจากหลายแถว pre_stock ให้เหลือข้อความเดียวสำหรับแสดงในตาราง
 *
 * @param string $raw ข้อความจาก GROUP_CONCAT หรือแถวเดียว
 * @return string หมายเหตุที่ normalize แล้ว
 */
function pickup_list_order_document_remarks($raw)
{
    $text = trim((string) $raw);
    if ($text === '') {
        return '';
    }

    $parts = preg_split('/\s*\/\s*/u', $text);
    if (!is_array($parts)) {
        return $text;
    }

    $unique = array();
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '' && !in_array($part, $unique, true)) {
            $unique[] = $part;
        }
    }

    return implode(' / ', $unique);
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
.pickup-list-wrap {
  font-family: 'Sarabun', 'Segoe UI', Tahoma, sans-serif;
  font-size: 15px;
  line-height: 1.5;
  overflow-x: auto;
  width: 100%;
}

.pickup-list-table {
  border-collapse: collapse;
  table-layout: fixed;
  width: 100%;
}

.pickup-list-table th,
.pickup-list-table td {
  border: 0;
  color: var(--inv-text, #1e293b);
  font-family: inherit;
  font-size: inherit;
  line-height: inherit;
  padding: 10px 8px;
  text-align: center;
  vertical-align: middle;
}

.pickup-list-table th {
  background: #e5e7eb;
  color: #334155;
  font-weight: 600;
}

.pickup-list-table tbody tr {
  border-bottom: 1px solid #eef2f7;
}

.pickup-list-table .col-remarks,
.pickup-list-table th.col-remarks {
  text-align: left;
  width: auto;
}

.pickup-list-table .col-doc a {
  color: #1d4ed8;
  font-family: ui-monospace, "Consolas", monospace;
  font-size: inherit;
  text-decoration: underline;
}

.pickup-list-table .col-no {
  width: 4%;
}

.pickup-list-table .col-date {
  width: 10%;
}

.pickup-list-table .col-doc {
  width: 12%;
}

.pickup-list-table .col-type {
  width: 14%;
}

.pickup-list-table .col-by {
  width: 7%;
}

.pickup-list-table .col-delete {
  width: 7%;
}

.pickup-list-table .col-delete form {
  margin: 0;
}

.pickup-list-empty {
  color: #64748b;
  padding: 16px;
  text-align: center;
}

.delete-button {
  background: #dc2626;
  border: 0;
  border-radius: 6px;
  color: #ffffff;
  cursor: pointer;
  font-size: inherit;
  font-weight: 600;
  padding: 6px 12px;
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

.pickup-list-remarks {
  color: inherit;
  display: block;
  font-size: inherit;
  line-height: inherit;
  max-width: 100%;
  overflow: hidden;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pickup-list-remarks.is-empty {
  color: #94a3b8;
  font-style: italic;
}

</style>
<?php
if ($message !== '') { ?>
<div class="pickup-list-message <?php echo pickup_list_order_h($message_type); ?>">
  <?php echo pickup_list_order_h($message); ?>
</div>
<?php } ?>
<div class="pickup-list-wrap">
<table class="pickup-list-table" id="table_result" name="table_result">
  <thead>
  <tr>
    <th class="col-no">No.</th>
    <th class="col-date">วันที่เบิก</th>
    <th class="col-doc">เลขเอกสาร</th>
    <th class="col-type">ประเภท</th>
    <th class="col-by">ผู้เบิก</th>
    <th class="col-remarks">หมายเหตุ</th>
    <th class="col-delete">ลบ</th>
  </tr>
  </thead>
  <tbody>
<?php

if (isset($search)) {
 /* echo '<script>alert(". $search . ")</script>';*/
  $sql = "SELECT `pre_id`, `pre_by`, `pre_type`, `ts_mtn_number`, `pre_date`,
                 GROUP_CONCAT(
                     DISTINCT NULLIF(TRIM(`pre_remarks`), '')
                     ORDER BY `pre_running` ASC
                     SEPARATOR ' / '
                 ) AS `doc_remarks`
          FROM `pre_stock`
          WHERE `pre_status` = 'pre'
          GROUP BY `pre_id`, `pre_by`, `pre_type`, `ts_mtn_number`, `pre_date`
          ORDER BY `pre_date` DESC, `pre_id` DESC";
  
  
  //  28/12/2021 ตัด where วันที่ออก ถ้าค้างให้แสดงรายการค้างทั้งหมด
  // pre_date  = '" . $search . "'  AND 
  
  $exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $num = 1;
  while ($result = db_fetch_array($exec)){
    $remarks = pickup_list_order_document_remarks(isset($result['doc_remarks']) ? $result['doc_remarks'] : '');
    $id = './pickup_list_order_detail.php?pre_id=' . urlencode($result['pre_id'])
        . '&pre_remarks=' . urlencode($remarks)
        . '&ts_mtn_number=' . urlencode($result['ts_mtn_number']);
	
//	echo $result["pre_type"];
    ?>
  <tr>
    <td class="col-no"><?php echo (int) $num; ?></td>
    <td class="col-date"><?php echo pickup_list_order_h($result["pre_date"]); ?></td>
    <td class="col-doc"><a href="<?php echo pickup_list_order_h($id); ?>"><?php echo pickup_list_order_h($result["pre_id"]); ?></a></td>
    <td class="col-type"><?php echo pickup_list_order_h($result["pre_type"]); ?></td>
    <td class="col-by"><?php echo pickup_list_order_h($result["pre_by"]); ?></td>
    <td class="col-remarks">
      <?php if ($remarks !== '') { ?>
        <span class="pickup-list-remarks" title="<?php echo pickup_list_order_h($remarks); ?>">
          <?php echo pickup_list_order_h($remarks); ?>
        </span>
      <?php } else { ?>
        <span class="pickup-list-remarks is-empty" title="-">-</span>
      <?php } ?>
    </td>
    <td class="col-delete">
      <form class="delete-pickup-form" method="post" action="pickup_list_order.php" onsubmit="return confirm('ยืนยันลบเอกสาร <?php echo pickup_list_order_h($result["pre_id"]); ?> ?');">
        <input type="hidden" name="delete_pre_id" value="<?php echo pickup_list_order_h($result["pre_id"]); ?>" />
        <input type="hidden" name="pre_date" value="<?php echo pickup_list_order_h($search); ?>" />
        <input type="hidden" name="csrf_token" value="<?php echo pickup_list_order_h($csrf_token); ?>" />
        <button class="delete-button" type="submit" data-pre-id="<?php echo pickup_list_order_h($result["pre_id"]); ?>">ลบ</button>
      </form>
    </td>
  </tr>

<?php 
$num++;
} ?>
<?php 
} else { ?>
  <tr>
    <td class="pickup-list-empty" colspan="7">Not Found</td>
  </tr>
<?php 
} ?>
  </tbody>
</table>
</div>

