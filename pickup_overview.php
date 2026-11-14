<?php
/**
 * หน้ารายการเบิกของของระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือแสดงเอกสารเบิกที่ยังรอคลังจ่าย (pre_status = pre) ทั้งหมด
 * โหลดรายการผ่าน `pickup_list_order.php` โดย AJAX — ไม่กรองช่วงวันที่
 *
 * Flow สั้น: เปิด `pickup_overview.php` → JavaScript โหลดตารางรอจ่ายทันที
 * ปุ่มช่วยยกของ (เมื่อเปิด config): ส่ง GET จากเบราว์เซอร์ไป Node-RED call_Lift ใน LAN
 */

// ─ Imports ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

$pickupOverviewFlash = array('message' => '', 'type' => '');
if (!empty($_SESSION['pickup_list_order_flash']) && is_array($_SESSION['pickup_list_order_flash'])) {
    $pickupOverviewFlash = array(
        'message' => isset($_SESSION['pickup_list_order_flash']['message'])
            ? (string) $_SESSION['pickup_list_order_flash']['message'] : '',
        'type' => isset($_SESSION['pickup_list_order_flash']['type'])
            ? (string) $_SESSION['pickup_list_order_flash']['type'] : '',
    );
    unset($_SESSION['pickup_list_order_flash']);
}

//$date = date("Y-m-d");
//echo $date . "<br>";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>

<title>รายการเบิกของ</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
<script type="text/javascript">
var inventoryActorName = <?php echo json_encode(inventory_actor_name(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
//Search   Enduser / 
$(document.getElementById("user_company")).ready(function(){
    $('.search-box-user input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result");
        if(inputVal.length){
            $.get("search_cusname.php", {term: inputVal}).done(function(data){
                resultDropdown.html(data);  // Display the returned data in browser
            });
        } else{  resultDropdown.empty(); }
	});
	
    // Set search input value on click of result item
    $(document).on("click", ".result p", function(){
        $(this).parents(".search-box-user").find('input[type="text"]').val($(this).text());
        $(this).parent(".result").empty();
    });
});

//Search ����ѷ û�.
$(document.getElementById("security_company")).ready(function(){

    $('.search-box-sec input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result_sec");
        if(inputVal.length){
            $.get("search_cusname.php", {term: inputVal}).done(function(data){
                resultDropdown.html(data);  // Display the returned data in browser
            });
        } else{  resultDropdown.empty(); }
    });
    // Set search input value on click of result item
    $(document).on("click", ".result_sec p", function(){
        $(this).parents(".search-box-sec").find('input[type="text"]').val($(this).text());
        $(this).parent(".result_sec").empty();
    });
});


function pickupOverviewLoadList() {
	$.get("pickup_list_order.php", function(data) {
		$("#result").html(data);
	});
}

function listDateNow() {
	pickupOverviewLoadList();
	if (typeof pickupLiftInitOfficeVisibility === "function") {
		pickupLiftInitOfficeVisibility();
	}
}

$(document).on("click", "#pickup_overview_sync_btn", function () {
	var btn = $(this);
	btn.prop("disabled", true);
	pickupOverviewLoadList();
	window.setTimeout(function () {
		btn.prop("disabled", false);
	}, 600);
});

$(document).on("submit", ".delete-pickup-form", function(event) {
    event.preventDefault();

    var form = $(this);
  var preId = form.find('input[name="delete_pre_id"]').val() || '';
  if (!window.confirm('ยืนยันลบเอกสาร ' + preId + ' ?')) {
    return;
  }

    $.post(form.attr("action"), form.serialize(), function(data) {
        $("#result").html(data);
    }).fail(function() {
        alert("ลบรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง");
    });
});

