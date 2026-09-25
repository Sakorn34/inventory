<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';
header("Content-Type: text/html; charset=UTF-8");
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/inventory-ui.css">
	<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">

	<title>Inventory</title>
	<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>
	<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
	<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
	<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
	<script type="text/javascript">
	

		function save_job() {

			if (document.getElementById("pre_product").value == "" ||
				document.getElementById("pre_amount").value == "" ||
				document.getElementById("pre_by").value == "") {
				alert("กรุณากรอกข้อมูลให้ครบถ้วน");
				return;
			}

			var pre_type = "";

			if (document.getElementById('myCheck1').checked) {
				pre_type = document.getElementById('myCheck1').value;
			} else if (document.getElementById('myCheck2').checked) {
				pre_type = document.getElementById('myCheck2').value;
			} else if (document.getElementById('myCheck3').checked) {
				pre_type = document.getElementById('myCheck3').value;
			} else if (document.getElementById('myCheck4').checked) {
				pre_type = document.getElementById('myCheck4').value;
			} else if (document.getElementById('myCheck5').checked) {
				pre_type = document.getElementById('myCheck5').value;
			}

			var data_update = "pre_date=" + encodeURIComponent(document.getElementById("pre_date").value) +
				"&pre_product=" + encodeURIComponent(document.getElementById("pre_product").value) +
				"&pre_type=" + encodeURIComponent(pre_type) +
				"&pre_amount=" + encodeURIComponent(document.getElementById("pre_amount").value) +
				"&pre_by=" + encodeURIComponent(document.getElementById("pre_by").value) +
				"&pre_remarks=";

			//	alert(data_update);
			window.location = "pre_pickup_save.php?" + data_update;
		}

		

		//-------------------------------------------- select product_name -------------------------------------
		/*$(document.getElementById("pre_product")).ready(function(){
		     $("#pre_product").change(function(){ 
				 $.get("list_production.php",{ product_name:$(this).val() },
				 function(data) { $("#result").html(data);  });
		      }); 
		});
		*/
		//-------------------------------------------- chkStock() -----------------------------------------------------
		/*
		function checkStock() {

				var pick = parseInt(document.getElementById("amount_sp1").value);
				
				var stock =  parseInt(document.getElementById("stock_sp1").value);
			
				if(pick > stock && stock != null){
				  alert('ใส่ยอดเบิกเกินจำนวน stock ที่มี ');
				  // document.getElementById('amount_sp1').value = '';
		        }
		    return "OK";

		}
		*/
		//-------------------------------------------------------------------------------------------------
	</script>
</head>
<style>
	.content {
		max-width: 1200px;
		margin: 0 auto;
		font-size: 16px;
		color: #666666;
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 12px;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
		padding: 1.25rem;
	}

	input[type="text"] {
		font-size: 16px;
		padding: 1px;
		border: 1px solid #C0C0C0;
		color: #FF9900;
	}

	textarea {
		font-size: 16px;
		padding: 1px;
		border: 1px solid #C0C0C0;
	}

	select {
		font-size: 16px;
		padding: 1px;
		border: 1px solid #C0C0C0;
	}

	table,
	td,
	th {
		border: 0px solid black;
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
		/*border: 3px solid #BADA55; */
	}

	.ex2 {
		border-radius: 10px;
		background: #CCFFFF;
		padding: 5px 10px 5px 10px;
		width: max-content;
		/*border: 3px solid #BADA55; */
	}

