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
$lblStockLevel = 'ระดับสต็อก';
$lblStockLowOnly = 'ต่ำกว่าขั้นต่ำ';
$lblStockOkOnly = 'สต็อกเพียงพอ';
$lblStockZero = 'สต็อกหมด (0)';
$lblStockNegative = 'สต็อกติดลบ';
$lblStatLineAlert = 'เปิดแจ้ง Line';
$lblChartStatus = 'สัดส่วนสถานะการสั่งซื้อ';
$lblChartSeverity = 'ความรุนแรงสต็อกต่ำ';
$lblChartTopShort = 'Top Part ที่ขาดมากที่สุด';
$lblChartTopEmpty = 'ไม่มี Part ที่ต่ำกว่าขั้นต่ำ';
$lblChartClickHint = 'คลิกที่กราฟหรือการ์ดเพื่อกรองตารางด้านล่าง';
$lblSeveritySlight = 'ต่ำเล็กน้อย';
$lblSeverityCritical = 'ต่ำมาก';
$lblSeverityEmpty = 'หมด/ติดลบ';
$lblStatusUnset = 'ไม่ระบุ';
$lblShortage = 'ขาด';
$stockFilterLow = 'low';
$stockFilterOk = 'ok';
$stockFilterZero = 'zero';
$stockFilterNegative = 'negative';
$severityFilterSlight = 'slight';
$severityFilterCritical = 'critical';
$severityFilterEmpty = 'empty';
$lblEditCol = 'แก้ไข';
$lblLineAlert = 'แจ้ง Line';
$lblLineAlertHint = 'เมื่อสต็อกต่ำกว่าขั้นต่ำ (วันละครั้ง)';
$lblLineSave = 'บันทึกการตั้งค่าแจ้ง Line';
$lblEmpty = 'ไม่พบข้อมูล';
$lblEditTitle = 'แก้ไข Part';
$lblCharts = 'สรุปภาพรวม Stock';
$lblStatTotal = 'Part ทั้งหมด';
$lblStatLow = 'ต่ำกว่าขั้นต่ำ';
$lblStatOk = 'สต็อกเพียงพอ';
$lblChartSummary = 'สัดส่วน Stock เทียบจำนวนขั้นต่ำ';
$lblChartFromTotal = 'จาก Part ทั้งหมด';
$h = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$search = '';
$getData = isset($_GET['product']) ? trim((string) $_GET['product']) : '';
$getStatus = isset($_GET['part_status']) ? trim((string) $_GET['part_status']) : '';
$getStockFilter = isset($_GET['stock_filter']) ? trim((string) $_GET['stock_filter']) : '';
$getSeverityFilter = isset($_GET['severity_filter']) ? trim((string) $_GET['severity_filter']) : '';
$getDataSql = ($getData !== '' && $getData !== 'All') ? (int) $getData : 0;
$getStatusSql = db_escape($connect, $getStatus);
$filterLowStock = ($getStockFilter === $stockFilterLow);
$filterOkStock = ($getStockFilter === $stockFilterOk);
$filterZeroStock = ($getStockFilter === $stockFilterZero);
$filterNegativeStock = ($getStockFilter === $stockFilterNegative);

