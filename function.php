<?php

function get_status_addjob($sn_spare)
{
	$txt_return = "";

	if ( trim($sn_spare) != '' ) {	
		$txt_return = "�������ͧ";		
	}else{	
		$txt_return =  "������ͧ����";	
	}
	return $txt_return;
}

function cal_wait_date($date)
{
	if($date == ""){
		return "";
	}

	$day_send = date("Y-m-d", strtotime($date));
	list($syear, $smonth, $sday) = explode("-", $day_send);       
	list($tyear, $tmonth, $tday) = explode("-", date("Y-m-d"));  
	$mday_send = mktime(0, 0, 0, $smonth, $sday, $syear);
	$mnow = mktime(0, 0, 0, $tmonth, $tday, $tyear);
	$mage = ($mnow - $mday_send);
	$u_y = date("Y", $mage) - 1970;
	$u_m = date("m", $mage) - 1;
	$u_d = date("d", $mage) - 1;
	$send = "";

	if($u_y <= 0 && $u_m <= 0 && $u_d <= 0){
		$send =  $u_d . " �ѹ ";
	}else{

		if ($u_y > 0) {
			if ($u_m > 0 && $u_d <= 0) {
				$send =  $u_y .  " �� "  . $u_m . " ��͹ ";
			} else if ($u_m <= 0 && $u_d > 0) {
				$send =  $u_y . " �� "  . $u_d . " �ѹ ";
			} else {
				$send =  $u_y . " �� " . $u_m . " ��͹ " . $u_d . " �ѹ ";
			}
		} else if ($u_m > 0) {
			if ($u_d <= 0) {
				$send =  $u_m . " ��͹ ";
			} else {
				$send =  $u_m . " ��͹ "  . $u_d .  " �ѹ ";
			}
		} else if ($u_d > 0) {
			$send =  $u_d . " �ѹ ";
		} else {
			$send =  $u_y . " �� " . $u_m . " ��͹ " . $u_d . " �ѹ ";
		}

	}

	if($u_m > 0 || $u_y > 0 || $u_d > 7){
		$send = "<span style='color:#FF0000'>".$send."</span>";
	}

	return $send;
}

function checkStock($pick, $stock) {

	$check = $pick + " "+ $stock;
	if($pick <= $stock && $stock != ""){
		$check = "ok";
	}else{
        $check = "false";
	}
return $check;
}

function employee($position)
{

	require_once __DIR__ . '/connect.php';
	$array = array();

	if ($position == "") {

		$sql =  "SELECT * FROM employee";
		$exe = db_exec($connect, $sql) or die("Error Query [" . $sql . "]");
		while ($result = db_fetch_array($exe)) {
			array_push($array, $result['emp_name']);
		}

	} else if ($position == "TC") {

		$sql_em =  "SELECT *  FROM employee WHERE emp_position = 'TC' ";
		$exe_em = db_exec($connect, $sql_em) or die("Error Query [" . $sql_em . "]");
		while ($result_em = db_fetch_array($exe_em)) {
			array_push($array, $result_em['emp_name']);
		}
		
	} else if ($position == "SP-QC") {

		$sql_em =  "SELECT *  FROM employee WHERE emp_position = 'SP-QC' ";
		$exe_em = db_exec($connect, $sql_em) or die("Error Query [" . $sql_em . "]");
		while ($result_em = db_fetch_array($exe_em)) {
			array_push($array, $result_em['emp_name']);
		}
		
	}

	//$arrLength = count($array);

	return $array;
}

function product()
{
	require_once __DIR__ . '/connect.php';
	$array = array();
	$sqlSP =  "SELECT * FROM Product_Name";
	$exeSP = db_exec($connect, $sqlSP) or die("Error Query [" . $sqlSP . "]");
	while ($resultSP = db_fetch_array($exeSP)) {
		array_push($array, $resultSP['pn_name']);
	}
	//$arrLength = count($array);
	
	return $array;
}

function strReplace($str)
{
	$data = "error";
	$data = str_replace("��.", '', str_replace("���.", '', str_replace("����ѷ", '', str_replace("�ѡ�Ҥ�����ʹ���", '', str_replace("�ӡѴ", '', $str)))));
	return $data;
}

function update_status_spare($status, $sn)
{

	if(trim($sn) != ""){

		require_once __DIR__ . '/connect.php';
		$sql_update = "UPDATE Spare SET sp_status = '".$status."' WHERE sp_sn = '".$sn."'  ";
		$exec = db_exec($connect, $sql_update); 
		if($exec){ 
			//return "ok";
		}else{
			//return "fail";
		}
		db_close($connect);

	}
	
}

