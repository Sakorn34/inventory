<?
require("connect.php");
include("function.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=TIS-620" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
	<link type="text/css" rel="stylesheet" href="css.css" />
	<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
	<title>รายละเอียดงานประสาน</title>
	<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>
	<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
	<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
	<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
	<script type="text/javascript">
		function save_job() {

			if (document.getElementById("ts_user_recive").value == '') {
				alert("กรุณาเลือก ผู้รับเรื่อง");
				document.getElementById("ts_user_recive").focus();
				return;
			} else if (document.getElementById("ts_enduser_com").value == '' && document.getElementById("ts_security_com").value == '') {
				alert("กรุณาระบุ ชื่อบริษัท");
				document.getElementById("ts_enduser_com").focus();
				return;
			} else if (document.getElementById("ts_report_name").value == '') {
				alert("กรุณาระบุ ผู้แจ้งเรื่อง");
				document.getElementById("ts_report_name").focus();
				return;
			}

			var ts_sendspare = "No";
			var data_insert = "ts_date=" + document.getElementById("ts_date").value +
				"&ts_user_recive=" + document.getElementById("ts_user_recive").value +
				"&ts_enduser_com=" + document.getElementById("ts_enduser_com").value +
				"&ts_security_com=" + document.getElementById("ts_security_com").value +
				"&ts_report_name=" + document.getElementById("ts_report_name").value +
				"&ts_report_tel=" + document.getElementById("ts_report_tel").value +
				"&ts_report_line=" + document.getElementById("ts_report_line").value +
				"&ts_report_email=" + document.getElementById("ts_report_email").value +
				"&ts_address=" + document.getElementById("ts_address1").value + document.getElementById("ts_address2").value +
				"&ts_approve_name=" + document.getElementById("ts_approve_name").value +
				"&ts_approve_tel=" + document.getElementById("ts_approve_tel").value +
				"&ts_approve_line=" + document.getElementById("ts_approve_line").value +
				"&ts_approve_email=" + document.getElementById("ts_approve_email").value +
				"&ts_remarks=" + document.getElementById("ts_remarks").value +
				"&ts_mtn1=" + document.getElementById("pd_mtn1").value + "@" + document.getElementById("sn_mtn1").value + "@" + document.getElementById("cause_mtn1").value +
				"&ts_mtn2=" + document.getElementById("pd_mtn2").value + "@" + document.getElementById("sn_mtn2").value + "@" + document.getElementById("cause_mtn2").value +
				"&ts_mtn3=" + document.getElementById("pd_mtn3").value + "@" + document.getElementById("sn_mtn3").value + "@" + document.getElementById("cause_mtn3").value +
				"&ts_mtn4=" + document.getElementById("pd_mtn4").value + "@" + document.getElementById("sn_mtn4").value + "@" + document.getElementById("cause_mtn4").value +
				"&ts_mtn5=" + document.getElementById("pd_mtn5").value + "@" + document.getElementById("sn_mtn5").value + "@" + document.getElementById("cause_mtn5").value +
				"&ts_mtn6=" + document.getElementById("pd_mtn6").value + "@" + document.getElementById("sn_mtn6").value + "@" + document.getElementById("cause_mtn6").value +
				"&ts_mtn7=" + document.getElementById("pd_mtn7").value + "@" + document.getElementById("sn_mtn7").value + "@" + document.getElementById("cause_mtn7").value +
				"&ts_mtn8=" + document.getElementById("pd_mtn8").value + "@" + document.getElementById("sn_mtn8").value + "@" + document.getElementById("cause_mtn8").value +
				"&ts_sp1=" + document.getElementById("sn_sp1").value + "@" + document.getElementById("sn_spAT1").value +
				"&ts_sp2=" + document.getElementById("sn_sp2").value + "@" + document.getElementById("sn_spAT2").value +
				"&ts_sp3=" + document.getElementById("sn_sp3").value + "@" + document.getElementById("sn_spAT3").value +
				"&ts_sp4=" + document.getElementById("sn_sp4").value + "@" + document.getElementById("sn_spAT4").value +
				"&ts_sp5=" + document.getElementById("sn_sp5").value + "@" + document.getElementById("sn_spAT5").value +
				"&ts_sp6=" + document.getElementById("sn_sp6").value + "@" + document.getElementById("sn_spAT6").value +
				"&ts_sp7=" + document.getElementById("sn_sp7").value + "@" + document.getElementById("sn_spAT7").value +
				"&ts_sp8=" + document.getElementById("sn_sp8").value + "@" + document.getElementById("sn_spAT8").value + " ";

			window.location = "insert_job.php?" + data_insert;
		}

		//Search ชื่อบริษัท  Enduser / ผวจ.
		$(document.getElementById("user_company")).ready(function() {
			$('.search-box-user input[type="text"]').on("keyup input", function() {
				/* Get input value on change */
				var inputVal = $(this).val();
				var resultDropdown = $(this).siblings(".result");
				if (inputVal.length) {
					$.get("search_cusname.php", {
						term: inputVal
					}).done(function(data) {
						resultDropdown.html(data); // Display the returned data in browser
					});
				} else {
					resultDropdown.empty();
				}
			});
			// Set search input value on click of result item
			$(document).on("click", ".result p", function() {
				$(this).parents(".search-box-user").find('input[type="text"]').val($(this).text());
				$(this).parent(".result").empty();
			});
		});
		//Search บริษัท รปภ.
		$(document.getElementById("security_company")).ready(function() {

			$('.search-box-sec input[type="text"]').on("keyup input", function() {
				/* Get input value on change */
				var inputVal = $(this).val();
				var resultDropdown = $(this).siblings(".result_sec");
				if (inputVal.length) {
					$.get("search_cusname.php", {
						term: inputVal
					}).done(function(data) {
						resultDropdown.html(data); // Display the returned data in browser
					});
				} else {
					resultDropdown.empty();
				}
			});
			// Set search input value on click of result item
		$(document).on("click", ".result_sec p", function() {
				$(this).parents(".search-box-sec").find('input[type="text"]').val($(this).text());
				$(this).parent(".result_sec").empty();
			});
		});
		//Search Tambon
		$(document.getElementById("s_tambon")).ready(function() {
			$('.search-box-tambon input').on("keyup input", function() {
				/* Get input value on change */
				var inputVal = $(this).val();
				var resultDropdown = $(this).siblings(".result_tambon");
				if (inputVal.length) {
					$.get("search_address.php", {
						term: inputVal
					}).done(function(data) {
						resultDropdown.html(data); // Display the returned data in browser
					});
				} else {
					resultDropdown.empty();
				}
			});
			// Set search input value on click of result item
		$(document).on("click", ".result_tambon p", function() {
				$(this).parents(".search-box-tambon").find('input').val($(this).text());
				$(this).parent(".result_tambon").empty();
			});
		});
		//select s/n repair 1
		$(document.getElementById("pd_mtn1")).ready(function() {
			$("select#pd_mtn1").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT1").style.display = '';
				} else {
					document.getElementById("p_spAT1").style.display = 'none';
					document.getElementById("sn_spAT1").value = "";
				}

				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp1").html(data);
						$("select#sn_spAT1").html(data);
					});
			});
		});
		//select s/n repair 2
		$(document.getElementById("pd_mtn2")).ready(function() {
			$("select#pd_mtn2").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT2").style.display = '';
				} else {
					document.getElementById("p_spAT2").style.display = 'none';
					document.getElementById("sn_spAT2").value = "";
				}
				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp2").html(data);
						$("select#sn_spAT2").html(data);
					});
			});
		});
		//select s/n repair 3
		$(document.getElementById("pd_mtn3")).ready(function() {
			$("select#pd_mtn3").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT3").style.display = '';
				} else {
					document.getElementById("p_spAT3").style.display = 'none';
					document.getElementById("sn_spAT3").value = "";
				}

				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp3").html(data);
						$("select#sn_spAT3").html(data);
					});
			});
		});
		//select s/n repair 4
		$(document.getElementById("pd_mtn4")).ready(function() {
			$("select#pd_mtn4").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT4").style.display = '';
				} else {
					document.getElementById("p_spAT4").style.display = 'none';
					document.getElementById("sn_spAT4").value = "";
				}
				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp4").html(data);
						$("select#sn_spAT4").html(data);
					});
			});
		});
		//select s/n repair 5
		$(document.getElementById("pd_mtn5")).ready(function() {
			$("select#pd_mtn5").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT5").style.display = '';
				} else {
					document.getElementById("p_spAT5").style.display = 'none';
					document.getElementById("sn_spAT5").value = "";
				}
				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp5").html(data);
						$("select#sn_spAT5").html(data);
					});
			});
		});
		//select s/n repair 6
		$(document.getElementById("pd_mtn6")).ready(function() {
			$("select#pd_mtn6").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT6").style.display = '';
				} else {
					document.getElementById("p_spAT6").style.display = 'none';
					document.getElementById("sn_spAT6").value = "";
				}
				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp6").html(data);
						$("select#sn_spAT6").html(data);
					});
			});
		});
		//select s/n repair 7
		$(document.getElementById("pd_mtn7")).ready(function() {
			$("select#pd_mtn7").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT7").style.display = '';
				} else {
					document.getElementById("p_spAT7").style.display = 'none';
					document.getElementById("sn_spAT7").value = "";
				}
				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp7").html(data);
						$("select#sn_spAT7").html(data);
					});
			});
		});
		//select s/n repair 8
		$(document.getElementById("pd_mtn8")).ready(function() {
			$("select#pd_mtn8").change(function() {

				if ($(this).val() == "bitSupply 125" || $(this).val() == "bitSupply 1224") {
					document.getElementById("p_spAT8").style.display = '';
				} else {
					document.getElementById("p_spAT8").style.display = 'none';
					document.getElementById("sn_spAT8").value = "";
				}
				$.get("list_sn_spare.php", {
						pd_mtn: $(this).val()
					},
					function(data) {
						$("select#sn_sp8").html(data);
						$("select#sn_spAT8").html(data);
					});
			});
		});

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

		//Add รายการซ่อม
		var r_ma = 1;
		function addListMaintenance(mode) {

			if (r_ma >= 8 && mode == "add") {
				alert("เพิ่มได้ไม่เกิน 8 รายการ");
				return;
			}
			if (mode == "add") {
				if (r_ma >= 1 && (document.getElementById("pd_mtn" + r_ma).value == "" || document.getElementById("cause_mtn" + r_ma).value == "")) {
					alert("กรุณากรอกข้อมูลก่อนหน้าให้ครบถ้วน");
					document.getElementById("cause_mtn" + r_ma).focus();
					return;
				}
				r_ma += 1;
				if (r_ma >= 8) {
					r_ma = 8;
				}
				document.getElementById("r_mt" + r_ma).style.display = '';
				document.getElementById("r_mt2" + r_ma).style.display = '';

				document.getElementById("pd_mtn" + r_ma).value = "";
				//sn_sp1
				document.getElementById("sn_mtn" + r_ma).value = "";
				//cause_mtn1
				document.getElementById("cause_mtn" + r_ma).value = "";
				//pd_sp1
				//document.getElementById("pd_sp" + r_ma).value = "";
				//sn_sp1
				document.getElementById("sn_sp" + r_ma).value = "";

			} else {

				if (r_ma <= 1) {
					r_ma = 1;
					document.getElementById("pd_mtn" + r_ma).value = "";
					document.getElementById("sn_mtn" + r_ma).value = "";
					document.getElementById("cause_mtn" + r_ma).value = "";

					document.getElementById("sn_sp" + r_ma).value = "";
					var selectBox = document.getElementById("sn_sp" + r_ma);
					selectBox.innerHTML = "";
					return;
				}
				//Row
				document.getElementById("r_mt" + r_ma).style.display = 'none';
				document.getElementById("r_mt2" + r_ma).style.display = 'none';
				//pd_sp1
				document.getElementById("pd_mtn" + r_ma).value = "";
				//sn_sp1
				document.getElementById("sn_mtn" + r_ma).value = "";
				//cause_mtn1
				document.getElementById("cause_mtn" + r_ma).value = "";

				//pd_sp1
				//document.getElementById("pd_sp" + r_ma).value = "";
				//sn_sp1
				document.getElementById("sn_sp" + r_ma).value = "";
				var selectBox = document.getElementById("sn_sp" + r_ma);
				selectBox.innerHTML = "";

				if (r_ma >= 2) {
					r_ma -= 1;
				}
			}
		}

	</script>