$where = array();
if ($getData !== '' && $getData !== 'All') {
    $where[] = 'part.[part_categories]=' . $getDataSql;
}
if ($getStatus !== '') {
    if ($getStatus === $statusNotYet) {
        $where[] = "(part.[part_status_purchase]='" . $getStatusSql . "' OR ((part.[part_status_purchase] IS NULL OR part.[part_status_purchase]='') AND part.[part_amount] <= part.[part_min_alert]))";
    } elseif ($getStatus === $lblStatusUnset) {
        $where[] = "(part.[part_status_purchase] IS NULL OR part.[part_status_purchase]='') AND part.[part_amount] > part.[part_min_alert]";
    } else {
        $where[] = "part.[part_status_purchase]='" . $getStatusSql . "'";
    }
}
if ($filterLowStock) {
    $where[] = 'part.[part_amount] <= part.[part_min_alert]';
} elseif ($filterOkStock) {
    $where[] = 'part.[part_amount] > part.[part_min_alert]';
} elseif ($filterZeroStock) {
    $where[] = 'part.[part_amount] = 0';
} elseif ($filterNegativeStock) {
    $where[] = 'part.[part_amount] < 0';
}
if ($getSeverityFilter === $severityFilterEmpty) {
    $where[] = 'part.[part_amount] <= 0';
} elseif ($getSeverityFilter === $severityFilterCritical) {
    $where[] = '(part.[part_amount] > 0 AND part.[part_amount] <= part.[part_min_alert] AND (part.[part_min_alert] <= 0 OR part.[part_amount] <= part.[part_min_alert] * 0.5))';
} elseif ($getSeverityFilter === $severityFilterSlight) {
    $where[] = '(part.[part_amount] > part.[part_min_alert] * 0.5 AND part.[part_amount] <= part.[part_min_alert] AND part.[part_min_alert] > 0)';
}

$hasFilter = count($where) > 0;
$orderBy = $hasFilter ? 'part.[part_name] ASC' : 'part.[part_categories],part.[part_name] ASC';
$sql = 'SELECT * FROM part INNER JOIN categories_product ON part.[part_categories]=categories_product.[ct_id]';
if ($hasFilter) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $orderBy;
$array = array('All'); $array2 = array('All');
$exeSP = db_exec($connect, 'SELECT * FROM categories_product') or die('Error categories_product');
while ($resultSP = db_fetch_array($exeSP)) { $array[] = $resultSP['ct_name']; $array2[] = $resultSP['ct_id']; }
$arrLength = count($array);

$statusKnownOrder = array($statusNormal, $statusReached, $statusNotYet, 'ยังไม่ได้สั่งซื้อ', 'สั่งซื้อแล้ว', 'ยกเลิกใช้');
$statusFromDb = array();
$exeStatus = db_exec($connect, "SELECT DISTINCT part_status_purchase FROM part ORDER BY part_status_purchase ASC") or die('Error status');
while ($rowSt = db_fetch_array($exeStatus)) {
    $st = trim((string) $rowSt['part_status_purchase']);
    if ($st !== '') {
        $statusFromDb[$st] = true;
    }
}
$statusList = array('');
$statusSeen = array();
foreach ($statusKnownOrder as $st) {
    $statusList[] = $st;
    $statusSeen[$st] = true;
}
foreach (array_keys($statusFromDb) as $st) {
    if (!isset($statusSeen[$st])) {
        $statusList[] = $st;
        $statusSeen[$st] = true;
    }
}
if (!isset($statusSeen[$lblStatusUnset])) {
    $statusList[] = $lblStatusUnset;
}

$statusBadgeClass = static function ($status) use ($statusNotYet, $statusReached, $statusNormal) {
    if ($status === $statusNormal) { return 'inv-badge--success'; }
    if ($status === $statusReached || $status === 'สั่งซื้อแล้ว') { return 'inv-badge--warn'; }
    if ($status === '' || $status === $statusNotYet || $status === 'ยังไม่ได้สั่งซื้อ') { return 'inv-badge--danger'; }
    if ($status === 'ยกเลิกใช้') { return 'inv-badge--muted'; }
    return 'inv-badge--muted';
};
$dash = "\xE2\x80\x94";

$parts = array();
$execChart = db_exec($connect, $sql) or die('Error Query');
while ($rowChart = db_fetch_array($execChart)) {
    $parts[] = $rowChart;
}

$totalParts = count($parts);
$lowStockCount = 0;
$okStockCount = 0;
$zeroStockCount = 0;
$negativeStockCount = 0;
$lineAlertCount = 0;
$severityCounts = array(
    $severityFilterSlight => 0,
    $severityFilterCritical => 0,
    $severityFilterEmpty => 0,
);
$statusCounts = array();
$shortageList = array();

