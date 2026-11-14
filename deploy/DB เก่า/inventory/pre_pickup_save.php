<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<META HTTP-EQUIV="Refresh"  CONTENT="1; URL='pickup_overview.php' "> 
</head>
<body>
<?

// ini_set('display_errors', 1);
// error_reporting(~0);

$date_pickup = $_GET['pre_date'];
$pre_by = $_GET['pre_by'];
$pre_amount;
$pre_product = iconv("UTF-8", "TIS-620", $_GET['pre_product']);
$pre_type = iconv("UTF-8", "TIS-620", $_GET['pre_type']);
$pre_remarks = iconv("UTF-8", "TIS-620", $_GET['pre_remarks']);


echo "Record Date = ".$date_pickup. "<br>";
echo "Record by = " .$pre_by. "<br>";
echo "Record amount = " .$pre_amount. "<br>";
echo "Record product = " .$pre_product. "<br>";
$pre_id = date("ymd-His");

if (isset($pre_product)) {

	//$sql = "SELECT * FROM part WHERE part_product LIKE '%" . $pre_product . "%' ";
	
	$sql = "SELECT * FROM part  INNER JOIN production_formula ON part.[part_id] = production_formula.[part_id]  WHERE production_formula.[pd_id] =  " . $pre_product . " ";
	$exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
	$num = 1;
	while ($result = odbc_fetch_array($exec)) {
		echo "No. ".$num." ".$result["part_name"]."<br>";
		
//		$pre_amount = $_GET['pre_amount'];
		
		if( $result['part_id'] == '57' ) {
			$pre_amount =  $_GET['pre_amount'] * 2;
		}else if( $result['part_id'] == '58' ){
			$pre_amount =  $_GET['pre_amount'] / 2;
		}else{
			$pre_amount =  $_GET['pre_amount'];
		}

		$sql = "INSERT INTO pre_stock (pre_id, pre_date, pre_product, pre_part, pre_type, pre_amount, pre_by ,pre_remarks 
		, ts_mtn_number, pre_check, pre_status) 
		VALUES ('" . $pre_id . "','" . $date_pickup . "','" . $result["pd_name"]. "' ,'" .$result["part_id"] . "' ,'" .$pre_type. "'
		,'" . $pre_amount . "','" . $pre_by . "' ,'" . $pre_remarks . "' , '' , 'NO', 'pre')";
	$query = odbc_exec($connect, $sql);
	}
}else{
	echo "Not found";
}

/*

for ($i = 1; $i <= (int)$_GET["count_sp"]; $i++) {
	if (isset($_GET["pd_sp$i"])) {
		if ($_GET["pd_sp$i"] != "" && $_GET["sn_sp$i"] != "" && $_GET["amount_sp$i"] != "") {
			$sql = "INSERT INTO pre_stock (pre_date, pre_product, pre_part,pre_type, pre_amount, pre_by ,pre_remarks) 
				VALUES ('" . $_GET["log_date"] . "','" . $_GET["pd_sp$i"] . "' ,'" . $_GET["sn_sp$i"] . "' ,'" . $_GET["log_type"] . "'
				,'" . $_GET["amount_sp$i"] . "','" . $_GET["log_by"] . "' ,'" . $_GET["log_remarks"] . "')";
			$query = odbc_exec($connect, $sql);
		}
	}
}*/
if ($query) {
	echo "OK";
} else {
	echo "Error Save ???? [" . $sql . "] ";
}

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	date_default_timezone_set("Asia/Bangkok");

	$sToken = "07Z2gxkz9ERbt5yYPqI5hnAlVsUymtCb7LQqKsOM8pG";
	
	//  LPTgUXeNKKN5lSUgseEOkMvNwqrG1DSPL6PRGge6ljf  // token ����� bit x 3
	//  bDCl7cpM3gdHyFQPqtUq8aWpL5zDCo80pYrDgylNats  // token ����� NOTE
	
	/*
����绹�� convert ������ �������
https://meyerweb.com/eric/tools/dencoder/
*/

	$sMessage = "\r\n%E0%B8%A1%E0%B8%B5%E0%B8%A3%E0%B8%B2%E0%B8%A2%E0%B8%81%E0%B8%B2%E0%B8%A3%E0%B9%80%E0%B8%9A%E0%B8%B4%E0%B8%81%E0%B8%82%E0%B8%AD%E0%B8%87%E0%B9%83%E0%B8%AB%E0%B8%A1%E0%B9%88\r\n%E0%B8%82%E0%B8%AD%E0%B9%80%E0%B8%9A%E0%B8%B4%E0%B8%81%E0%B9%82%E0%B8%94%E0%B8%A2%20.... ".$pre_by." \r\n
	%E0%B8%81%E0%B8%94%E0%B8%A5%E0%B8%B4%E0%B8%87%E0%B8%84%E0%B9%8C%E0%B8%94%E0%B8%B9%E0%B8%A3%E0%B8%B2%E0%B8%A2%E0%B8%A5%E0%B8%B0%E0%B9%80%E0%B8%AD%E0%B8%B5%E0%B8%A2%E0%B8%94\r\n\r\n http://192.168.2.199/inventory/pickup_overview.php";
	

	$chOne = curl_init(); 
	curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify"); 
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0); 
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0); 
	curl_setopt( $chOne, CURLOPT_POST, 1); 
	curl_setopt( $chOne, CURLOPT_POSTFIELDS, "message=".$sMessage); 
	$headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
	curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers); 
	curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1); 
	$result = curl_exec( $chOne ); 

	//Result error 
	if(curl_error($chOne)) 
	{ 
		echo 'error:' . curl_error($chOne); 
	} 
	else { 
		$result_ = json_decode($result, true); 
		echo "status : ".$result_['status']; echo "message : ". $result_['message'];
	} 
	curl_close( $chOne );   
	
	
odbc_close($connect);

?>
</body>
</html>