</head>

<style>
	.content {
		max-width: 1024px;
		margin-top: 50px;
		font-size: 16px;
		color: #666666;
		/*  */
	}

	input[type="text"] {
		font-size: 16px;
		padding: 1px;
		border: 0px solid #C0C0C0;
		color: blue;
	}

	textarea {
		font-size: 16px;
		padding: 1px;
		border: 1px solid #C0C0C0;
		color: blue;
	}

	select {
		font-size: 16px;
		padding: 1px;
		border: 1px solid #C0C0C0;
		width: 170px;
	}

	table,
	td,
	th {
		border: 0px solid black;
		padding: 5px 0 5px 0;
	}

	tr.spaceUnder>td {
		padding-bottom: 0.5em;
	}

	tr.spaceUnder05>td {
		padding-bottom: 0.5em;
	}

	tr.spaceTop>td {
		padding-top: 0.5em;
	}

	.Tex {
		border-radius: 10px;
		background: #FFCCCC;
		padding: 5px 10px 5px 10px;
		width: max-content;
		/*border: 3px solid #BADA55; */
	}

	.Tex2 {
		border-radius: 10px;
		background: #CCFFFF;
		padding: 5px 10px 5px 10px;
		width: max-content;
		/*border: 3px solid #BADA55; */
	}

	.Tex3 {
		border-radius: 10px;
		background: #CCFFFF;
		padding: 5px 10px 5px 10px;
		width: auto;
		/*border: 3px solid #BADA55; */
	}

	/* The navigation bar */
	.navbar {
		background-color: #E9E9E9;
		overflow: hidden;
		position: fixed;
		/* Set the navbar to fixed position */
		top: 0;
		/* Position the navbar at the top of the page */
		width: 100%;
		/* Full width */
	}
