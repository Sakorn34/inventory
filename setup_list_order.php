<?php
/**
 * fragment ตารางรายการประสานงาน (Setup)
 *
 * ใช้โดย `setup_overview.php` ผ่าน AJAX เพื่อแสดงรายการ setup ตามวันที่ที่เลือก
 * คืนเฉพาะ HTML ตาราง ไม่รวม layout หลักของหน้าเว็บ
 *
 * ตัวอย่าง: $.get("setup_list_order.php", { pre_date: "2026-06-02" })
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

/**
 * escape ค่าก่อน render กลับไปยัง HTML
 *
 * @param mixed $value ค่าที่ต้องแสดงผลบนหน้าเว็บ
 * @return string ข้อความที่ปลอดภัยสำหรับ HTML
 */
function setup_list_order_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$search = isset($_GET['pre_date']) ? trim((string) $_GET['pre_date']) : '';
?>
<style>
.setup-list-table {
  border-collapse: collapse;
  width: 100%;
}

.setup-list-table th,
.setup-list-table td {
  border: 0;
  padding: 6px 10px;
}

.setup-list-table th {
  background: #e1e1e1;
}

.setup-list-table tr:not(:first-child) {
  border-bottom: 1px solid #eeeeee;
}
</style>
<table class="setup-list-table" id="table_result" name="table_result">
  <tr>
    <th width="20"><div align="center">No.</div></th>
    <th width="100"><div align="center">PO</div></th>
    <th width="100"><div align="center">รอ (วัน)</div></th>
    <th width="100"><div align="center">Site</div></th>
    <th width="100"><div align="center">Security</div></th>
    <th width="50"><div align="center">Product</div></th>
    <th width="50"><div align="center">จำนวน</div></th>
    <th width="50"><div align="center">สถานะ</div></th>
    <th width="50"><div align="center">ชื่อลูกค้า</div></th>
    <th width="50"><div align="center">info</div></th>
  </tr>
<?php
if ($search !== '') {
    $sql = 'SELECT * FROM setup_log';
    $exec = db_exec($connect, $sql);
    $num = 1;

    if ($exec instanceof mysqli_result) {
        while ($result = db_fetch_array($exec)) {
            $detailUrl = './setup_detail.php?setup_id=' . urlencode((string) $result['setup_id']);
            ?>
  <tr>
    <td><div align="center"><?php echo (int) $num; ?></div></td>
    <td><div align="center"><?php echo setup_list_order_h($result['setup_po']); ?></div></td>
    <td><div align="center"><?php echo setup_list_order_h(cal_wait_date($result['setup_po'])); ?></div></td>
    <td><div align="center"><a href="<?php echo setup_list_order_h($detailUrl); ?>"><?php echo setup_list_order_h($result['setup_site']); ?></a></div></td>
    <td><div align="center"><?php echo setup_list_order_h($result['setup_security']); ?></div></td>
    <td><div align="center"><?php echo setup_list_order_h($result['setup_product']); ?></div></td>
    <td><div align="center"><?php echo setup_list_order_h($result['setup_amount']); ?></div></td>
    <td><div align="center"><?php echo setup_list_order_h($result['setup_status']); ?></div></td>
    <td><div align="center"><?php echo setup_list_order_h($result['setup_name']); ?></div></td>
    <td><div align="center"><a target="_blank" href="<?php echo setup_list_order_h($detailUrl); ?>"><i class="fa fa-info-circle" aria-hidden="true"> info</i></a></div></td>
  </tr>
            <?php
            $num++;
        }
    }
} else {
    ?>
  <tr>
    <td colspan="10"><div align="center">ไม่พบข้อมูล</div></td>
  </tr>
    <?php
}

db_close($connect);
?>
</table>