foreach ($parts as $rowChart) {
    $amount = (float) $rowChart['part_amount'];
    $min = (float) $rowChart['part_min_alert'];
    $diff = $amount - $min;
    $low = ($diff <= 0);

    if ($low) {
        $lowStockCount++;
        $shortageList[] = array(
            'name' => (string) $rowChart['part_name'],
            'shortage' => $min - $amount,
        );
        if ($amount <= 0) {
            $severityCounts[$severityFilterEmpty]++;
        } elseif ($min > 0 && $amount <= $min * 0.5) {
            $severityCounts[$severityFilterCritical]++;
        } else {
            $severityCounts[$severityFilterSlight]++;
        }
    } else {
        $okStockCount++;
    }
    if ($amount === 0.0) {
        $zeroStockCount++;
    }
    if ($amount < 0) {
        $negativeStockCount++;
    }
    if (!empty($rowChart['part_line_alert'])) {
        $lineAlertCount++;
    }

    $ps = trim((string) $rowChart['part_status_purchase']);
    if ($ps !== '') {
        $displayStatus = $ps;
    } elseif ($low) {
        $displayStatus = $statusNotYet;
    } else {
        $displayStatus = $lblStatusUnset;
    }
    if (!isset($statusCounts[$displayStatus])) {
        $statusCounts[$displayStatus] = 0;
    }
    $statusCounts[$displayStatus]++;
}

usort($shortageList, static function ($a, $b) {
    if ($a['shortage'] === $b['shortage']) {
        return 0;
    }
    return ($a['shortage'] > $b['shortage']) ? -1 : 1;
});
$shortageList = array_slice($shortageList, 0, 8);

$pctOk = 0.0;
$pctLow = 0.0;
if ($totalParts > 0) {
    $pctOk = round($okStockCount / $totalParts * 100, 1);
    $pctLow = round(100 - $pctOk, 1);
}

$statusChartLabels = array();
$statusChartValues = array();
$statusChartKeys = array();
foreach ($statusCounts as $stLabel => $stCount) {
    $statusChartLabels[] = $stLabel;
    $statusChartValues[] = $stCount;
    $statusChartKeys[] = ($stLabel === $lblStatusUnset) ? $lblStatusUnset : $stLabel;
}

$chartPayload = array(
    'total' => $totalParts,
    'ok' => $okStockCount,
    'low' => $lowStockCount,
    'zero' => $zeroStockCount,
    'negative' => $negativeStockCount,
    'lineAlert' => $lineAlertCount,
    'pctOk' => $pctOk,
    'pctLow' => $pctLow,
    'statusLabels' => $statusChartLabels,
    'statusValues' => $statusChartValues,
    'statusKeys' => $statusChartKeys,
    'severityLabels' => array($lblSeveritySlight, $lblSeverityCritical, $lblSeverityEmpty),
    'severityValues' => array(
        $severityCounts[$severityFilterSlight],
        $severityCounts[$severityFilterCritical],
        $severityCounts[$severityFilterEmpty],
    ),
    'severityKeys' => array($severityFilterSlight, $severityFilterCritical, $severityFilterEmpty),
    'shortageLabels' => array_column($shortageList, 'name'),
    'shortageValues' => array_column($shortageList, 'shortage'),
    'filters' => array(
        'stockLow' => $stockFilterLow,
        'stockOk' => $stockFilterOk,
        'stockZero' => $stockFilterZero,
        'stockNegative' => $stockFilterNegative,
    ),
    'labels' => array(
        'ok' => $lblStatOk,
        'low' => $lblStatLow,
        'zero' => $lblStockZero,
        'negative' => $lblStockNegative,
        'lineAlert' => $lblStatLineAlert,
        'total' => $lblStatTotal,
        'fromTotal' => $lblChartFromTotal,
        'shortage' => $lblShortage,
    ),
);

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $h($titlePage); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>
<script src="js/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var invStockPage='stock_overview.php';
function invBuildFilterUrl(overrides){
  overrides=overrides||{};
  var pEl=document.getElementById("product_name");
  var sEl=document.getElementById("part_status");
  var fEl=document.getElementById("stock_filter");
  var vEl=document.getElementById("severity_filter");
  var p=pEl?pEl.value:"";
  if(p==="All"){p="";}
  var s=overrides.part_status!==undefined?(overrides.part_status||""):(sEl?sEl.value:"");
  var f=overrides.stock_filter!==undefined?(overrides.stock_filter||""):(fEl?fEl.value:"");
  var v=overrides.severity_filter!==undefined?(overrides.severity_filter||""):(vEl?vEl.value:"");
  var q=[];
  if(p){q.push("product="+encodeURIComponent(p));}
  if(s){q.push("part_status="+encodeURIComponent(s));}
  if(f){q.push("stock_filter="+encodeURIComponent(f));}
  if(v){q.push("severity_filter="+encodeURIComponent(v));}
  return invStockPage+(q.length?"?"+q.join("&"):"");
}
function invApplyChartFilter(overrides){
  var url=invBuildFilterUrl(overrides);
  if(document.getElementById("inv-part-table")){
    url+=(url.indexOf("#")<0?"#inv-part-table":"");
  }
  location=url;
}
function sort_product(){
  var vEl=document.getElementById("severity_filter");
  if(vEl){vEl.value="";}
  location=invBuildFilterUrl({severity_filter:''});
}
function reset_filters(){location=invStockPage;}
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

