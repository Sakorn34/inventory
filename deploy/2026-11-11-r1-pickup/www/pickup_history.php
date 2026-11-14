<?php
/**
 * หน้าประวัติการเบิกของ — เอกสารที่จ่ายแล้ว (อ่านจาก log_stock)
 *
 * แสดงรายการตาม pre_id ทุกประเภทที่ log_type ขึ้นต้นด้วย เบิก
 * กรองตามประเภท ปี และเดือนได้
 *
 * Flow: pickup_overview.php → ปุ่มดูประวัติ → หน้านี้
 *
 * ตัวอย่าง: pickup_history.php?log_type=เบิกผลิต&year=2026&month=5
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/pickup_history_lib.php';

header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$lblTitle = 'ประวัติการเบิกของ';
$lblDesc = 'เอกสารที่คลังจ่ายแล้ว — อ่านจาก log_stock (ไม่รวมรายการรอจ่ายบนหน้ารายการเบิกของ)';
$lblFilter = 'กรองข้อมูล';
$lblLogType = 'ประเภทการเบิก';
$lblYear = 'ปี';
$lblMonth = 'เดือน';
$lblAllType = '-- ทุกประเภท --';
$lblAllYear = '-- ทุกปี --';
$lblAllMonth = '-- ทุกเดือน --';
$lblBtnShow = 'แสดงผล';
$lblBtnReset = 'ล้าง';
$lblBtnPending = 'รายการรอจ่าย';
$lblTable = 'ประวัติเอกสารเบิก';
$lblEmpty = 'ไม่พบประวัติในช่วงที่เลือก';
$lblNote = 'แสดงเฉพาะรายการที่บันทึกใน log_stock หลังยืนยันจ่ายของ — กดที่แถวเพื่อดูรายการ Part';
$lblColNo = 'ลำดับ';
$lblColDate = 'วันที่เบิก';
$lblColPreId = 'เลขเอกสาร';
$lblColType = 'ประเภท';
$lblColParts = 'จำนวน Part';
$lblColQty = 'รวมจำนวน';
$lblColBy = 'ผู้เบิก';
$lblLimitNote = 'แสดงล่าสุด';

$typeList = pickup_history_type_list($connect);
$availableYears = pickup_history_available_years($connect);
$monthLabels = stock_report_month_labels();

$getLogType = pickup_history_resolve_type(
    isset($_GET['log_type']) ? (string) $_GET['log_type'] : '',
    $typeList
);
$getYear = stock_report_resolve_history_year(
    isset($_GET['year']) ? (int) $_GET['year'] : 0,
    $availableYears
);
$getMonth = stock_report_resolve_history_month(isset($_GET['month']) ? (int) $_GET['month'] : 0);

$historyRows = pickup_history_fetch_documents($connect, $getLogType, $getYear, $getMonth);
$historyLimit = (int) PICKUP_HISTORY_DOC_LIMIT;
$isTruncated = count($historyRows) >= $historyLimit;

$typeSuffix = ($getLogType !== '') ? $getLogType : $lblAllType;
$periodSuffix = stock_report_history_period_label($getYear, $getMonth, $lblAllMonth);
$tableTitleSuffix = $typeSuffix . ' / ' . $periodSuffix;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $h($lblTitle); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>
<script src="js/jquery-3.5.1.min.js"></script>
<script>
function pickupHistorySubmit(){
  var q = [];
  var t = document.getElementById("filter_log_type").value;
  var y = document.getElementById("filter_year").value;
  var m = document.getElementById("filter_month").value;
  if (t) { q.push("log_type=" + encodeURIComponent(t)); }
  if (y && y !== "0") { q.push("year=" + encodeURIComponent(y)); }
  if (m && m !== "0") { q.push("month=" + encodeURIComponent(m)); }
  location = "pickup_history.php" + (q.length ? "?" + q.join("&") : "");
}
function pickupHistoryReset(){ location = "pickup_history.php"; }

function pickupHistoryEscape(text) {
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function pickupHistoryClosePreview() {
    var modal = document.getElementById("pickup_history_preview_modal");
    if (!modal) {
        return;
    }
    modal.style.display = "none";
    modal.setAttribute("aria-hidden", "true");
}

function pickupHistoryRenderPreview(data) {
    var title = document.getElementById("pickup_history_preview_title");
    var meta = document.getElementById("pickup_history_preview_meta");
    var body = document.getElementById("pickup_history_preview_body");
    if (!title || !meta || !body) {
        return;
    }
    title.textContent = data.pre_id;
    meta.textContent = [
        data.pre_date || "",
        data.pre_type || "",
        data.pre_by ? ("ผู้เบิก: " + data.pre_by) : ""
    ].filter(function (part) { return part !== ""; }).join(" · ");

    var remarksBlock = "";
    if (data.remarks) {
        remarksBlock = '<p class="pickup-doc-preview-remarks">' + pickupHistoryEscape(data.remarks) + "</p>";
    }

    var linesHtml = "";
    for (var i = 0; i < data.lines.length; i++) {
        var line = data.lines[i];
        var lineRemark = line.remarks
            ? ('<span class="pickup-doc-preview-line__remark">' + pickupHistoryEscape(line.remarks) + "</span>")
            : "";
        linesHtml +=
            '<li class="pickup-doc-preview-line pickup-doc-preview-line--readonly">' +
            '<div class="pickup-doc-preview-line__main">' +
            '<span class="pickup-doc-preview-line__name">' + pickupHistoryEscape(line.part_name) + "</span>" +
            (line.product ? ('<span class="pickup-doc-preview-line__product">' + pickupHistoryEscape(line.product) + "</span>") : "") +
            lineRemark +
            "</div>" +
            '<span class="pickup-doc-preview-line__qty">' + pickupHistoryEscape(line.amount) + "</span>" +
            "</li>";
    }

    body.innerHTML =
        remarksBlock +
        '<p class="pickup-doc-preview-count">' + data.line_count + " รายการอะไหล่</p>" +
        '<ul class="pickup-doc-preview-lines">' + linesHtml + "</ul>";
}

function pickupHistoryOpenPreview(preId) {
    var modal = document.getElementById("pickup_history_preview_modal");
    var loading = document.getElementById("pickup_history_preview_loading");
    var content = document.getElementById("pickup_history_preview_content");
    if (!modal || !loading || !content || !preId) {
        return;
    }
    modal.style.display = "flex";
    modal.setAttribute("aria-hidden", "false");
    loading.style.display = "block";
    content.style.display = "none";

    $.getJSON("pickup_history_lines.php", { pre_id: preId })
        .done(function (data) {
            if (!data || !data.ok) {
                alert((data && data.message) ? data.message : "โหลดรายการไม่สำเร็จ");
                pickupHistoryClosePreview();
                return;
            }
            pickupHistoryRenderPreview(data);
            loading.style.display = "none";
            content.style.display = "flex";
        })
        .fail(function () {
            alert("โหลดรายการอะไหล่ไม่สำเร็จ");
            pickupHistoryClosePreview();
        });
}

$(document).on("click", ".pickup-history-row", function () {
  pickupHistoryOpenPreview($(this).data("pre-id"));
});

$(document).on("keydown", ".pickup-history-row", function (event) {
  if (event.key === "Enter" || event.key === " ") {
    event.preventDefault();
    pickupHistoryOpenPreview($(this).data("pre-id"));
  }
});
</script>
</head>
<body class="inv-app pickup-history">
<?php require __DIR__ . '/title-h.php'; ?>
<main class="inv-main">
  <header class="inv-page-header">
    <h1 class="inv-page-title"><?php echo $h($lblTitle); ?></h1>
    <p class="inv-page-desc"><?php echo $h($lblDesc); ?></p>
  </header>

  <section class="inv-card pickup-history-filter-card">
    <div class="inv-card__head pickup-history-filter-head">
      <span class="pickup-history-filter-head__title"><i class="fa fa-filter"></i> <?php echo $h($lblFilter); ?></span>
      <a class="inv-btn inv-btn--ghost pickup-history-pending-link" href="pickup_overview.php"><i class="fa fa-list-alt"></i> <?php echo $h($lblBtnPending); ?></a>
    </div>
    <div class="inv-card__body">
      <form class="inv-filters inv-report-filters" onsubmit="return false;">
        <div class="inv-field">
          <label for="filter_log_type"><?php echo $h($lblLogType); ?></label>
          <select class="inv-select" id="filter_log_type" name="filter_log_type">
            <option value=""<?php echo ($getLogType === '') ? ' selected' : ''; ?>><?php echo $h($lblAllType); ?></option>
<?php foreach ($typeList as $typeName) {
    $sel = ($getLogType === $typeName) ? ' selected' : '';
    echo '<option value="' . $h($typeName) . '"' . $sel . '>' . $h($typeName) . '</option>';
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
          <button type="button" class="inv-btn inv-btn--primary" onclick="pickupHistorySubmit();">
            <i class="fa fa-search"></i> <?php echo $h($lblBtnShow); ?>
          </button>
          <button type="button" class="inv-btn inv-btn--ghost" onclick="pickupHistoryReset();">
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
<?php if ($isTruncated) { ?>
      <p class="inv-muted inv-history-limit"><i class="fa fa-exclamation-circle"></i>
        <?php echo $h($lblLimitNote); ?> <?php echo $h((string) $historyLimit); ?> เอกสาร</p>
<?php } ?>
      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th class="inv-col-num"><?php echo $h($lblColNo); ?></th>
              <th><?php echo $h($lblColDate); ?></th>
              <th><?php echo $h($lblColPreId); ?></th>
              <th><?php echo $h($lblColType); ?></th>
              <th class="inv-col-num"><?php echo $h($lblColParts); ?></th>
              <th class="inv-col-num"><?php echo $h($lblColQty); ?></th>
              <th><?php echo $h($lblColBy); ?></th>
            </tr>
          </thead>
          <tbody>
<?php
$num = 1;
foreach ($historyRows as $row) {
    $rowPreId = $row['pre_id'];
?>
            <tr class="pickup-history-row"
                data-pre-id="<?php echo $h($rowPreId); ?>"
                tabindex="0"
                role="button"
                aria-label="ดูรายการอะไหล่เอกสาร <?php echo $h($rowPreId); ?>">
              <td class="inv-col-num"><?php echo $h((string) $num); ?></td>
              <td><?php echo $h($row['log_date_display']); ?></td>
              <td class="pickup-history-doc-id"><?php echo $h($rowPreId); ?></td>
              <td><?php echo $h($row['log_type']); ?></td>
              <td class="inv-col-num"><?php echo $h((string) $row['part_count']); ?></td>
              <td class="inv-col-num"><strong><?php echo $h(number_format($row['total_qty'], 2, '.', ',')); ?></strong></td>
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
</main>

<div id="pickup_history_preview_modal" class="pickup-modal pickup-doc-preview-modal pickup-doc-preview-modal--readonly" aria-hidden="true" role="dialog" aria-labelledby="pickup_history_preview_title">
  <div class="pickup-modal__backdrop" onclick="pickupHistoryClosePreview();"></div>
  <div class="pickup-modal__panel pickup-doc-preview-panel">
    <button type="button" class="inv-btn inv-btn--primary pickup-history-preview__close" onclick="pickupHistoryClosePreview(); return false;">ปิด</button>
    <div id="pickup_history_preview_loading" class="pickup-doc-preview-loading">กำลังโหลดรายการ…</div>
    <div id="pickup_history_preview_content" class="pickup-doc-preview-content" style="display:none;">
      <header class="pickup-doc-preview-head">
        <p class="pickup-doc-preview-kicker">รายการอะไหล่ที่เบิก</p>
        <h2 id="pickup_history_preview_title" class="pickup-doc-preview-title">—</h2>
        <p id="pickup_history_preview_meta" class="pickup-doc-preview-meta"></p>
      </header>
      <div id="pickup_history_preview_body" class="pickup-doc-preview-body"></div>
    </div>
  </div>
</div>
</body>
</html>
