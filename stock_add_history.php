<?php
/**
 * ประวัติการรับของเข้า Stock (log_type = นำเข้า)
 * แสดงแบบจัดกลุ่มตามวันที่ + Vendor + ผู้บันทึก พร้อม toggle ดูรายละเอียด
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

$thaiMonths = array(
    1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',
    7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'
);

function thaiDate($dateStr, $months) {
    $ts = strtotime((string)$dateStr);
    if (!$ts) return (string)$dateStr;
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return $d . ' ' . $months[$m] . ' ' . $y;
}

// ─ ตัวกรอง ──────────────────────────────────────────────────────────────────

$today    = date('Y-m-d');
$default  = date('Y-m-d', strtotime('-30 days'));
$dateFrom = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])
            ? $_GET['date_from'] : $default;
$dateTo   = isset($_GET['date_to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])
            ? $_GET['date_to'] : $today;
$vendorQ  = isset($_GET['vendor']) ? trim((string)$_GET['vendor']) : '';

if ($dateFrom > $dateTo) { $tmp = $dateFrom; $dateFrom = $dateTo; $dateTo = $tmp; }

// ─ Query ────────────────────────────────────────────────────────────────────

$sql = "SELECT log_id, log_date, log_vendor, log_by,
               log_product, log_part, log_amount, log_price, log_remarks
        FROM log_stock
        WHERE log_type = 'นำเข้า'
          AND log_date >= '$dateFrom'
          AND log_date <= '$dateTo'";

if ($vendorQ !== '') {
    $vLike = '%' . str_replace(array('%','_'), array('\%','\_'), $vendorQ) . '%';
    $vEsc  = mysqli_real_escape_string($connect, $vLike);
    $sql  .= " AND log_vendor LIKE '$vEsc'";
}
$sql .= ' ORDER BY log_date DESC, log_id DESC LIMIT 1000';

$res = mysqli_query($connect, $sql);

// จัดกลุ่มใน PHP: key = "log_date|log_vendor|log_by"
$groups    = array();
$groupKeys = array();

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $key = $row['log_date'] . '|' . $row['log_vendor'] . '|' . $row['log_by'];
        if (!isset($groups[$key])) {
            $groups[$key] = array(
                'log_date'    => $row['log_date'],
                'log_vendor'  => $row['log_vendor'],
                'log_by'      => $row['log_by'],
                'total_qty'   => 0,
                'total_price' => 0,
                'lines'       => array(),
            );
            $groupKeys[] = $key;
        }
        $groups[$key]['total_qty']   += (float)$row['log_amount'];
        $groups[$key]['total_price'] += (float)$row['log_price'];
        $groups[$key]['lines'][]      = $row;
    }
}

$totalGroups = count($groupKeys);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>
<title>ประวัติการรับของเข้า Stock</title>
<script src="js/jquery-3.5.1.min.js"></script>
<style>
.sah-filter { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; padding:.85rem 1rem; background:var(--inv-bg,#f8fafc); border:1px solid var(--inv-border); border-radius:8px; margin-bottom:1rem; }
.sah-filter label { font-size:.8rem; color:var(--inv-muted); white-space:nowrap; }
.sah-filter input[type=date], .sah-filter input[type=text] { height:34px; padding:0 .6rem; border:1px solid var(--inv-border); border-radius:6px; font-size:.88rem; background:#fff; color:var(--inv-text); }
.sah-filter input[type=date] { width:148px; }
.sah-filter input[type=text]  { width:180px; }
.sah-sep { color:var(--inv-muted); }
.sah-tbl { width:100%; border-collapse:collapse; }
.sah-tbl th { font-size:.78rem; font-weight:600; color:var(--inv-muted); text-transform:uppercase; letter-spacing:.04em; padding:8px 10px; border-bottom:2px solid var(--inv-border); background:var(--inv-bg,#f8fafc); text-align:left; }
.sah-tbl td { padding:9px 10px; border-bottom:1px solid var(--inv-border); font-size:.88rem; vertical-align:middle; }
.sah-row-summary td { cursor:pointer; }
.sah-row-summary:hover td { background:#f0f9ff; }
.sah-toggle { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:4px; border:1px solid var(--inv-border); font-size:10px; color:var(--inv-muted); transition:transform .15s; }
.sah-toggle.open { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
.sah-badge { display:inline-flex; align-items:center; padding:1px 8px; border-radius:999px; font-size:.78rem; background:#eff6ff; color:#1d4ed8; font-weight:600; }
.sah-row-detail td { padding:0; background:var(--inv-bg,#f8fafc); }
.sah-detail-wrap { padding:.5rem .75rem .5rem 2.75rem; }
.sah-dtbl { width:100%; border-collapse:collapse; }
.sah-dtbl td { padding:5px 8px; font-size:.82rem; border-bottom:1px solid var(--inv-border); color:var(--inv-text); }
.sah-dtbl tr:last-child td { border-bottom:none; }
.sah-dtbl .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.sah-dtbl .muted { color:var(--inv-muted); font-size:.78rem; }
.sah-num { text-align:right; font-variant-numeric:tabular-nums; }
.sah-muted { color:var(--inv-muted); }
.sah-vname { font-weight:500; }
.sah-empty { text-align:center; padding:2.5rem; color:var(--inv-muted); }
.sah-foot { font-size:.78rem; color:var(--inv-muted); text-align:right; margin-top:.4rem; }
</style>
</head>
<body class="inv-app">
<?php require_once __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <div class="inv-page-header" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
    <div style="flex:1;">
      <h1 class="inv-page-title"><i class="fa fa-history"></i> ประวัติการรับของเข้า Stock</h1>
      <p class="inv-page-desc">รายการ นำเข้า ย้อนหลัง — คลิกแถวเพื่อดูรายละเอียด</p>
    </div>
    <a href="stock_add.php" class="inv-btn inv-btn--ghost">
      <i class="fa fa-plus-circle"></i> เพิ่ม Stock
    </a>
  </div>

  <!-- ─ ตัวกรอง ──────────────────────────────────────── -->
  <form method="get" action="stock_add_history.php" id="form_filter">
    <div class="sah-filter">
      <label>วันที่จาก</label>
      <input type="date" name="date_from" value="<?php echo $h($dateFrom); ?>">
      <span class="sah-sep">—</span>
      <label>ถึง</label>
      <input type="date" name="date_to"   value="<?php echo $h($dateTo); ?>">
      <label style="margin-left:.5rem;">Vendor</label>
      <input type="text" name="vendor" value="<?php echo $h($vendorQ); ?>" placeholder="ทั้งหมด">
      <button type="submit" class="inv-btn inv-btn--primary" style="margin-left:auto;">
        <i class="fa fa-search"></i> ค้นหา
      </button>
      <?php if ($vendorQ !== '' || $dateFrom !== $default || $dateTo !== $today) { ?>
      <a href="stock_add_history.php" class="inv-btn inv-btn--ghost">ล้างตัวกรอง</a>
      <?php } ?>
    </div>
  </form>

  <!-- ─ ตาราง ───────────────────────────────────────── -->
  <div class="inv-card">
    <div class="inv-card__body" style="padding:0;overflow-x:auto;">
      <table class="sah-tbl">
        <thead>
          <tr>
            <th style="width:30px;"></th>
            <th>วันที่</th>
            <th>Vendor</th>
            <th style="text-align:center;width:72px;">รายการ</th>
            <th style="text-align:right;width:120px;">ราคารวม</th>
            <th style="width:90px;">บันทึกโดย</th>
          </tr>
        </thead>
        <tbody id="sah-tbody">
        <?php if ($totalGroups === 0) { ?>
          <tr><td colspan="6" class="sah-empty">
            <i class="fa fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
            ไม่มีข้อมูลในช่วงที่เลือก
          </td></tr>
        <?php } else {
            $gi = 0;
            foreach ($groupKeys as $key) {
                $g = $groups[$key];
                $gi++;
                $gid = 'grp_' . $gi;
                $linesCount = count($g['lines']);
        ?>
          <tr class="sah-row-summary" data-target="<?php echo $gid; ?>">
            <td><span class="sah-toggle" id="toggle_<?php echo $gid; ?>">▶</span></td>
            <td><?php echo $h(thaiDate($g['log_date'], $thaiMonths)); ?></td>
            <td class="sah-vname"><?php echo $h($g['log_vendor']); ?></td>
            <td style="text-align:center;"><span class="sah-badge"><?php echo $linesCount; ?></span></td>
            <td class="sah-num"><?php echo number_format($g['total_price'], 2); ?></td>
            <td class="sah-muted"><?php echo $h($g['log_by']); ?></td>
          </tr>
          <tr class="sah-row-detail" id="<?php echo $gid; ?>" style="display:none;">
            <td colspan="6">
              <div class="sah-detail-wrap">
                <table class="sah-dtbl">
                  <thead>
                    <tr>
                      <td style="color:var(--inv-muted);font-size:.75rem;width:24px;">#</td>
                      <td style="color:var(--inv-muted);font-size:.75rem;">Product</td>
                      <td style="color:var(--inv-muted);font-size:.75rem;">Part</td>
                      <td style="color:var(--inv-muted);font-size:.75rem;text-align:right;">จำนวน</td>
                      <td style="color:var(--inv-muted);font-size:.75rem;text-align:right;">ราคา</td>
                      <td style="color:var(--inv-muted);font-size:.75rem;">หมายเหตุ</td>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($g['lines'] as $li => $line) { ?>
                    <tr>
                      <td class="muted"><?php echo ($li + 1); ?></td>
                      <td><?php echo $h($line['log_product']); ?></td>
                      <td><?php echo $h($line['log_part']); ?></td>
                      <td class="num"><?php echo number_format((float)$line['log_amount'], 0); ?></td>
                      <td class="num"><?php echo number_format((float)$line['log_price'], 2); ?></td>
                      <td class="muted"><?php echo $h($line['log_remarks']); ?></td>
                    </tr>
                  <?php } ?>
                  </tbody>
                </table>
              </div>
            </td>
          </tr>
        <?php } } ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($totalGroups > 0) { ?>
  <p class="sah-foot">แสดง <?php echo $totalGroups; ?> ชุดรายการ
    (<?php echo $h(thaiDate($dateFrom, $thaiMonths)); ?> — <?php echo $h(thaiDate($dateTo, $thaiMonths)); ?>)
  </p>
  <?php } ?>
</main>

<script>
$(function () {
    $(document).on('click', '.sah-row-summary', function () {
        var target = $(this).data('target');
        var $detail = $('#' + target);
        var $toggle = $('#toggle_' + target);
        if ($detail.is(':visible')) {
            $detail.hide();
            $toggle.text('▶').removeClass('open');
        } else {
            $detail.show();
            $toggle.text('▼').addClass('open');
        }
    });
});
</script>
</body>
</html>
