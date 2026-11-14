<?php
/**
 * หน้าเบิกของรวม (U1) — เบิกเป็นเซต + เบิกแยกรายการ
 *
 * สลับโหมดที่หัวการ์ดรายการเบิก โหมดเซตโหลดสูตรจาก pickup_set_formula.php แล้วบันทึก pre_pickup_save.php
 * โหมดแยกรายการบันทึกผ่าน pickup_ma_save.php — ธีม inv-app / layout H5
 */
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';
header("Content-Type: text/html; charset=UTF-8");
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');


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
			return el ? el.value : "ma";
		}

		function pickupGetPurposeType() {
			var ids = ["myCheck1", "myCheck2", "myCheck6", "myCheck3", "myCheck4", "myCheck5"];
			for (var i = 0; i < ids.length; i++) {
				var node = document.getElementById(ids[i]);
				if (node && node.checked) {
					return node.value;
				}
			}
			return "";
		}

		function pickupUnifiedHasLineData() {
			if (pickupGetWorkMode() === "set") {
				return document.getElementById("pickup_set_has_rows").value === "1";
			}
			return parseInt(document.getElementById("count_sp").value || "0", 10) > 0;
		}

		function pickupUnifiedClearMaLines() {
			while (parseInt(document.getElementById("count_sp").value || "0", 10) > 0) {
				addSpare("del");
			}
		}

		function pickupUnifiedClearSetLines() {
			document.getElementById("pickup_set_tbody").innerHTML =
				'<tr><td colspan="6" class="pickup-set-empty">เลือก Product และจำนวนชุดเพื่อโหลดสูตร</td></tr>';
			document.getElementById("pickup_set_has_rows").value = "0";
			var preProduct = document.getElementById("pre_product");
			var preAmount = document.getElementById("pre_amount");
			if (preProduct) {
				preProduct.value = "";
			}
			if (preAmount) {
				preAmount.value = "";
			}
		}

		function pickupSetPickerActivate(ev, selectId) {
			var sel = document.getElementById(selectId);
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
			var fields = [
				{ id: "pre_product", wrap: "pre_product_wrap" },
				{ id: "pre_amount", wrap: "pre_amount_wrap" }
			];
			for (var f = 0; f < fields.length; f++) {
				var sel = document.getElementById(fields[f].id);
				var wrap = document.getElementById(fields[f].wrap);
				if (!sel || !wrap) {
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
			if (pickupGetWorkMode() !== "set") {
				return;
			}
			var preProduct = document.getElementById("pre_product");
			var preAmount = document.getElementById("pre_amount");
			if (!preProduct || !preAmount || preProduct.value === "" || preAmount.value === "") {
				document.getElementById("pickup_set_tbody").innerHTML =
					'<tr><td colspan="6" class="pickup-set-empty">เลือก Product และจำนวนชุดเพื่อโหลดสูตร</td></tr>';
				document.getElementById("pickup_set_has_rows").value = "0";
				return;
			}
			$.get(
				"pickup_set_formula.php",
				{ pre_product: preProduct.value, pre_amount: preAmount.value },
				function(html) {
					document.getElementById("pickup_set_tbody").innerHTML = html;
					var hasRows = html.indexOf("pickup-set-empty") === -1 && $.trim(html) !== "";
					document.getElementById("pickup_set_has_rows").value = hasRows ? "1" : "0";
				}
			);
		}

		function pickupUnifiedApplyMode(mode) {
			document.getElementById("pickup_work_mode").value = mode;
			document.getElementById("pickup_body_set").style.display = mode === "set" ? "" : "none";
			document.getElementById("pickup_body_ma").style.display = mode === "ma" ? "" : "none";
			var tools = document.getElementById("pickup_ma_tools");
			if (tools) {
				tools.style.display = mode === "ma" ? "" : "none";
			}
			var buttons = document.getElementById("pickup_mode_switch").getElementsByTagName("button");
			for (var b = 0; b < buttons.length; b++) {
				var btnMode = buttons[b].getAttribute("data-mode");
				var isActive = btnMode === mode;
				buttons[b].classList.toggle("is-active", isActive);
				buttons[b].classList.toggle("inv-btn--primary", isActive);
				buttons[b].classList.toggle("inv-btn--ghost", !isActive);
			}
			var footer = document.getElementById("pickup_unified_footer");
			if (footer) {
				footer.style.display = "";
			}
			if (mode === "set") {
				pickupSetBarSyncState();
				pickupLoadSetFormula();
			}
		}

		function pickupUnifiedSwitchMode(mode) {
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
			} else {
				pickupUnifiedClearSetLines();
			}
			pickupUnifiedApplyMode(mode);
		}

		function save_pickup_set() {
			var preProduct = document.getElementById("pre_product");
			var preAmount = document.getElementById("pre_amount");
			if (!preProduct || preProduct.value === "" || !preAmount || preAmount.value === "") {
				alert("กรุณาเลือก Product และจำนวนชุด");
				return;
			}
			if (document.getElementById("pickup_set_has_rows").value !== "1") {
				alert("ไม่พบรายการจากสูตร — ตรวจสอบ Product ที่เลือก");
				return;
			}
			var preType = pickupGetPurposeType();
			if (preType === "") {
				alert("กรุณาเลือกวัตถุประสงค์");
				return;
			}
			var dataUpdate =
				"pre_date=" + encodeURIComponent(document.getElementById("log_date").value) +
				"&pre_product=" + encodeURIComponent(preProduct.value) +
				"&pre_type=" + encodeURIComponent(preType) +
				"&pre_amount=" + encodeURIComponent(preAmount.value) +
				"&pre_by=" + encodeURIComponent(document.getElementById("log_by").value) +
				"&pre_remarks=" + encodeURIComponent(document.getElementById("log_remarks").value);
			window.location = "pre_pickup_save.php?" + dataUpdate;
		}

		function save_job() {
			if (document.getElementById("log_by").value == "") {
				alert("กรุณากรอกชื่อผู้บันทึกให้ครบถ้วน");
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
			var docRow = document.querySelector(".pickup-ma-doc-fields--h4");
			if (!jobWrap) {
				return;
			}

			if (document.getElementById('myCheck1').checked) {
				rate_value = document.getElementById('myCheck1').value;
				jobWrap.style.display = "";
				if (docRow) {
					docRow.classList.remove("pickup-ma-doc-fields--no-job");
				}

			} else if (document.getElementById('myCheck2').checked) {
				rate_value = document.getElementById('myCheck2').value;
				jobWrap.style.display = "none";
				if (docRow) {
					docRow.classList.add("pickup-ma-doc-fields--no-job");
				}

			} else if (document.getElementById('myCheck3').checked) {
				rate_value = document.getElementById('myCheck3').value;
				jobWrap.style.display = "none";
				if (docRow) {
					docRow.classList.add("pickup-ma-doc-fields--no-job");
				}
			} else if (document.getElementById('myCheck4').checked) {
				rate_value = document.getElementById('myCheck4').value;
				jobWrap.style.display = "none";
				if (docRow) {
					docRow.classList.add("pickup-ma-doc-fields--no-job");
				}
			} else if (document.getElementById('myCheck5').checked) {
				rate_value = document.getElementById('myCheck5').value;
				jobWrap.style.display = "none";
				if (docRow) {
					docRow.classList.add("pickup-ma-doc-fields--no-job");
				}
			} else if (document.getElementById('myCheck6').checked) {
				rate_value = document.getElementById('myCheck6').value;
				jobWrap.style.display = "none";
				if (docRow) {
					docRow.classList.add("pickup-ma-doc-fields--no-job");
				}
			}
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
			var delBtn = document.getElementById("pickup_ma_btn_del");
			if (!delBtn) {
				return;
			}
			delBtn.style.display = r_spare > 0 ? "" : "none";
			delBtn.disabled = r_spare <= 0;
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
					'<td><span class="' + statusClass + '">' + statusText.replace(/</g, "&lt;") + "</span></td>";
				if (emptyHint && emptyHint.parentNode === tbody) {
					tbody.insertBefore(tr, emptyHint);
				} else {
					tbody.appendChild(tr);
				}
			}
			pickupMaSyncToolbarState();
		}

		function openPickupMaAddModal() {
			var modal = document.getElementById("pickup_ma_add_modal");
			if (!modal) {
				return;
			}
			if (r_spare >= 5) {
				alert("เพิ่มรายการเบิกได้สูงสุด 5 รายการ");
				return;
			}
			resetPickupMaAddModal();
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
			if (r_spare >= 5) {
				alert("เพิ่มรายการเบิกได้สูงสุด 5 รายการ");
				return;
			}
			var pick = parseFloat(amount);
			var stock = parseFloat(document.getElementById("pickup_ma_modal_stock").textContent);
			if (!isNaN(stock) && pick > stock) {
				alert("ใส่ยอดเบิกเกินจำนวน stock ที่มี");
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

		function pickupMaRemoveLastLine() {
			addSpare("del");
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
		justify-content: flex-end;
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

		$pickupInitialMode = (isset($_GET['mode']) && $_GET['mode'] === 'set') ? 'set' : 'ma';
		$pickupProductIds = array();
		$pickupProductNames = array();
		$sqlPickupProducts = 'SELECT pd_id, pd_name FROM product ORDER BY pd_name ASC';
		$exePickupProducts = db_exec($connect, $sqlPickupProducts);
		if ($exePickupProducts) {
			while ($rowPickupProduct = db_fetch_array($exePickupProducts)) {
				$pickupProductIds[] = $rowPickupProduct['pd_id'];
				$pickupProductNames[] = $rowPickupProduct['pd_name'];
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
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck1" form="form_add_stock" onclick="myFunction()" value="เบิกซ่อมลูกค้า" checked /><span>เบิกซ่อมลูกค้า</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck2" form="form_add_stock" onclick="myFunction()" value="เบิกเสียระหว่างผลิต" /><span>เบิกเสียระหว่างผลิต</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck6" form="form_add_stock" onclick="myFunction()" value="เบิกเสียระหว่างขนส่ง" /><span>เบิกเสียระหว่างขนส่ง</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck3" form="form_add_stock" onclick="myFunction()" value="เบิกผลิต" /><span>เบิกผลิต</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck4" form="form_add_stock" onclick="myFunction()" value="เบิกตาม PO" /><span>เบิกตาม PO</span></label>
						<label class="inv-btn inv-btn--ghost pickup-ma-purpose-btn"><input type="radio" name="myCheck" id="myCheck5" form="form_add_stock" onclick="myFunction()" value="เบิกอื่นๆ" /><span>เบิกอื่นๆ</span></label>
					</div>
				</div>
				<div class="inv-card__body pickup-ma-doc-card__body">
					<div class="pickup-ma-doc-fields pickup-ma-doc-fields--h4">
						<div class="inv-field">
							<label for="log_date">วันที่เบิก</label>
							<input class="inv-input pickup-ma-doc-input" type="date" name="log_date" id="log_date" form="form_add_stock" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" />
						</div>
						<div class="inv-field" id="pickup_ma_job_wrap">
							<label for="search_id" id="lb_id">เลขที่ใบงาน</label>
							<input class="inv-input pickup-ma-doc-input" type="text" name="search_id" id="search_id" form="form_add_stock" />
						</div>
						<div class="inv-field pickup-ma-remarks-field">
							<label for="log_remarks">หมายเหตุ</label>
							<input class="inv-input pickup-ma-doc-input" type="text" name="log_remarks" id="log_remarks" form="form_add_stock" maxlength="5000" autocomplete="off" />
						</div>
					</div>
				</div>
		</section>

			<section class="inv-card pickup-unified-lines-card">
				<input type="hidden" id="pickup_work_mode" value="<?php echo htmlspecialchars($pickupInitialMode, ENT_QUOTES, 'UTF-8'); ?>" />
				<input type="hidden" id="pickup_set_has_rows" value="0" />
				<div class="inv-card__head pickup-unified-lines-head">
					<div class="pickup-unified-lines-head__title">
						<i class="fa fa-list"></i> รายการเบิก
					</div>
					<div class="pickup-unified-mode pickup-unified-mode--invbtn" id="pickup_mode_switch" role="group" aria-label="โหมดการเบิก">
						<button type="button" data-mode="set" class="inv-btn pickup-unified-mode-btn<?php echo $pickupInitialMode === 'set' ? ' inv-btn--primary is-active' : ' inv-btn--ghost'; ?>" onclick="pickupUnifiedSwitchMode('set'); return false;"><i class="fa fa-cubes"></i> เบิกเป็นเซต</button>
						<button type="button" data-mode="ma" class="inv-btn pickup-unified-mode-btn<?php echo $pickupInitialMode === 'ma' ? ' inv-btn--primary is-active' : ' inv-btn--ghost'; ?>" onclick="pickupUnifiedSwitchMode('ma'); return false;"><i class="fa fa-list-ul"></i> เบิกแยกรายการ</button>
					</div>
				</div>
				<div id="pickup_body_set" class="inv-card__body pickup-unified-body" style="display:<?php echo $pickupInitialMode === 'set' ? 'block' : 'none'; ?>;">
					<div class="pickup-unified-set-bar">
						<div class="pickup-set-picker pickup-set-picker--product">
							<span class="pickup-set-picker__label" id="pre_product_label">Product</span>
							<div class="inv-btn inv-btn--ghost pickup-set-picker__btn-wrap<?php echo ($srotPro !== '') ? ' is-filled inv-btn--primary' : ''; ?>" id="pre_product_wrap" role="button" tabindex="0" title="เลือก Product" onclick="pickupSetPickerActivate(event, 'pre_product');" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();pickupSetPickerActivate(event,'pre_product');}">
								<select class="pickup-set-picker__select" id="pre_product" name="pre_product" aria-labelledby="pre_product_label">
									<option value="">เลือก Product</option>
									<?php for ($pickupPi = 0; $pickupPi < $pickupProductCount; $pickupPi++) {
										$pickupPid = (string) $pickupProductIds[$pickupPi];
										$pickupSelected = ($srotPro !== '' && (string) $srotPro === $pickupPid) ? ' selected="selected"' : '';
										?>
									<option value="<?php echo htmlspecialchars($pickupPid, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $pickupSelected; ?>><?php echo htmlspecialchars($pickupProductNames[$pickupPi], ENT_QUOTES, 'UTF-8'); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
						<div class="pickup-set-picker pickup-set-picker--qty">
							<span class="pickup-set-picker__label" id="pre_amount_label">จำนวนชุด</span>
							<div class="inv-btn inv-btn--ghost pickup-set-picker__btn-wrap" id="pre_amount_wrap" role="button" tabindex="0" title="เลือกจำนวนชุด" onclick="pickupSetPickerActivate(event, 'pre_amount');" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();pickupSetPickerActivate(event,'pre_amount');}">
								<select class="pickup-set-picker__select" id="pre_amount" name="pre_amount" aria-labelledby="pre_amount_label">
									<option value="">เลือกจำนวน</option>
									<?php foreach ($pickupSetAmountOptions as $pickupAmt) { ?>
									<option value="<?php echo (int) $pickupAmt; ?>"><?php echo (int) $pickupAmt; ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
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
								</tr>
							</thead>
							<tbody id="pickup_set_tbody">
								<tr><td colspan="6" class="pickup-set-empty">เลือก Product และจำนวนชุดเพื่อโหลดสูตร</td></tr>
							</tbody>
						</table>
					</div>
					</div>
				</div>
				<div id="pickup_body_ma" class="inv-card__body pickup-unified-body" style="display:<?php echo $pickupInitialMode === 'ma' ? 'block' : 'none'; ?>;">
					<div class="pickup-ma-line-tools" id="pickup_ma_tools">
						<div class="pickup-ma-line-tools__actions">
							<button type="button" class="inv-btn inv-btn--primary" onclick="openPickupMaAddModal(); return false;"><i class="fa fa-plus"></i> เพิ่มรายการ</button>
							<button type="button" class="inv-btn inv-btn--ghost" id="pickup_ma_btn_del" style="display:none;" onclick="pickupMaRemoveLastLine(); return false;"><i class="fa fa-minus"></i> ลบรายการล่าสุด</button>
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
		</tr>
		</thead>
		<tbody id="pickup_ma_lines_display">
		<tr id="pickup_ma_empty_hint" class="pickup-ma-empty-row">
			<td colspan="6" class="pickup-set-empty">ยังไม่มีรายการ — กด「เพิ่มรายการ」เพื่อเลือกอะไหล่</td>
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
				<div class="inv-card__footer pickup-unified-footer" id="pickup_unified_footer">
					<input type="hidden" id="count_sp" name="count_sp" />
					<div class="pickup-ma-save-row">
						<div class="inv-field">
							<label for="log_by">ผู้บันทึก</label>
							<select class="inv-select custom-select" name="log_by" id="log_by" required>
								<option value="">-- Select --</option>
								<option value="Ken">Ken</option>
								<option value="Don">Don</option>
								<option value="ICE">ICE</option>
								<option value="TOM">TOM</option>
								<option value="NOI">NOI</option>
								<option value="NI">NI</option>
								<option value="MOOK">MOOK</option>
								<option value="AO">AO</option>
								<option value="MAX">Max</option>
								<option value="DAY">DAY</option>
								<option value="NUT">NUT</option>
							</select>
						</div>
						<button type="button" class="inv-btn inv-btn--primary" onclick="javascript:save_job();">Save</button>
					</div>
				</div>
			</section>

		<?php

		if ($no != "") {

			$sub_no = substr($no, 0, 7);

			echo '<script>';
			echo 'document.getElementById("search_id").value = ' . json_encode($sub_no, JSON_UNESCAPED_UNICODE) . ';';
			echo 'document.getElementById("log_remarks").value = ' . json_encode($detail, JSON_UNESCAPED_UNICODE) . ';';
			echo 'pickupUnifiedApplyMode("ma");';
			echo 'openPickupMaAddModal();';
			echo '</script>';
		}

		echo '<script>';
		echo '$(function(){';
		echo '$("#pre_product, #pre_amount").on("focus", function(){ pickupSetPickerOnFocus(this); }).on("blur", function(){ pickupSetPickerOnBlur(this); }).change(function(){ pickupSetBarSyncState(); pickupLoadSetFormula(); });';
		echo 'pickupSetBarSyncState();';
		echo '$("#pickup_ma_modal_pd").change(loadPickupMaModalParts);';
		echo '$("#pickup_ma_modal_sn").change(loadPickupMaModalStock);';
		if ($pickupInitialMode === 'set') {
			echo 'pickupLoadSetFormula();';
		}
		echo 'pickupMaRenderAllDisplayRows();';
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
					<?php for ($pickupModalPi = 0; $pickupModalPi < $pickupProductCount; $pickupModalPi++) { ?>
					<option value="<?php echo htmlspecialchars((string) $pickupProductIds[$pickupModalPi], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pickupProductNames[$pickupModalPi], ENT_QUOTES, 'UTF-8'); ?></option>
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

</main>
</body>

</html>
