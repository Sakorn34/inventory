<?php
/**
 * หน้าประวัติการเบิกผลิต — แสดงรายการเบิกผลิตรายใบ (pre_id) พร้อมกรอง log_part
 *
 * อ่านจาก log_stock เฉพาะ log_type = เบิกผลิต
 * จำนวนเครื่อง = SUM(log_amount) ของ log_part ที่เลือก ต่อ pre_id
 *
 * Flow: เลือก log_part + ปี/เดือน → แสดงตารางประวัติเรียงวันที่ล่าสุดก่อน
 *
 * Example: production_history.php?log_part=Board+Main&year=2026&month=5
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/stock_report_lib.php';

header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$lblTitle = 'ประวัติการเบิกผลิต';
$lblDesc = 'รายการเบิกผลิตรายใบ — กรองตาม Part (log_part), ปี และเดือนได้';
$lblFilter = 'กรองข้อมูล';
$lblLogPart = 'Part (log_part)';
$lblYear = 'ปี';
$lblMonth = 'เดือน';
$lblAllLogPart = '-- ทั้งหมด --';
$lblAllYear = '-- ทุกปี --';
$lblAllMonth = '-- ทุกเดือน --';
$lblBtnShow = 'แสดงผล';
$lblBtnReset = 'ล้าง';
$lblTable = 'รายการเบิกผลิต';
$lblEmpty = 'ไม่พบประวัติการเบิกผลิตในช่วงที่เลือก';
$lblNote = 'แสดงเฉพาะ log_type = เบิกผลิต — จำนวนเครื่อง = log_amount ของ log_part ที่กรอง (รวมต่อ pre_id)';
$lblColNo = 'ลำดับ';
$lblColDate = 'วันที่เบิก';
$lblColPreId = 'เลขเอกสาร';
$lblColLogPart = 'Part (log_part)';
$lblColQty = 'จำนวนเครื่อง';
$lblColBy = 'ผู้เบิก';
$lblSummaryDocs = 'จำนวนใบเบิก';
$lblSummaryQty = 'รวมจำนวนเครื่อง';
$lblViewReport = 'รายงานกราฟ';
$lblLimitNote = 'แสดงล่าสุด';

$logPartList = stock_report_log_part_list($connect);
$availableYears = stock_report_available_years($connect);
$monthLabels = stock_report_month_labels();

$getLogPart = stock_report_resolve_log_part(
    isset($_GET['log_part']) ? (string) $_GET['log_part'] : '',
    $logPartList
);

$getYear = stock_report_resolve_history_year(
    isset($_GET['year']) ? (int) $_GET['year'] : 0,
    $availableYears
);
$getMonth = stock_report_resolve_history_month(isset($_GET['month']) ? (int) $_GET['month'] : 0);

$historyRows = stock_report_fetch_production_history($connect, $getLogPart, 0, $getYear, $getMonth);
$summary = stock_report_summarize_history($historyRows);
$historyLimit = (int) STOCK_REPORT_HISTORY_LIMIT;
$isTruncated = count($historyRows) >= $historyLimit;

$partSuffix = ($getLogPart !== '') ? $getLogPart : $lblAllLogPart;
$periodSuffix = stock_report_history_period_label($getYear, $getMonth, $lblAllMonth);
$tableTitleSuffix = $partSuffix . ' / ' . $periodSuffix;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $h($lblTitle); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" href="css/inventory-ui.css">
<script src="js/jquery-3.5.1.min.js"></script>
<script>
function productionHistorySubmit(){
  var q=[];
  var p=document.getElementById("log_part_name").value;
  var y=document.getElementById("filter_year").value;
  var m=document.getElementById("filter_month").value;
  if(p&&p!=="All"){q.push("log_part="+encodeURIComponent(p));}
  if(y&&y!=="0"){q.push("year="+encodeURIComponent(y));}
  if(m&&m!=="0"){q.push("month="+encodeURIComponent(m));}
  location="production_history.php"+(q.length?"?"+q.join("&"):"");
}
function productionHistoryReset(){location="production_history.php";}
</script>
</head>
<body class="inv-app">
<?php require 'title-h.php'; ?>
<main class="inv-main">
  <header class="inv-page-header">
    <h1 class="inv-page-title"><?php echo $h($lblTitle); ?></h1>
    <p class="inv-page-desc"><?php echo $h($lblDesc); ?></p>
  </header>

  <section class="inv-card">
    <div class="inv-card__head"><i class="fa fa-filter"></i> <?php echo $h($lblFilter); ?></div>
    <div class="inv-card__body">
      <form class="inv-filters inv-report-filters" onsubmit="return false;">
        <div class="inv-field">
          <label for="log_part_name"><?php echo $h($lblLogPart); ?></label>
          <select class="inv-select" id="log_part_name" name="log_part_name">
            <option value="All"<?php echo ($getLogPart === '') ? ' selected' : ''; ?>><?php echo $h($lblAllLogPart); ?></option>
<?php foreach ($logPartList as $partName) {
    $sel = ($getLogPart === $partName) ? ' selected' : '';
    echo '<option value="' . $h($partName) . '"' . $sel . '>' . $h($partName) . '</option>';
} ?>
          </select>
        </div>
        <div class="inv-field">
          <label for="filter_year"><?php echo $h($lblYear); ?></label>
          <select class="inv-select" id="filter_year" name="filter_year">
            <option value="0"<?php echo ($getYear === 0) ? ' selected' : ''; ?>><?php echo $h($lblAllYear); ?></option>
<?php foreach ($availableYears as $yr) {
    $sel = ((int) $yr === $getYear) ? ' selected' : '';
    echo '<option value="' . $h((string) $yr) . '"' . $sel . '>' . $h((string) $yr) . '</option>';
} ?>
          </select>
        </div>
        <div class="inv-field">
          <label for="filter_month"><?php echo $h($lblMonth); ?></label>
          <select class="inv-select" id="filter_month" name="filter_month">
            <option value="0"<?php echo ($getMonth === 0) ? ' selected' : ''; ?>><?php echo $h($lblAllMonth); ?></option>
<?php
for ($mi = 1; $mi <= 12; $mi++) {
    $sel = ($getMonth === $mi) ? ' selected' : '';
    echo '<option value="' . $h((string) $mi) . '"' . $sel . '>' . $h($monthLabels[$mi - 1]) . '</option>';
}
?>
          </select>
        </div>
        <div class="inv-filters__actions">
          <button type="button" class="inv-btn inv-btn--primary" onclick="productionHistorySubmit();">
            <i class="fa fa-search"></i> <?php echo $h($lblBtnShow); ?>
          </button>
          <button type="button" class="inv-btn inv-btn--ghost" onclick="productionHistoryReset();">
            <i class="fa fa-refresh"></i> <?php echo $h($lblBtnReset); ?>
          </button>
        </div>
      </form>
    </div>
  </section>

  <section class="inv-card">
    <div class="inv-card__head"><i class="fa fa-history"></i> <?php echo $h($lblTable); ?> — <?php echo $h($tableTitleSuffix); ?></div>
    <div class="inv-card__body">
      <p class="inv-chart-note"><i class="fa fa-info-circle"></i> <?php echo $h($lblNote); ?></p>
<?php if (count($historyRows) > 0) { ?>
      <div class="inv-usage-year-totals inv-history-summary">
        <div class="inv-usage-year-card inv-usage-year-card--latest">
          <span class="inv-usage-year-card__year"><?php echo $h($lblSummaryDocs); ?></span>
          <span class="inv-usage-year-card__val"><?php echo $h(number_format($summary['total_docs'], 0, '.', ',')); ?></span>
          <span class="inv-usage-year-card__lbl"><?php echo $h($tableTitleSuffix); ?></span>
        </div>
        <div class="inv-usage-year-card">
          <span class="inv-usage-year-card__year"><?php echo $h($lblSummaryQty); ?></span>
          <span class="inv-usage-year-card__val"><?php echo $h(number_format($summary['total_qty'], 0, '.', ',')); ?></span>
          <span class="inv-usage-year-card__lbl">เครื่อง</span>
        </div>
      </div>
<?php if ($isTruncated) { ?>
      <p class="inv-muted inv-history-limit"><i class="fa fa-exclamation-circle"></i>
        <?php echo $h($lblLimitNote); ?> <?php echo $h((string) $historyLimit); ?> รายการ</p>
<?php } ?>
      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th class="inv-col-num"><?php echo $h($lblColNo); ?></th>
              <th><?php echo $h($lblColDate); ?></th>
              <th><?php echo $h($lblColPreId); ?></th>
              <th><?php echo $h($lblColLogPart); ?></th>
              <th class="inv-col-num"><?php echo $h($lblColQty); ?></th>
              <th><?php echo $h($lblColBy); ?></th>
            </tr>
          </thead>
          <tbody>
<?php
$num = 1;
foreach ($historyRows as $row) {
?>
            <tr>
              <td class="inv-col-num"><?php echo $h((string) $num); ?></td>
              <td><?php echo $h($row['log_date_display']); ?></td>
              <td><code class="inv-pre-id"><?php echo $h($row['pre_id']); ?></code></td>
              <td><?php echo $h($row['log_part']); ?></td>
              <td class="inv-col-num"><strong><?php echo $h(number_format($row['prod_qty'], 0, '.', ',')); ?></strong></td>
              <td><?php echo $h($row['log_by']); ?></td>
            </tr>
<?php
    $num++;
}
?>
          </tbody>
        </table>
      </div>
<?php } else { ?>
      <div class="inv-chart-empty"><i class="fa fa-inbox"></i> <?php echo $h($lblEmpty); ?></div>
<?php } ?>
    </div>
  </section>

  <section class="inv-card">
    <div class="inv-card__body inv-report-actions">
      <a href="stock_report.php" class="inv-btn inv-btn--primary"><i class="fa fa-line-chart"></i> <?php echo $h($lblViewReport); ?></a>
    </div>
  </section>
</main>
</body>
</html>
