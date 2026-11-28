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

/**
 * ชื่อแถวคลังช่างใน popup — ไม่แสดงรหัส Pxxxxx (รองรับข้อมูลเก่าใน DB)
 *
 * @param {{part_name?: string, tech_product_id?: number}} line
 * @returns {string}
 */
function pickupDocPreviewTechPartName(line) {
    var raw = line && line.part_name ? String(line.part_name).trim() : "";
    if (raw === "") {
        return "";
    }
    var stripped = raw
        .replace(/^P\d{3,}\s*[—\-–:]+\s*/iu, "")
        .replace(/^P\d{3,}\s+/iu, "");
    if (/^P\d{3,}$/iu.test(stripped)) {
        return "";
    }
    return stripped !== "" ? stripped : raw;
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
    pickupDocPreviewAllocate(data);
    pickupDocPreviewBindInputs();
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
    var invCount = typeof data.line_count_inventory === "number" ? data.line_count_inventory : 0;
    var techCount = typeof data.line_count_tech === "number" ? data.line_count_tech : 0;
    for (var i = 0; i < data.lines.length; i++) {
        var line = data.lines[i];
        var isTech = line.line_kind === "tech";
        var lineRemark = "";
        if (line.remarks && !isTech) {
            lineRemark = '<span class="pickup-doc-preview-line__remark">' + pickupDocPreviewEscape(line.remarks) + "</span>";
        } else if (line.remarks && isTech) {
            lineRemark = '<span class="pickup-doc-preview-line__remark">S/N: ' + pickupDocPreviewEscape(line.remarks) + "</span>";
        }
        if (isTech) {
            var techPartLabel = pickupDocPreviewTechPartName(line);
            linesHtml +=
                '<li class="pickup-doc-preview-line pickup-doc-preview-line--tech">' +
                '<span class="pickup-doc-preview-line__tech-badge" title="ตัดสต็อกคลังช่างแล้ว">คลังช่าง</span>' +
                '<div class="pickup-doc-preview-line__main">' +
                '<span class="pickup-doc-preview-line__name">' + pickupDocPreviewEscape(techPartLabel) + "</span>" +
                (line.product ? ('<span class="pickup-doc-preview-line__product">' + pickupDocPreviewEscape(line.product) + "</span>") : "") +
                lineRemark +
                "</div>" +
                '<span class="pickup-doc-preview-line__qty pickup-doc-preview-line__qty--tech">' + pickupDocPreviewEscape(line.amount) + "</span>" +
                "</li>";
            continue;
        }
        var req = pickupDocNum(line.amount);
        var stock = pickupDocNum(line.stock);
        var pay = line.pay;
        var stockState = stock <= 0 ? "is-out" : (stock < req ? "is-short" : "is-ok");
        var stockText = stock <= 0 ? "หมดคลัง" : (stock < req ? "ไม่พอ" : "พอ");
        var paidNote = pickupDocNum(line.paid_amount) > 0
            ? '<span class="pickup-doc-preview-line__paid">ค้างจ่าย · จ่ายไปแล้ว ' + pickupDocFmt(line.paid_amount) + "</span>"
            : "";
        linesHtml +=
            '<li class="pickup-doc-preview-line' + (stockState === "is-ok" ? "" : " " + stockState) + '">' +
            '<label class="pickup-doc-preview-line__check" title="เลือกจ่ายรายการนี้">' +
            '<input type="checkbox" class="pickup-doc-preview-chk" data-line-index="' + i + '" value="YES"' + (pay > 0 ? " checked" : "") + ' aria-label="เลือกรายการที่ ' + (i + 1) + '">' +
            "</label>" +
            '<div class="pickup-doc-preview-line__main">' +
            '<span class="pickup-doc-preview-line__name">' + pickupDocPreviewEscape(line.part_name) + "</span>" +
            (line.product ? ('<span class="pickup-doc-preview-line__product">' + pickupDocPreviewEscape(line.product) + "</span>") : "") +
            '<span class="pickup-doc-preview-line__stock ' + stockState + '">' + stockText + " · มี " + pickupDocFmt(stock) + " / ขอ " + pickupDocFmt(req) + "</span>" +
            paidNote +
            lineRemark +
            "</div>" +
            '<div class="pickup-doc-preview-line__pay">' +
            '<label for="pickup_doc_pay_' + i + '">จ่ายครั้งนี้</label>' +
            '<input type="number" id="pickup_doc_pay_' + i + '" class="pickup-doc-preview-qty" data-line-index="' + i + '" min="0" step="any" inputmode="decimal" value="' + pickupDocFmt(pay) + '">' +
            '<span class="pickup-doc-preview-line__rem" data-rem-index="' + i + '"></span>' +
            "</div>" +
            "</li>";
    }

    var countText = data.line_count + " รายการ";
    if (techCount > 0) {
        countText += " (คลังกลาง " + (invCount || (data.line_count - techCount)) + " · คลังช่าง " + techCount + " · ตัดแล้ว)";
    }
    countText += " · ใส่จำนวนที่จ่ายครั้งนี้ ส่วนที่ไม่ได้จ่ายจะค้างไว้ในเอกสาร";

    body.innerHTML =
        remarksBlock +
        '<p class="pickup-doc-preview-count">' + countText + "</p>" +
        '<ul class="pickup-doc-preview-lines">' + linesHtml + "</ul>" +
        '<div class="pickup-doc-preview-summary" id="pickup_doc_preview_summary"></div>';
    pickupDocPreviewUpdateSummary();
}

