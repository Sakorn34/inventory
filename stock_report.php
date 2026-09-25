<?php
/**
 * รายงาน Stock — กราฟเส้นเปรียบเทียบการเบิกใช้รายเดือนต่อปี (กรองตาม log_part)
 *
 * อ่านจาก log_stock เฉพาะ log_type = เบิกผลิต
 * จำนวนเครื่อง = SUM(log_amount) ของ log_part ที่เลือก ต่อ pre_id รายเดือน
 *
 * Flow: เลือก log_part + ปี → กราฟเส้น (1 เส้นต่อ 1 ปี)
 *
 * Example: stock_report.php?log_part=Board+Main&years[]=2025&years[]=2026
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/stock_report_lib.php';

header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$lblTitle = 'รายงาน Stock';
$lblDesc = 'กราฟเส้นเปรียบเทียบยอดเบิกรายเดือน — เลือก Part (log_part) แล้วเทียบข้ามปีได้';
$lblChartNote = 'นับเฉพาะ log_type = เบิกผลิต — ยอดรายเดือน = รวม log_amount ของ log_part ที่เลือก (ต่อ pre_id)';
$lblChartY = 'จำนวนเบิกผลิต (เครื่อง)';
$lblFilter = 'กรองข้อมูล';
$lblLogPart = 'Part (log_part)';
$lblYears = 'เลือกปีเทียบ';
$lblAllLogPart = '-- ทั้งหมด --';
$lblBtnShow = 'แสดงผล';
$lblBtnReset = 'ล้าง';
$lblChartTitle = 'สถิติการเบิกใช้ Stock รายเดือน';
$lblEmpty = 'ไม่พบข้อมูลการเบิกในช่วงที่เลือก';
$lblTotalYear = 'รวมทั้งปี';
$lblTxYear = 'จำนวนรายการเบิกผลิต';
$lblViewOverview = 'ภาพรวม Stock';
$lblViewPartList = 'ดูรายการ Part';

$availableYears = stock_report_available_years($connect);
$logPartList = stock_report_log_part_list($connect);

$requestedYears = array();
if (isset($_GET['years'])) {
    $rawYears = is_array($_GET['years']) ? $_GET['years'] : explode(',', (string) $_GET['years']);
    foreach ($rawYears as $y) {
        $requestedYears[] = (int) trim((string) $y);
    }
}
$selectedYears = stock_report_resolve_years($requestedYears, $availableYears);

$logPartExplicit = isset($_GET['log_part']);
$getLogPart = $logPartExplicit ? trim((string) $_GET['log_part']) : '';
$getLogPart = stock_report_resolve_log_part($getLogPart, $logPartList);

if ($getLogPart === '' && !$logPartExplicit && count($logPartList) > 0 && count($selectedYears) > 0) {
    $defaultParts = stock_report_top_log_parts_by_year($connect, $selectedYears[0], 1);
    if (count($defaultParts) > 0) {
        $getLogPart = stock_report_resolve_log_part($defaultParts[0], $logPartList);
    }
}

$reportData = stock_report_monthly_usage($connect, $selectedYears, $getLogPart);

$chartYearsSorted = $reportData['years'];
sort($chartYearsSorted, SORT_NUMERIC);
$latestYear = count($chartYearsSorted) > 0 ? (int) max($chartYearsSorted) : 0;
$mutedYearColors = array('#94a3b8', '#a8a29e', '#9ca3af', '#b0bec5', '#cbd5e1');
$vividLatestColor = '#dc2626';

$chartPayload = array(
    'months' => $reportData['months'],
    'datasets' => array(),
    'yLabel' => $lblChartY,
    'lblPieces' => 'เครื่อง',
);

$mutedIdx = 0;
foreach ($chartYearsSorted as $year) {
    $isLatest = ((int) $year === $latestYear);
    $lineColor = $isLatest ? $vividLatestColor : $mutedYearColors[$mutedIdx % count($mutedYearColors)];
    $chartPayload['datasets'][] = array(
        'label' => (string) $year . ($isLatest ? ' (ล่าสุด)' : ''),
        'data' => array_values($reportData['series'][$year]),
        'borderColor' => $lineColor,
        'backgroundColor' => $lineColor,
        'borderWidth' => $isLatest ? 4 : 1.5,
        'pointRadius' => $isLatest ? 6 : 2,
        'pointHoverRadius' => $isLatest ? 8 : 4,
        'pointBorderWidth' => $isLatest ? 2 : 0,
        'pointBorderColor' => $isLatest ? '#ffffff' : $lineColor,
        'borderDash' => $isLatest ? array() : array(6, 4),
        'tension' => 0.25,
        'fill' => false,
        'order' => $isLatest ? 0 : 1,
    );
    if (!$isLatest) {
        $mutedIdx++;
    }
}

$hasData = false;
foreach ($reportData['yearTotals'] as $total) {
    if ($total > 0) {
        $hasData = true;
        break;
    }
}

$chartTitleSuffix = ($getLogPart !== '') ? $getLogPart : $lblAllLogPart;
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
function stockReportSubmit(){
  var q=[];
  var p=document.getElementById("log_part_name").value;
  if(p&&p!=="All"){q.push("log_part="+encodeURIComponent(p));}
  $('input[name="years[]"]:checked').each(function(){
    q.push("years[]="+encodeURIComponent($(this).val()));
  });
  location="stock_report.php"+(q.length?"?"+q.join("&"):"");
}
function stockReportReset(){location="stock_report.php";}
function invInitUsageLineChart(payload){
  if(!payload||typeof Chart==="undefined"){return;}
  var el=document.getElementById("invUsageLineChart");
  if(!el||!payload.datasets||!payload.datasets.length){return;}
  Chart.defaults.font.family="'Sarabun','Segoe UI',Tahoma,sans-serif";
  new Chart(el,{
    type:"line",
    data:{labels:payload.months,datasets:payload.datasets},
    options:{
      responsive:true,
      maintainAspectRatio:false,
      interaction:{mode:"index",intersect:false},
      plugins:{
        legend:{position:"top",labels:{boxWidth:12,padding:14}},
        tooltip:{
          callbacks:{
            label:function(){return "";},
            afterBody:function(items){
              if(!items||!items.length){return [];}
              return items.map(function(item){
                return "▸ "+item.dataset.label+" — รวม "+(item.parsed.y||0).toLocaleString("th-TH")+" "+payload.lblPieces;
              });
            }
          }
        }
      },
      scales:{
        y:{
          beginAtZero:true,
          title:{display:true,text:payload.yLabel,font:{size:12,weight:"600"}},
          grid:{color:"#e2e8f0"}
        },
        x:{grid:{display:false}}
      }
    }
  });
}
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
        <div class="inv-field inv-field--years">
          <span class="inv-field__legend"><?php echo $h($lblYears); ?></span>
          <div class="inv-year-picks">
<?php if (count($availableYears) === 0) { ?>
            <span class="inv-muted">ยังไม่มีข้อมูล log การเบิก</span>
<?php } else {
    foreach ($availableYears as $yr) {
        $checked = in_array((int) $yr, $selectedYears, true) ? ' checked' : '';
        echo '<label class="inv-year-pick"><input type="checkbox" name="years[]" value="' . $h((string) $yr) . '"' . $checked . '> ' . $h((string) $yr) . '</label>';
    }
} ?>
          </div>
        </div>
        <div class="inv-filters__actions">
          <button type="button" class="inv-btn inv-btn--primary" onclick="stockReportSubmit();">
            <i class="fa fa-search"></i> <?php echo $h($lblBtnShow); ?>
          </button>
          <button type="button" class="inv-btn inv-btn--ghost" onclick="stockReportReset();">
            <i class="fa fa-refresh"></i> <?php echo $h($lblBtnReset); ?>
          </button>
        </div>
      </form>
    </div>
  </section>

  <section class="inv-card">
    <div class="inv-card__head"><i class="fa fa-line-chart"></i> <?php echo $h($lblChartTitle); ?> — <?php echo $h($chartTitleSuffix); ?></div>
    <div class="inv-card__body">
      <p class="inv-chart-note"><i class="fa fa-info-circle"></i> <?php echo $h($lblChartNote); ?></p>
<?php if ($hasData) { ?>
      <div class="inv-usage-chart-wrap">
        <canvas id="invUsageLineChart"></canvas>
      </div>
      <div class="inv-usage-year-totals">
<?php foreach ($reportData['yearTotals'] as $year => $total) {
    $isLatestCard = ((int) $year === $latestYear);
    $cardClass = $isLatestCard ? ' inv-usage-year-card--latest' : '';
?>
        <div class="inv-usage-year-card<?php echo $cardClass; ?>">
          <span class="inv-usage-year-card__year"><?php echo $h((string) $year); ?><?php echo $isLatestCard ? ' ★' : ''; ?></span>
          <span class="inv-usage-year-card__val"><?php echo $h(number_format($total, 0, '.', ',')); ?></span>
          <span class="inv-usage-year-card__lbl"><?php echo $h($lblTotalYear); ?> (<?php echo $h($lblChartY); ?>)</span>
          <span class="inv-usage-year-card__sub"><?php echo $h($lblTxYear); ?>: <?php echo $h((string) $reportData['txCounts'][$year]); ?></span>
        </div>
<?php } ?>
      </div>
<?php } else { ?>
      <div class="inv-chart-empty"><i class="fa fa-inbox"></i> <?php echo $h($lblEmpty); ?></div>
<?php } ?>
    </div>
  </section>

  <section class="inv-card">
    <div class="inv-card__body inv-report-actions">
      <a href="stock_overview.php" class="inv-btn inv-btn--ghost"><i class="fa fa-star"></i> <?php echo $h($lblViewOverview); ?></a>
      <a href="stock_overview.php#inv-part-table" class="inv-btn inv-btn--primary"><i class="fa fa-table"></i> <?php echo $h($lblViewPartList); ?></a>
    </div>
  </section>
</main>
<?php if ($hasData) { ?>
<script>invInitUsageLineChart(<?php echo json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>);</script>
<?php } ?>
</body>
</html>
