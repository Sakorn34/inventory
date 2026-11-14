<?php
/**
 * fragment ตารางรายการเบิกของที่ยังรอดำเนินการ
 *
 * ใช้โดย `pickup_overview.php` ผ่าน AJAX เพื่อแสดงเอกสารรอคลังจ่ายทั้งหมด และรับคำสั่งลบ
 * เอกสารที่ยังมีสถานะ `pre` เท่านั้น ปุ่มลบส่งคำขอแบบ POST พร้อม CSRF token เพื่อกัน
 * การลบจาก URL หรือ request ปลอม
 *
 * ตารางใช้ layout แบบ A2: กว้างเต็มพื้นที่ผลลัพธ์ หมายเหตุชิดซ้าย คอลัมน์อื่นกึ่งกลาง
 * คอลัมน์「เบิกของ」สรุป pre_check ต่อเอกสาร (จากหน้ามือถือ pickup_prep_board)
 *
 * ตัวอย่างการใช้งาน: $.get("pickup_list_order.php", { pre_date_from: "2026-05-01", pre_date_to: "2026-05-18" })
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/pickup_constants.php';
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
/**
 * ตรวจและคืนค่าวันที่รูปแบบ Y-m-d สำหรับกรอง
 *
 * @param string $value วันที่จาก request
 * @return string|null วันที่ที่ valid หรือ null
 */
/**
 * ตรวจว่าช่วงวันที่ไม่เกินจำนวนวันที่อนุญาต
 *
 * @param string|null $dateFrom วันเริ่ม Y-m-d
 * @param string|null $dateTo วันสิ้นสุด Y-m-d
 * @return array{ok:bool,message:string} ผลการตรวจ
 */
function pickup_list_order_validate_date_range($dateFrom, $dateTo)
{
    if ($dateFrom === null || $dateTo === null) {
        return array('ok' => true, 'message' => '');
    }

    $from = DateTime::createFromFormat('Y-m-d', $dateFrom);
    $to = DateTime::createFromFormat('Y-m-d', $dateTo);
    if (!$from || !$to) {
        return array('ok' => true, 'message' => '');
    }

    $inclusiveDays = (int) $from->diff($to)->days + 1;
    $maxDays = (int) PICKUP_SEARCH_MAX_RANGE_DAYS;
    if ($inclusiveDays > $maxDays) {
        return array(
            'ok' => false,
            'message' => 'ช่วงวันที่ค้นหาได้ไม่เกิน ' . $maxDays . ' วัน (ที่เลือก ' . $inclusiveDays . ' วัน)',
        );
    }

    return array('ok' => true, 'message' => '');
}

function pickup_list_order_parse_date($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
}

/**
 * คืนข้อความและ class สำหรับคอลัมน์สถานะจัดของ (จาก pre_check)
 *
 * @param int $preparedCount จำนวนแถวที่ pre_check = YES
 * @param int $totalLines จำนวนแถวทั้งหมดในเอกสาร
 * @return array{label: string, class: string, title: string} ข้อมูลสำหรับ render
 */