function pickupDocNum(value) {
    var n = parseFloat(String(value === undefined || value === null ? "" : value).replace(/,/g, ""));
    return isNaN(n) ? 0 : n;
}

function pickupDocFmt(value) {
    var n = pickupDocNum(value);
    return Math.abs(n - Math.round(n)) < 0.005 ? String(Math.round(n)) : n.toFixed(2);
}

/** สต็อกของอะไหล่ใช้ร่วมกันทุกแถวที่เป็นอะไหล่ตัวเดียวกัน — ค่าเริ่มต้นแบ่งให้ตามลำดับแถว */
function pickupDocPreviewAllocate(data) {
    var left = {};
    for (var i = 0; i < data.lines.length; i++) {
        var line = data.lines[i];
        if (line.line_kind === "tech") {
            continue;
        }
        var key = String(line.part_id);
        if (!(key in left)) {
            left[key] = Math.max(0, pickupDocNum(line.stock));
        }
        line.pay = Math.min(pickupDocNum(line.amount), left[key]);
        left[key] -= line.pay;
    }
}

/** จ่ายได้สูงสุดของแถว = min(จำนวนที่ขอ, สต็อก − ที่แถวอื่นของอะไหล่ตัวเดียวกันจองไว้) */
function pickupDocPreviewMaxPay(index) {
    var data = pickupDocPreviewData;
    var line = data.lines[index];
    var used = 0;
    for (var i = 0; i < data.lines.length; i++) {
        var other = data.lines[i];
        if (i !== index && other.line_kind !== "tech" && String(other.part_id) === String(line.part_id)) {
            used += other.pay || 0;
        }
    }
    return Math.max(0, Math.min(pickupDocNum(line.amount), Math.max(0, pickupDocNum(line.stock)) - used));
}

