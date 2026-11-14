<?php
/**
 * หน้าเบิกของรวม (U1) — เบิกเป็นเซต + เบิกแยกรายการ
 *
 * สลับโหมดที่หัวการ์ดรายการเบิก โหมดเซตโหลดสูตรจาก pickup_set_formula.php แล้วบันทึก pre_pickup_save.php
 * โหมดแยกรายการบันทึกผ่าน pickup_ma_save.php — ธีม inv-app / layout H5
 */
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/pickup_repair_part_product.php';
require_once __DIR__ . '/inventory_session_actor.php';
header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * ตรวจว่า Product มีชุดซ่อมอย่างน้อย 1 Part (ตาราง repair_formula)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @param string $pdName ชื่อ Product จาก product.pd_name
 * @return bool true เมื่อมี Part ในชุดซ่อม
 */
function pickup_ma_product_has_repair_set($conn, $productId, $pdName)
{
    $productId = (int) $productId;
    $pdName = trim((string) $pdName);
    if ($productId <= 0 || $pdName === '') {
        return false;
    }

    return pickup_repair_part_scope_count($conn, $productId, $pdName) > 0;
}

/**
 * ตรวจว่า Product มีชุดผลิตอย่างน้อย 1 Part (production_formula)
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId pd_id
 * @return bool true เมื่อมี Part ในชุดผลิต
 */
function pickup_ma_product_has_production_set($conn, $productId)
{
    $productId = (int) $productId;
    if ($productId <= 0) {
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT 1 FROM `production_formula` WHERE `pd_id` = ? LIMIT 1'
    );
    if ($stmt === false) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return false;
    }
    mysqli_stmt_store_result($stmt);
    $hasRow = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $hasRow;
}

/**
 * แยกหมายเหตุเบิกซ่อมลูกค้าที่มาจาก maintenance เป็น ชื่อบริษัท + หมายเลขสินค้า
 *
 * @param string $detail ข้อความจาก GET detail (เช่น Shinpack | SN:BP21121070)
 * @return array{company: string, sn: string}
 */