function pickupDocPreviewEscape(text) {
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

var pickupDocPreviewData = null;

function closePickupDocPreview() {
    var modal = document.getElementById("pickup_doc_preview_modal");
    if (!modal) {
        return;
    }
    modal.style.display = "none";
    modal.setAttribute("aria-hidden", "true");
    pickupDocPreviewData = null;
}

function renderPickupDocPreview(data) {
    var title = document.getElementById("pickup_doc_preview_title");
    var meta = document.getElementById("pickup_doc_preview_meta");
    var body = document.getElementById("pickup_doc_preview_body");
    var detail = document.getElementById("pickup_doc_preview_detail_link");
    if (!title || !meta || !body || !detail) {
        return;
    }
    pickupDocPreviewData = data;
    title.textContent = data.pre_id;
    var metaParts = [
        data.pre_date || "",
        data.pre_type || "",
        data.pre_by ? ("ผู้เบิก: " + data.pre_by) : ""
    ].filter(function(part) { return part !== ""; });
    meta.textContent = metaParts.join(" · ");
    detail.href = data.detail_url || "#";

    var remarksBlock = "";
    if (data.remarks) {
        remarksBlock = '<p class="pickup-doc-preview-remarks">' + pickupDocPreviewEscape(data.remarks) + "</p>";
    }

    var linesHtml = "";
    for (var i = 0; i < data.lines.length; i++) {
        var line = data.lines[i];
        var lineRemark = line.remarks
            ? ('<span class="pickup-doc-preview-line__remark">' + pickupDocPreviewEscape(line.remarks) + "</span>")
            : "";
        linesHtml +=
            '<li class="pickup-doc-preview-line">' +
            '<label class="pickup-doc-preview-line__check" title="เลือกจ่ายรายการนี้">' +
            '<input type="checkbox" class="pickup-doc-preview-chk" data-line-index="' + i + '" value="YES" checked aria-label="เลือกรายการที่ ' + (i + 1) + '">' +
            "</label>" +
            '<div class="pickup-doc-preview-line__main">' +
            '<span class="pickup-doc-preview-line__name">' + pickupDocPreviewEscape(line.part_name) + "</span>" +
            (line.product ? ('<span class="pickup-doc-preview-line__product">' + pickupDocPreviewEscape(line.product) + "</span>") : "") +
            lineRemark +
            "</div>" +
            '<span class="pickup-doc-preview-line__qty">' + pickupDocPreviewEscape(line.amount) + "</span>" +
            "</li>";
    }

    body.innerHTML =
        remarksBlock +
        '<p class="pickup-doc-preview-count">' + data.line_count + " รายการ · เลือกรายการที่จะจ่ายแล้วกดยืนยัน</p>" +
        '<ul class="pickup-doc-preview-lines">' + linesHtml + "</ul>";
}

/**
 * ยืนยันจ่ายของจาก popup — ใช้ flow เดียวกับ pickup_list_order_detail (รหัสผ่าน + pickup_save_log)
 */
function confirmPickupDocPreview() {
    var data = pickupDocPreviewData;
    if (!data || !data.lines || !data.lines.length) {
        alert("ไม่พบรายการสำหรับยืนยัน");
        return;
    }

    var password = prompt("กรุณาใส่รหัสผ่าน", "");
    if (password === null) {
        return;
    }

    $.ajax({
        url: "check_password.php",
        type: "post",
        data: {
            password_check: 1,
            password: password
        },
        success: function(response) {
            if (response === "") {
                alert("รหัสผ่านไม่ถูกต้อง กรุณาใส่ใหม่อีกครั้ง");
                return;
            }

            var logBy = typeof inventoryActorName === 'string' ? inventoryActorName : '';
            if (logBy === '') {
                alert("ไม่พบชื่อผู้บันทึกจาก session — กรุณาเข้าสู่ระบบ SSO ใหม่");
                return;
            }
            var count = data.lines.length;
            var countCheck = 0;
            var dataRow =
                "count=" + encodeURIComponent(count) +
                "&ts_mtn_number=" + encodeURIComponent(data.ts_mtn_number || "") +
                "&log_by=" + encodeURIComponent(logBy) +
                "&pre_id=" + encodeURIComponent(data.pre_id) +
                "&pre_type=" + encodeURIComponent(data.pre_type || "");

            for (var i = 0; i < count; i++) {
                var line = data.lines[i];
                var chkEl = document.querySelector(
                    '.pickup-doc-preview-chk[data-line-index="' + i + '"]'
                );
                var chk = chkEl && chkEl.checked ? "YES" : "NO";
                if (chk === "YES") {
                    countCheck++;
                }
                var idx = i + 1;
                dataRow +=
                    "&log_part" + idx + "=" + encodeURIComponent(line.part_id || "") +
                    "&log_product" + idx + "=" + encodeURIComponent(line.product || "") +
                    "&log_amount" + idx + "=" + encodeURIComponent(line.amount || "") +
                    "&log_remarks" + idx + "=" + encodeURIComponent(line.remarks || "") +
                    "&check" + idx + "=" + encodeURIComponent(chk);
            }

            if (countCheck === 0) {
                alert("กรุณาเลือกรายการที่ต้องการจ่ายอย่างน้อย 1 รายการ");
                return;
            }

            window.location = "pickup_save_log.php?" + dataRow;
        },
        error: function() {
            alert("ตรวจรหัสผ่านไม่สำเร็จ กรุณาลองใหม่");
        }
    });
}

function openPickupDocPreview(preId) {
    if (!preId) {
        return;
    }
    var modal = document.getElementById("pickup_doc_preview_modal");
    var loading = document.getElementById("pickup_doc_preview_loading");
    var content = document.getElementById("pickup_doc_preview_content");
    if (!modal || !loading || !content) {
        return;
    }
    modal.style.display = "flex";
    modal.setAttribute("aria-hidden", "false");
    loading.style.display = "block";
    content.style.display = "none";

    $.getJSON("pickup_document_lines.php", { pre_id: preId })
        .done(function(data) {
            if (!data || !data.ok) {
                alert((data && data.message) ? data.message : "โหลดรายการไม่สำเร็จ");
                closePickupDocPreview();
                return;
            }
            renderPickupDocPreview(data);
            loading.style.display = "none";
            content.style.display = "flex";
        })
        .fail(function() {
            alert("โหลดรายการอะไหล่ไม่สำเร็จ");
            closePickupDocPreview();
        });
}

$(document).on("click", ".pickup-list-doc-row", function(event) {
    if ($(event.target).closest(".pickup-list-doc-row__actions, .delete-pickup-form, .delete-button").length) {
        return;
    }
    openPickupDocPreview($(this).data("pre-id"));
});

$(document).on("keydown", ".pickup-list-doc-row", function(event) {
    if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        openPickupDocPreview($(this).data("pre-id"));
    }
});