function invInitStockDashboard(payload){
  if(!payload||typeof Chart==='undefined'){return;}
  var fontFamily="'Sarabun','Segoe UI',Tahoma,sans-serif";
  Chart.defaults.font.family=fontFamily;
  Chart.defaults.color='#64748b';

  var pctPlugin={
    id:'invPiePercent',
    afterDraw:function(chart){
      var ctx=chart.ctx, meta=chart.getDatasetMeta(0), data=chart.data.datasets[0].data;
      var total=data.reduce(function(a,b){return a+b;},0);
      if(!total){return;}
      meta.data.forEach(function(arc,i){
        var pct=Math.round(data[i]/total*1000)/10;
        if(pct<6){return;}
        var pos=arc.tooltipPosition();
        ctx.save();
        ctx.fillStyle='#fff';
        ctx.font='600 10px '+fontFamily;
        ctx.textAlign='center';
        ctx.textBaseline='middle';
        ctx.fillText(pct+'%',pos.x,pos.y);
        ctx.restore();
      });
    }
  };

  var pieOpts={
    responsive:true,
    maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    onClick:function(evt,els){
      if(!els.length){return;}
      var chart=this, idx=els[0].index, action=chart.config.options.invClick;
      if(!action){return;}
      if(action.type==='stock'){invApplyChartFilter({stock_filter:action.values[idx],severity_filter:'',part_status:''});}
      if(action.type==='status'){invApplyChartFilter({part_status:action.values[idx],stock_filter:'',severity_filter:''});}
      if(action.type==='severity'){invApplyChartFilter({severity_filter:action.values[idx],stock_filter:payload.filters.stockLow,part_status:''});}
    }
  };

  var summaryEl=document.getElementById('invChartSummary');
  if(summaryEl){
    var sOpts=Object.assign({},pieOpts,{invClick:{type:'stock',values:[payload.filters.stockOk,payload.filters.stockLow]}});
    new Chart(summaryEl,{
      type:'pie',
      data:{
        labels:[payload.labels.ok,payload.labels.low],
        datasets:[{data:[payload.ok,payload.low],backgroundColor:['#059669','#dc2626'],borderWidth:2,borderColor:'#fff'}]
      },
      plugins:[pctPlugin],
      options:sOpts
    });
  }

  var statusEl=document.getElementById('invChartStatus');
  if(statusEl&&payload.statusLabels&&payload.statusLabels.length){
    var statusColors=['#059669','#d97706','#dc2626','#64748b','#7c3aed','#0891b2','#ca8a04','#94a3b8'];
    var stOpts=Object.assign({},pieOpts,{invClick:{type:'status',values:payload.statusKeys||payload.statusLabels}});
    new Chart(statusEl,{
      type:'pie',
      data:{
        labels:payload.statusLabels,
        datasets:[{data:payload.statusValues,backgroundColor:statusColors.slice(0,payload.statusLabels.length),borderWidth:2,borderColor:'#fff'}]
      },
      plugins:[pctPlugin],
      options:stOpts
    });
  }

  var sevEl=document.getElementById('invChartSeverity');
  if(sevEl){
    var sevOpts=Object.assign({},pieOpts,{invClick:{type:'severity',values:payload.severityKeys}});
    new Chart(sevEl,{
      type:'pie',
      data:{
        labels:payload.severityLabels,
        datasets:[{data:payload.severityValues,backgroundColor:['#fbbf24','#f97316','#dc2626'],borderWidth:2,borderColor:'#fff'}]
      },
      plugins:[pctPlugin],
      options:sevOpts
    });
  }

  var topEl=document.getElementById('invChartTopShort');
  if(topEl&&payload.shortageLabels&&payload.shortageLabels.length){
    new Chart(topEl,{
      type:'bar',
      data:{
        labels:payload.shortageLabels,
        datasets:[{label:payload.labels.shortage,data:payload.shortageValues,backgroundColor:'rgba(220,38,38,0.75)',borderRadius:4}]
      },
      options:{
        indexAxis:'y',
        responsive:true,
        maintainAspectRatio:false,
        scales:{x:{beginAtZero:true,grid:{color:'#e2e8f0'}},y:{grid:{display:false}}},
        plugins:{legend:{display:false}},
        onClick:function(){
          invApplyChartFilter({stock_filter:payload.filters.stockLow,severity_filter:'',part_status:''});
        }
      }
    });
  }

  $(document).on('click','[data-inv-filter]',function(){
    var $t=$(this), o={};
    if($t.data('stock-filter')!==undefined){o.stock_filter=$t.data('stock-filter');o.severity_filter='';o.part_status='';}
    if($t.data('part-status')!==undefined){o.part_status=$t.data('part-status');o.stock_filter='';o.severity_filter='';}
    invApplyChartFilter(o);
  });
}
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
        <input type="hidden" id="severity_filter" value="<?php echo $h($getSeverityFilter); ?>">
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
        <div class="inv-field">
          <label for="stock_filter"><?php echo $h($lblStockLevel); ?></label>
          <select class="inv-select" id="stock_filter" name="stock_filter">
            <option value=""<?php echo ($getStockFilter === '') ? ' selected' : ''; ?>><?php echo $h($lblAllOpt); ?></option>
            <option value="<?php echo $h($stockFilterOk); ?>"<?php echo $filterOkStock ? ' selected' : ''; ?>><?php echo $h($lblStockOkOnly); ?></option>
            <option value="<?php echo $h($stockFilterLow); ?>"<?php echo $filterLowStock ? ' selected' : ''; ?>><?php echo $h($lblStockLowOnly); ?></option>
            <option value="<?php echo $h($stockFilterZero); ?>"<?php echo $filterZeroStock ? ' selected' : ''; ?>><?php echo $h($lblStockZero); ?></option>
            <option value="<?php echo $h($stockFilterNegative); ?>"<?php echo $filterNegativeStock ? ' selected' : ''; ?>><?php echo $h($lblStockNegative); ?></option>
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
    <div class="inv-card__head"><i class="fa fa-pie-chart"></i> <?php echo $h($lblCharts); ?></div>
    <div class="inv-card__body">
      <p class="inv-chart-hint"><i class="fa fa-hand-pointer-o"></i> <?php echo $h($lblChartClickHint); ?></p>