function update_status_job($status, $id)
{

	require_once __DIR__ . '/connect.php';
	$sql = "UPDATE Transac_repair SET trp_status_job = '".$status."' WHERE trp_id = ".$id."  ";
	$exec = db_exec($connect, $sql); 
	if($exec){ 
		//return "ok";
	}else{
		//return "fail";
	}
	db_close($connect);
	
}

function logistisc()
{
	$array = array();

	array_push($array, "TNT");
	array_push($array, "SCG");
	array_push($array, "Kerry");
	array_push($array, "Post Office");
	array_push($array, "J And T");
	array_push($array, "Best Express");
	array_push($array, "Customer");
	array_push($array, "Emp");
	array_push($array, "Other");

	return $array;

}

function formatPhoneNumber($phoneNumber)
{
	$phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
	if (strstr($phoneNumber, "02")) {
		if (strpos($phoneNumber, "02") == 0) {
			$areaCode = substr($phoneNumber, 0, 2);
			$nextThree = substr($phoneNumber, 3, 3);
			$lastFour = substr($phoneNumber, 5, strlen($phoneNumber));
			$phoneNumber = $areaCode . "-" . $nextThree . '-' . $lastFour;
		}
	} else {

		if (strlen($phoneNumber) > 10) {
			$areaCode = substr($phoneNumber, 0, 3);
			$nextThree = substr($phoneNumber, 3, 3);
			$lastFour = substr($phoneNumber, 6, strlen($phoneNumber));

			$phoneNumber = $areaCode . '-' . $nextThree . '-' . $lastFour;
		} else if (strlen($phoneNumber) == 10) {
			$areaCode = substr($phoneNumber, 0, 3);
			$nextThree = substr($phoneNumber, 3, 3);
			$lastFour = substr($phoneNumber, 6, 4);

			$phoneNumber = $areaCode . '-' . $nextThree . '-' . $lastFour;
		} else if (strlen($phoneNumber) == 9) {
			$oneThree = substr($phoneNumber, 0, 3);
			$nextThree = substr($phoneNumber, 3, 3);
			$lastThree = substr($phoneNumber, 6, 3);

			$phoneNumber = $oneThree . "-" . $nextThree . '-' . $lastThree;
		}
	}
	return $phoneNumber;
}

function cal_tracking_due($date)
{
	if($date == ""){
		return "<span style='color:#0033FF'>�͵Դ���</span>";
	}
	$day_send = date("Y-m-d", strtotime($date));
	list($syear, $smonth, $sday) = explode("-", $day_send);       
	list($tyear, $tmonth, $tday) = explode("-", date("Y-m-d"));  
	$mday_send = mktime(0, 0, 0, $smonth, $sday, $syear);
	$mnow = mktime(0, 0, 0, $tmonth, $tday, $tyear);
	if($mnow > $mday_send){
		$mage = ($mnow - $mday_send);
	}else{
		$mage = ($mday_send - $mnow);
	}
	
	$u_y = date("Y", $mage) - 1970;
	$u_m = date("m", $mage) - 1;
	$u_d = date("d", $mage) - 1;
	$send = "";

	if($u_y <= 0 && $u_m <= 0 && $u_d <= 0){
		$send =  $u_d . " �ѹ ";
	}else{

		if ($u_y > 0) {
			if ($u_m > 0 && $u_d <= 0) {
				$send =  $u_y . " �� " . $u_m . " ��͹ ";
			} else if ($u_m <= 0 && $u_d > 0) {
				$send =  $u_y . " �� " . $u_d . " �ѹ ";
			} else {
				$send =  $u_y . " �� " . $u_m . " ��͹ " . $u_d . " �ѹ ";
			}
		} else if ($u_m > 0) {
			if ($u_d <= 0) {
				$send =  $u_m . " ��͹ ";
			} else {
				$send =  $u_m . " ��͹ " . $u_d . " �ѹ ";
			}
		} else if ($u_d > 0) {
			$send =  $u_d . " �ѹ ";
		} else {
			$send =  $u_y . " �� " . $u_m . " ��͹ " . $u_d . " �ѹ ";
		}

	}

	if($mnow > $mday_send){
		$send = "<span style='color:#FF0000'>Past due ".$send." </span>";
	}else if($u_d < 3){
		$send = "<span style='color:#FF0000'>Next due ".$send." </span>";
	}else{
		$send = "Next due ".$send;
	}

	return $send;
}

function check_duplicateSpare($sn)
{

	require_once __DIR__ . '/connect.php';
	$sql = "SELECT * FROM Spare WHERE sp_sn = '".$sn."' ";
	$exec = db_exec($connect, $sql); 
	$result = db_fetch_array($exec);
	if($result != ""){ 
		return "duplicate";
	}else{
		return "ok";
	}
	db_close($connect);
	
}


?>