</script>
</head>
<body class="inv-app pickup-overview" onload="listDateNow()">
<?php require __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <header class="inv-page-header">
    <h1 class="inv-page-title">รายการเบิกของ</h1>
    <p class="inv-page-desc">แสดงเฉพาะเอกสารที่ยังรอคลังจ่าย — รายการที่เคลียร์จ่ายแล้วจะไม่ปรากฏที่นี่</p>
  </header>

  <form id="form_production" name="form_production" method="post" action="save_stock">
    <section class="inv-card">
      <div class="inv-card__head pickup-overview__list-head">
        <span class="pickup-overview__list-head-title"><i class="fa fa-table"></i> รายการเอกสารรอจ่าย</span>
        <div class="pickup-overview__list-head-actions">
          <a class="inv-btn inv-btn--ghost pickup-overview__history-link" href="pickup_history.php"><i class="fa fa-list-alt" aria-hidden="true"></i> ดูประวัติการเบิกของ</a>
          <button type="button" class="inv-btn inv-btn--ghost pickup-overview__sync-btn" id="pickup_overview_sync_btn" title="โหลดสถานะเบิกของล่าสุดจากมือถือ" aria-label="ซิงก์สถานะมือถือ">
            <i class="fa fa-refresh" aria-hidden="true"></i>
          </button>
        </div>
      </div>
      <div class="inv-card__body inv-card__body--flush">
        <?php if ($pickupOverviewFlash['message'] !== '') { ?>
        <div class="pickup-list-message <?php echo htmlspecialchars($pickupOverviewFlash['type'], ENT_QUOTES, 'UTF-8'); ?>" id="pickup_overview_flash">
          <?php echo htmlspecialchars($pickupOverviewFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php } ?>
        <div id="result" name="result" class="pickup-result">result</div>
      </div>
    </section>
  </form>

  <?php require __DIR__ . '/pickup_lift_call_fragment.php'; ?>
</main>

<div id="pickup_doc_preview_modal" class="pickup-modal pickup-doc-preview-modal" aria-hidden="true" role="dialog" aria-labelledby="pickup_doc_preview_title">
  <div class="pickup-modal__backdrop" onclick="closePickupDocPreview();"></div>
  <div class="pickup-modal__panel pickup-doc-preview-panel">
    <button type="button" class="inv-btn inv-btn--ghost pickup-doc-preview-panel__close" onclick="closePickupDocPreview(); return false;">ปิด</button>
    <div id="pickup_doc_preview_loading" class="pickup-doc-preview-loading">กำลังโหลดรายการ…</div>
    <div id="pickup_doc_preview_content" class="pickup-doc-preview-content" style="display:none;">
      <header class="pickup-doc-preview-head">
        <p class="pickup-doc-preview-kicker">รายการอะไหล่</p>
        <h2 id="pickup_doc_preview_title" class="pickup-doc-preview-title">—</h2>
        <p id="pickup_doc_preview_meta" class="pickup-doc-preview-meta"></p>
      </header>
      <div id="pickup_doc_preview_body" class="pickup-doc-preview-body"></div>
      <footer class="pickup-doc-preview-foot">
        <a id="pickup_doc_preview_detail_link" class="inv-btn inv-btn--ghost" href="#">เปิดหน้าเอกสาร</a>
        <button type="button" class="inv-btn inv-btn--primary pickup-doc-preview-confirm" id="pickup_doc_preview_confirm_btn" onclick="confirmPickupDocPreview();">ยืนยันจ่ายของ</button>
      </footer>
    </div>
  </div>
</div>

</body>
</html>