function pickup_ma_parse_repair_detail($detail)
{
    $detail = trim((string) $detail);
    $company = $detail;
    $sn = '';
    if ($detail !== '' && preg_match('/\|\s*SN:(.+)$/iu', $detail, $matches)) {
        $company = trim((string) preg_replace('/\s*\|\s*SN:.+$/iu', '', $detail));
        $sn = trim((string) $matches[1]);
    }

    return array(
        'company' => $company,
        'sn' => $sn,
    );
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta http-equiv="Content-Language" content="th">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
	<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>

	<title>เบิกของ</title>
	<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>
	<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
	<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
	<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

	<script type="text/javascript">
		function pickupGetWorkMode() {
			var el = document.getElementById("pickup_work_mode");
			return el ? String(el.value) : "";
		}

		function pickupMaBuildRepairRemarks() {
			var companyEl = document.getElementById("pickup_ma_customer_com");
			var snEl = document.getElementById("pickup_ma_product_sn");
			var company = companyEl ? String(companyEl.value).trim() : "";
			var sn = snEl ? String(snEl.value).trim() : "";
			var marker = sn !== "" ? "SN:" + sn : "";
			if (company !== "" && marker !== "") {
				return company + " | " + marker;
			}
			if (company !== "") {
				return company;
			}
			if (marker !== "") {
				return marker;
			}

			return "";
		}

		function pickupMaSyncLogRemarks() {
			var remarksEl = document.getElementById("log_remarks");
			if (!remarksEl) {
				return;
			}
			if (pickupGetPurposeType() === "เบิกซ่อมลูกค้า") {
				remarksEl.value = pickupMaBuildRepairRemarks();
			}
		}

		function pickupMaApplyRepairDetailFromText(detailText) {
			var companyEl = document.getElementById("pickup_ma_customer_com");
			var snEl = document.getElementById("pickup_ma_product_sn");
			if (!companyEl || !snEl) {
				return;
			}
			var detail = String(detailText || "").trim();
			var company = detail;
			var sn = "";
			var snMatch = detail.match(/\|\s*SN:(.+)$/i);
			if (snMatch) {
				company = detail.replace(/\s*\|\s*SN:.+$/i, "").trim();
				sn = String(snMatch[1] || "").trim();
			}
			companyEl.value = company;
			snEl.value = sn;
			pickupMaSyncLogRemarks();
		}

		function pickupGetPurposeType() {
			var ids = ["myCheck1", "myCheck2", "myCheck6", "myCheck3", "myCheck4", "myCheck5", "myCheck7"];
			for (var i = 0; i < ids.length; i++) {
				var node = document.getElementById(ids[i]);
				if (node && node.checked) {
					return node.value;
				}
			}
			return "";
		}

		/**
		 * ตรวจว่าผู้ใช้เลือกวัตถุประสงค์แล้วหรือยัง
		 *
		 * @returns {boolean} true เมื่อเลือกวัตถุประสงค์แล้ว
		 */
		function pickupHasPurposeSelected() {
			return pickupGetPurposeType() !== "";
		}

		/**
		 * บังคับเลือกวัตถุประสงค์ก่อนทำรายการเบิก
		 *
		 * @returns {boolean} true เมื่อเลือกวัตถุประสงค์แล้ว
		 */
		function pickupRequirePurpose() {
			if (pickupHasPurposeSelected()) {
				return true;
			}
			alert("กรุณาเลือกวัตถุประสงค์การเบิกด้านบนก่อน");
			var purposeGroup = document.getElementById("checkForm");
			if (purposeGroup) {
				purposeGroup.scrollIntoView({ behavior: "smooth", block: "nearest" });
			}
			return false;
		}

		/**
		 * ล็อก/ปลดล็อกส่วนรายการเบิกตามว่าเลือกวัตถุประสงค์แล้วหรือยัง
		 */
		function pickupSyncLinesPurposeGate() {
			var card = document.querySelector(".pickup-unified-lines-card");
			var overlay = document.getElementById("pickup_lines_gate_overlay");
			if (!card) {
				return;
			}
			var locked = !pickupHasPurposeSelected();
			card.classList.toggle("pickup-unified-lines-card--locked", locked);
			if (overlay) {
				overlay.hidden = !locked;
				overlay.setAttribute("aria-hidden", locked ? "false" : "true");
			}
			var modeSwitch = document.getElementById("pickup_mode_switch");
			if (modeSwitch) {
				var modeButtons = modeSwitch.getElementsByTagName("button");
				for (var b = 0; b < modeButtons.length; b++) {
					modeButtons[b].disabled = locked;
				}
			}
		}

		function pickupIsRepairPurpose() {
			return pickupGetPurposeType() === "เบิกซ่อมลูกค้า";
		}

		function pickupGetSetFormulaMode() {
			return pickupIsRepairPurpose() ? "repair" : "production";
		}

		/**
		 * สลับป้ายโหมดเซต (เบิกเป็นเซต / เบิกเซตซ่อม) ตามวัตถุประสงค์
		 */
		function pickupUpdateSetModeUi() {
			pickupSyncLinesPurposeGate();
			if (!pickupHasPurposeSelected()) {
				return;
			}
			var setBtn = document.querySelector("#pickup_mode_switch button[data-mode=\"set\"]");
			if (setBtn) {
				if (pickupIsRepairPurpose()) {
					setBtn.innerHTML = '<i class="fa fa-wrench"></i> เบิกเซตซ่อม';
					setBtn.setAttribute("title", "โหลดรายการจาก part_product — ชุดซ่อม");
				} else {
					setBtn.innerHTML = '<i class="fa fa-cubes"></i> เบิกเป็นเซต';
					setBtn.setAttribute("title", "โหลดรายการจากสูตรผลิต");
				}
			}
			var hint = document.getElementById("pickup_set_mode_hint");
			if (hint) {
				hint.textContent = pickupIsRepairPurpose()
					? "ชุดซ่อม: รายการ Part ที่ผูก Product ใน repair_formula"
					: "สูตรผลิต: รายการจาก production_formula";
			}
			pickupFilterPreProductOptions();
			pickupSyncSetModalCopy();
			if (pickupGetWorkMode() === "set") {
				pickupLoadSetFormula();
			}
		}

		/**
		 * ซ่อน option Product ที่ยังไม่มีชุดผูกอะไหล่ (ชุดซ่อมหรือสูตรผลิต ตามวัตถุประสงค์)
		 *
		 * @param {HTMLSelectElement} productSelect dropdown Product (เซตหรือ modal เพิ่มรายการ)
		 * @param {boolean} repairOnly true = ใช้ data-repair-set
		 * @returns {boolean} true ถ้าเลือกค่าที่ถูกซ่อนแล้วถูกล้าง
		 */
		function pickupFilterOneProductSelect(productSelect, repairOnly) {
			var selectedHidden = false;
			for (var i = 0; i < productSelect.options.length; i++) {
				var opt = productSelect.options[i];
				if (opt.value === "") {
					opt.hidden = false;
					opt.disabled = false;
					continue;
				}
				var hasRepair = opt.getAttribute("data-repair-set") === "1";
				var hasProduction = opt.getAttribute("data-production-set") === "1";
				var show = repairOnly ? hasRepair : hasProduction;
				opt.hidden = !show;
				opt.disabled = !show;
				if (!show && productSelect.value === opt.value) {
					selectedHidden = true;
				}
			}
			if (selectedHidden) {
				productSelect.value = "";
			}
			return selectedHidden;
		}

		/**
		 * จำ catalog Product สำหรับ popup โหลดชุด (ครั้งเดียว) แล้วสร้าง option ใหม่ตามโหมดซ่อม/ผลิต
		 */
		function pickupApplySetModalProductFilter() {
			var sel = document.getElementById("pickup_set_modal_product");
			if (!sel) {
				return;
			}
			if (!sel.dataset.pickupCatalog) {
				var catalog = [];
				for (var i = 1; i < sel.options.length; i++) {
					var opt = sel.options[i];
					catalog.push({
						value: opt.value,
						text: opt.text,
						repair: opt.getAttribute("data-repair-set") === "1",
						production: opt.getAttribute("data-production-set") === "1"
					});
				}
				sel.dataset.pickupCatalog = JSON.stringify(catalog);
			}
			var items = JSON.parse(sel.dataset.pickupCatalog);
			var repairOnly = pickupIsRepairPurpose();
			var previous = sel.value;
			while (sel.options.length > 1) {
				sel.remove(1);
			}
			for (var c = 0; c < items.length; c++) {
				var item = items[c];
				var show = repairOnly ? item.repair : item.production;
				if (!show) {
					continue;
				}
				var next = document.createElement("option");
				next.value = item.value;
				next.textContent = item.text;
				next.setAttribute("data-repair-set", item.repair ? "1" : "0");
				next.setAttribute("data-production-set", item.production ? "1" : "0");
				sel.appendChild(next);
			}
			var stillValid = false;
			for (var j = 0; j < sel.options.length; j++) {
				if (sel.options[j].value === previous) {
					stillValid = true;
					break;
				}
			}
			sel.value = stillValid ? previous : "";
		}

		/**
		 * กรอง dropdown Product — แถบเซตตามวัตถุประสงค์, popup ชุดใช้รายการซ่อม/ผลิตแยก, modal แยกรายการใช้ production เสมอ
		 */
		function pickupFilterPreProductOptions() {
			var repairOnlySet = pickupIsRepairPurpose();
			var needReload = false;
			var setSelects = document.querySelectorAll("#pickup_set_bars .pickup-set-row-product");
			for (var s = 0; s < setSelects.length; s++) {
				if (pickupFilterOneProductSelect(setSelects[s], repairOnlySet)) {
					needReload = true;
				}
			}
			pickupApplySetModalProductFilter();
			var modalPd = document.getElementById("pickup_ma_modal_pd");
			var modalCleared = false;
			if (modalPd) {
				modalCleared = pickupFilterOneProductSelect(modalPd, false);
			}
			if (modalCleared && typeof loadPickupMaModalParts === "function") {
				loadPickupMaModalParts();
			}
			if (needReload) {
				pickupSetBarSyncState();
				if (pickupGetWorkMode() === "set") {
					pickupLoadSetFormula();
				}
			}
		}

		function pickupCollectSetRows() {
			var rows = [];
			var rowEls = document.querySelectorAll("#pickup_set_bars .pickup-set-bar-row");
			for (var i = 0; i < rowEls.length; i++) {
				var prod = rowEls[i].querySelector(".pickup-set-row-product");
				var amt = rowEls[i].querySelector(".pickup-set-row-amount");
				if (!prod || !amt || prod.value === "" || amt.value === "") {
					continue;
				}
				rows.push({
					product: prod.value,
					amount: amt.value
				});
			}
			return rows;
		}

		var pickupSetFormulaLines = {};
		var pickupSetManualLines = {};
		var pickupSetOmittedPartIds = {};

		/**
		 * โหมดเซต — เปิดฟอร์มเพิ่ม Part แยกรายการให้รวมในตารางเซต
		 */
		function pickupGoSeparateLinePickup() {
			if (!pickupRequirePurpose()) {
				return;
			}
			openPickupMaAddModal();
		}

		function pickupSetEscHtml(value) {
			return String(value)
				.replace(/&/g, "&amp;")
				.replace(/</g, "&lt;")
				.replace(/"/g, "&quot;");
		}

		function pickupSetParseHtmlChunk(html) {
			var map = {};
			var wrap = document.createElement("tbody");
			wrap.innerHTML = html;
			var trs = wrap.querySelectorAll("tr");
			for (var t = 0; t < trs.length; t++) {
				var tr = trs[t];
				if (tr.classList.contains("pickup-set-empty")) {
					continue;
				}
				var partId = tr.getAttribute("data-part-id");
				if (!partId) {
					continue;
				}
				var cells = tr.getElementsByTagName("td");
				if (cells.length < 5) {
					continue;
				}
				var pickQty = parseFloat(tr.getAttribute("data-pick-qty"));
				if (isNaN(pickQty)) {
					pickQty = parseFloat(cells[4].textContent);
				}
				if (!map[partId]) {
					map[partId] = {
						name: cells[1].textContent,
						min: cells[2].textContent,
						stock: cells[3].textContent,
						pick: pickQty
					};
				} else {
					map[partId].pick += pickQty;
				}
			}
			return map;
		}

		function pickupSetMergeTwoMaps(mapA, mapB) {
			var out = {};
			function ingest(map) {
				for (var id in map) {
					if (!Object.prototype.hasOwnProperty.call(map, id)) {
						continue;
					}
					if (!out[id]) {
						out[id] = {
							name: map[id].name,
							min: map[id].min,
							stock: map[id].stock,
							pick: map[id].pick,
							productName: map[id].productName || ""
						};
					} else {
						out[id].pick += map[id].pick;
					}
				}
			}
			ingest(mapA);
			ingest(mapB);
			return out;
		}

		function pickupSetMapToHtml(map) {
			var keys = Object.keys(map);
			if (!keys.length) {
				return "";
			}
			keys.sort(function(a, b) {
				return map[a].name.localeCompare(map[b].name, "th");
			});
			var html = "";
			for (var n = 0; n < keys.length; n++) {
				var partId = keys[n];
				var row = map[partId];
				var stockOk = pickupSetCheckStock(row.pick, row.stock);
				var statusClass = stockOk ? "stock-ok" : "stock-bad";
				var statusText = stockOk ? "OK" : "Stock ไม่พอ";
				html += "<tr data-part-id=\"" + pickupSetEscHtml(partId) + "\" data-pick-qty=\"" + pickupSetEscHtml(row.pick) + "\">";
				html += "<td class=\"inv-col-num\">" + (n + 1) + "</td>";
				html += "<td>" + pickupSetEscHtml(row.name) + "</td>";
				html += "<td class=\"inv-col-num\">" + pickupSetEscHtml(row.min) + "</td>";
				html += "<td class=\"inv-col-num\">" + pickupSetEscHtml(row.stock) + "</td>";
				html += "<td class=\"inv-col-num\">" + pickupSetEscHtml(row.pick) + "</td>";
				html += "<td class=\"" + statusClass + "\">" + pickupSetEscHtml(statusText) + "</td>";
				html += "<td class=\"inv-col-action pickup-set-line-action\">";
				html += "<button type=\"button\" class=\"inv-btn inv-btn--ghost pickup-set-line-remove\" title=\"ลบรายการนี้ออกจากใบเบิก\" onclick=\"pickupSetRemoveLine('" + pickupSetEscHtml(partId) + "'); return false;\"><i class=\"fa fa-trash\"></i></button>";
				html += "</td>";
				html += "</tr>";
			}
			return html;
		}

		/**
		 * ลบ Part ออกจากตารางเซต (ไม่บันทึกรายการนี้เมื่อกด Save)
		 *
		 * @param {string} partId รหัส part
		 */
		function pickupSetRemoveLine(partId) {
			var id = String(partId);
			if (id === "") {
				return;
			}
			delete pickupSetFormulaLines[id];
			delete pickupSetManualLines[id];
			pickupSetOmittedPartIds[id] = true;
			pickupSetRenderCombined();
		}

		function pickupSetRenderCombined() {
			var merged = pickupSetMergeTwoMaps(pickupSetFormulaLines, pickupSetManualLines);
			for (var omitId in pickupSetOmittedPartIds) {
				if (Object.prototype.hasOwnProperty.call(pickupSetOmittedPartIds, omitId)) {
					delete merged[omitId];
				}
			}
			var html = pickupSetMapToHtml(merged);
			var tbody = document.getElementById("pickup_set_tbody");
			if (!tbody) {
				return;
			}
			if (html === "") {
				tbody.innerHTML = '<tr><td colspan="7" class="pickup-set-empty">เลือก Product และจำนวนชุดเพื่อโหลดสูตร หรือกด「เพิ่มเบิกแยกรายการ」</td></tr>';
				document.getElementById("pickup_set_has_rows").value = "0";
				pickupSyncUnifiedClearButton();
				return;
			}
			tbody.innerHTML = html;
			document.getElementById("pickup_set_has_rows").value = "1";
			pickupSyncUnifiedClearButton();
		}

		function pickupSetAddManualLine(partId, partName, minText, stockText, pickQty, productName) {
			var id = String(partId);
			var addPick = parseFloat(pickQty);
			if (isNaN(addPick) || addPick <= 0) {
				return;
			}
			if (!pickupSetManualLines[id]) {
				pickupSetManualLines[id] = {
					name: partName,
					min: minText,
					stock: stockText,
					pick: addPick,
					productName: productName || ""
				};
			} else {
				pickupSetManualLines[id].pick += addPick;
			}
			pickupSetRenderCombined();
		}

		function pickupSetCheckStock(pickQty, stockQty) {
			var pick = parseFloat(pickQty);
			var stock = parseFloat(stockQty);
			if (isNaN(pick)) {
				pick = 0;
			}
			if (isNaN(stock)) {
				stock = 0;
			}
			if (stockQty !== "" && stockQty !== null && pick <= stock) {
				return true;
			}
			return false;
		}

		function pickupSetMergeFormulaHtml(htmlList) {
			var map = {};
			for (var h = 0; h < htmlList.length; h++) {
				map = pickupSetMergeTwoMaps(map, pickupSetParseHtmlChunk(htmlList[h]));
			}
			return pickupSetMapToHtml(map);
		}

		function pickupUnifiedHasLineData() {
			if (pickupGetWorkMode() === "set") {
				return document.getElementById("pickup_set_has_rows").value === "1";
			}
			return parseInt(document.getElementById("count_sp").value || "0", 10) > 0;
		}

		/**
		 * ตรวจว่าตาราง part มีข้อมูล (เซตหรือแยกรายการ)
		 *
		 * @returns {boolean}
		 */
		function pickupUnifiedHasAnyLineData() {
			if (document.getElementById("pickup_set_has_rows").value === "1") {
				return true;
			}
			return parseInt(document.getElementById("count_sp").value || "0", 10) > 0;
		}

		/**
		 * แสดงปุ่มล้างค่าเฉพาะเมื่อตารางมีรายการ part
		 */
		function pickupSyncUnifiedClearButton() {
			var btn = document.querySelector(".pickup-unified-lines-clear");
			if (!btn) {
				return;
			}
			var show = pickupUnifiedHasAnyLineData();
			btn.hidden = !show;
			btn.style.display = show ? "" : "none";
		}

		function pickupUnifiedClearMaLines() {
			while (parseInt(document.getElementById("count_sp").value || "0", 10) > 0) {
				addSpare("del");
			}
		}

		function pickupUnifiedClearSetLines() {
			pickupSetFormulaLines = {};
			pickupSetManualLines = {};
			pickupSetOmittedPartIds = {};
			document.getElementById("pickup_set_tbody").innerHTML =
				'<tr><td colspan="7" class="pickup-set-empty">เลือกโหมดเซตแล้วโหลดชุดจาก popup หรือกด「เพิ่มเบิกแยกรายการ」</td></tr>';
			document.getElementById("pickup_set_has_rows").value = "0";
			var first = document.querySelector("#pickup_set_bars .pickup-set-bar-row");
			if (first) {
				first.querySelectorAll("select").forEach(function(sel) {
					sel.value = "";
				});
			}
			pickupSetBarSyncState();
			pickupSyncSetSelectionSummary();
			pickupSyncUnifiedClearButton();
		}

		/**
		 * แสดงชื่อ Product และจำนวนชุดที่เลือกไว้เหนือตารางเซต
		 */
		function pickupSyncSetSelectionSummary() {
			var box = document.getElementById("pickup_set_selection_summary");
			var title = document.getElementById("pickup_set_selection_title");
			var qtyEl = document.getElementById("pickup_set_selection_qty");
			if (!box || !title) {
				return;
			}
			var prod = document.querySelector("#pickup_set_bars .pickup-set-row-product");
			var amt = document.querySelector("#pickup_set_bars .pickup-set-row-amount");
			if (!prod || prod.value === "") {
				box.hidden = true;
				box.classList.add("is-empty");
				title.textContent = "—";
				if (qtyEl) {
					qtyEl.textContent = "";
				}
				return;
			}
			var name = "";
			if (prod.selectedIndex >= 0 && prod.options[prod.selectedIndex]) {
				name = prod.options[prod.selectedIndex].text;
			}
			if (name === "") {
				name = prod.value;
			}
			title.textContent = name;
			if (qtyEl) {
				var qty = amt && amt.value ? String(amt.value) : "";
				qtyEl.textContent = qty !== "" ? "· จำนวนชุด " + qty : "";
			}
			box.hidden = false;
			box.classList.remove("is-empty");
		}

		function pickupSetPickerActivate(ev, wrapEl) {
			if (!pickupRequirePurpose()) {
				return;
			}
			var sel = wrapEl && wrapEl.querySelector
				? wrapEl.querySelector(".pickup-set-picker__select")
				: document.getElementById(wrapEl);
			if (!sel) {
				return;
			}
			if (ev && ev.target === sel) {
				return;
			}
			sel.focus();
			if (typeof sel.showPicker === "function") {
				try {
					sel.showPicker();
				} catch (pickerErr) {
					sel.click();
				}
			} else {
				sel.click();
			}
		}

		function pickupSetBarSyncState() {
			var selects = document.querySelectorAll("#pickup_set_bars .pickup-set-picker__select");
			for (var i = 0; i < selects.length; i++) {
				var sel = selects[i];
				var wrap = sel.closest(".pickup-set-picker__btn-wrap");
				if (!wrap) {
					continue;
				}
				var filled = sel.value !== "";
				wrap.classList.toggle("is-filled", filled);
				wrap.classList.toggle("inv-btn--primary", filled);
				wrap.classList.toggle("inv-btn--ghost", !filled);
				if (document.activeElement !== sel) {
					sel.style.color = filled ? "#ffffff" : "";
				}
			}
		}

		function pickupSetPickerOnFocus(selectEl) {
			if (!selectEl) {
				return;
			}
			selectEl.style.color = "#0f172a";
		}

		function pickupSetPickerOnBlur(selectEl) {
			if (!selectEl) {
				return;
			}
			pickupSetBarSyncState();
		}

		function pickupLoadSetFormula() {
			if (!pickupHasPurposeSelected()) {
				return;
			}
			if (pickupGetWorkMode() !== "set") {
				return;
			}
			var sets = pickupCollectSetRows();
			if (!sets.length) {
				pickupSetFormulaLines = {};
				pickupSetOmittedPartIds = {};
				pickupSetRenderCombined();
				return;
			}
			var setMode = pickupGetSetFormulaMode();
			var pending = sets.length;
			var htmlList = [];
			var loadFailed = false;
			for (var i = 0; i < sets.length; i++) {
				(function(setRow) {
					$.get(
						"pickup_set_formula.php",
						{
							pre_product: setRow.product,
							pre_amount: setRow.amount,
							set_mode: setMode
						},
						function(html) {
							if (loadFailed) {
								return;
							}
							htmlList.push(html);
							pending--;
							if (pending === 0) {
								pickupSetFormulaLines = {};
								pickupSetOmittedPartIds = {};
								for (var hi = 0; hi < htmlList.length; hi++) {
									pickupSetFormulaLines = pickupSetMergeTwoMaps(
										pickupSetFormulaLines,
										pickupSetParseHtmlChunk(htmlList[hi])
									);
								}
								pickupSetRenderCombined();
							}
						}
					).fail(function() {
						loadFailed = true;
						pickupSetFormulaLines = {};
						pickupSetRenderCombined();
					});
				})(sets[i]);
			}
		}

		function pickupUnifiedApplyMode(mode) {
			document.getElementById("pickup_work_mode").value = mode;
			var isSet = mode === "set";
			var isMa = mode === "ma";
			document.getElementById("pickup_body_set").style.display = isSet ? "" : "none";
			document.getElementById("pickup_body_ma").style.display = isMa ? "" : "none";
			var maTools = document.getElementById("pickup_ma_tools");
			if (maTools) {
				maTools.style.display = isMa ? "" : "none";
			}
			var buttons = document.getElementById("pickup_mode_switch").getElementsByTagName("button");
			for (var b = 0; b < buttons.length; b++) {
				var btnMode = buttons[b].getAttribute("data-mode");
				var isActive = mode !== "" && btnMode === mode;
				buttons[b].classList.toggle("is-active", isActive);
				buttons[b].classList.toggle("inv-btn--primary", isActive);
				buttons[b].classList.toggle("inv-btn--ghost", !isActive);
			}
			var footer = document.getElementById("pickup_unified_footer");
			if (footer) {
				footer.style.display = isSet || isMa ? "" : "none";
			}
			if (isSet) {
				pickupSetBarSyncState();
				pickupLoadSetFormula();
			}
			pickupFilterPreProductOptions();
		}

		/**
		 * ล้างรายการเบิกทั้งโหมดเซตและแยกรายการ แล้วยกเลิกการเลือกโหมด
		 */
		function pickupUnifiedClearAllLines() {
			if (!pickupHasPurposeSelected()) {
				pickupRequirePurpose();
				return;
			}
			var hasData = pickupUnifiedHasLineData();
			var mode = pickupGetWorkMode();
			if (!hasData && mode === "") {
				return;
			}
			if (hasData && !confirm("ล้างรายการเบิกทั้งหมดในเอกสารนี้?")) {
				return;
			}
			pickupUnifiedClearMaLines();
			pickupUnifiedClearSetLines();
			pickupUnifiedApplyMode("");
			closePickupMaAddModal();
			closePickupSetAddModal();
			pickupSyncLinesPurposeGate();
			pickupSyncUnifiedClearButton();
		}

		function pickupUnifiedSwitchMode(mode) {
			if (!pickupRequirePurpose()) {
				return;
			}
			if (mode === pickupGetWorkMode()) {
				return;
			}
			if (pickupUnifiedHasLineData()) {
				if (!confirm("สลับโหมดจะล้างรายการในตารางที่กรอกไว้ ต้องการดำเนินการต่อหรือไม่?")) {
					return;
				}
			}
			if (mode === "set") {
				pickupUnifiedClearMaLines();
			} else if (mode === "ma") {
				pickupUnifiedClearSetLines();
			}
			pickupUnifiedApplyMode(mode);
			if (mode === "ma") {
				openPickupMaAddModal();
			} else if (mode === "set") {
				openPickupSetAddModal();
			}
		}

		function save_pickup_set() {
			pickupMaSyncLogRemarks();
			var sets = pickupCollectSetRows();
			var manualKeys = Object.keys(pickupSetManualLines);
			if (!sets.length && manualKeys.length === 0) {
				alert("กรุณาโหลดเซตจาก Product หรือเพิ่มรายการแยก");
				return;
			}
			if (document.getElementById("pickup_set_has_rows").value !== "1") {
				alert("ไม่พบรายการในตาราง — ตรวจสอบ Product หรือรายการที่เพิ่ม");
				return;
			}
			var preType = pickupGetPurposeType();
			if (preType === "") {
				alert("กรุณาเลือกวัตถุประสงค์");
				return;
			}
			var dataUpdate =
				"pre_date=" + encodeURIComponent(document.getElementById("log_date").value) +
				"&pre_type=" + encodeURIComponent(preType) +
				"&pre_by=" + encodeURIComponent(document.getElementById("log_by").value) +
				"&pre_remarks=" + encodeURIComponent(document.getElementById("log_remarks").value) +
				"&search_id=" + encodeURIComponent(document.getElementById("search_id").value);
			for (var i = 0; i < sets.length; i++) {
				dataUpdate += "&pre_product[]=" + encodeURIComponent(sets[i].product);
				dataUpdate += "&pre_amount[]=" + encodeURIComponent(sets[i].amount);
			}
			for (var m = 0; m < manualKeys.length; m++) {
				var manualId = manualKeys[m];
				if (pickupSetOmittedPartIds[manualId]) {
					continue;
				}
				var manualRow = pickupSetManualLines[manualId];
				dataUpdate += "&manual_part_id[]=" + encodeURIComponent(manualId);
				dataUpdate += "&manual_part_qty[]=" + encodeURIComponent(manualRow.pick);
				dataUpdate += "&manual_product_name[]=" + encodeURIComponent(manualRow.productName || "");
			}
			for (var omitKey in pickupSetOmittedPartIds) {
				if (Object.prototype.hasOwnProperty.call(pickupSetOmittedPartIds, omitKey)) {
					dataUpdate += "&omit_part_id[]=" + encodeURIComponent(omitKey);
				}
			}
			window.location = "pre_pickup_save.php?" + dataUpdate;
		}

		function save_job() {
			pickupMaSyncLogRemarks();
			if (document.getElementById("log_by").value == "") {
				alert("ไม่พบชื่อผู้บันทึกจาก session — กรุณาเข้าสู่ระบบ SSO ใหม่");
				return;
			}
			if (pickupGetWorkMode() === "") {
				alert("กรุณาเลือกโหมดการเบิก (เซตหรือแยกรายการ)");
				return;
			}

			if (pickupGetWorkMode() === "set") {
				save_pickup_set();
				return;
			}

			var count = document.getElementById("count_sp").value;
			if (count == 0) {
				alert("กรุณากด「เพิ่มรายการ」เพื่อเลือกอะไหล่ก่อนบันทึก");
				return;
			}

			var type = pickupGetPurposeType();
			if (type === "") {
				alert("กรุณาเลือกวัตถุประสงค์");
				return;
			}

			var data_update = "log_date=" + encodeURIComponent(document.getElementById("log_date").value) +
				"&log_type=" + encodeURIComponent(type) +
				"&log_by=" + encodeURIComponent(document.getElementById("log_by").value) +
				"&count_sp=" + encodeURIComponent(document.getElementById("count_sp").value) +
				"&log_remarks=" + encodeURIComponent(document.getElementById("log_remarks").value) +
				"&search_id=" + encodeURIComponent(document.getElementById("search_id").value);

			var data_row = "";

			// alert("show1 = " + count);

			for ($a = 1; $a <= count; $a++) {

				if (count >= 1 && (document.getElementById("pd_sp" + $a).value == "" || document.getElementById("sn_sp" + $a).value == "" ||
						document.getElementById("amount_sp" + $a).value == "")) {
					alert("กรุณาป้อนข้อมูลให้ครบถ้วน");
					return;
				}
				data_row +=
					"&pd_sp" + $a + "=" + encodeURIComponent(document.getElementById("pd_sp" + $a).value) +
					"&sn_sp" + $a + "=" + encodeURIComponent(document.getElementById("sn_sp" + $a).value) +
					"&amount_sp" + $a + "=" + encodeURIComponent(document.getElementById("amount_sp" + $a).value) +
					"&stock_sp" + $a + "=" + encodeURIComponent(document.getElementById("stock_sp" + $a).value)

				//alert(data_row);
			}


			var sum_data = data_update + data_row;

			// alert(sum_data)

			window.location = "pickup_ma_save.php?" + sum_data; //  save jobw

		}

		//select s/n spare 1
		$(document.getElementById("pd_sp1")).ready(function() {
			$("select#pd_sp1").change(function() {
				$.get("list_part.php", {
						pd_sp1: $(this).val()
					},
					function(data) {
						// $("select#sn_sp1").html(data);
						$("select#sn_sp1").html(data);
					});
				// alert($(this).val())
			});
		});
		//select s/n spare 2
		$(document.getElementById("pd_sp2")).ready(function() {
			$("select#pd_sp2").change(function() {
				$.get("list_part.php", {
						pd_sp2: $(this).val()
					},
					function(data) {
						$("select#sn_sp2").html(data);
					});
			});
		});
		//select s/n spare 3
		$(document.getElementById("pd_sp3")).ready(function() {
			$("select#pd_sp3").change(function() {
				$.get("list_part.php", {
						pd_sp3: $(this).val()
					},
					function(data) {
						$("select#sn_sp3").html(data);
					});
			});
		});
		//select s/n spare 4
		$(document.getElementById("pd_sp4")).ready(function() {
			$("select#pd_sp4").change(function() {
				$.get("list_part.php", {
						pd_sp4: $(this).val()
					},
					function(data) {
						$("select#sn_sp4").html(data);
					});
			});
		});
		//select s/n spare 5
		$(document.getElementById("pd_sp5")).ready(function() {
			$("select#pd_sp5").change(function() {
				$.get("list_part.php", {
						pd_sp5: $(this).val()
					},
					function(data) {
						$("select#sn_sp5").html(data);
					});
			});
		});

		///------------------------------------- Select part check stock ---------------------------------------------------
		//select s/n spare 1

		$(document.getElementById("sn_sp1")).ready(function() {
			$("#sn_sp1").change(function() {
				$.get("list_stock.php", {
						sn_sp1: $(this).val()
					},
					function(data) {
						$("#stock_sp1").html(data);
						pickupMaRefreshRowUi(1);
					});
			});
		});

		//select s/n spare 2
		$(document.getElementById("sn_sp2")).ready(function() {
			$("select#sn_sp2").change(function() {
				$.get("list_stock.php", {
						sn_sp2: $(this).val()
					},
					function(data) {
						$("#stock_sp2").html(data);
						pickupMaRefreshRowUi(2);
					});
			});
		});
		//select s/n spare 3
		$(document.getElementById("sn_sp3")).ready(function() {
			$("select#sn_sp3").change(function() {
				$.get("list_stock.php", {
						sn_sp3: $(this).val()
					},
					function(data) {
						$("#stock_sp3").html(data);
						pickupMaRefreshRowUi(3);
					});
			});
		});
		//select s/n spare 4
		$(document.getElementById("sn_sp4")).ready(function() {
			$("select#sn_sp4").change(function() {
				$.get("list_stock.php", {
						sn_sp4: $(this).val()
					},
					function(data) {
						$("#stock_sp4").html(data);
						pickupMaRefreshRowUi(4);
					});
			});
		});
		//select s/n spare 5
		$(document.getElementById("sn_sp5")).ready(function() {
			$("select#sn_sp5").change(function() {
				$.get("list_stock.php", {
						sn_sp5: $(this).val()
					},
					function(data) {
						$("#stock_sp5").html(data);
						pickupMaRefreshRowUi(5);
					});
			});
		});

		///----------------------------------------------------------------------------------------

		// Add Product Maintenance
		$(document).ready(function() {
			var max_fields = 10;
			var wrapper = $(".container_maintenance");
			var add_button = $(".add_form_field");

			var x = 1;
			$(add_button).click(function(e) {
				e.preventDefault();
				if (x < max_fields) {
					x++;
					$(wrapper).append('<div><input type="text" name="mytext[]"/><a href="#" class="delete">Delete</a></div>'); //add input box
				} else {
					alert('You Reached the limits')
				}
			});

			$(wrapper).on("click", ".delete", function(e) {
				e.preventDefault();
				$(this).parent('div').remove();
				x--;
			})
		});

		function myFunction() {
			var jobWrap = document.getElementById("pickup_ma_job_wrap");
			var customerWrap = document.getElementById("pickup_ma_customer_wrap");
			var snWrap = document.getElementById("pickup_ma_product_sn_wrap");
			var remarksWrap = document.getElementById("pickup_ma_remarks_wrap");
			var docRow = document.querySelector(".pickup-ma-doc-fields--h4");
			if (!jobWrap) {
				return;
			}

			var purpose = pickupGetPurposeType();
			rate_value = purpose;
			var isRepair = purpose === "เบิกซ่อมลูกค้า";

			if (isRepair) {
				jobWrap.style.display = "";
				if (customerWrap) {
					customerWrap.style.display = "";
				}
				if (snWrap) {
					snWrap.style.display = "";
				}
				if (remarksWrap) {
					remarksWrap.style.display = "none";
				}
				if (docRow) {
					docRow.classList.remove("pickup-ma-doc-fields--no-job");
					docRow.classList.add("pickup-ma-doc-fields--repair");
				}
				pickupMaSyncLogRemarks();
			} else {
				jobWrap.style.display = "none";
				if (customerWrap) {
					customerWrap.style.display = "none";
				}
				if (snWrap) {
					snWrap.style.display = "none";
				}
				if (remarksWrap) {
					remarksWrap.style.display = "";
				}
				if (docRow) {
					docRow.classList.add("pickup-ma-doc-fields--no-job");
					docRow.classList.remove("pickup-ma-doc-fields--repair");
				}
			}
			pickupUpdateSetModeUi();
		}
		//-------------------------------------------- chkStock() -----------------------------------------------------

		function pickupMaRefreshRowUi(row) {
			var stockEl = document.getElementById("stock_sp" + row);
			var minEl = document.getElementById("min_sp" + row);
			var statusEl = document.getElementById("status_sp" + row);
			var amountEl = document.getElementById("amount_sp" + row);
			if (!stockEl || !minEl || !statusEl || !amountEl) {
				return;
			}
			var opt = stockEl.options[stockEl.selectedIndex];
			var minVal = opt && opt.getAttribute("data-min") ? opt.getAttribute("data-min") : "";
			minEl.textContent = minVal !== "" ? minVal : "—";
			var pick = parseFloat(amountEl.value);
			var stock = parseFloat(stockEl.value);
			if (isNaN(pick) || pick <= 0 || isNaN(stock) || stockEl.value === "") {
				statusEl.textContent = "—";
				statusEl.className = "pickup-ma-line-status";
				return;
			}
			if (pick > stock) {
				statusEl.textContent = "Stock ไม่พอ";
				statusEl.className = "pickup-ma-line-status stock-bad";
				return;
			}
			statusEl.textContent = "OK";
			statusEl.className = "pickup-ma-line-status stock-ok";
		}

		function chkStock() {
			pickupMaRefreshRowUi(1);
			var pick = parseFloat(document.getElementById("amount_sp1").value);
			var stock = parseFloat(document.getElementById("stock_sp1").value);
			if (!isNaN(pick) && !isNaN(stock) && pick > stock) {
				alert("ใส่ยอดเบิกเกินจำนวน stock ที่มี ");
				document.getElementById("amount_sp1").value = "";
				pickupMaRefreshRowUi(1);
			}
		}

		function chkStockRow(row) {
			pickupMaRefreshRowUi(row);
			var amountEl = document.getElementById("amount_sp" + row);
			var stockEl = document.getElementById("stock_sp" + row);
			if (!amountEl || !stockEl) {
				return;
			}
			var pick = parseFloat(amountEl.value);
			var stock = parseFloat(stockEl.value);
			if (!isNaN(pick) && !isNaN(stock) && pick > stock) {
				alert("ใส่ยอดเบิกเกินจำนวน stock ที่มี ");
				amountEl.value = "";
				pickupMaRefreshRowUi(row);
			}
		}

		function pickupMaOptionText(selectEl) {
			if (!selectEl || selectEl.selectedIndex < 0) {
				return "—";
			}
			return selectEl.options[selectEl.selectedIndex].text;
		}

		function pickupMaClearSlot(row) {
			var pd = document.getElementById("pd_sp" + row);
			var sn = document.getElementById("sn_sp" + row);
			var amount = document.getElementById("amount_sp" + row);
			var stock = document.getElementById("stock_sp" + row);
			var minEl = document.getElementById("min_sp" + row);
			var statusEl = document.getElementById("status_sp" + row);
			if (pd) {
				pd.value = "";
			}
			if (sn) {
				sn.innerHTML = '<option value="">--เลือก part--</option>';
			}
			if (amount) {
				amount.value = "";
			}
			if (stock) {
				stock.innerHTML = '<option value="">none</option>';
			}
			if (minEl) {
				minEl.textContent = "—";
			}
			if (statusEl) {
				statusEl.textContent = "—";
				statusEl.className = "pickup-ma-line-status";
			}
		}

		function pickupMaSyncToolbarState() {
			/* ปุ่มลบอยู่ในคอลัมน์ตารางแล้ว — ไม่มี toolbar ลบล่าสุด */
		}

		/**
		 * คัดลอกข้อมูลสล็อตแถวเบิกแยกรายการ (ใช้ตอนลบแถวกลางแล้วเลื่อนขึ้น)
		 *
		 * @param {number} fromRow ลำดับต้นทาง
		 * @param {number} toRow ลำดับปลายทาง
		 */
		function pickupMaCopySlot(fromRow, toRow) {
			var pdFrom = document.getElementById("pd_sp" + fromRow);
			var pdTo = document.getElementById("pd_sp" + toRow);
			if (pdFrom && pdTo) {
				pdTo.value = pdFrom.value;
			}
			var snFrom = document.getElementById("sn_sp" + fromRow);
			var snTo = document.getElementById("sn_sp" + toRow);
			if (snFrom && snTo) {
				snTo.innerHTML = snFrom.innerHTML;
				snTo.value = snFrom.value;
			}
			var stockFrom = document.getElementById("stock_sp" + fromRow);
			var stockTo = document.getElementById("stock_sp" + toRow);
			if (stockFrom && stockTo) {
				stockTo.innerHTML = stockFrom.innerHTML;
				stockTo.value = stockFrom.value;
			}
			var amtFrom = document.getElementById("amount_sp" + fromRow);
			var amtTo = document.getElementById("amount_sp" + toRow);
			if (amtFrom && amtTo) {
				amtTo.value = amtFrom.value;
			}
			pickupMaRefreshRowUi(toRow);
		}

		/**
		 * ลบรายการเบิกแยกตามลำดับในตาราง
		 *
		 * @param {number} rowIndex ลำดับ 1..r_spare
		 */
		function pickupMaRemoveLine(rowIndex) {
			rowIndex = parseInt(rowIndex, 10);
			if (isNaN(rowIndex) || rowIndex < 1 || rowIndex > r_spare) {
				return;
			}
			for (var i = rowIndex; i < r_spare; i++) {
				pickupMaCopySlot(i + 1, i);
			}
			pickupMaClearSlot(r_spare);
			r_spare -= 1;
			$("#count_sp").val(r_spare);
			pickupMaRenderAllDisplayRows();
		}

		function pickupMaRenderAllDisplayRows() {
			var tbody = document.getElementById("pickup_ma_lines_display");
			var emptyHint = document.getElementById("pickup_ma_empty_hint");
			if (!tbody) {
				return;
			}
			var oldRows = tbody.querySelectorAll("tr.pickup-ma-display-row");
			for (var r = 0; r < oldRows.length; r++) {
				oldRows[r].parentNode.removeChild(oldRows[r]);
			}
			if (emptyHint) {
				emptyHint.style.display = r_spare > 0 ? "none" : "";
			}
			for (var i = 1; i <= r_spare; i++) {
				var sn = document.getElementById("sn_sp" + i);
				var minEl = document.getElementById("min_sp" + i);
				var stock = document.getElementById("stock_sp" + i);
				var amount = document.getElementById("amount_sp" + i);
				var statusEl = document.getElementById("status_sp" + i);
				var tr = document.createElement("tr");
				tr.className = "pickup-ma-display-row";
				var statusText = statusEl ? statusEl.textContent : "—";
				var statusClass = statusEl ? statusEl.className : "pickup-ma-line-status";
				tr.innerHTML =
					'<td class="inv-col-num">' + i + "</td>" +
					'<td>' + pickupMaOptionText(sn).replace(/</g, "&lt;") + "</td>" +
					'<td class="inv-col-num">' + (minEl ? minEl.textContent.replace(/</g, "&lt;") : "—") + "</td>" +
					'<td class="inv-col-num">' + (stock && stock.value !== "" ? stock.value : "—") + "</td>" +
					'<td class="inv-col-num">' + (amount && amount.value !== "" ? amount.value : "—") + "</td>" +
					'<td><span class="' + statusClass + '">' + statusText.replace(/</g, "&lt;") + "</span></td>" +
					'<td class="inv-col-action inv-col-actions">' +
					'<button type="button" class="inv-btn inv-btn--ghost pickup-set-line-remove" title="ลบรายการนี้ออกจากใบเบิก" onclick="pickupMaRemoveLine(' + i + '); return false;"><i class="fa fa-trash"></i></button>' +
					"</td>";
				if (emptyHint && emptyHint.parentNode === tbody) {
					tbody.insertBefore(tr, emptyHint);
				} else {
					tbody.appendChild(tr);
				}
			}
			pickupMaSyncToolbarState();
			pickupSyncUnifiedClearButton();
		}

		/**
		 * อัปเดตข้อความหัว popup โหลดชุดตามวัตถุประสงค์
		 */
		function pickupSyncSetModalCopy() {
			var title = document.getElementById("pickup_set_modal_title");
			var desc = document.getElementById("pickup_set_modal_desc");
			if (!title || !desc) {
				return;
			}
			if (pickupIsRepairPurpose()) {
				title.textContent = "โหลดชุดซ่อม";
				desc.textContent = "เลือก Product และจำนวนชุด — รายการจาก repair_formula";
			} else {
				title.textContent = "โหลดชุดผลิต";
				desc.textContent = "เลือก Product และจำนวนชุด — รายการจากสูตรผลิต";
			}
		}

		function resetPickupSetAddModal() {
			var modalPd = document.getElementById("pickup_set_modal_product");
			var modalAmt = document.getElementById("pickup_set_modal_amount");
			var barProd = document.querySelector("#pickup_set_bars .pickup-set-row-product");
			var barAmt = document.querySelector("#pickup_set_bars .pickup-set-row-amount");
			pickupFilterPreProductOptions();
			if (modalPd) {
				modalPd.value = barProd && barProd.value ? barProd.value : "";
			}
			if (modalAmt) {
				var barAmount = barAmt && barAmt.value ? parseInt(barAmt.value, 10) : 1;
				if (isNaN(barAmount) || barAmount < 1) {
					barAmount = 1;
				}
				modalAmt.value = String(barAmount);
			}
			pickupSyncSetModalCopy();
		}

		function openPickupSetAddModal() {
			if (!pickupRequirePurpose()) {
				return;
			}
			var modal = document.getElementById("pickup_set_add_modal");
			if (!modal) {
				return;
			}
			resetPickupSetAddModal();
			modal.style.display = "flex";
			modal.setAttribute("aria-hidden", "false");
		}

		function closePickupSetAddModal() {
			var modal = document.getElementById("pickup_set_add_modal");
			if (!modal) {
				return;
			}
			modal.style.display = "none";
			modal.setAttribute("aria-hidden", "true");
		}

		/**
		 * ยืนยัน popup ชุด — ส่งค่าไปแถบซ่อนแล้วโหลดสูตร
		 */
		function submitPickupSetAddModal() {
			var modalPd = document.getElementById("pickup_set_modal_product");
			var modalAmt = document.getElementById("pickup_set_modal_amount");
			var setQty = modalAmt ? parseInt(modalAmt.value, 10) : 0;
			if (!modalPd || !modalAmt || modalPd.value === "" || isNaN(setQty) || setQty < 1) {
				alert("กรุณาเลือก Product และจำนวนชุด");
				return;
			}
			var barProd = document.querySelector("#pickup_set_bars .pickup-set-row-product");
			if (barProd) {
				barProd.value = modalPd.value;
			}
			pickupSyncSetBarAmount(setQty);
			pickupSetBarSyncState();
			pickupSyncSetSelectionSummary();
			pickupLoadSetFormula();
			closePickupSetAddModal();
		}

		function openPickupMaAddModal() {
			if (!pickupRequirePurpose()) {
				return;
			}
			var modal = document.getElementById("pickup_ma_add_modal");
			if (!modal) {
				return;
			}
			if (pickupGetWorkMode() === "set") {
				if (Object.keys(pickupSetManualLines).length >= 20) {
					alert("เพิ่มรายการแยกในเซตได้สูงสุด 20 Part");
					return;
				}
			} else if (r_spare >= 5) {
				alert("เพิ่มรายการเบิกได้สูงสุด 5 รายการ");
				return;
			}
			resetPickupMaAddModal();
			pickupFilterPreProductOptions();
			modal.style.display = "flex";
			modal.setAttribute("aria-hidden", "false");
		}

		function closePickupMaAddModal() {
			var modal = document.getElementById("pickup_ma_add_modal");
			if (!modal) {
				return;
			}
			modal.style.display = "none";
			modal.setAttribute("aria-hidden", "true");
		}

		function resetPickupMaAddModal() {
			var pd = document.getElementById("pickup_ma_modal_pd");
			var sn = document.getElementById("pickup_ma_modal_sn");
			if (pd) {
				pd.value = "";
			}
			if (sn) {
				sn.innerHTML = '<option value="">-- เลือก part --</option>';
			}
			document.getElementById("pickup_ma_modal_stock").textContent = "—";
			document.getElementById("pickup_ma_modal_min").textContent = "—";
			document.getElementById("pickup_ma_modal_amount").value = "1";
		}

		function pickupMaModalQtyStep(delta) {
			var input = document.getElementById("pickup_ma_modal_amount");
			if (!input) {
				return;
			}
			var current = parseInt(input.value, 10);
			if (isNaN(current)) {
				current = 1;
			}
			current = Math.max(1, current + delta);
			input.value = String(current);
		}

		/**
		 * ส่งค่าจำนวนชุดจาก popup ไปยัง select ซ่อน (สร้าง option ถ้าค่าไม่อยู่ในรายการเดิม)
		 *
		 * @param {string|number} amount จำนวนชุด
		 */
		function pickupSyncSetBarAmount(amount) {
			var barAmt = document.querySelector("#pickup_set_bars .pickup-set-row-amount");
			if (!barAmt) {
				return;
			}
			var val = String(amount);
			if (val === "" || val === "0") {
				barAmt.value = "";
				return;
			}
			var found = false;
			for (var i = 0; i < barAmt.options.length; i++) {
				if (barAmt.options[i].value === val) {
					found = true;
					break;
				}
			}
			if (!found) {
				var opt = document.createElement("option");
				opt.value = val;
				opt.textContent = val;
				barAmt.appendChild(opt);
			}
			barAmt.value = val;
		}

		function loadPickupMaModalParts() {
			var productId = document.getElementById("pickup_ma_modal_pd").value;
			var sn = document.getElementById("pickup_ma_modal_sn");
			document.getElementById("pickup_ma_modal_stock").textContent = "—";
			document.getElementById("pickup_ma_modal_min").textContent = "—";
			if (!productId) {
				sn.innerHTML = '<option value="">-- เลือก part --</option>';
				return;
			}
			$.get("list_part.php", { pd_sp1: productId }, function(data) {
				sn.innerHTML = data;
			});
		}

		function loadPickupMaModalStock() {
			var partId = document.getElementById("pickup_ma_modal_sn").value;
			var stockDisplay = document.getElementById("pickup_ma_modal_stock");
			var minDisplay = document.getElementById("pickup_ma_modal_min");
			if (!partId) {
				stockDisplay.textContent = "—";
				minDisplay.textContent = "—";
				return;
			}
			$.get("list_stock.php", { sn_sp1: partId }, function(html) {
				var tmp = document.createElement("div");
				tmp.innerHTML = html;
				var opt = tmp.querySelector('option[value]:not([value=""])');
				if (!opt) {
					stockDisplay.textContent = "—";
					minDisplay.textContent = "—";
					return;
				}
				stockDisplay.textContent = opt.value;
				minDisplay.textContent = opt.getAttribute("data-min") || "—";
			});
		}

		function submitPickupMaAddModal() {
			var pdId = document.getElementById("pickup_ma_modal_pd").value;
			var partId = document.getElementById("pickup_ma_modal_sn").value;
			var amount = document.getElementById("pickup_ma_modal_amount").value;
			if (!pdId) {
				alert("กรุณาเลือก Product");
				return;
			}
			if (!partId) {
				alert("กรุณาเลือก Part");
				return;
			}
			var pickInt = parseInt(amount, 10);
			if (!amount || isNaN(pickInt) || pickInt < 1) {
				alert("กรุณากรอกจำนวนเบิก (อย่างน้อย 1)");
				document.getElementById("pickup_ma_modal_amount").focus();
				return;
			}
			amount = String(pickInt);
			var pick = parseFloat(amount);
			var stock = parseFloat(document.getElementById("pickup_ma_modal_stock").textContent);
			if (!isNaN(stock) && pick > stock) {
				alert("ใส่ยอดเบิกเกินจำนวน stock ที่มี");
				return;
			}
			if (pickupGetWorkMode() === "set") {
				var pdSelect = document.getElementById("pickup_ma_modal_pd");
				var snSelect = document.getElementById("pickup_ma_modal_sn");
				var partLabel = snSelect.options[snSelect.selectedIndex]
					? snSelect.options[snSelect.selectedIndex].text
					: "";
				var pdLabel = pdSelect.options[pdSelect.selectedIndex]
					? pdSelect.options[pdSelect.selectedIndex].text
					: "";
				pickupSetAddManualLine(
					partId,
					partLabel,
					document.getElementById("pickup_ma_modal_min").textContent,
					document.getElementById("pickup_ma_modal_stock").textContent,
					amount,
					pdLabel
				);
				closePickupMaAddModal();
				return;
			}
			if (r_spare >= 5) {
				alert("เพิ่มรายการเบิกได้สูงสุด 5 รายการ");
				return;
			}
			var row = r_spare + 1;
			document.getElementById("pd_sp" + row).value = pdId;
			$.get("list_part.php", { pd_sp1: pdId }, function(partHtml) {
				var snSelect = document.getElementById("sn_sp" + row);
				snSelect.innerHTML = partHtml;
				snSelect.value = partId;
				$.get("list_stock.php", { sn_sp1: partId }, function(stockHtml) {
					document.getElementById("stock_sp" + row).innerHTML = stockHtml;
					document.getElementById("amount_sp" + row).value = amount;
					r_spare = row;
					$("#count_sp").val(r_spare);
					pickupMaRefreshRowUi(row);
					pickupMaRenderAllDisplayRows();
					closePickupMaAddModal();
				});
			});
		}

		//-------------------------------------------------------------------------------------------------

		var r_spare = 0;

		function addSpare(mode) { //Add 


			// document.getElementById("stock_sp1").innerText = "100"

			if (mode === "add") {
				openPickupMaAddModal();
				return;
			}

			if (r_spare <= 0) {
				r_spare = 0;
				pickupMaRenderAllDisplayRows();
				return;
			}
			pickupMaClearSlot(r_spare);
			r_spare -= 1;
			$("#count_sp").val(r_spare);
			pickupMaRenderAllDisplayRows();
		}
	</script>
</head>
<style>
	table,
	td,
	th {
		border: 0;
	}

	tr.spaceUnder>td {
		padding-bottom: 1em;
	}

	tr.spaceUnder05>td {
		padding-bottom: 0.5em;
	}

	tr.spaceTop>td {
		padding-top: 1em;
	}

	.ex {
		border-radius: 10px;
		background: #FFCCCC;
		padding: 5px 10px 5px 10px;
		width: max-content;
	}

	.ex2 {
		border-radius: 10px;
		background: #CCFFFF;
		padding: 5px 10px 5px 10px;
		width: max-content;
	}

	.pickup-ma-doc-card__body {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		max-width: 100%;
		min-width: 0;
		overflow-x: clip;
		padding-top: 0.25rem;
	}

	.pickup-ma-doc-card__body > * {
		max-width: 100%;
		min-width: 0;
	}

	.pickup-ma-purpose-label {
		color: var(--inv-muted);
		font-size: 0.8rem;
		font-weight: 600;
		letter-spacing: 0.03em;
		margin: 1rem 0 0.5rem;
		text-transform: uppercase;
	}

	.pickup-ma-purpose-label--subhead {
		margin: 0 0 0.5rem;
	}

	.pickup-ma-purpose-subhead {
		padding: 1rem 1.5rem 1.1rem;
	}

	.pickup-unified-lines-gate {
		position: relative;
	}

	.pickup-unified-lines-gate__overlay {
		align-items: center;
		background: rgba(255, 255, 255, 0.72);
		backdrop-filter: blur(2px);
		border-radius: 0 0 12px 12px;
		bottom: 0;
		cursor: pointer;
		display: flex;
		justify-content: center;
		left: 0;
		padding: 1.5rem;
		position: absolute;
		right: 0;
		top: 0;
		z-index: 6;
	}

	.pickup-unified-lines-gate__overlay-inner {
		background: #fff;
		border: 1px solid rgba(46, 125, 50, 0.18);
		border-radius: 12px;
		box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
		color: #334155;
		font-size: 0.95rem;
		font-weight: 600;
		max-width: 22rem;
		padding: 0.85rem 1.1rem;
		text-align: center;
	}

	.pickup-unified-lines-card--locked .pickup-unified-lines-gate__overlay[hidden] {
		display: none !important;
	}

	.pickup-unified-lines-card:not(.pickup-unified-lines-card--locked) .pickup-unified-lines-gate__overlay {
		display: none !important;
	}

	.pickup-ma-doc-card > .inv-card__head,
	.pickup-ma-doc-card > .inv-card__body,
	section.inv-card > .inv-card__head,
	section.inv-card > .inv-card__footer {
		padding-left: 1.5rem;
		padding-right: 1.5rem;
	}

	section.inv-card > .inv-card__footer {
		padding-top: 1.25rem;
		padding-bottom: 1.25rem;
	}

	.pickup-ma-doc-fields--h4 {
		align-items: end;
		display: grid;
		gap: 0.75rem 1.25rem;
		grid-template-columns: minmax(0, 10.5rem) minmax(0, 14rem) minmax(0, 1fr);
		max-width: 100%;
		min-width: 0;
		width: 100%;
	}

	.pickup-ma-doc-fields--h4 .inv-field {
		min-width: 0;
	}

	.pickup-ma-doc-fields--h4 .pickup-ma-doc-input {
		box-sizing: border-box;
		display: block;
		height: 2.75rem;
		line-height: 1.25;
		margin: 0;
		max-height: 2.75rem;
		max-width: 100%;
		min-height: 2.75rem;
		padding: 0.5rem 0.75rem;
		width: 100%;
	}

	.pickup-ma-doc-fields--h4 input[type="date"].pickup-ma-doc-input {
		appearance: none;
		-webkit-appearance: none;
	}

	.pickup-ma-doc-fields--no-job {
		grid-template-columns: minmax(0, 10.5rem) minmax(0, 1fr);
	}

	@media (max-width: 900px) {
		.pickup-ma-doc-fields--h4,
		.pickup-ma-doc-fields--no-job {
			grid-template-columns: 1fr;
		}
	}

	.pickup-ma-purpose-grid label {
		align-items: center;
		cursor: pointer;
		display: flex;
		font-size: 0.95rem;
		gap: 0.5rem;
	}

	.pickup-ma-line-head {
		align-items: center;
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;
		justify-content: space-between;
		width: 100%;
	}

	.pickup-ma-line-head a {
		color: var(--inv-accent);
		font-weight: 600;
		text-decoration: none;
	}

	.pickup-ma-line-head a.pickup-ma-line-del {
		color: var(--inv-danger);
	}

	.pickup-ma-save-row {
		align-items: flex-end;
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem 1rem;
		justify-content: flex-start;
		width: 100%;
	}

	.pickup-ma-save-row .inv-field {
		flex: 0 1 auto;
		margin: 0;
		max-width: 240px;
		min-width: 10.5rem;
	}

	.pickup-ma-save-row > .inv-btn {
		flex-shrink: 0;
	}

</style>

<body class="inv-app pickup-ma pickup-unified">
<?php require __DIR__ . '/title-h.php'; ?>
<main class="inv-main">
		<header class="inv-page-header">
			<h1 class="inv-page-title">เบิกของ</h1>
			<p class="inv-page-desc">เบิกเป็นเซตจากสูตรผลิต หรือเบิกแยกรายการ — กรอกหัวเอกสารแล้วเลือกโหมดที่การ์ดรายการเบิก</p>
		</header>

		<?php

		$srotPro = "";    // ค่า sort ที่เลือก

		$no = isset($_GET['no_repair']) ? trim((string) $_GET['no_repair']) : '';
		$detail = isset($_GET['detail']) ? trim((string) $_GET['detail']) : '';
		$product = isset($_GET['product']) ? trim((string) $_GET['product']) : '';

		if ($no != "") {

			$auto_se = "";    // ค่า auto select

			if (strpos($product, "bitScan") !== false) {
				$auto_se = "11";
			} else if (strpos($product, "Printer 80 mm") !== false) {
				$auto_se = "14";
			} else if (strpos($product, "bitVisitor M") !== false) {
				$auto_se = "2";
			} else if (strpos($product, "bitVisitor Plus") !== false) {
				$auto_se = "1";
			} else {
				$auto_se = "";
			}

			$srotPro = $auto_se;
		}

		$pickupInitialMode = '';
		if (isset($_GET['mode']) && $_GET['mode'] === 'set') {
			$pickupInitialMode = 'set';
		} elseif (isset($_GET['mode']) && $_GET['mode'] === 'ma') {
			$pickupInitialMode = 'ma';
		}
		$pickupShowSetBody = $pickupInitialMode === 'set';
		$pickupShowMaBody = $pickupInitialMode === 'ma';
		$pickupShowLinesFooter = $pickupInitialMode !== '';
		$pickupProductIds = array();
		$pickupProductNames = array();
		$pickupProductHasRepairSet = array();
		$pickupProductHasProductionSet = array();
		$sqlPickupProducts = 'SELECT pd_id, pd_name FROM product ORDER BY pd_name ASC';
		$exePickupProducts = db_exec($connect, $sqlPickupProducts);
		if ($exePickupProducts) {
			while ($rowPickupProduct = db_fetch_array($exePickupProducts)) {
				$pickupProductIds[] = $rowPickupProduct['pd_id'];
				$pickupProductNames[] = $rowPickupProduct['pd_name'];
				$pickupPdId = (int) $rowPickupProduct['pd_id'];
				$pickupProductHasRepairSet[] = pickup_ma_product_has_repair_set(
					$connect,
					$pickupPdId,
					(string) $rowPickupProduct['pd_name']
				);
				$pickupProductHasProductionSet[] = pickup_ma_product_has_production_set($connect, $pickupPdId);
			}
		}
		$pickupProductCount = count($pickupProductIds);
		$pickupSetAmountOptions = array(1, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20);

		?>

		<section class="inv-card pickup-ma-doc-card pickup-ma-doc-card--h5">
				<div class="inv-card__head"><i class="fa fa-file-text-o"></i> หัวเอกสาร</div>
				<div class="inv-card__subhead pickup-ma-purpose-subhead">
					<p class="pickup-ma-purpose-label pickup-ma-purpose-label--subhead">วัตถุประสงค์</p>
					<div class="pickup-ma-purpose-mode pickup-ma-purpose-mode--invbtn" id="checkForm" role="radiogroup" aria-label="วัตถุประสงค์การเบิก">
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck1" form="form_add_stock" onclick="myFunction()" value="เบิกซ่อมลูกค้า" /><span>เบิกซ่อมลูกค้า</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck2" form="form_add_stock" onclick="myFunction()" value="เบิกเสียระหว่างผลิต" /><span>เบิกเสียระหว่างผลิต</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck6" form="form_add_stock" onclick="myFunction()" value="เบิกเสียระหว่างขนส่ง" /><span>เบิกเสียระหว่างขนส่ง</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck3" form="form_add_stock" onclick="myFunction()" value="เบิกผลิต" /><span>เบิกผลิต</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck4" form="form_add_stock" onclick="myFunction()" value="เบิกตาม PO" /><span>เบิกตาม PO</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck5" form="form_add_stock" onclick="myFunction()" value="เบิก MA" /><span>เบิก MA</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck7" form="form_add_stock" onclick="myFunction()" value="เบิกอื่นๆ" /><span>เบิกอื่นๆ</span></label>
					</div>
				</div>
				<div class="inv-card__body pickup-ma-doc-card__body">
					<div class="pickup-ma-doc-fields pickup-ma-doc-fields--h4 pickup-ma-doc-fields--no-job">
						<div class="inv-field">
							<label for="log_date">วันที่เบิก</label>
							<input class="inv-input pickup-ma-doc-input" type="date" name="log_date" id="log_date" form="form_add_stock" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" />
						</div>
						<div class="inv-field" id="pickup_ma_job_wrap" style="display:none;">
							<label for="search_id" id="lb_id">เลขที่ใบซ่อม</label>
							<input class="inv-input pickup-ma-doc-input" type="text" name="search_id" id="search_id" form="form_add_stock" autocomplete="off" />
						</div>
						<div class="inv-field pickup-ma-customer-field" id="pickup_ma_customer_wrap" style="display:none;">
							<label for="pickup_ma_customer_com">ชื่อบริษัทลูกค้า</label>
							<input class="inv-input pickup-ma-doc-input" type="text" name="pickup_ma_customer_com" id="pickup_ma_customer_com" form="form_add_stock" maxlength="500" autocomplete="off" />
						</div>
						<div class="inv-field pickup-ma-product-sn-field" id="pickup_ma_product_sn_wrap" style="display:none;">
							<label for="pickup_ma_product_sn">หมายเลขสินค้า</label>
							<input class="inv-input pickup-ma-doc-input" type="text" name="pickup_ma_product_sn" id="pickup_ma_product_sn" form="form_add_stock" maxlength="200" autocomplete="off" />
						</div>
						<div class="inv-field pickup-ma-remarks-field" id="pickup_ma_remarks_wrap">
							<label for="log_remarks">หมายเหตุ</label>
							<input class="inv-input pickup-ma-doc-input" type="text" name="log_remarks" id="log_remarks" form="form_add_stock" maxlength="5000" autocomplete="off" />
						</div>
					</div>
				</div>
		</section>

			<section class="inv-card pickup-unified-lines-card pickup-unified-lines-card--locked">
				<input type="hidden" id="pickup_work_mode" value="<?php echo htmlspecialchars($pickupInitialMode, ENT_QUOTES, 'UTF-8'); ?>" />
				<input type="hidden" id="pickup_set_has_rows" value="0" />
				<div class="pickup-unified-lines-gate" id="pickup_lines_gate">
				<div class="pickup-unified-lines-gate__overlay" id="pickup_lines_gate_overlay" role="presentation" onclick="pickupRequirePurpose(); return false;">
					<p class="pickup-unified-lines-gate__overlay-inner">เลือกวัตถุประสงค์ด้านบนก่อนเพิ่มรายการเบิก</p>
				</div>
				<div class="inv-card__head pickup-unified-lines-head">
					<div class="pickup-unified-lines-head__cluster">
						<div class="pickup-unified-lines-head__title">
							<i class="fa fa-list"></i> รายการเบิก
						</div>
						<div class="pickup-unified-mode pickup-unified-mode--invbtn" id="pickup_mode_switch" role="group" aria-label="โหมดการเบิก">
							<button type="button" data-mode="set" class="inv-btn pickup-unified-mode-btn<?php echo $pickupInitialMode === 'set' ? ' inv-btn--primary is-active' : ' inv-btn--ghost'; ?>" onclick="pickupUnifiedSwitchMode('set'); return false;" title="โหลดรายการจากสูตรผลิต"><i class="fa fa-wrench"></i> เบิกเซตซ่อม</button>
							<button type="button" data-mode="ma" class="inv-btn pickup-unified-mode-btn<?php echo $pickupInitialMode === 'ma' ? ' inv-btn--primary is-active' : ' inv-btn--ghost'; ?>" onclick="pickupUnifiedSwitchMode('ma'); return false;"><i class="fa fa-list-ul"></i> เบิกแยกรายการ</button>
						</div>
					</div>
					<div class="pickup-unified-lines-head__actions">
						<button type="button" class="inv-btn inv-btn--ghost pickup-unified-lines-clear" hidden style="display:none;" onclick="pickupUnifiedClearAllLines(); return false;" title="ล้างรายการเบิกและยกเลิกการเลือกโหมด"><i class="fa fa-eraser"></i> ล้างค่า</button>
					</div>
				</div>
				<div id="pickup_body_set" class="inv-card__body pickup-unified-body" style="display:<?php echo $pickupShowSetBody ? 'block' : 'none'; ?>;">
					<div id="pickup_set_bars" class="pickup-set-bars pickup-set-bars--hidden" aria-hidden="true">
						<div class="pickup-unified-set-bar pickup-set-bar-row">
							<div class="pickup-set-picker pickup-set-picker--product">
								<span class="pickup-set-picker__label">Product</span>
								<div class="inv-btn inv-btn--ghost pickup-set-picker__btn-wrap<?php echo ($srotPro !== '') ? ' is-filled inv-btn--primary' : ''; ?>" role="button" tabindex="0" title="เลือก Product" onclick="pickupSetPickerActivate(event, this);" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();pickupSetPickerActivate(event,this);}">
									<select class="pickup-set-picker__select pickup-set-row-product" name="pre_product[]">
										<option value="">เลือก Product</option>
										<?php for ($pickupPi = 0; $pickupPi < $pickupProductCount; $pickupPi++) {
											$pickupPid = (string) $pickupProductIds[$pickupPi];
											$pickupSelected = ($srotPro !== '' && (string) $srotPro === $pickupPid) ? ' selected="selected"' : '';
											$pickupHasRepair = !empty($pickupProductHasRepairSet[$pickupPi]);
											$pickupHasProduction = !empty($pickupProductHasProductionSet[$pickupPi]);
											?>
										<option value="<?php echo htmlspecialchars($pickupPid, ENT_QUOTES, 'UTF-8'); ?>" data-repair-set="<?php echo $pickupHasRepair ? '1' : '0'; ?>" data-production-set="<?php echo $pickupHasProduction ? '1' : '0'; ?>"<?php echo $pickupSelected; ?>><?php echo htmlspecialchars($pickupProductNames[$pickupPi], ENT_QUOTES, 'UTF-8'); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<div class="pickup-set-picker pickup-set-picker--qty">
								<span class="pickup-set-picker__label">จำนวนชุด</span>
								<div class="inv-btn inv-btn--ghost pickup-set-picker__btn-wrap" role="button" tabindex="0" title="เลือกจำนวนชุด" onclick="pickupSetPickerActivate(event, this);" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();pickupSetPickerActivate(event,this);}">
									<select class="pickup-set-picker__select pickup-set-row-amount" name="pre_amount[]">
										<option value="">เลือกจำนวน</option>
										<?php foreach ($pickupSetAmountOptions as $pickupAmt) { ?>
										<option value="<?php echo (int) $pickupAmt; ?>"><?php echo (int) $pickupAmt; ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
						</div>
					</div>
					<p id="pickup_set_mode_hint" class="pickup-set-mode-hint">ชุดซ่อม: รายการ Part ที่ผูก Product ใน repair_formula</p>
					<div id="pickup_set_selection_summary" class="pickup-set-selection-summary is-empty" hidden>
						<div class="pickup-set-selection-summary__main">
							<span class="pickup-set-selection-summary__label">ชุดที่เลือก</span>
							<strong id="pickup_set_selection_title" class="pickup-set-selection-summary__title">—</strong>
							<span id="pickup_set_selection_qty" class="pickup-set-selection-summary__qty"></span>
						</div>
						<button type="button" class="inv-btn inv-btn--ghost pickup-set-selection-summary__change" onclick="openPickupSetAddModal(); return false;">เปลี่ยนชุด</button>
					</div>
					<div class="pickup-unified-table-card">
					<div class="inv-table-wrap">
						<table class="inv-table pickup-set-lines-table pickup-unified-lines-table" width="100%">
							<thead>
								<tr>
									<th class="inv-col-num">ลำดับ</th>
									<th>Part name</th>
									<th class="inv-col-num">จำนวนขั้นต่ำที่ต้องสั่ง</th>
									<th class="inv-col-num">จำนวน Stock</th>
									<th class="inv-col-num">จำนวนเบิก</th>
									<th>สถานะ</th>
									<th class="inv-col-action">ลบ</th>
								</tr>
							</thead>
							<tbody id="pickup_set_tbody">
								<tr><td colspan="7" class="pickup-set-empty">เลือกโหมดเซตแล้วโหลดชุดจาก popup หรือกด「เพิ่มเบิกแยกรายการ」</td></tr>
							</tbody>
						</table>
					</div>
					<div class="pickup-set-table-foot">
						<button type="button" class="inv-btn inv-btn--ghost" onclick="pickupGoSeparateLinePickup(); return false;"><i class="fa fa-list-ul"></i> เพิ่มเบิกแยกรายการ</button>
					</div>
					</div>
				</div>
				<div id="pickup_body_ma" class="inv-card__body pickup-unified-body" style="display:<?php echo $pickupShowMaBody ? 'block' : 'none'; ?>;">
					<div class="pickup-ma-line-tools" id="pickup_ma_tools" style="display:<?php echo $pickupShowMaBody ? '' : 'none'; ?>;">
						<div class="pickup-ma-line-tools__actions">
							<button type="button" class="inv-btn inv-btn--primary" onclick="openPickupMaAddModal(); return false;"><i class="fa fa-plus"></i> เพิ่มรายการ</button>
						</div>
					</div>
		<form id="form_add_stock" name="form_add_stock" method="post" action="save_stock">
				<div class="pickup-unified-table-card">
				<div class="inv-table-wrap">
				<table id="table1" class="inv-table pickup-ma-lines-table pickup-unified-lines-table" width="100%">
		<thead>
		<tr>
		<th class="inv-col-num">ลำดับ</th>
		<th>Part name</th>
		<th class="inv-col-num">จำนวนขั้นต่ำที่ต้องสั่ง</th>
		<th class="inv-col-num">จำนวน Stock</th>
		<th class="inv-col-num">จำนวนเบิก</th>
		<th>สถานะ</th>
		<th class="inv-col-action">ลบ</th>
		</tr>
		</thead>
		<tbody id="pickup_ma_lines_display">
		<tr id="pickup_ma_empty_hint" class="pickup-ma-empty-row">
			<td colspan="7" class="pickup-set-empty">ยังไม่มีรายการ — กด「เพิ่มรายการ」เพื่อเลือกอะไหล่</td>
		</tr>
		<tr class="pickup-ma-lines-row pickup-ma-slot-store" style="display:none;" aria-hidden="true">
			<td width="15%" align="center" class="inv-col-num">
				<p><label id="number_sp1" style="display:none;">1</label></p>
				<p><label id="number_sp2" style="display:none;">2</label></p>
				<p><label id="number_sp3" style="display:none;">3</label></p>
				<p><label id="number_sp4" style="display:none;">4</label></p>
				<p><label id="number_sp5" style="display:none;">5</label></p>

			</td>
			<td class="pickup-ma-part-col">

				<?php
				$array = array();
				$array2 = array();
				$sqlSP = "SELECT * FROM product";
				$exeSP = db_exec($connect, $sqlSP) or die("Error Query [" . $sqlSP . "]");
				while ($resultSP = db_fetch_array($exeSP)) {
					array_push($array, $resultSP['pd_name']);
					array_push($array2, $resultSP['pd_id']);
				}
				$arrLength = count($array);
				?>
				<p><select class="inv-select pickup-ma-line-control custom-select" id="pd_sp1" name="pd_sp1" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" id="pd_sp2" name="pd_sp2" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" id="pd_sp3" name="pd_sp3" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" id="pd_sp4" name="pd_sp4" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" id="pd_sp5" name="pd_sp5" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>

				<p class="pickup-ma-line-stack-label" id="part_label_sp1" style="display:none;"><span>Part</span></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp1" id="sn_sp1" style="display:none;">
						<option value="">--เลือก part--</option>
						<?php

						$search = (int) $srotPro;

						if ($search != "") {

							$sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $search . "  ORDER BY part.[part_name] ASC";
							$exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");

							while ($result = db_fetch_array($exec)) {

								echo '<script>';
								echo 'document.getElementById("test").value = "' . $result['part_amount'] . '"';
								echo '</script>';

						?>
								<option value="<?php echo htmlspecialchars($result['part_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($result['part_name'], ENT_QUOTES, 'UTF-8'); ?></option>

							<?php } ?>
						<?php
						} else { ?>
							<option value="">--เลือก part--</option>
						<?php
						}
						?>

					</select></p>

				<p class="pickup-ma-line-stack-label" id="part_label_sp2" style="display:none;"><span>Part</span></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp2" id="sn_sp2" style="display:none;">
						<option value="">--เลือก part--</option>
						<?php

						$search = (int) $srotPro;

						if ($search != "") {

							$sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $search . "  ORDER BY part.[part_name] ASC";
							$exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");

							while ($result = db_fetch_array($exec)) {
						?>
								<option value="<?php echo htmlspecialchars($result['part_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($result['part_name'], ENT_QUOTES, 'UTF-8'); ?></option>

							<?php } ?>
						<?php
						} else { ?>
							<option value="">--เลือก part--</option>
						<?php
						}
						?>

					</select></p>

				<p class="pickup-ma-line-stack-label" id="part_label_sp3" style="display:none;"><span>Part</span></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp3" id="sn_sp3" style="display:none;">
						<option value="">--เลือก part--</option>
						<?php

						$search = (int) $srotPro;

						if ($search != "") {

							$sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $search . "  ORDER BY part.[part_name] ASC";
							$exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");

							while ($result = db_fetch_array($exec)) {
						?>
								<option value="<?php echo htmlspecialchars($result['part_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($result['part_name'], ENT_QUOTES, 'UTF-8'); ?></option>

							<?php } ?>
						<?php
						} else { ?>
							<option value="">--เลือก part--</option>
						<?php
						}
						?>

					</select></p>

				<p class="pickup-ma-line-stack-label" id="part_label_sp4" style="display:none;"><span>Part</span></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp4" id="sn_sp4" style="display:none;">
						<option value="">--เลือก part--</option>
						<?php

						$search = (int) $srotPro;

						if ($search != "") {

							$sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $search . "  ORDER BY part.[part_name] ASC";
							$exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");

							while ($result = db_fetch_array($exec)) {
						?>
								<option value="<?php echo htmlspecialchars($result['part_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($result['part_name'], ENT_QUOTES, 'UTF-8'); ?></option>

							<?php } ?>
						<?php
						} else { ?>
							<option value="">--เลือก part--</option>
						<?php
						}
						?>

					</select></p>

				<p class="pickup-ma-line-stack-label" id="part_label_sp5" style="display:none;"><span>Part</span></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp5" id="sn_sp5" style="display:none;">
						<option value="">--เลือก part--</option>
						<?php

						$search = (int) $srotPro;

						if ($search != "") {

							$sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $search . "  ORDER BY part.[part_name] ASC";
							$exec = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");

							while ($result = db_fetch_array($exec)) {
						?>
								<option value="<?php echo htmlspecialchars($result['part_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($result['part_name'], ENT_QUOTES, 'UTF-8'); ?></option>

							<?php } ?>
						<?php
						} else { ?>
							<option value="">--เลือก part--</option>
						<?php
						}
						?>

					</select></p>


				<!-- <p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp1" id="sn_sp1" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				
					<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp2" id="sn_sp2" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp3" id="sn_sp3" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp4" id="sn_sp4" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="sn_sp5" id="sn_sp5" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p> -->
			</td>
			<td class="inv-col-num pickup-ma-min-col">
				<p><span class="pickup-ma-min-val" id="min_sp1" style="display:none;">—</span></p>
				<p><span class="pickup-ma-min-val" id="min_sp2" style="display:none;">—</span></p>
				<p><span class="pickup-ma-min-val" id="min_sp3" style="display:none;">—</span></p>
				<p><span class="pickup-ma-min-val" id="min_sp4" style="display:none;">—</span></p>
				<p><span class="pickup-ma-min-val" id="min_sp5" style="display:none;">—</span></p>
			</td>
			<td class="inv-col-num pickup-ma-stock-col">

				<p><select class="inv-select pickup-ma-line-control custom-select" name="stock_sp1" id="stock_sp1" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="stock_sp2" id="stock_sp2" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="stock_sp3" id="stock_sp3" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="stock_sp4" id="stock_sp4" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="inv-select pickup-ma-line-control custom-select" name="stock_sp5" id="stock_sp5" style="display:none;">
						<option value="">none</option>
					</select></p>


				<!-- ---------------- -->

				<!-- <p>
					<label for="stock_sp1" id="stock_sp1" style="display:none;"></label>
				</p>
				<p>
					<label for="stock_sp2" id="stock_sp2" style="display:none;"></label>
				</p>
				<p>
					<label for="stock_sp3" id="stock_sp3" style="display:none;"></label>
				</p>
				<p>
					<label for="stock_sp4" id="stock_sp4" style="display:none;"></label>
				</p>
				<p>
					<label for="stock_sp5" id="stock_sp5" style="display:none;"></label>
				</p> -->

			</td>
			<td class="inv-col-num pickup-ma-qty-col">
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp1" id="amount_sp1" style="display:none;" onkeyup="chkStock()"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp2" id="amount_sp2" style="display:none;" onkeyup="chkStockRow(2)"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp3" id="amount_sp3" style="display:none;" onkeyup="chkStockRow(3)"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp4" id="amount_sp4" style="display:none;" onkeyup="chkStockRow(4)"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp5" id="amount_sp5" style="display:none;" onkeyup="chkStockRow(5)"></p>
			</td>
			<td class="pickup-ma-status-col">
				<p><span class="pickup-ma-line-status" id="status_sp1" style="display:none;">—</span></p>
				<p><span class="pickup-ma-line-status" id="status_sp2" style="display:none;">—</span></p>
				<p><span class="pickup-ma-line-status" id="status_sp3" style="display:none;">—</span></p>
				<p><span class="pickup-ma-line-status" id="status_sp4" style="display:none;">—</span></p>
				<p><span class="pickup-ma-line-status" id="status_sp5" style="display:none;">—</span></p>
			</td>
		</tr>
		</tbody>
		</table>
				</div>
				</div>
		</form>
				</div>
				<div class="inv-card__footer pickup-unified-footer" id="pickup_unified_footer" style="display:<?php echo $pickupShowLinesFooter ? '' : 'none'; ?>;">
					<input type="hidden" id="count_sp" name="count_sp" />
					<div class="pickup-ma-save-row">
						<?php inventory_render_recorder_field('log_by', 'log_by', 'ผู้บันทึก', 'pickup-ma-recorder-field'); ?>
						<button type="button" class="inv-btn inv-btn--primary" onclick="javascript:save_job();">Save</button>
					</div>
				</div>
				</div>
			</section>

		<?php

		if ($no != "") {

			$sub_no = substr($no, 0, 7);
			$repairDetail = pickup_ma_parse_repair_detail($detail);

			echo '<script>';
			echo 'document.getElementById("search_id").value = ' . json_encode($sub_no, JSON_UNESCAPED_UNICODE) . ';';
			echo 'pickupMaApplyRepairDetailFromText(' . json_encode($detail, JSON_UNESCAPED_UNICODE) . ');';
			echo 'if (!document.getElementById("pickup_ma_customer_com").value) {';
			echo 'document.getElementById("pickup_ma_customer_com").value = ' . json_encode($repairDetail['company'], JSON_UNESCAPED_UNICODE) . ';';
			echo '}';
			echo 'if (!document.getElementById("pickup_ma_product_sn").value) {';
			echo 'document.getElementById("pickup_ma_product_sn").value = ' . json_encode($repairDetail['sn'], JSON_UNESCAPED_UNICODE) . ';';
			echo '}';
			echo 'pickupMaSyncLogRemarks();';
			echo 'var chkRepair = document.getElementById("myCheck1");';
			echo 'if (chkRepair) { chkRepair.checked = true; }';
			echo 'myFunction();';
			echo '</script>';
		}

		echo '<script>';
		echo '$(function(){';
		echo '$("#pickup_set_bars").on("focus", ".pickup-set-picker__select", function(){ pickupSetPickerOnFocus(this); }).on("blur", ".pickup-set-picker__select", function(){ pickupSetPickerOnBlur(this); }).on("change", ".pickup-set-row-product, .pickup-set-row-amount", function(){ pickupSetBarSyncState(); pickupSyncSetSelectionSummary(); pickupLoadSetFormula(); });';
		echo 'pickupSetBarSyncState();';
		echo 'pickupSyncSetSelectionSummary();';
		echo 'pickupUpdateSetModeUi();';
		echo 'myFunction();';
		echo '$("#pickup_ma_modal_pd").change(loadPickupMaModalParts);';
		echo '$("#pickup_ma_modal_sn").change(loadPickupMaModalStock);';
		if ($pickupInitialMode === 'set') {
			echo 'pickupLoadSetFormula();';
		}
		echo 'pickupMaRenderAllDisplayRows();';
		echo '$("#pickup_ma_customer_com, #pickup_ma_product_sn").on("input change", pickupMaSyncLogRemarks);';
		echo 'pickupSyncUnifiedClearButton();';
		echo '});';
		echo '</script>';
		?>

<div id="pickup_ma_add_modal" class="pickup-modal pickup-modal--add-line" aria-hidden="true" role="dialog" aria-labelledby="pickup_ma_add_modal_title">
	<div class="pickup-modal__backdrop" onclick="closePickupMaAddModal();"></div>
	<div class="pickup-modal__panel pickup-add-line-panel">
		<button type="button" class="pickup-add-line-panel__close" aria-label="ปิด" onclick="closePickupMaAddModal(); return false;"><i class="fa fa-times"></i></button>
		<header class="pickup-add-line-panel__head">
			<span class="pickup-add-line-panel__icon" aria-hidden="true"><i class="fa fa-plus-circle"></i></span>
			<div>
				<h2 id="pickup_ma_add_modal_title" class="pickup-add-line-panel__title">เพิ่มรายการเบิก</h2>
				<p class="pickup-add-line-panel__desc">เลือก Product และ Part แล้วระบุจำนวนเบิก</p>
			</div>
		</header>
		<div class="pickup-add-line-panel__body">
			<div class="inv-field pickup-add-line-field">
				<label for="pickup_ma_modal_pd">Product</label>
				<select class="inv-select" id="pickup_ma_modal_pd" name="pickup_ma_modal_pd">
					<option value="">-- เลือก Product --</option>
					<?php for ($pickupModalPi = 0; $pickupModalPi < $pickupProductCount; $pickupModalPi++) {
						if (empty($pickupProductHasProductionSet[$pickupModalPi])) {
							continue;
						}
						$pickupModalPid = (string) $pickupProductIds[$pickupModalPi];
						?>
					<option value="<?php echo htmlspecialchars($pickupModalPid, ENT_QUOTES, 'UTF-8'); ?>" data-repair-set="<?php echo !empty($pickupProductHasRepairSet[$pickupModalPi]) ? '1' : '0'; ?>" data-production-set="1"><?php echo htmlspecialchars($pickupProductNames[$pickupModalPi], ENT_QUOTES, 'UTF-8'); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="inv-field pickup-add-line-field">
				<label for="pickup_ma_modal_sn">Part</label>
				<select class="inv-select" id="pickup_ma_modal_sn" name="pickup_ma_modal_sn">
					<option value="">-- เลือก part --</option>
				</select>
			</div>
			<div class="pickup-add-line-stats" aria-live="polite">
				<div class="pickup-add-line-stat">
					<span class="pickup-add-line-stat__label">ขั้นต่ำสั่ง</span>
					<span class="pickup-add-line-stat__value" id="pickup_ma_modal_min">—</span>
				</div>
				<div class="pickup-add-line-stat">
					<span class="pickup-add-line-stat__label">Stock</span>
					<span class="pickup-add-line-stat__value pickup-add-line-stat__value--stock" id="pickup_ma_modal_stock">—</span>
				</div>
			</div>
			<div class="inv-field pickup-add-line-field pickup-add-line-field--qty">
				<label for="pickup_ma_modal_amount">จำนวนเบิก</label>
				<div class="pickup-qty-stepper">
					<button type="button" class="pickup-qty-stepper__btn" aria-label="ลดจำนวน" onclick="pickupMaModalQtyStep(-1); return false;">−</button>
					<input class="inv-input pickup-qty-stepper__input" type="number" id="pickup_ma_modal_amount" name="pickup_ma_modal_amount" min="1" step="1" inputmode="numeric" value="1" />
					<button type="button" class="pickup-qty-stepper__btn" aria-label="เพิ่มจำนวน" onclick="pickupMaModalQtyStep(1); return false;">+</button>
				</div>
			</div>
		</div>
		<footer class="pickup-add-line-panel__foot">
			<button type="button" class="inv-btn inv-btn--ghost" onclick="closePickupMaAddModal();">ยกเลิก</button>
			<button type="button" class="inv-btn inv-btn--primary" onclick="submitPickupMaAddModal();"><i class="fa fa-check"></i> เพิ่มรายการ</button>
		</footer>
	</div>
</div>

<div id="pickup_set_add_modal" class="pickup-modal pickup-modal--add-line" aria-hidden="true" role="dialog" aria-labelledby="pickup_set_modal_title">
	<div class="pickup-modal__backdrop" onclick="closePickupSetAddModal();"></div>
	<div class="pickup-modal__panel pickup-add-line-panel">
		<button type="button" class="pickup-add-line-panel__close" aria-label="ปิด" onclick="closePickupSetAddModal(); return false;"><i class="fa fa-times"></i></button>
		<header class="pickup-add-line-panel__head">
			<span class="pickup-add-line-panel__icon" aria-hidden="true"><i class="fa fa-cubes"></i></span>
			<div>
				<h2 id="pickup_set_modal_title" class="pickup-add-line-panel__title">โหลดชุดเบิก</h2>
				<p id="pickup_set_modal_desc" class="pickup-add-line-panel__desc">เลือก Product และจำนวนชุด</p>
			</div>
		</header>
		<div class="pickup-add-line-panel__body">
			<div class="inv-field pickup-add-line-field">
				<label for="pickup_set_modal_product" id="pickup_set_modal_product_label">Product</label>
				<select class="inv-select" id="pickup_set_modal_product" name="pickup_set_modal_product">
					<option value="">-- เลือก Product --</option>
					<?php for ($pickupSetModalPi = 0; $pickupSetModalPi < $pickupProductCount; $pickupSetModalPi++) {
						$pickupSetModalPid = (string) $pickupProductIds[$pickupSetModalPi];
						$pickupSetModalHasRepair = !empty($pickupProductHasRepairSet[$pickupSetModalPi]);
						$pickupSetModalHasProduction = !empty($pickupProductHasProductionSet[$pickupSetModalPi]);
						if (!$pickupSetModalHasRepair && !$pickupSetModalHasProduction) {
							continue;
						}
						?>
					<option value="<?php echo htmlspecialchars($pickupSetModalPid, ENT_QUOTES, 'UTF-8'); ?>" data-repair-set="<?php echo $pickupSetModalHasRepair ? '1' : '0'; ?>" data-production-set="<?php echo $pickupSetModalHasProduction ? '1' : '0'; ?>"><?php echo htmlspecialchars($pickupProductNames[$pickupSetModalPi], ENT_QUOTES, 'UTF-8'); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="inv-field pickup-add-line-field pickup-add-line-field--qty">
				<label for="pickup_set_modal_amount">จำนวนชุด</label>
				<input class="inv-input" type="number" id="pickup_set_modal_amount" name="pickup_set_modal_amount" min="1" step="1" inputmode="numeric" value="1" />
			</div>
		</div>
		<footer class="pickup-add-line-panel__foot">
			<button type="button" class="inv-btn inv-btn--ghost" onclick="closePickupSetAddModal();">ยกเลิก</button>
			<button type="button" class="inv-btn inv-btn--primary" onclick="submitPickupSetAddModal();"><i class="fa fa-check"></i> โหลดรายการ</button>
		</footer>
	</div>
</div>

</main>
</body>

</html>
