<?php
/**
 * หน้าเบิกแยกรายการ (Pickup MA)
 *
 * ฟอร์มเบิกอะไหล่ทีละรายการ บันทึกผ่าน pickup_ma_save.php
 * ใช้ธีม inv-app / ฟอนต์ Sarabun จาก inventory-ui.css ให้สอดคล้อง stock_overview
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

			var text = document.getElementById("lb_id");
			var searchid = document.getElementById("search_id");

			if (document.getElementById('myCheck1').checked) {
				rate_value = document.getElementById('myCheck1').value;
				text.style.display = "block";
				searchid.style.display = "block";

			} else if (document.getElementById('myCheck2').checked) {
				rate_value = document.getElementById('myCheck2').value;
				text.style.display = "none";
				searchid.style.display = "none";

			} else if (document.getElementById('myCheck3').checked) {
				rate_value = document.getElementById('myCheck3').value;
				text.style.display = "none";
				searchid.style.display = "none";
			} else if (document.getElementById('myCheck4').checked) {
				rate_value = document.getElementById('myCheck4').value;
				text.style.display = "none";
				searchid.style.display = "none";
			} else if (document.getElementById('myCheck5').checked) {
				rate_value = document.getElementById('myCheck5').value;
				text.style.display = "none";
				searchid.style.display = "none";
			} else if (document.getElementById('myCheck6').checked) {
				rate_value = document.getElementById('myCheck6').value;
				text.style.display = "none";
				searchid.style.display = "none";
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

				var select = document.getElementById("sortProduct").value

				if (select != "") { //  auto select part

					var select_name = document.getElementById("sortProduct").options[document.getElementById("sortProduct").selectedIndex].text;

					var auto_pro = select
					var auto_part; // รายการ part ที่ต้องเลือกอัตโนมัติ

					if (select_name == "printer") { //  bitScan
						auto_part = ["95", "96", "346"];
					} else if (select_name == "bitScan") { //  bitScan
						auto_part = ["365", "68"];
					} else if (select_name == "bitVisitor M") { //  bitScan
						auto_part = ["54", "71", "72", "53"];
					} else if (select_name == "bitVisitor plus") { //  bitScan
						auto_part = ["54", "55", "53"];
					}

					for (var i = 0; i < auto_part.length; i++) {

						if (r_spare >= 5) {
							alert("เพิ่มรายการเบิกได้สูงสุด 5 รายการ");
							return;
						}

						r_spare += 1;
						document.getElementById("number_sp" + r_spare).style.display = '';

						//pd_sp1 เลือก product
						document.getElementById("pd_sp" + r_spare).style.display = '';
						document.getElementById("pd_sp" + r_spare).value = auto_pro;

						//sn_sp1 แสดง part
						document.getElementById("sn_sp" + r_spare).style.display = '';
						document.getElementById("sn_sp" + r_spare).value = auto_part[i];

						//amount_sp1 แสดงช่องกรอกจำนวน
						document.getElementById("amount_sp" + r_spare).style.display = '';
						document.getElementById("amount_sp" + r_spare).value = "1";

						//amount_sp1 แสดงจำนวน stock
						document.getElementById("stock_sp" + r_spare).style.display = '';
						document.getElementById("stock_sp" + r_spare).value = "1";

					}

					document.getElementById("sortProduct").value = ""; //  clear select

					$(document).ready(function() {
						$.get("list_stock.php", {
								sn_sp1: auto_part[0] // กำหนดค่า part อัตโนมัติ
							},
							function(data) {
								$("#stock_sp1").html(data);
							});
					});

					$(document).ready(function() {
						$.get("list_stock.php", {
								sn_sp2: auto_part[1] // กำหนดค่า part อัตโนมัติ
							},
							function(data) {
								$("#stock_sp2").html(data);
							});
					});

					$(document).ready(function() {
						$.get("list_stock.php", {
								sn_sp3: auto_part[2] // กำหนดค่า part อัตโนมัติ
							},
							function(data) {
								$("#stock_sp3").html(data);
							});
					});

					$(document).ready(function() {
						$.get("list_stock.php", {
								sn_sp4: auto_part[3] // กำหนดค่า part อัตโนมัติ
							},
							function(data) {
								$("#stock_sp4").html(data);
							});
					});

					$(document).ready(function() {
						$.get("list_stock.php", {
								sn_sp5: auto_part[4] // กำหนดค่า part อัตโนมัติ
							},
							function(data) {
								$("#stock_sp5").html(data);
							});
					});

				} else {

					r_spare += 1;
					//number_sp1 
					document.getElementById("number_sp" + r_spare).style.display = '';

					//pd_sp1 เลือก product
					document.getElementById("pd_sp" + r_spare).style.display = '';
					document.getElementById("pd_sp" + r_spare).value = "";

					//sn_sp1 แสดง part
					document.getElementById("sn_sp" + r_spare).style.display = '';
					document.getElementById("sn_sp" + r_spare).value = "";

					//amount_sp1 แสดงช่องกรอกจำนวน
					document.getElementById("amount_sp" + r_spare).style.display = '';
					document.getElementById("amount_sp" + r_spare).value = "";

					//amount_sp1 แสดงจำนวน stock
					document.getElementById("stock_sp" + r_spare).style.display = '';
					document.getElementById("stock_sp" + r_spare).value = "";

					//count row

				}

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

	.pickup-ma .select-container {
		align-items: center;
		background-color: rgb(214, 241, 239);
		border-radius: 10px;
		box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
		display: flex;
		margin: 0 0 1rem;
		padding: 8px;
		transition: all 0.3s ease;
		width: 265px;
	}

	.pickup-ma .select-container .label {
		color: rgb(4, 100, 89);
		margin-right: 15px;
	}

	.pickup-ma .select-container select {
		background-color: #e8f5e9;
		border: 2px solid #4caf50;
		border-radius: 8px;
		color: #333;
		cursor: pointer;
		outline: none;
		padding: 4px;
		text-align: center;
		transition: all 0.3s ease;
	}

	.pickup-ma input[type="number"] {
		background-color: #e8f5e9;
		border: 2px solid #4caf50;
		border-radius: 8px;
		color: #333;
		padding: 4px;
		text-align: center;
		width: 60px;
	}

	.pickup-ma .select-container:hover {
		box-shadow: 0 4px 20px rgba(0, 128, 0, 0.2);
	}

	.pickup-ma .select-container select:hover {
		background-color: #c8e6c9;
		border-color: #388e3c;
	}

	.pickup-ma .select-container select:focus {
		border-color: #388e3c;
		box-shadow: 0 0 8px rgba(56, 142, 60, 0.3);
	}
</style>

<body class="inv-app pickup-ma">
<?php require __DIR__ . '/title-h.php'; ?>
<main class="inv-main">
		<form method="POST" action="">
			<div class="select-container">
				<div class="label">เลือกชุด set</div>
				<select name="sortProduct" id="sortProduct" onchange="this.form.submit()">
					<option value="">--- เลือกของ ---</option>
					<option value="11">bitScan</option>
					<option value="14">printer</option>
					<option value="2">bitVisitor M</option>
					<option value="1">bitVisitor plus</option>
				</select>
			</div>
		</form>

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

			echo '<script>';
			echo 'const select = document.getElementById("sortProduct");';
			echo 'select.value = ' . json_encode($auto_se, JSON_UNESCAPED_UNICODE) . ';';
			echo '</script>';
		}

		if (isset($_POST["sortProduct"])) {
			$srotPro = (string) (int) $_POST["sortProduct"];
		}

		if ($srotPro != "") {
			echo '<script>';
			echo 'const select = document.getElementById("sortProduct");';
			echo 'select.value = ' . json_encode($srotPro, JSON_UNESCAPED_UNICODE) . ';';
			echo '</script>';
		}

		?>

			<form id="form_add_stock" name="form_add_stock" method="post" action="save_stock">
				<table id="table1" width="100%">

					<tr class="spaceUnder05">
						<td colspan="5" align="center" valign="center"><b class="ex">เบิกแยกรายการ</b></td>
					<tr>
						<td colspan="5">
							<hr />
						</td>
					</tr>

					<tr class="spaceUnder05">
						<td align="right" valign="center"><b>วันที่เบิก : </b></td>
						<td><input type="date" name="log_date" id="log_date" value="<?php echo date("Y-m-d"); ?>" style="width:auto;" /></td>
						<td></td>
					</tr>
					</tr>


					<tr>
						<td align="right">
							<div id="checkForm">
								<label>วัตถุประสงค์ : </label>
						</td>

						<td align="left" valign="center" colspan="2">
							<input type="radio" name="myCheck" id="myCheck1" onclick="myFunction()" value="เบิกซ่อมลูกค้า" checked>
							<label>เบิกซ่อมลูกค้า</label>

							<input type="radio" name="myCheck" id="myCheck2" onclick="myFunction()" value="เบิกเสียระหว่างผลิต">
							<label>เบิกเสียระหว่างผลิต</label>

							<input type="radio" name="myCheck" id="myCheck6" onclick="myFunction()" value="เบิกเสียระหว่างขนส่ง">
							<label>เบิกเสียระหว่างขนส่ง</label>

							<input type="radio" name="myCheck" id="myCheck3" onclick="myFunction()" value="เบิกผลิต">
							<label>เบิกผลิต</label><br>

							<input type="radio" name="myCheck" id="myCheck4" onclick="myFunction()" value="เบิกตาม PO">
							<label>เบิกตาม PO</label>

							<input type="radio" name="myCheck" id="myCheck5" onclick="myFunction()" value="เบิกอื่นๆ">
							<label>เบิกอื่นๆ</label>
		</div>
		</td>
		<tr>
			<td align="right" valign="center">
				<p id="lb_id" style="display:">เลขที่ใบงาน : </p>
			</td>
			<td>
				<p><input type="text" name="search_id" id="search_id" style="display:"></p>
			</td>
		</tr>
		<tr>
			<td align="right" valign="center">หมายเหตุ : </td>
			<td colspan="2"><textarea name="log_remarks" id="log_remarks" rows="3" cols="40"></textarea></td>
		</tr>
		<tr>
			<td colspan="5">
				<hr />
			</td>
		</tr>

		<tr>
			<td colspan="5">
				<p class="ex2"><b>รายการเบิก &nbsp; &nbsp;
						<a href='javascript:addSpare("del");'><i class="fa fa-minus-circle" aria-hidden="true" style="color:#FF0000"> Del </i></a> &nbsp; &nbsp;
						<a href='javascript:addSpare("add");'><i class="fa fa-plus-circle" aria-hidden="true" style="color:#339900"> Add </i></a></b></p>
			</td>
		</tr>
		<tr>
			<td>
				<br><br>
			</td>
		</tr>

		<td width="15%">

			<div align="center"><b>ลำดับ</b></div>
		</td>
		<td width="25%">
			<div align="left"><b>Product</b></div>
		</td>
		<td width="30%">
			<div align="center"><b>Part</b></div>
		</td>
		<td width="25%">
			<div align="center"><b>จำนวนเบิก</b></div>
		</td>
		<td>
			<div align="center"><b>จำนวน Stock</b></div>
		</td>

		</tr>
		<tr>
			<td width="25%" align="center">
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
				<p><select class="custom-select" id="pd_sp1" name="pd_sp1" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="custom-select" id="pd_sp2" name="pd_sp2" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="custom-select" id="pd_sp3" name="pd_sp3" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="custom-select" id="pd_sp4" name="pd_sp4" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>
				<p><select class="custom-select" id="pd_sp5" name="pd_sp5" style="display:none">
						<option value="">--เลือก Product--</option>
						<?php for ($a = 0; $a < $arrLength; $a++) { ?>
							<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php
						} ?>
					</select></p>

			</td>
			<td width="25%" align="center">

				<p><select class="custom-select" name="sn_sp1" id="sn_sp1" style="display:none;">
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

				<p><select class="custom-select" name="sn_sp2" id="sn_sp2" style="display:none;">
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

				<p><select class="custom-select" name="sn_sp3" id="sn_sp3" style="display:none;">
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

				<p><select class="custom-select" name="sn_sp4" id="sn_sp4" style="display:none;">
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

				<p><select class="custom-select" name="sn_sp5" id="sn_sp5" style="display:none;">
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


				<!-- <p><select class="custom-select" name="sn_sp1" id="sn_sp1" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				
					<p><select class="custom-select" name="sn_sp2" id="sn_sp2" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				<p><select class="custom-select" name="sn_sp3" id="sn_sp3" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				<p><select class="custom-select" name="sn_sp4" id="sn_sp4" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p>
				<p><select class="custom-select" name="sn_sp5" id="sn_sp5" style="display:none;">
						<option value="">--เลือก part--</option>
					</select></p> -->
			</td>
			<td width="25%" align="center" class="number">
				<p><input type="number" name="amount_sp1" id="amount_sp1" style="display:none;" onkeyup="chkStock()"></p>
				<p><input type="number" name="amount_sp2" id="amount_sp2" style="display:none;" onkeyup="chkStock()"></p>
				<p><input type="number" name="amount_sp3" id="amount_sp3" style="display:none;"></p>
				<p><input type="number" name="amount_sp4" id="amount_sp4" style="display:none;"></p>
				<p><input type="number" name="amount_sp5" id="amount_sp5" style="display:none;"></p>
			</td>
			</td>
			<td width="25%" align="center" colspan="2">

				<p><select class="custom-select" name="stock_sp1" id="stock_sp1" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="custom-select" name="stock_sp2" id="stock_sp2" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="custom-select" name="stock_sp3" id="stock_sp3" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="custom-select" name="stock_sp4" id="stock_sp4" style="display:none;">
						<option value="">none</option>
					</select></p>
				<p><select class="custom-select" name="stock_sp5" id="stock_sp5" style="display:none;">
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
		<tr>
			<td colspan="5">
				<hr />
			</td>
		</tr>
		<tr>
			<p><input type="hidden" id="count_sp" name="count_sp"></p>
			<td align="right" valign="center"><b class="ex">ผู้บันทึก : </b></td>
			<td>
				<select class="custom-select" name="log_by" id="log_by" required>
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
			</td>
			<td colspan="5" align="center" witdh="100%" valign="center"><input type="button" value="Save" onclick="javascript:save_job();"></td>
		</tr>
		</table>
		<br /><br />
		<div align="center"></div>
		<div align="center"></div>
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