<?php if ($totalParts > 0) { ?>
      <div class="inv-dash-pies">
        <div class="inv-dash-pie">
          <p class="inv-chart-box__title"><?php echo $h($lblChartSummary); ?></p>
          <div class="inv-pie-summary__chart-wrap"><canvas id="invChartSummary"></canvas></div>
        </div>
        <div class="inv-dash-pie">
          <p class="inv-chart-box__title"><?php echo $h($lblChartStatus); ?></p>
          <div class="inv-pie-summary__chart-wrap"><canvas id="invChartStatus"></canvas></div>
        </div>
        <div class="inv-dash-pie">
          <p class="inv-chart-box__title"><?php echo $h($lblChartSeverity); ?></p>
          <div class="inv-pie-summary__chart-wrap"><canvas id="invChartSeverity"></canvas></div>
        </div>
      </div>
      <div class="inv-dash-stats">
        <button type="button" class="inv-dash-stat inv-dash-stat--ok" data-inv-filter data-stock-filter="<?php echo $h($stockFilterOk); ?>">
          <span class="inv-dash-stat__val"><?php echo $h((string) $pctOk); ?>%</span>
          <span class="inv-dash-stat__lbl"><?php echo $h($lblStatOk); ?></span>
          <span class="inv-dash-stat__sub"><?php echo $h((string) $okStockCount); ?> <?php echo $h($lblStatTotal); ?></span>
        </button>
        <button type="button" class="inv-dash-stat inv-dash-stat--low" data-inv-filter data-stock-filter="<?php echo $h($stockFilterLow); ?>">
          <span class="inv-dash-stat__val"><?php echo $h((string) $pctLow); ?>%</span>
          <span class="inv-dash-stat__lbl"><?php echo $h($lblStatLow); ?></span>
          <span class="inv-dash-stat__sub"><?php echo $h((string) $lowStockCount); ?> <?php echo $h($lblStatTotal); ?></span>
        </button>
        <button type="button" class="inv-dash-stat inv-dash-stat--zero" data-inv-filter data-stock-filter="<?php echo $h($stockFilterZero); ?>">
          <span class="inv-dash-stat__val"><?php echo $h((string) $zeroStockCount); ?></span>
          <span class="inv-dash-stat__lbl"><?php echo $h($lblStockZero); ?></span>
        </button>
        <button type="button" class="inv-dash-stat inv-dash-stat--neg" data-inv-filter data-stock-filter="<?php echo $h($stockFilterNegative); ?>">
          <span class="inv-dash-stat__val"><?php echo $h((string) $negativeStockCount); ?></span>
          <span class="inv-dash-stat__lbl"><?php echo $h($lblStockNegative); ?></span>
        </button>
        <div class="inv-dash-stat inv-dash-stat--line">
          <span class="inv-dash-stat__val"><?php echo $h((string) $lineAlertCount); ?></span>
          <span class="inv-dash-stat__lbl"><?php echo $h($lblStatLineAlert); ?></span>
        </div>
        <div class="inv-dash-stat inv-dash-stat--total">
          <span class="inv-dash-stat__val"><?php echo $h((string) $totalParts); ?></span>
          <span class="inv-dash-stat__lbl"><?php echo $h($lblStatTotal); ?></span>
        </div>
      </div>
      <div class="inv-dash-top">
        <p class="inv-chart-box__title"><?php echo $h($lblChartTopShort); ?></p>
<?php if (count($shortageList) > 0) { ?>
        <div class="inv-dash-top__chart" style="height:<?php echo max(160, count($shortageList) * 32); ?>px;">
          <canvas id="invChartTopShort"></canvas>
        </div>
<?php } else { ?>
        <div class="inv-chart-empty"><i class="fa fa-check-circle"></i> <?php echo $h($lblChartTopEmpty); ?></div>
<?php } ?>
      </div>
<?php } else { ?>
      <div class="inv-chart-empty"><i class="fa fa-inbox"></i> <?php echo $h($lblEmpty); ?></div>
<?php } ?>
    </div>
  </section>
<?php if ($totalParts > 0) { ?>
  <script>
  invInitStockDashboard(<?php echo json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>);
  </script>
<?php } ?>
  <section class="inv-card" id="inv-part-table">
    <div class="inv-card__head"><i class="fa fa-table"></i> <?php echo $h($lblTable); ?></div>
    <form method="post" action="part_line_alert_save.php">
      <input type="hidden" name="product" value="<?php echo $h($getData); ?>">
      <input type="hidden" name="part_status" value="<?php echo $h($getStatus); ?>">
      <input type="hidden" name="stock_filter" value="<?php echo $h($getStockFilter); ?>">
      <input type="hidden" name="severity_filter" value="<?php echo $h($getSeverityFilter); ?>">
    <div class="inv-table-wrap inv-table-wrap--scroll-y">
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
    $num = 1;
    $rows = count($parts);
    foreach ($parts as $result) {
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
