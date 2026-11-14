<?php
/**
 * หน้าเบิกแยกรายการ (Pickup MA)
 *
 * ฟอร์มเบิกอะไหล่ทีละรายการ บันทึกผ่าน pickup_ma_save.php
 * ใช้ธีม inv-app / layout H5 (แถบวัตถุประสงค์แบบ pill + ฟิลด์หัวเอกสารแถวเดียว, การ์ดรายการแยก)
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
	<?php
	if (!defined('INVENTORY_UI_CSS')) {
		define('INVENTORY_UI_CSS', true);
	}
	?>
	<link rel="stylesheet" href="css/inventory-ui.css">
	<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">

	<title>เบิก Stock</title>
	<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>
	<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
	<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
	<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

	<script type="text/javascript">
		function save_job() {
			// document.getElementById("form1").submit();

			if (document.getElementById("log_by").value == "") {
				alert("กรุณากรอกชื่อผู้บันทึกให้ครบถ้วน");
				return;
			}

			var count = document.getElementById("count_sp").value;
			if (count == 0) {
				alert("กรุณากด Add เพิ่มรายการก่อน " + count);
				return;
			}

			var type = "";

			if (document.getElementById('myCheck1').checked) {
				type = document.getElementById('myCheck1').value;
			} else if (document.getElementById('myCheck2').checked) {
				type = document.getElementById('myCheck2').value;
			} else if (document.getElementById('myCheck3').checked) {
				type = document.getElementById('myCheck3').value;
			} else if (document.getElementById('myCheck4').checked) {
				type = document.getElementById('myCheck4').value;
			} else if (document.getElementById('myCheck5').checked) {
				type = document.getElementById('myCheck5').value;
			} else if (document.getElementById('myCheck6').checked) {
				type = document.getElementById('myCheck6').value;
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

		function chkStock() {

			var pick = parseInt(document.getElementById("amount_sp1").value);

			var stock = parseInt(document.getElementById("stock_sp1").value);

			if (pick > stock && stock != null) {
				alert('ใส่ยอดเบิกเกินจำนวน stock ที่มี ');
				document.getElementById('amount_sp1').value = '';
			}

		}

		//-------------------------------------------------------------------------------------------------

		var r_spare = 0;

		function addSpare(mode) { //Add 


			// document.getElementById("stock_sp1").innerText = "100"

			if (r_spare >= 5 && mode == "add") {
				alert("เพิ่มรายการเบิกได้สูงสุด 5 รายการ");
				return;
			}

			//$('#log_type').val("");

			if (mode == "add") {

				if (r_spare >= 1 && (document.getElementById("pd_sp" + r_spare).value == "" ||
						document.getElementById("sn_sp" + r_spare).value == "" ||
						document.getElementById("amount_sp" + r_spare).value == "")) {
					alert("กรุณากรอกข้อมูลรายการก่อนหน้าให้ครบถ้วน");
					return;
				}

				if (r_spare >= 5) {
					r_spare = 5;
				}

				r_spare += 1;
				document.getElementById("number_sp" + r_spare).style.display = '';
				document.getElementById("pd_sp" + r_spare).style.display = '';
				document.getElementById("pd_sp" + r_spare).value = "";
				document.getElementById("sn_sp" + r_spare).style.display = '';
				document.getElementById("sn_sp" + r_spare).value = "";
				document.getElementById("amount_sp" + r_spare).style.display = '';
				document.getElementById("amount_sp" + r_spare).value = "";
				document.getElementById("stock_sp" + r_spare).style.display = '';
				document.getElementById("stock_sp" + r_spare).value = "";

				$('#count_sp').val(r_spare);


			} else {
				if (r_spare <= 0) {
					r_spare = 0;
					return;
				}
				//number_sp1
				document.getElementById("number_sp" + r_spare).style.display = 'none';
				//pd_sp1
				document.getElementById("pd_sp" + r_spare).style.display = 'none';
				document.getElementById("pd_sp" + r_spare).value = "";
				//sn_sp1
				document.getElementById("sn_sp" + r_spare).style.display = 'none';
				document.getElementById("sn_sp" + r_spare).value = 0;

				//amount_sp1
				document.getElementById("amount_sp" + r_spare).style.display = 'none';
				document.getElementById("amount_sp" + r_spare).value = "";

				//amount_sp1 แสดงจำนวน stock
				document.getElementById("stock_sp" + r_spare).style.display = 'none';
				document.getElementById("stock_sp" + r_spare).value = "";


				if (r_spare >= 1) {
					r_spare -= 1;
				}

				//count row
				$('#count_sp').val(r_spare);
			}
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

	.pickup-ma-purpose-segmented {
		display: flex;
		flex-wrap: wrap;
		gap: 0.45rem 0.5rem;
	}

	.pickup-ma-segment {
		align-items: center;
		background: #fff;
		border: 1px solid var(--inv-border);
		border-radius: 6px;
		color: var(--inv-text);
		cursor: pointer;
		display: inline-flex;
		font-size: 0.82rem;
		gap: 0.35rem;
		line-height: 1.25;
		padding: 0.4rem 0.7rem;
		transition: background 0.15s, border-color 0.15s, color 0.15s;
	}

	.pickup-ma-segment input {
		height: 0;
		opacity: 0;
		position: absolute;
		width: 0;
	}

	.pickup-ma-segment:has(input:checked) {
		background: var(--inv-primary);
		border-color: var(--inv-primary-dark);
		color: #fff;
		font-weight: 600;
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

<body class="inv-app pickup-ma">
<?php require __DIR__ . '/title-h.php'; ?>
<main class="inv-main">
		<header class="inv-page-header">
			<h1 class="inv-page-title">เบิกแยกรายการ</h1>
			<p class="inv-page-desc">บันทึกการเบิกอะไหล่ทีละรายการ — กรอกหัวเอกสารแล้วเพิ่มรายการด้านล่าง</p>
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

		?>

		<section class="inv-card pickup-ma-doc-card pickup-ma-doc-card--h5">
				<div class="inv-card__head"><i class="fa fa-file-text-o"></i> หัวเอกสาร</div>
				<div class="inv-card__subhead pickup-ma-purpose-subhead">
					<p class="pickup-ma-purpose-label pickup-ma-purpose-label--subhead">วัตถุประสงค์</p>
					<div class="pickup-ma-purpose-segmented" id="checkForm" role="radiogroup" aria-label="วัตถุประสงค์การเบิก">
						<label class="pickup-ma-segment pickup-ma-segment--pill"><input type="radio" name="myCheck" id="myCheck1" form="form_add_stock" onclick="myFunction()" value="เบิกซ่อมลูกค้า" checked /><span>เบิกซ่อมลูกค้า</span></label>
						<label class="pickup-ma-segment pickup-ma-segment--pill"><input type="radio" name="myCheck" id="myCheck2" form="form_add_stock" onclick="myFunction()" value="เบิกเสียระหว่างผลิต" /><span>เบิกเสียระหว่างผลิต</span></label>
						<label class="pickup-ma-segment pickup-ma-segment--pill"><input type="radio" name="myCheck" id="myCheck6" form="form_add_stock" onclick="myFunction()" value="เบิกเสียระหว่างขนส่ง" /><span>เบิกเสียระหว่างขนส่ง</span></label>
						<label class="pickup-ma-segment pickup-ma-segment--pill"><input type="radio" name="myCheck" id="myCheck3" form="form_add_stock" onclick="myFunction()" value="เบิกผลิต" /><span>เบิกผลิต</span></label>
						<label class="pickup-ma-segment pickup-ma-segment--pill"><input type="radio" name="myCheck" id="myCheck4" form="form_add_stock" onclick="myFunction()" value="เบิกตาม PO" /><span>เบิกตาม PO</span></label>
						<label class="pickup-ma-segment pickup-ma-segment--pill"><input type="radio" name="myCheck" id="myCheck5" form="form_add_stock" onclick="myFunction()" value="เบิกอื่นๆ" /><span>เบิกอื่นๆ</span></label>
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

			<section class="inv-card">
				<div class="inv-card__head">
					<div class="pickup-ma-line-head">
						<span><i class="fa fa-list"></i> รายการเบิก</span>
						<span>
							<a href="javascript:addSpare('del');" class="pickup-ma-line-del"><i class="fa fa-minus-circle"></i> Del</a>
							&nbsp;
							<a href="javascript:addSpare('add');"><i class="fa fa-plus-circle"></i> Add</a>
						</span>
					</div>
				</div>
				<div class="inv-card__body inv-card__body--flush">
		<form id="form_add_stock" name="form_add_stock" method="post" action="save_stock">
				<div class="inv-table-wrap">
				<table id="table1" class="inv-table pickup-ma-lines-table" width="100%">
		<thead>
		<tr>
		<th class="inv-col-num">ลำดับ</th>
		<th>Product</th>
		<th>Part</th>
		<th class="inv-col-num">จำนวนเบิก</th>
		<th class="inv-col-num">จำนวน Stock</th>
		</tr>
		</thead>
		<tbody>
		<tr class="pickup-ma-lines-row">
			<td width="15%" align="center" class="inv-col-num">
				<p><label id="number_sp1" style="display:none;">1</label></p>
				<p><label id="number_sp2" style="display:none;">2</label></p>
				<p><label id="number_sp3" style="display:none;">3</label></p>
				<p><label id="number_sp4" style="display:none;">4</label></p>
				<p><label id="number_sp5" style="display:none;">5</label></p>

			</td>
			<td width="25%">

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

			</td>
			<td width="25%" align="center">

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
			<td width="25%" align="center" class="number">
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp1" id="amount_sp1" style="display:none;" onkeyup="chkStock()"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp2" id="amount_sp2" style="display:none;" onkeyup="chkStock()"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp3" id="amount_sp3" style="display:none;"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp4" id="amount_sp4" style="display:none;"></p>
				<p><input class="inv-input pickup-ma-line-control pickup-ma-line-qty" type="number" name="amount_sp5" id="amount_sp5" style="display:none;"></p>
			</td>
			</td>
			<td width="25%" align="center" colspan="2">

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
			</td>

			<!--   <td width="25%" align="center">
	<p><div name="stock_min_sp1" id="stock_min_sp1" style="display:none;"></p>
	<p><div name="stock_min_sp2" id="stock_min_sp2" style="display:none;"></p>
	<p><div name="stock_min_sp3" id="stock_min_sp3" style="display:none;"></p>
	<p><div name="stock_min_sp4" id="stock_min_sp4" style="display:none;"></p>
	<p><div name="stock_min_sp5" id="stock_min_sp5" style="display:none;"></p>  -->
			</td>
		</tr>
		</tbody>
		</table>
				</div>
				</div>
				<div class="inv-card__footer">
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
		</form>

		<?php

		if ($no != "") {

			$sub_no = substr($no, 0, 7);

			echo '<script>';
			echo 'document.getElementById("search_id").value = ' . json_encode($sub_no, JSON_UNESCAPED_UNICODE) . ';';
			echo 'document.getElementById("log_remarks").value = ' . json_encode($detail, JSON_UNESCAPED_UNICODE) . ';';
			echo 'addSpare("add")';
			echo '</script>';
		}
		?>

</main>
</body>

</html>