function pickup_list_order_prep_status($preparedCount, $totalLines)
{
    $prepared = max(0, (int) $preparedCount);
    $total = max(0, (int) $totalLines);
    if ($total <= 0) {
        return array(
            'label' => '-',
            'class' => 'pickup-list-prep is-none',
            'title' => 'ไม่มีรายการในเอกสาร',
        );
    }
    if ($prepared <= 0) {
        return array(
            'label' => 'รอ',
            'class' => 'pickup-list-prep is-pending',
            'title' => 'ยังไม่จัดบนมือถือ (0/' . $total . ' รายการ)',
        );
    }
    if ($prepared >= $total) {
        return array(
            'label' => 'เสร็จ',
            'class' => 'pickup-list-prep is-done',
            'title' => 'จัดครบบนมือถือแล้ว (' . $total . '/' . $total . ' รายการ)',
        );
    }

    return array(
        'label' => 'กำลัง ' . $prepared . '/' . $total,
        'class' => 'pickup-list-prep is-partial',
        'title' => 'จัดบนมือถือแล้ว ' . $prepared . ' จาก ' . $total . ' รายการ',
    );
}

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
$filterDateFrom = pickup_list_order_parse_date(isset($_REQUEST['pre_date_from']) ? $_REQUEST['pre_date_from'] : '');
$filterDateTo = pickup_list_order_parse_date(isset($_REQUEST['pre_date_to']) ? $_REQUEST['pre_date_to'] : '');
if ($filterDateFrom === null && $filterDateTo === null && isset($_REQUEST['pre_date'])) {
    $legacyDate = pickup_list_order_parse_date($_REQUEST['pre_date']);
    if ($legacyDate !== null) {
        $filterDateFrom = $legacyDate;
        $filterDateTo = $legacyDate;
    }
}
if ($filterDateFrom !== null && $filterDateTo !== null && $filterDateFrom > $filterDateTo) {
    $swapDate = $filterDateFrom;
    $filterDateFrom = $filterDateTo;
    $filterDateTo = $swapDate;
}
$rangeValidation = pickup_list_order_validate_date_range($filterDateFrom, $filterDateTo);
if (!$rangeValidation['ok']) {
    $message = $rangeValidation['message'];
    $message_type = 'error';
}
$filterDateFromHidden = $filterDateFrom !== null ? $filterDateFrom : '';
$filterDateToHidden = $filterDateTo !== null ? $filterDateTo : '';
/** โหลดรายการทุกครั้งที่เรียก fragment (GET จาก overview หรือ POST หลังลบ) */
$listPickupOrders = true;

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
.pickup-list-table,
.pickup-list-table th,
.pickup-list-table td,
.pickup-list-message,
.pickup-list-empty,
.delete-button {
  font-family: inherit;
}