</style>
<center>
	<body>
		<div>
		<table id="table1" width="1024px">
		<?
		$i = 0;
		$m = 0;
		$show = false;
		$sql = "SELECT * FROM setup_log INNER JOIN Transac_repair ON Transac.[ts_id] = Transac_repair.[trp_ts_id]  WHERE Transac_repair.[trp_ts_id] = ".$_GET['ts_id']."" ;
	   $exe = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");
       while($result = db_fetch_array($exe)){
	   if($show == false){
	   $show = true;
	  ?>
					<tr><td colspan="4"  align="center" style="font-size:x-large;"><b>รายละเอียดงานซ่อม :  &nbsp; <?= $result['ts_mtn_number']; ?></b><br /></td></tr>
					<tr><td colspan="4"><hr /></td></tr>
					<tr>
						<td colspan="2" width="50%"><b class="ex2">วันที่รับเรื่อง : </b> &nbsp;
							<input type="date" name="ts_date" id="ts_date" value="<?= $result['ts_date']; ?>" style="width:auto;" />
						</td>
						<td colspan="2" width="50%">

							<b class="ex2">ผู้รับเรื่อง : </b> &nbsp;
							<?php
							$array = array();
							$array = employee("");
							$arrLength = count($array);
							?>
							<select class="custom-select" id="ts_user_recive" name="ts_user_recive">
								<option value="<?= $result['ts_user_recive']; ?>" selected="selected"><?= $result['ts_user_recive']; ?></option
								><? for ($a = 0; $a <= $arrLength; $a++) { ?>
									<option value="<?= $array[$a]; ?>"><?= $array[$a]; ?></option>
								<? } ?>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<div class="search-box-user"><b>บริษัท End user/ผู้ว่าจ้าง</b>
								<input type="text" autocomplete="off" name="ts_enduser_com" id="ts_enduser_com" style="width:310px;" value="<?= $result['ts_enduser_com']; ?>" />
								<div class="result"></div>
							</div>
						</td>
						<td colspan="2">
							<div class="search-box-sec"><b>บริษัท รปภ.</b>
								<input type="text" autocomplete="off" name="ts_security_com" id="ts_security_com" style="width: 400px;" value="<?= $result['ts_security_com']; ?>" />
								<div class="result_sec"></div>
							</div>
						</td>
					</tr>
					<tr>
						<td colspan="4">
							<b>ข้อมูลผู้แจ้ง : </b>
						</td>
					</tr>
					<tr>
						<td colspan="4">
							<p style="display:inline"><b>Name </b><input type="text" name="ts_report_name" id="ts_report_name" style="width:auto;"  value="<?= $result['ts_report_name']; ?>" />
								&nbsp; &nbsp; <b> Tel </b><input type="text" name="ts_report_tel" id="ts_report_tel" style="width:190px;" value="<?= $result['ts_report_tel']; ?>" />
								&nbsp; &nbsp; <b>Line </b><input type="text" name="ts_report_line" id="ts_report_line" style="width:auto;" value="<?= $result['ts_report_line']; ?>" />
								&nbsp; &nbsp; <b>Email </b><input type="text" name="ts_report_email" id="ts_report_email" style="width:auto;" value="<?= $result['ts_report_email']; ?>" /></p>
						</td>
					</tr>
					<tr>
						<td colspan="4">
							<b>ข้อมูลผู้อนุมัติ : </b>
						</td>
					</tr>
					<tr>
						<td colspan="4">
							<p style="display:inline"><b>Name </b><input type="text" name="ts_approve_name" id="ts_approve_name" style="width:auto;" value="<?= $result['ts_approve_name']; ?>" />
								&nbsp; &nbsp; <b> Tel </b><input type="text" name="ts_approve_tel" id="ts_approve_tel" style="width:190px;" value="<?= $result['ts_approve_tel']; ?>" />
								&nbsp; &nbsp; <b>Line </b><input type="text" name="ts_approve_line" id="ts_approve_line" style="width:auto;" value="<?= $result['ts_approve_line']; ?>" />
								&nbsp; &nbsp; <b>Email </b><input type="text" name="ts_approve_email" id="ts_approve_email" style="width:auto;" value="<?= $result['ts_approve_email']; ?>" /></p>
						</td>
					</tr>
					<tr>
						<td colspan="4">
							<div class="search-box-tambon"><b>ที่อยู่จัดส่ง</b>
								<input type="text" name="ts_address2" id="ts_address2" placeholder="พิมพ์ตำบล เพื่อค้นหา" style="width: 925px;" value="<?= $result['ts_address']; ?>" />
								<div class="result_tambon"></div>
							</div>
						</td>
					</tr>
					<tr><td colspan="4"><b>หมายเหตุ </b><input type="text" name="ts_remarks" id="ts_remarks"  style="width: 925px;" value="<?= $result['ts_remarks']; ?>" /></td></tr>
					<tr><td colspan="4"><hr /></td></tr>
					<tr>
						<td><b>ข้อมูลเครื่องซ่อม </b></td>
						<td align="left"><!--<p class="ex">S/N เครื่องซ่อม</p> --></td>
						<td colspan="2"><!--<p class="ex2"><b>ข้อมูลเครื่องสำรอง &nbsp; &nbsp;</b></p> --></td>
					</tr>
					<? } ?>
					<?php
					$array1 = array();
					$sql1 =  "SELECT * FROM Product_Name";
					$exe1 = db_exec($connect, $sql1) or die("Error Query [" . $sql1 . "]");
					while ($result1 = db_fetch_array($exe1)) {
						array_push($array1, $result1['pn_name']);
					}
					$arrLength1 = count($array1);
					
					//Query เครื่องซ่อม
					$temp_spare  = "";
					$tsp_datesend = ""; $tsp_logistics = ""; $tsp_tracking = ""; $tsp_sendby = "";
					$sqlch_sp = "SELECT tsp_trp_id FROM Transac_spare WHERE tsp_trp_id = ".$result['trp_id']." ";
					$exech_sp = db_exec($connect, $sqlch_sp) or die("Error Query [" . $sqlch_sp . "]");
					$resultch_sp = db_fetch_array($exech_sp);
					$sql2 = "SELECT * FROM Transac_repair   WHERE trp_id = ".$result['trp_id']."" ;
					if($resultch_sp['tsp_trp_id'] != ''){
						$sql2 = "SELECT * FROM Transac_repair INNER JOIN Transac_spare ON Transac_repair.[trp_id] = Transac_spare.[tsp_trp_id]  WHERE Transac_spare.[tsp_trp_id] = ".$result['trp_id']."" ;
					}
					
					//$sql2 = "SELECT * FROM Transac_repair INNER JOIN Transac_spare ON Transac_repair.[trp_id] = Transac_spare.[tsp_trp_id]  WHERE Transac_spare.[tsp_trp_id] = ".$result['trp_id']."" ;
					$exe2 = db_exec($connect, $sql2) or die("Error Query [" . $sql2 . "]");
					while ($result2 = db_fetch_array($exe2)) { 
					$i++; 
					$m++;
					$n = strval($i).strval($m);
					if($temp_spare != $result2['trp_product']){
					$temp_spare =  $result2['trp_product'];
					?>
					<tr valign="top">
						<td>
							<b>No. <?= $i; ?> </b>
							<select class="custom-select" id="pd_mtn<?= $n; ?>" name="pd_mtn1<?= $n; ?>" style="width:150px;">
								<option value="<?= $result['trp_product']; ?>" selected="selected"><?= $result['trp_product']; ?></option>
								<? for ($a = 0; $a <= $arrLength1; $a++) { ?>
									<option value="<?= $array1[$a]; ?>"><?= $array1[$a]; ?></option>
								<? } ?>
							</select>
						</td>
						<td><b>เครื่องซ่อม </b>  <input type="text" name="sn_mtn<?= $n; ?>" id="sn_mtn<?= $n; ?>" style="width:150px;"  value="<?= $result['trp_sn']; ?>" /></td>
						<?
						//sn เครื่องสำรอง 1
						$sql3 = "SELECT * FROM Transac_spare  WHERE tsp_trp_id = ".$result2['trp_id']."" ;
						$exe3 = db_exec($connect, $sql3) or die("Error Query [" . $sql3 . "]");
						$l = 0;
						while ($result3 = db_fetch_array($exe3)) {  
						if($l == 0){
							$l++;
						   $tsp_datesend = $result3['tsp_datesend'];
						   $tsp_logistics = $result3['tsp_logistics']; 
						   $tsp_tracking = $result3['tsp_tracking']; 
						   $tsp_sendby = $result3['tsp_sendby'];
						}
						?>
						<td><b>เครื่องสำรอง </b> <input type="text" name="sn_sp<?= $n; ?>" id="sn_sp<?= $n; ?>" style="width:150px;" value="<?= $result3['tsp_sn']; ?>" /></td>
						<? } ?>
						</tr>
						<tr>
							<td align="left" colspan="2" style="color:blue;">
							<b  style='color:#666666;'>อาการที่แจ้ง : </b> <?= $result2['trp_repair_inform']; ?><br />
							<b style='color:#666666;'>วันที่รับ </b> <?php echo $result['trp_receive_date'];  echo "<b style='color:#666666;'> ผู้รับ </b>".$result['trp_user_recive_ma']; ?><br />
							<b style='color:#666666;'>วันที่ซื้อ </b> <?= $result['trp_buy_date']; ?> <b style='color:#666666;'> อายุเครื่อง </b><?= $result['trp_repair_age']; ?> <?= $result['trp_insurance']; ?>
							</td>
							<td colspan="2" style="color:blue;">
							<b  style='color:#666666;'>วันที่ส่ง </b> &nbsp; &nbsp;<?php echo $tsp_datesend;  ?>&nbsp; &nbsp; &nbsp;<?php echo "<b style='color:#666666;'> ส่งโดย </b>".$tsp_logistics; 
							echo "<br><b style='color:#666666;'> Tracking </b>".$tsp_tracking; echo "<b style='color:#666666;'> ผู้ส่ง </b>".$tsp_sendby; ?></td>
						</tr>
						<tr>
							<td align="left" colspan="4"  style="color:blue;">
							<b  style='color:#666666;'>วันที่ช่างประเมิน : </b> <?= $result['trp_rate_date']; ?>  <b  style='color:#666666;'>ผู้ประเมิน : </b> <?= $result['trp_user_rate']; ?>	<br />
							<b  style='color:#666666;'>อาการที่ช่างประเมิน : </b> 
							<textarea name="trp_repair_technician<?= $i; ?>" id="trp_repair_technician<?= $i; ?>" cols="100%" rows="3" ><?= $result2['trp_repair_technician']; ?></textarea><br />
							<b  style='color:#666666;'>รายการที่ซ่อม : </b><?= $result['trp_clean']; ?> &nbsp; <?= $result['trp_repeat_solder']; ?> &nbsp; <?= $result['trp_spray']; ?> &nbsp; <?= $result['trp_fw']; ?>
							&nbsp; <?= $result['trp_repair_no1']; ?> &nbsp; <?= $result['trp_repair_no2']; ?> &nbsp; <?= $result['trp_repair_no3']; ?> &nbsp; <?= $result['trp_repair_no4']; ?> &nbsp; <?= $result['trp_repair_no5']; ?>
							&nbsp; <?= $result['trp_repair_no6']; ?> &nbsp; <?= $result['trp_repair_no7']; ?> &nbsp; <?= $result['trp_repair_no8']; ?> &nbsp; <?= $result['trp_repair_no9']; ?> &nbsp; <?= $result['trp_repair_no10']; ?> <br />
							<b  style='color:#666666;'>วันที่ซ่อมเสร็จ : </b> <?= $result['trp_success_date']; ?><br />
							<b  style='color:#666666;'>หมายเหตุซ่อม : </b> <?= $result['trp_repair_remarks']; ?><br /><br />
							
							<b  style='color:#666666;'>ส่งคืนเครื่องซ่อม : </b><?= $result['trp_send_date']; ?>
							<b  style='color:#666666;'>ส่งโดย : </b><?= $result['trp_logistics']; ?>
							<b  style='color:#666666;'>tracking : </b><?= $result['trp_tracking']; ?>
							<b  style='color:#666666;'>ผู้ส่ง : </b><?= $result['trp_sendby']; ?><br /><br />

							<b  style='color:#666666;'>ใบเสนอราคา : </b> <?= $result['trp_qv_number']; ?>
							<b  style='color:#666666;'>วันที่เสนอราคา : </b> <?= $result['trp_qv_date']; ?>
							<b  style='color:#666666;'>จำนวนเงิน : </b> <?= $result['trp_qv_price']; ?>
							<b  style='color:#666666;'>วันที่ส่งใบเสนอราคา : </b> <?= $result['trp_qv_send']; ?>
							<b  style='color:#666666;'>วันที่อนุมัติซ่อม : </b> <?= $result['trp_firm_qv_date']; ?><br /><br />

							<b  style='color:#666666;'>วันที่ QC : </b> <?= $result['trp_qc_date']; ?>	
							<b  style='color:#666666;'>คน QC : </b> <?= $result['trp_qc_user']; ?>	<br />
							<b  style='color:#666666;'>Comment QC : </b> <?= $result['trp_qc_comment']; ?>
							<hr />
							</td>
						</tr>
						<? } ?>
					<? } ?>
					<? } ?>
					<?php db_close($connect); ?>
					<tr>
						<td colspan="4" style="font-size:24px; text-align:center; color:#009900; display:none;">
							<p><a href='javascript: save_job();'><i class="fa fa-save" style="color:#006600"> Save</i></a></p>
						</td>
					</tr>
				</table>
				<br /><br />
		</div>
	</body>
</center>

</html>
