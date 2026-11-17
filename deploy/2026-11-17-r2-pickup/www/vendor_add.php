<?php
/**
 * หน้าเพิ่ม Vendor ใหม่
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

// CSRF token
if (empty($_SESSION['vendor_csrf_token'])) {
    $_SESSION['vendor_csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['vendor_csrf_token'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>
<title>เพิ่ม Vendor ใหม่</title>
<script src="js/jquery-3.5.1.min.js"></script>
<style>
.vendor-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem 1.5rem; }
.vendor-section-label { font-size:.7rem; font-weight:700; text-transform:uppercase; color:var(--inv-primary); letter-spacing:.07em; margin-bottom:.75rem; padding-bottom:.35rem; border-bottom:2px solid var(--inv-primary-light,#e0eaf4); }
.required-mark { color:var(--inv-danger); }
</style>
</head>
<body class="inv-app">
<?php require_once __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <div class="inv-page-header" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div style="flex:1;">
      <h1 class="inv-page-title"><i class="fa fa-plus-circle"></i> เพิ่ม Vendor ใหม่</h1>
      <p class="inv-page-desc">กรอกข้อมูลผู้จำหน่ายแล้วกด "บันทึก"</p>
    </div>
    <a href="vendor_manage.php" class="inv-btn inv-btn--ghost">
      <i class="fa fa-arrow-left"></i> กลับ
    </a>
  </div>

  <form method="post" action="vendor_save.php" id="form_vendor">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>">

    <!-- ─ ข้อมูลทั่วไป ──────────────────────────────────────────────────── -->
    <div class="inv-card" style="margin-bottom:1rem;">
      <div class="inv-card__head"><i class="fa fa-info-circle"></i> ข้อมูลทั่วไป</div>
      <div class="inv-card__body">
        <p class="vendor-section-label">ข้อมูลหลัก</p>
        <div class="vendor-form-grid">
          <div class="inv-field" style="grid-column:1/-1;">
            <label>ชื่อ Vendor <span class="required-mark">*</span></label>
            <input type="text" name="vname" class="inv-input" required maxlength="255"
              placeholder="ชื่อบริษัท / ผู้จำหน่าย">
          </div>
          <div class="inv-field">
            <label>ประเภท</label>
            <input type="text" name="type" class="inv-input" maxlength="100"
              placeholder="เช่น ผู้ผลิต, ตัวแทนจำหน่าย">
          </div>
          <div class="inv-field">
            <label>ผู้ติดต่อ</label>
            <input type="text" name="contactname" class="inv-input" maxlength="255"
              placeholder="ชื่อผู้ติดต่อหลัก">
          </div>
        </div>
      </div>
    </div>

    <!-- ─ ที่อยู่ ─────────────────────────────────────────────────────────── -->
    <div class="inv-card" style="margin-bottom:1rem;">
      <div class="inv-card__head"><i class="fa fa-map-marker"></i> ที่อยู่</div>
      <div class="inv-card__body">
        <div class="vendor-form-grid">
          <div class="inv-field" style="grid-column:1/-1;">
            <label>ที่อยู่บรรทัด 1</label>
            <input type="text" name="add1" class="inv-input" maxlength="255"
              placeholder="เลขที่ ถนน หมู่บ้าน">
          </div>
          <div class="inv-field" style="grid-column:1/-1;">
            <label>ที่อยู่บรรทัด 2</label>
            <input type="text" name="add2" class="inv-input" maxlength="255"
              placeholder="แขวง/ตำบล เขต/อำเภอ">
          </div>
          <div class="inv-field" style="grid-column:1/-1;">
            <label>ที่อยู่บรรทัด 3</label>
            <input type="text" name="add3" class="inv-input" maxlength="255"
              placeholder="จังหวัด">
          </div>
          <div class="inv-field">
            <label>รหัสไปรษณีย์</label>
            <input type="text" name="zipcode" class="inv-input" maxlength="10"
              placeholder="10000">
          </div>
          <div class="inv-field">
            <label>สาขา / ที่ตั้ง</label>
            <input type="text" name="Location" class="inv-input" maxlength="255"
              placeholder="สำนักงานใหญ่ / สาขา...">
          </div>
        </div>
      </div>
    </div>

    <!-- ─ ข้อมูลติดต่อ ─────────────────────────────────────────────────── -->
    <div class="inv-card" style="margin-bottom:1rem;">
      <div class="inv-card__head"><i class="fa fa-phone"></i> ข้อมูลติดต่อ</div>
      <div class="inv-card__body">
        <div class="vendor-form-grid">
          <div class="inv-field">
            <label>โทรศัพท์ 1</label>
            <input type="text" name="tel1" class="inv-input" maxlength="50"
              placeholder="02-xxx-xxxx">
          </div>
          <div class="inv-field">
            <label>โทรศัพท์ 2</label>
            <input type="text" name="tel2" class="inv-input" maxlength="50"
              placeholder="08x-xxx-xxxx">
          </div>
          <div class="inv-field">
            <label>โทรสาร 1</label>
            <input type="text" name="fax1" class="inv-input" maxlength="50"
              placeholder="02-xxx-xxxx">
          </div>
          <div class="inv-field">
            <label>โทรสาร 2</label>
            <input type="text" name="fax2" class="inv-input" maxlength="50"
              placeholder="02-xxx-xxxx">
          </div>
          <div class="inv-field" style="grid-column:1/-1;">
            <label>อีเมล</label>
            <input type="email" name="email" class="inv-input" maxlength="255"
              placeholder="email@example.com">
          </div>
        </div>
      </div>
    </div>

    <!-- ─ ข้อมูลธนาคาร ─────────────────────────────────────────────────── -->
    <div class="inv-card" style="margin-bottom:1rem;">
      <div class="inv-card__head"><i class="fa fa-university"></i> ข้อมูลธนาคาร</div>
      <div class="inv-card__body">
        <div class="vendor-form-grid">
          <div class="inv-field">
            <label>ธนาคาร</label>
            <input type="text" name="bank" class="inv-input" maxlength="100"
              placeholder="กสิกรไทย / ไทยพาณิชย์ ...">
          </div>
          <div class="inv-field">
            <label>ชื่อบัญชี</label>
            <input type="text" name="accname" class="inv-input" maxlength="255"
              placeholder="ชื่อเจ้าของบัญชี">
          </div>
          <div class="inv-field">
            <label>เลขบัญชี</label>
            <input type="text" name="accid" class="inv-input" maxlength="50"
              placeholder="xxx-x-xxxxx-x">
          </div>
          <div class="inv-field">
            <label>สาขาธนาคาร</label>
            <input type="text" name="branch" class="inv-input" maxlength="100"
              placeholder="สาขา...">
          </div>
          <div class="inv-field">
            <label>เลขประจำตัวผู้เสียภาษี</label>
            <input type="text" name="taxid" class="inv-input" maxlength="50"
              placeholder="0000000000000 (13 หลัก)">
          </div>
        </div>
      </div>
    </div>

    <!-- ─ หมายเหตุ ─────────────────────────────────────────────────────── -->
    <div class="inv-card" style="margin-bottom:1.25rem;">
      <div class="inv-card__head"><i class="fa fa-sticky-note-o"></i> หมายเหตุ</div>
      <div class="inv-card__body">
        <div class="inv-field">
          <label>รายละเอียดเพิ่มเติม</label>
          <textarea name="vdetail" class="inv-input" rows="4"
            placeholder="บันทึกเพิ่มเติมเกี่ยวกับ Vendor..."></textarea>
        </div>
      </div>
    </div>

    <!-- ─ Actions ─────────────────────────────────────────────────────── -->
    <div style="display:flex;gap:.75rem;justify-content:flex-end;flex-wrap:wrap;">
      <a href="vendor_manage.php" class="inv-btn inv-btn--ghost">
        <i class="fa fa-times"></i> ยกเลิก
      </a>
      <button type="submit" class="inv-btn inv-btn--primary">
        <i class="fa fa-save"></i> บันทึก Vendor
      </button>
    </div>
  </form>
</main>
</body>
</html>
