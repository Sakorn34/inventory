<?php
/**
 * หน้ารายการเบิกของของระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือแสดงเอกสารเบิกที่ยังรอคลังจ่าย (pre_status = pre) ทั้งหมด
 * โหลดรายการผ่าน `pickup_list_order.php` โดย AJAX — ไม่กรองช่วงวันที่
 *
 * Flow สั้น: เปิด `pickup_overview.php` → JavaScript โหลดตารางรอจ่ายทันที
 */

// ─ Imports ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/connect.php';
header("Content-Type: text/html; charset=UTF-8");
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');
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
}

$(document).on("submit", ".delete-pickup-form", function(event) {
    event.preventDefault();

    var form = $(this);

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

function closePickupDocPreview() {
    var modal = document.getElementById("pickup_doc_preview_modal");
    if (!modal) {
        return;
    }
    modal.style.display = "none";
    modal.setAttribute("aria-hidden", "true");
}

function renderPickupDocPreview(data) {
    var title = document.getElementById("pickup_doc_preview_title");
    var meta = document.getElementById("pickup_doc_preview_meta");
    var body = document.getElementById("pickup_doc_preview_body");
    var detail = document.getElementById("pickup_doc_preview_detail_link");
    if (!title || !meta || !body || !detail) {
        return;
    }
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
        '<p class="pickup-doc-preview-count">' + data.line_count + " รายการ</p>" +
        '<ul class="pickup-doc-preview-lines">' + linesHtml + "</ul>";
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
            content.style.display = "block";
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
      <div class="inv-card__head"><i class="fa fa-table"></i> รายการเอกสารรอจ่าย</div>
      <div class="inv-card__body inv-card__body--flush">
        <div id="result" name="result" class="pickup-result">result</div>
      </div>
    </section>
  </form>
</main>

<div id="pickup_doc_preview_modal" class="pickup-modal pickup-doc-preview-modal" aria-hidden="true" role="dialog" aria-labelledby="pickup_doc_preview_title">
  <div class="pickup-modal__backdrop" onclick="closePickupDocPreview();"></div>
  <div class="pickup-modal__panel pickup-doc-preview-panel">
    <button type="button" class="pickup-add-line-panel__close" aria-label="ปิด" onclick="closePickupDocPreview(); return false;"><i class="fa fa-times"></i></button>
    <div id="pickup_doc_preview_loading" class="pickup-doc-preview-loading">กำลังโหลดรายการ…</div>
    <div id="pickup_doc_preview_content" style="display:none;">
      <header class="pickup-doc-preview-head">
        <p class="pickup-doc-preview-kicker">รายการอะไหล่</p>
        <h2 id="pickup_doc_preview_title" class="pickup-doc-preview-title">—</h2>
        <p id="pickup_doc_preview_meta" class="pickup-doc-preview-meta"></p>
      </header>
      <div id="pickup_doc_preview_body" class="pickup-doc-preview-body"></div>
      <footer class="pickup-doc-preview-foot">
        <button type="button" class="inv-btn inv-btn--ghost" onclick="closePickupDocPreview();">ปิด</button>
        <a id="pickup_doc_preview_detail_link" class="inv-btn inv-btn--primary" href="#">เปิดหน้าเอกสาร</a>
      </footer>
    </div>
  </div>
</div>

</body>
</html>
