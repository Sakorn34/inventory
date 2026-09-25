<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/line_stock_alert.php';

inventory_line_alert_ensure_schema($connect);
header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');
$statusNotYet = 'ยังไม่ถึงจุดสั่ง';
$statusReached = 'ถึงจุดสั่งแล้ว';
$statusNormal = 'ปกติ';
$lblOverview = 'ภาพรวมสต็อก';
$lblCategory = 'หมวด';
$lblStatus = 'สถานะ';
$lblPartName = 'ชื่อ';
$lblMinStock = 'จำนวนขั้นต่ำ';
$lblInStock = 'จำนวนใน';
$titlePage = 'ภาพรวม Stock';
$lblDesc = 'ตรวจสอบจำนวนสต็อก สถานะการสั่งซื้อ และแก้ไขรายการ Part';
$lblFilter = 'กรองข้อมูล';
$lblTable = 'รายการ Part';
$lblBtnShow = 'แสดงผล';
$lblBtnReset = 'ล้าง';
$lblAllOpt = '-- ทั้งหมด --';
$lblEditCol = 'แก้ไข';
$lblLineAlert = 'แจ้ง Line';
$lblLineAlertHint = 'เมื่อสต็อกต่ำกว่าขั้นต่ำ (วันละครั้ง)';
$lblLineSave = 'บันทึกการตั้งค่าแจ้ง Line';
$lblEmpty = 'ไม่พบข้อมูล';
$lblEditTitle = 'แก้ไข Part';
$h = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$search = '';
$getData = isset($_GET['product']) ? trim((string) $_GET['product']) : '';
$getStatus = isset($_GET['part_status']) ? trim((string) $_GET['part_status']) : '';
$getDataSql = ($getData !== '' && $getData !== 'All') ? (int) $getData : 0;
$getStatusSql = db_escape($connect, $getStatus);
if ($getData !== '' || $getStatus !== '') {
    if ($getData !== '' && $getStatus !== '') {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_categories]=$getDataSql AND part.[part_status_purchase]='$getStatusSql' ORDER BY part.[part_name] ASC";
    } elseif ($getData !== '' && $getStatus === '') {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_categories]=$getDataSql ORDER BY part.[part_name] ASC";
    } elseif ($getData === '' && $getStatus !== '') {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] WHERE part.[part_status_purchase]='$getStatusSql' ORDER BY part.[part_name] ASC";
    } else {
        $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] ORDER BY part.[part_categories],part.[part_name] ASC";
    }
} else {
    $sql = "SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id] ORDER BY part.[part_categories],part.[part_name] ASC";
}
$array = array('All'); $array2 = array('All');
$exeSP = db_exec($connect, 'SELECT * FROM categories_product') or die('Error categories_product');
while ($resultSP = db_fetch_array($exeSP)) { $array[] = $resultSP['ct_name']; $array2[] = $resultSP['ct_id']; }
$arrLength = count($array);
$statusList = array('', $statusNotYet, $statusReached, $statusNormal);
$statusBadgeClass = static function ($status) use ($statusNotYet, $statusReached, $statusNormal) {
    if ($status === '' || $status === $statusNotYet) { return 'inv-badge--danger'; }
    if ($status === $statusReached) { return 'inv-badge--warn'; }
    if ($status === $statusNormal) { return 'inv-badge--success'; }
    return 'inv-badge--muted';
};
$dash = "\xE2\x80\x94";
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $h($titlePage); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" href="css/inventory-ui.css">
<script src="js/jquery-3.5.1.min.js"></script>
<script>
function sort_product(){
  var p=document.getElementById("product_name").value,s=document.getElementById("part_status").value;
  if(p==="All")p="";if(s==="All")s="";
  if(p&&s)location="stock_overview.php?product="+encodeURIComponent(p)+"&part_status="+encodeURIComponent(s);
  else if(p)location="stock_overview.php?product="+encodeURIComponent(p);
  else if(s)location="stock_overview.php?part_status="+encodeURIComponent(s);
  else location="stock_overview.php";
}
function reset_filters(){location="stock_overview.php";}
(function($){
  var savingClass='is-saving', okClass='is-saved', errClass='is-save-error';
  function clearState($label){
    $label.removeClass(savingClass+' '+okClass+' '+errClass);
  }
  $(document).on('change','.inv-line-alert input[type=checkbox]',function(){
    var $cb=$(this), $label=$cb.closest('.inv-line-alert'), partId=$cb.data('part-id');
    if(!partId){return;}
    clearState($label);
    $label.addClass(savingClass).attr('title','กำลังบันทึก...');
    $.ajax({
      url:'part_line_alert_toggle.php',
      method:'POST',
      dataType:'json',
      data:{part_id:partId, part_line_alert:$cb.prop('checked')?1:0}
    }).done(function(res){
      clearState($label);
      if(res&&res.ok){
        $label.addClass(okClass).attr('title','บันทึกแล้ว');
        setTimeout(function(){clearState($label);$label.attr('title',$label.data('hint')||'');},1500);
      }else{
        $label.addClass(errClass).attr('title',(res&&res.message)?res.message:'บันทึกไม่สำเร็จ');
        alert((res&&res.message)?res.message:'บันทึกการตั้งค่าแจ้ง Line ไม่สำเร็จ');
        $cb.prop('checked',!$cb.prop('checked'));
      }
    }).fail(function(){
      clearState($label);
      $label.addClass(errClass).attr('title','เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ');
      alert('บันทึกการตั้งค่าแจ้ง Line ไม่สำเร็จ — ลองใหม่อีกครั้ง');
      $cb.prop('checked',!$cb.prop('checked'));
    });
  });
})(jQuery);
</script>
<style>
.inv-line-alert.is-saving{opacity:.55}
.inv-line-alert.is-saved input{outline:2px solid #22c55e;outline-offset:2px}
.inv-line-alert.is-save-error input{outline:2px solid #ef4444;outline-offset:2px}
</style>
</head>
<body class="inv-app">
<?php require "title-h.php"; ?>
<main class="inv-main">
  <header class="inv-page-header">
    <h1 class="inv-page-title"><?php echo $h($lblOverview); ?></h1>
    <p class="inv-page-desc"><?php echo $h($lblDesc); ?></p>
  </header>
  <section class="inv-card">
    <div class="inv-card__head"><i class="fa fa-filter"></i> <?php echo $h($lblFilter); ?></div>
    <div class="inv-card__body">
      <form class="inv-filters" onsubmit="return false;">
        <div class="inv-field">
          <label for="product_name"><?php echo $h($lblCategory); ?> Product</label>
          <select class="inv-select" id="product_name" name="product_name">
<?php for ($a = 0; $a < $arrLength; $a++) {
    $sel = ($getData == $array2[$a] || ($getData === '' && $array2[$a] === 'All')) ? ' selected' : '';
    echo '<option value="' . $h($array2[$a]) . '"' . $sel . '>' . $h($array[$a]) . '</option>';
} ?>
          </select>
        </div>
        <div class="inv-field">
          <label for="part_status"><?php echo $h($lblStatus); ?></label>
          <select class="inv-select" id="part_status" name="part_status">
<?php foreach ($statusList as $st) {
    $sel = ($getStatus === $st) ? ' selected' : '';
    $lab = ($st === '') ? $lblAllOpt : $st;
    echo '<option value="' . $h($st) . '"' . $sel . '>' . $h($lab) . '</option>';
} ?>
          </select>
        </div>
        <div class="inv-filters__actions">
          <button type="button" class="inv-btn inv-btn--primary" onclick="sort_product();">
            <i class="fa fa-search"></i> <?php echo $h($lblBtnShow); ?>
          </button>
          <button type="button" class="inv-btn inv-btn--ghost" onclick="reset_filters();">
            <i class="fa fa-refresh"></i> <?php echo $h($lblBtnReset); ?>
          </button>
        </div>
      </form>
    </div>
  </section>
  <section class="inv-card">
    <div class="inv-card__head"><i class="fa fa-table"></i> <?php echo $h($lblTable); ?></div>
    <form method="post" action="part_line_alert_save.php">
      <input type="hidden" name="product" value="<?php echo $h($getData); ?>">
      <input type="hidden" name="part_status" value="<?php echo $h($getStatus); ?>">
    <div class="inv-table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th class="inv-col-num">No.</th>
            <th><?php echo $h($lblCategory); ?> Product</th>
            <th><?php echo $h($lblPartName); ?> Part</th>
            <th class="inv-col-num"><?php echo $h($lblMinStock); ?></th>
            <th class="inv-col-num"><?php echo $h($lblInStock); ?></th>
            <th><?php echo $h($lblStatus); ?></th>
            <th class="inv-col-num" title="<?php echo $h($lblLineAlertHint); ?>"><?php echo $h($lblLineAlert); ?></th>
            <th class="inv-col-actions"><?php echo $h($lblEditCol); ?></th>
          </tr>
        </thead>
        <tbody>
<?php
if (isset($search)) {
    $exec = db_exec($connect, $sql) or die('Error Query');
    $num = 1;
    $rows = 0;
    while ($result = db_fetch_array($exec)) {
        $rows++;
        $rs = (float) $result['part_amount'] - (float) $result['part_min_alert'];
        $ps = (string) $result['part_status_purchase'];
        $low = ($rs <= 0);
        $qc = $low ? 'inv-qty--low' : 'inv-qty--ok';
        $bc = $statusBadgeClass($ps);
        if ($low && ($ps === '' || $ps === $statusNotYet)) {
            $sl = ($ps !== '') ? $ps : $statusNotYet;
        } elseif ($low && $ps !== '') {
            $sl = $ps . ' (' . (string) $result['part_date_purchase'] . ')';
        } else {
            $sl = ($ps !== '') ? $ps : $dash;
        }
        $pid = (int) $result['part_id'];
        $lineOn = !empty($result['part_line_alert']);
?>
          <tr>
            <td class="inv-col-num"><?php echo $num; ?></td>
            <td><?php echo $h($result['ct_name']); ?></td>
            <td><a class="inv-link-part" target="_blank" rel="noopener" href="part_edit.php?part_id=<?php echo $pid; ?>"><?php echo $h($result['part_name']); ?></a></td>
            <td class="inv-col-num"><?php echo $h($result['part_min_alert']); ?></td>
            <td class="inv-col-num"><span class="<?php echo $qc; ?>"><?php echo $h($result['part_amount']); ?></span></td>
            <td><span class="inv-badge <?php echo $bc; ?>"><?php echo $h($sl); ?></span></td>
            <td class="inv-col-num">
              <input type="hidden" name="part_line_alert[<?php echo $pid; ?>]" value="0">
              <label class="inv-line-alert" data-hint="<?php echo $h($lblLineAlertHint); ?>" title="<?php echo $h($lblLineAlertHint); ?>">
                <input type="checkbox" name="part_line_alert[<?php echo $pid; ?>]" value="1" data-part-id="<?php echo $pid; ?>"<?php echo $lineOn ? ' checked' : ''; ?>>
              </label>
            </td>
            <td class="inv-col-actions">
              <a class="inv-icon-btn" target="_blank" rel="noopener" href="part_edit.php?part_id=<?php echo $pid; ?>" title="<?php echo $h($lblEditTitle); ?>">
                <i class="fa fa-pencil"></i>
              </a>
            </td>
          </tr>
<?php
        $num++;
    }
    if ($rows === 0) {
        echo '<tr><td colspan="8" class="inv-empty"><i class="fa fa-inbox"></i> ' . $h($lblEmpty) . '</td></tr>';
    }
} else {
    echo '<tr><td colspan="8" class="inv-empty">' . $h($lblEmpty) . '</td></tr>';
}
?>
        </tbody>
      </table>
    </div>
    <div class="inv-card__foot" style="padding:1rem 1.25rem;border-top:1px solid #e2e8f0;">
      <button type="submit" class="inv-btn inv-btn--primary">
        <i class="fa fa-bell"></i> <?php echo $h($lblLineSave); ?>
      </button>
      <span class="inv-muted" style="margin-left:.75rem;font-size:.9rem;color:#64748b;"><?php echo $h($lblLineAlertHint); ?> — ติ๊กแล้วบันทึกอัตโนมัติ หรือกดปุ่มนี้เพื่อบันทึกทั้งหมดพร้อมกัน</span>
    </div>
    </form>
  </section>
</main>
</body>
</html>