function pickupDocPreviewUpdateSummary() {
    var data = pickupDocPreviewData;
    var box = document.getElementById("pickup_doc_preview_summary");
    if (!data || !box) {
        return;
    }
    var paid = 0;
    var pending = 0;
    for (var i = 0; i < data.lines.length; i++) {
        var line = data.lines[i];
        if (line.line_kind === "tech") {
            continue;
        }
        var chk = document.querySelector('.pickup-doc-preview-chk[data-line-index="' + i + '"]');
        var pay = chk && chk.checked ? (line.pay || 0) : 0;
        var rest = Math.max(0, pickupDocNum(line.amount) - pay);
        var rem = document.querySelector('[data-rem-index="' + i + '"]');
        if (rem) {
            rem.textContent = rest > 0.005 ? "ค้าง " + pickupDocFmt(rest) : "";
        }
        if (pay > 0) {
            paid++;
        }
        if (rest > 0.005) {
            pending++;
        }
    }
    box.innerHTML = '<span class="is-paid">จ่ายครั้งนี้ ' + paid + " รายการ</span>" +
        (pending > 0
            ? '<span class="is-pending">ค้างไว้ในเอกสาร ' + pending + " รายการ</span>"
            : '<span class="is-closed">จ่ายครบ · ปิดเอกสาร</span>');
}

function pickupDocPreviewBindInputs() {
    var body = document.getElementById("pickup_doc_preview_body");
    if (!body || body.getAttribute("data-bound") === "1") {
        return;
    }
    body.setAttribute("data-bound", "1");
    body.addEventListener("change", function(ev) {
        var t = ev.target;
        var data = pickupDocPreviewData;
        if (!data) {
            return;
        }
        var index = parseInt(t.getAttribute("data-line-index"), 10);
        if (isNaN(index)) {
            return;
        }
        var line = data.lines[index];
        var qty = document.getElementById("pickup_doc_pay_" + index);
        if (t.classList.contains("pickup-doc-preview-qty")) {
            var v = Math.max(0, Math.min(pickupDocNum(t.value), pickupDocPreviewMaxPay(index)));
            line.pay = v;
            t.value = pickupDocFmt(v);
            var chk = document.querySelector('.pickup-doc-preview-chk[data-line-index="' + index + '"]');
            if (chk) {
                chk.checked = v > 0;
            }
        } else if (t.classList.contains("pickup-doc-preview-chk")) {
            if (t.checked && (line.pay || 0) <= 0) {
                line.pay = pickupDocPreviewMaxPay(index);
                if (line.pay <= 0) {
                    t.checked = false;
                    alert("อะไหล่นี้ไม่มีในคลังให้จ่าย — รายการจะค้างไว้ในเอกสาร");
                }
            } else if (!t.checked) {
                line.pay = 0;
            }
            if (qty) {
                qty.value = pickupDocFmt(line.pay);
            }
        }
        pickupDocPreviewUpdateSummary();
    });
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
            var logIdx = 0;
            var countCheck = 0;
            var dataRow =
                "&ts_mtn_number=" + encodeURIComponent(data.ts_mtn_number || "") +
                "&log_by=" + encodeURIComponent(logBy) +
                "&pre_id=" + encodeURIComponent(data.pre_id) +
                "&pre_type=" + encodeURIComponent(data.pre_type || "");

            for (var i = 0; i < data.lines.length; i++) {
                var line = data.lines[i];
                if (line.line_kind === "tech") {
                    continue;
                }
                var chkEl = document.querySelector(
                    '.pickup-doc-preview-chk[data-line-index="' + i + '"]'
                );
                var pay = chkEl && chkEl.checked ? (line.pay || 0) : 0;
                var chk = pay > 0 ? "YES" : "NO";
                if (chk === "YES") {
                    countCheck++;
                }
                logIdx++;
                dataRow +=
                    "&pre_running" + logIdx + "=" + encodeURIComponent(line.pre_running || "") +
                    "&log_amount" + logIdx + "=" + encodeURIComponent(pickupDocFmt(pay)) +
                    "&check" + logIdx + "=" + encodeURIComponent(chk);
            }

            if (countCheck === 0) {
                alert("ยังไม่มีรายการที่จ่ายได้ — เลือกรายการและใส่จำนวนจ่ายอย่างน้อย 1 รายการ");
                return;
            }

            window.location = "pickup_save_log.php?count=" + encodeURIComponent(logIdx) + dataRow;
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