</style>
	<body class="inv-app">
		<?php require "title-h.php"; ?>
		<main class="inv-main">
		<div class="content">
			<form id="form_production" name="form_production" method="post" action="production_pre_pickup.php">
				<table id="table1" width="100%">

					<tr class="spaceUnder05">
						<td colspan="6" align="center" valign="center"><b class="ex">เบิกของเป็นเซต</b><br /></td>
					<tr>
					</tr>
					<tr>
						<td colspan="6">
							<hr />
						</td>
					</tr>

					<tr>
						<td align="right" valign="center" width="20"><b>วันเบิก : </b></td>
						<td><input type="date" name="pre_date" id="pre_date" value="<?php echo date("Y-m-d"); ?>" style="width:auto;" /></td>


						<td align="left" valign="center" colspan="4">
							<label>วัตถุประสงค์ : </label>
							<?php

							if (isset($_POST['pickup_type'])) {

								if ($_POST['pickup_type'] == "เบิกผลิต") {
									echo "<input type='radio' name='pickup_type' id='myCheck1'   value='เบิกผลิต' checked>";
									echo  "<label>เบิกผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck2'   value='เบิกตาม PO' >";
									echo  "<label>เบิกตาม PO</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck3'   value='เบิกซ่อมลูกค้า' >";
									echo  "<label>เบิกซ่อมลูกค้า</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck4'   value='เบิกเสียระหว่างผลิต' >";
									echo  "<label>เบิกเสียระหว่างผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck5'   value='เบิกอื่นๆ' >";
									echo  "<label>เบิกอื่นๆ</label> ";
								} else if ($_POST['pickup_type'] == "เบิกตาม PO") {
									echo "<input type='radio' name='pickup_type' id='myCheck1'   value='เบิกผลิต' >";
									echo  "<label>เบิกผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck2'   value='เบิกตาม PO' checked>";
									echo  "<label>เบิกตาม PO</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck3'   value='เบิกซ่อมลูกค้า' >";
									echo  "<label>เบิกซ่อมลูกค้า</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck4'   value='เบิกเสียระหว่างผลิต' >";
									echo  "<label>เบิกเสียระหว่างผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck5'   value='เบิกอื่นๆ' >";
									echo  "<label>เบิกอื่นๆ</label> ";
								} else  if ($_POST['pickup_type'] == "เบิกซ่อมลูกค้า") {
									echo "<input type='radio' name='pickup_type' id='myCheck1'   value='เบิกผลิต' >";
									echo  "<label>เบิกผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck2'   value='เบิกตาม PO' >";
									echo  "<label>เบิกตาม PO</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck3'   value='เบิกซ่อมลูกค้า' checked>";
									echo  "<label>เบิกซ่อมลูกค้า</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck4'   value='เบิกเสียระหว่างผลิต' >";
									echo  "<label>เบิกเสียระหว่างผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck5'   value='เบิกอื่นๆ' >";
									echo  "<label>เบิกอื่นๆ</label> ";
								} else  if ($_POST['pickup_type'] == "เบิกเสียระหว่างผลิต") {
									echo "<input type='radio' name='pickup_type' id='myCheck1'   value='เบิกผลิต' >";
									echo  "<label>เบิกผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck2'   value='เบิกตาม PO' >";
									echo  "<label>เบิกตาม PO</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck3'   value='เบิกซ่อมลูกค้า' >";
									echo  "<label>เบิกซ่อมลูกค้า</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck4'   value='เบิกเสียระหว่างผลิต' checked>";
									echo  "<label>เบิกเสียระหว่างผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck5'   value='เบิกอื่นๆ' >";
									echo  "<label>เบิกอื่นๆ</label> ";
								} else  if ($_POST['pickup_type'] == "เบิกอื่นๆ") {
									echo "<input type='radio' name='pickup_type' id='myCheck1'   value='เบิกผลิต' >";
									echo  "<label>เบิกผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck2'   value='เบิกตาม PO' >";
									echo  "<label>เบิกตาม PO</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck3'   value='เบิกซ่อมลูกค้า' >";
									echo  "<label>เบิกซ่อมลูกค้า</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck4'   value='เบิกเสียระหว่างผลิต' >";
									echo  "<label>เบิกเสียระหว่างผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck5'   value='เบิกอื่นๆ' checked>";
									echo  "<label>เบิกอื่นๆ</label> ";
								} else {
									echo "<input type='radio' name='pickup_type' id='myCheck1'   value='เบิกผลิต' checked>";
									echo  "<label>เบิกผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck2'   value='เบิกตาม PO' >";
									echo  "<label>เบิกตาม PO</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck3'   value='เบิกซ่อมลูกค้า' >";
									echo  "<label>เบิกซ่อมลูกค้า</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck4'   value='เบิกเสียระหว่างผลิต' >";
									echo  "<label>เบิกเสียระหว่างผลิต</label> ";
									echo "<input type='radio' name='pickup_type' id='myCheck5'   value='เบิกอื่นๆ' >";
									echo  "<label>เบิกอื่นๆ</label> ";
								}
							} else {
								echo "<input type='radio' name='pickup_type' id='myCheck1'   value='เบิกผลิต' checked>";
								echo  "<label>เบิกผลิต</label> ";
								echo "<input type='radio' name='pickup_type' id='myCheck2'   value='เบิกตาม PO' >";
								echo  "<label>เบิกตาม PO</label> ";
								echo "<input type='radio' name='pickup_type' id='myCheck3'   value='เบิกซ่อมลูกค้า' >";
								echo  "<label>เบิกซ่อมลูกค้า</label> ";
								echo "<input type='radio' name='pickup_type' id='myCheck4'   value='เบิกเสียระหว่างผลิต' >";
								echo  "<label>เบิกเสียระหว่างผลิต</label> ";
								echo "<input type='radio' name='pickup_type' id='myCheck5'   value='เบิกอื่นๆ' >";
								echo  "<label>เบิกอื่นๆ</label> ";
							}

							?>

						</td>
					</tr>
					</tr>

					<?php
					$userby;
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

					<tr>
						<td align="right" valign="center"> Product : </td>
						<td>
							<p>
								<select id="pre_product" name="pre_product">
									<?php
									if (!empty($_POST['pre_product'])) {
										$preProductId = (int) $_POST['pre_product'];
										echo "select : " . htmlspecialchars((string) $preProductId, ENT_QUOTES, 'UTF-8');
										$pd_select;
										$sqlSelect = "SELECT * FROM product WHERE pd_id = " . $preProductId;
										$exeSelect = db_exec($connect, $sqlSelect) or die("Error Query [" . $sqlSelect . "]");
										while ($resultSelect = db_fetch_array($exeSelect)) {
											$pd_select = $resultSelect['pd_name'];
										}

									?>
										<option value="<?php echo $preProductId; ?>"><?php echo htmlspecialchars($pd_select, ENT_QUOTES, 'UTF-8'); ?></option>
									<?php
									} else {
									?>
										<option value="">-- Select --</option>
									<?php
									}

									for ($a = 0; $a < $arrLength; $a++) { ?>
										<!-- <option value="<?= $array2[$a]; ?>"></option> -->
										<option value="<?php echo htmlspecialchars($array2[$a], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($array[$a], ENT_QUOTES, 'UTF-8'); ?></option>
										
									<?php
									}
									if (!empty($_POST['pre_amount']) && !empty($_POST['pre_product'])) {
										$amount = (int) $_POST['pre_amount'];
										$product = (int) $_POST['pre_product'];
										$userby = trim((string) $_POST['pre_by']);
										$userbyValue = $userby;
										//	$pickuptype = $_POST['pickup_type'];

									} else {
										$amount = "";
										$userby = "--- Select ---";
										$userbyValue = "";
									}

									?>

								</select>
							</p>
						</td>
						<td align="left" valign="center" colspan="2">
							<b>ผู้เบิก : </b> <select class="custom-select" name="pre_by" id="pre_by" required>
								<option value="<?php echo htmlspecialchars($userbyValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($userby, ENT_QUOTES, 'UTF-8'); ?></option>
								<option value="Ken">Ken</option>
								<option value="Don">Don</option>
								<option value="ICE">ICE</option>
								<option value="TOM">TOM</option>
								<option value="KONG">KONG</option>
								<option value="NI">NI</option>
								<option value="AO">AO</option>
								<option value="MAX">Max</option>
								<option value="DAY">DAY</option>
								<option value="NUT">NUT</option>
							</select>
						</td>
						<td align="left" valign="center" colspan="2">
							<b>จำนวน : </b><select class="custom-select" name="pre_amount" id="pre_amount" required>
								<option value="<?php echo (int) $amount; ?>"><?php echo (int) $amount; ?></option>
								<option value="1">1</option>
								<option value="2">2</option>
								<option value="4">4</option>
								<option value="6">6</option>
								<option value="8">8</option>
								<option value="10">10</option>
								<option value="12">12</option>
								<option value="14">14</option>
								<option value="16">16</option>
								<option value="18">18</option>
								<option value="20">20</option>
							</select> <input type="submit" value="OK">
						</td>
					</tr>
					<tr>
						<td colspan="6">
							<hr />
						</td>
					</tr>
					<tr>
						<th width="30">
							<div align="center">No. </div>
						</th>
						<th width="150">
							<div align="center">Part name </div>
						</th>
						<th width="150">
							<div align="center">จำนวนขั้นต่ำที่ต้องสั่ง</div>
						</th>
						<th width="100">
							<div align="center">จำนวน Stock</div>
						</th>
						<th width="100">
							<div align="center">จำนวนที่เบิก</div>
						</th>
						<th width="100">
							<div align="center">สถานะเบิก</div>
						</th>
					</tr>

					<tr>

						<?php
						//------------------ แสดงผลการ query
						if (!empty($_POST['pre_amount']) && !empty($_POST['pre_product'])) {
							//echo "Search : ".$_POST['pickup_type'];
							$amount;
							$product = (int) $_POST['pre_product'];
							$sqlList = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $product . " ";
							//	    $sqlList = "SELECT * FROM part WHERE part_product LIKE '%" . $product . "%'  ";
							$exec = db_exec($connect, $sqlList) or die("Error Query [" . $sqlList . "]");
							$num = 1;
							while ($result = db_fetch_array($exec)) {

								if ($result['part_id'] == '57') {
									$amount =  $_POST['pre_amount'] * 2;
								} else if ($result['part_id'] == '58') {
									$amount =  $_POST['pre_amount'] / 2;
								} else {
									$amount =  $_POST['pre_amount'];
								}

								$resultStock = "";
								if (checkStock($amount, $result["part_amount"]) == "ok") {
									$resultStock =  "<span style=color:black;text-align:center;>OK</span>";
								} else {
									$resultStock = "<span style=color:red;text-align:center;>Stock ไม่พอ</span>";
								}
						?>
					<tr>
						<td>
							<div align="center"><?php echo $num; ?></div>
						</td>
						<td>
							<div align="center"><?php echo htmlspecialchars($result["part_name"], ENT_QUOTES, 'UTF-8'); ?></div>
						</td>
						<td>
							<div align="center"><?php echo htmlspecialchars($result["part_min_alert"], ENT_QUOTES, 'UTF-8'); ?></div>
						</td>
						<td>
							<div align="center"><?php echo htmlspecialchars($result["part_amount"], ENT_QUOTES, 'UTF-8'); ?></div>
						</td>
						<td>
							<div align="center"><input type='text' style='width: 3em ;text-align: right;' value='<?= $amount ?>' disabled></div>
						</td>
						<td>
							<div align="center"><?= $resultStock ?>
						</td>
						<td style="display:none;">
							<div align="center">OK
						</td>
					</tr>
			<?php
								$num++;
							}
						}

			?>
			</tr>
			<tr>
				<td colspan="6">
					<hr />
				</td>
			</tr>
			<tr>
				<p><input type="hidden" id="pre_type" name="pre_type"></p>

				<td colspan="6" align="center" valign="center"><input type="button" value="Save" onclick="javascript:save_job();"></td>
			</tr>

				</table>
				<br /><br />
				<div align="center"></div>
				<div align="center"></div>
			</form>
		</div>
		</main>
	</body>

</html>
