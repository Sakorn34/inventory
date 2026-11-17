<?php
/**
 * แก้ไข / ลบ รายการ log_stock รายบุคคล
 * GET ?id=log_id
 */
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/stock_flash.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$logId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($logId <= 0) {
    stock_flash_set('ไม่พบรายการที่ต้องการ', 'error');
    header('Location: stock_add_history.php');
    exit;
}

$stmt = mysqli_prepare($connect,
    'SELECT log_id, log_date, log_product, log_part, log_vendor, log_amount, log_price, log_remarks
     FROM log_stock WHERE log_id = ? LIMIT 1');
if (!$stmt) {
    stock_flash_set('ไม่สามารถโหลดข้อมูลได้', 'error');
    header('Location: stock_add_history.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $logId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$log = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$log) {
    stock_flash_set('ไม่พบรายการ log_id=' . $logId, 'error');
    header('Location: stock_add_history.php');
    exit;
}

// CSRF
if (empty($_SESSION['stock_log_csrf_token'])) {
    $_SESSION['stock_log_csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['stock_log_csrf_token'];

// flash
$flash = stock_flash_consume();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>
<title>แก้ไขรายการรับของ</title>
<script src="js/jquery-3.5.1.min.js"></script>
</head>
<body class="inv-app">
<?php require_once __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <div class="inv-page-header" style="margin-bottom:1.25rem;">
    <h1 class="inv-page-title"><i class="fa fa-pencil"></i> แก้ไขรายการรับของ</h1>
    <p class="inv-page-desc">แก้ไขข้อมูลเฉพาะรายการนี้ — การเปลี่ยนจำนวนจะปรับ Stock ของ Part อัตโนมัติ</p>
  </div>

  <?php if ($flash && $flash['message'] !== '') { ?>
  <div class="inv-flash inv-flash--<?php echo $h($flash['type']); ?>" style="margin-bottom:1rem;">
    <?php echo $h($flash['message']); ?>
  </div>
  <?php } ?>

  <div class="inv-card" style="max-width:640px;">
    <div class="inv-card__head">
      <i class="fa fa-history"></i> รายการ #<?php echo $logId; ?>
    </div>
    <div class="inv-card__body">

      <!-- ข้อมูล read-only -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem 1.25rem;margin-bottom:1.25rem;padding:.85rem 1rem;background:var(--inv-bg,#f8fafc);border:1px solid var(--inv-border);border-radius:8px;font-size:.88rem;">
        <div>
          <div style="color:var(--inv-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">Product</div>
          <div style="font-weight:500;"><?php echo $h($log['log_product']); ?></div>
        </div>
        <div>
          <div style="color:var(--inv-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">Part</div>
          <div style="font-weight:500;"><?php echo $h($log['log_part']); ?></div>
        </div>
      </div>

      <!-- ฟอร์มแก้ไข -->
      <form method="post" action="stock_log_save.php" id="form_edit_log">
        <input type="hidden" name="action" value="edit_log">
        <input type="hidden" name="log_id" value="<?php echo $logId; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1.25rem;">
          <div>
            <label class="inv-label" for="log_date">วันที่รับของ</label>
            <input type="date" id="log_date" name="log_date" class="inv-input"
              value="<?php echo $h($log['log_date']); ?>" required>
          </div>
          <div>
            <label class="inv-label" for="log_vendor">Vendor / ร้านค้า</label>
            <input type="text" id="log_vendor" name="log_vendor" class="inv-input"
              value="<?php echo $h($log['log_vendor']); ?>" maxlength="255" required>
          </div>
          <div>
            <label class="inv-label" for="log_amount">
              จำนวน
              <span style="font-weight:400;color:var(--inv-muted);font-size:.78rem;">(เปลี่ยนจะปรับ Stock ด้วย)</span>
            </label>
            <input type="number" id="log_amount" name="log_amount" class="inv-input"
              value="<?php echo $h($log['log_amount']); ?>" min="0" step="any" required>
          </div>
          <div>
            <label class="inv-label" for="log_price">ราคา</label>
            <input type="text" id="log_price" name="log_price" class="inv-input"
              value="<?php echo $h($log['log_price']); ?>" maxlength="50">
          </div>
          <div style="grid-column:span 2;">
            <label class="inv-label" for="log_remarks">หมายเหตุ</label>
            <input type="text" id="log_remarks" name="log_remarks" class="inv-input"
              value="<?php echo $h($log['log_remarks']); ?>" maxlength="2000">
          </div>
        </div>

        <div style="display:flex;gap:.75rem;justify-content:space-between;flex-wrap:wrap;align-items:center;margin-top:1.5rem;">
          <button type="button" class="inv-btn inv-btn--danger" id="btn_delete_log">
            <i class="fa fa-trash-o"></i> ลบรายการนี้
          </button>
          <div style="display:flex;gap:.75rem;">
            <a href="stock_add_history.php" class="inv-btn inv-btn--ghost">ยกเลิก</a>
            <button type="submit" class="inv-btn inv-btn--primary">
              <i class="fa fa-save"></i> บันทึกการแก้ไข
            </button>
          </div>
        </div>
      </form>

      <!-- inline delete confirmation -->
      <div id="delete_confirm_box" style="display:none;margin-top:1rem;padding:.85rem 1rem;background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;">
        <p style="margin:0 0 .75rem;font-size:.9rem;color:#be123c;font-weight:500;">
          <i class="fa fa-exclamation-triangle"></i>
          ยืนยันลบ "<strong><?php echo $h($log['log_part']); ?></strong>"
          จำนวน <strong><?php echo $h($log['log_amount']); ?></strong> ?<br>
          <span style="font-size:.8rem;font-weight:400;">Stock ของ Part จะถูกหักออกด้วย — ไม่สามารถกู้คืนได้</span>
        </p>
        <div style="display:flex;gap:.6rem;">
          <form method="post" action="stock_log_save.php">
            <input type="hidden" name="action" value="delete_log">
            <input type="hidden" name="log_id" value="<?php echo $logId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>">
            <button type="submit" class="inv-btn inv-btn--danger">
              <i class="fa fa-trash-o"></i> ยืนยันลบ
            </button>
          </form>
          <button type="button" class="inv-btn inv-btn--ghost" id="btn_cancel_delete">ยกเลิก</button>
        </div>
      </div>

    </div>
  </div>
</main>

<script>
document.getElementById('btn_delete_log').addEventListener('click', function () {
    document.getElementById('delete_confirm_box').style.display = 'block';
    this.style.display = 'none';
});
document.getElementById('btn_cancel_delete').addEventListener('click', function () {
    document.getElementById('delete_confirm_box').style.display = 'none';
    document.getElementById('btn_delete_log').style.display = '';
});
</script>
</body>
</html>