.pickup-list-table {
  table-layout: fixed;
  width: 100%;
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

.pickup-list-table .col-prep {
  text-align: center;
  vertical-align: middle;
  width: 13%;
}

.pickup-list-table th.col-prep {
  text-align: center;
}

.pickup-list-prep {
  border-radius: 999px;
  display: inline-block;
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.2;
  padding: 0.2rem 0.55rem;
  white-space: nowrap;
}

.pickup-list-prep.is-pending {
  background: #f1f5f9;
  color: #64748b;
}

.pickup-list-prep.is-partial {
  background: #fffbeb;
  color: #b45309;
  font-size: 0.78rem;
  letter-spacing: -0.02em;
}

.pickup-list-prep.is-done {
  background: #ecfdf5;
  color: #047857;
}

.pickup-list-prep.is-none {
  color: #94a3b8;
}

.pickup-list-table .col-delete {
  text-align: center;
  vertical-align: middle;
  width: 7%;
}

.pickup-list-table th.col-delete {
  text-align: center;
}

.pickup-list-table .col-delete form {
  display: flex;
  justify-content: center;
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

.pickup-list-doc-row {
  cursor: pointer;
  transition: background 0.12s ease;
}

.pickup-list-doc-row:hover,
.pickup-list-doc-row:focus {
  background: #f0f9ff;
  outline: none;
}

.pickup-list-doc-row:focus-visible {
  box-shadow: inset 0 0 0 2px rgba(13, 148, 136, 0.45);
}

.pickup-list-doc-id {
  color: var(--inv-primary-dark, #16466d);
  font-weight: 600;
  text-decoration: underline;
  text-decoration-color: rgba(22, 70, 109, 0.35);
  text-underline-offset: 2px;
}

.pickup-list-doc-row__actions {
  cursor: default;
  text-align: center;
}

</style>
<?php
if ($message !== '') { ?>
<div class="pickup-list-message <?php echo pickup_list_order_h($message_type); ?>">
  <?php echo pickup_list_order_h($message); ?>
</div>
<?php } ?>
<div class="inv-table-wrap">
<table class="inv-table pickup-list-table" id="table_result" name="table_result">
  <thead>
  <tr>
    <th class="col-prep">เบิกของ</th>
    <th class="col-no inv-col-num">No.</th>
    <th class="col-date">วันที่เบิก</th>
    <th class="col-doc">เลขเอกสาร</th>
    <th class="col-type">ประเภท</th>
    <th class="col-by">ผู้เบิก</th>
    <th class="col-remarks">หมายเหตุ</th>
    <th class="col-delete inv-col-actions">ลบ</th>
  </tr>
  </thead>
  <tbody>
<?php

if ($listPickupOrders && $rangeValidation['ok']) {
  $sql = "SELECT `pre_id`, `pre_by`, `pre_type`, `ts_mtn_number`, `pre_date`,
                 GROUP_CONCAT(
                     DISTINCT NULLIF(TRIM(`pre_remarks`), '')
                     ORDER BY `pre_running` ASC
                     SEPARATOR ' / '
                 ) AS `doc_remarks`,
                 COUNT(*) AS `line_total`,
                 SUM(CASE WHEN UPPER(TRIM(`pre_check`)) = 'YES' THEN 1 ELSE 0 END) AS `prep_count`
          FROM `pre_stock`
          WHERE `pre_status` = 'pre'";
  $bindTypes = '';
  $bindValues = array();
  if ($filterDateFrom !== null) {
      $sql .= ' AND `pre_date` >= ?';
      $bindTypes .= 's';
      $bindValues[] = $filterDateFrom;
  }
  if ($filterDateTo !== null) {
      $sql .= ' AND `pre_date` <= ?';
      $bindTypes .= 's';
      $bindValues[] = $filterDateTo;
  }
  $sql .= " GROUP BY `pre_id`, `pre_by`, `pre_type`, `ts_mtn_number`, `pre_date`
          ORDER BY `pre_date` DESC, `pre_id` DESC";

  $exec = false;
  if ($bindTypes === '') {
      $exec = db_exec($connect, $sql);
  } else {
      $stmt = mysqli_prepare($connect, $sql);
      if ($stmt !== false) {
          $bindParams = array($bindTypes);
          foreach ($bindValues as $idx => $val) {
              $bindParams[] = &$bindValues[$idx];
          }
          call_user_func_array(array($stmt, 'bind_param'), $bindParams);
          if (mysqli_stmt_execute($stmt)) {
              $exec = mysqli_stmt_get_result($stmt);
          }
      }
  }
  if ($exec === false) {
      die('Error Query [' . pickup_list_order_h($sql) . ']');
  }
  $num = 1;
  while ($result = db_fetch_array($exec)){
    $remarks = pickup_list_order_document_remarks(isset($result['doc_remarks']) ? $result['doc_remarks'] : '');
    $prepStatus = pickup_list_order_prep_status(
        isset($result['prep_count']) ? $result['prep_count'] : 0,
        isset($result['line_total']) ? $result['line_total'] : 0
    );
    $id = './pickup_list_order_detail.php?pre_id=' . urlencode($result['pre_id'])
        . '&pre_remarks=' . urlencode($remarks)
        . '&ts_mtn_number=' . urlencode($result['ts_mtn_number']);
	
//	echo $result["pre_type"];
    ?>
  <tr class="pickup-list-doc-row" role="button" tabindex="0"
      data-pre-id="<?php echo pickup_list_order_h($result['pre_id']); ?>"
      data-detail-url="<?php echo pickup_list_order_h($id); ?>"
      aria-label="ดูรายการอะไหล่เอกสาร <?php echo pickup_list_order_h($result['pre_id']); ?>">
    <td class="col-prep pickup-list-doc-row__actions">
      <span class="<?php echo pickup_list_order_h($prepStatus['class']); ?>" title="<?php echo pickup_list_order_h($prepStatus['title']); ?>">
        <?php echo pickup_list_order_h($prepStatus['label']); ?>
      </span>
    </td>
    <td class="col-no inv-col-num"><?php echo (int) $num; ?></td>
    <td class="col-date"><?php echo pickup_list_order_h($result["pre_date"]); ?></td>
    <td class="col-doc"><span class="pickup-list-doc-id"><?php echo pickup_list_order_h($result["pre_id"]); ?></span></td>
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
    <td class="col-delete inv-col-actions pickup-list-doc-row__actions">
      <form class="delete-pickup-form" method="post" action="pickup_list_order.php" onsubmit="return confirm('ยืนยันลบเอกสาร <?php echo pickup_list_order_h($result["pre_id"]); ?> ?');">
        <input type="hidden" name="delete_pre_id" value="<?php echo pickup_list_order_h($result["pre_id"]); ?>" />
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
    <td class="pickup-list-empty" colspan="8">Not Found</td>
  </tr>
<?php 
} ?>
  </tbody>
</table>
</div>

