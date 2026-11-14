<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<META HTTP-EQUIV="Refresh"  CONTENT="2; URL='pickup_overview.php' "> 
</head>
<body>

<!-- ?????????????????? node red -->

<form id="subToNode" action="http://192.168.2.208:1880/endpoint/speak" method="get" style="display: none">    
   <input type="text" id="nameIN" name="name"><br>
</form>

<?

ini_set('display_errors', 1);
error_reporting(~0);

echo "Record Date = ".$_GET['log_date']."<br>";
echo "Record by = ".$_GET['log_by']."<br>";
echo "Record count = ".$_GET['count_sp']."<br>";

//------- ?????????????????? node red ------------------------

$pickup_by = $_GET['log_by'];

echo '<script>';
echo 'document.getElementById("nameIN").value = "' .$pickup_by . '"';
echo '</script>';

//------------------------------------------------------



$pre_id = date("ymd-His");

for ($i = 1; $i<= (int)$_GET["count_sp"]; $i++){
	if(isset($_GET["pd_sp$i"]))
	{
		if($_GET["pd_sp$i"] != "" && $_GET["sn_sp$i"] != "" && $_GET["amount_sp$i"] != "" )
		{
		
		    $part_id  = $_GET["sn_sp$i"];
		    $sqlPN = "SELECT * FROM part WHERE part_id = " .$part_id." ";
			$queryPN = odbc_exec($connect, $sqlPN);
			$resultPN =odbc_fetch_array($queryPN);
			
			$pd_name;
			$sqlPD = "SELECT * FROM product WHERE  pd_id = ".$_GET["pd_sp$i"]." ";
		
			$queryPD = odbc_exec($connect, $sqlPD);
			$resultPD =odbc_fetch_array($queryPD);
			$pd_name = $resultPD['pd_name'];
			
			if(isset($resultPN)){
				
				$sql = "INSERT INTO pre_stock (pre_id, pre_date, pre_product, pre_part, pre_type, pre_amount, pre_by ,pre_remarks ,ts_mtn_number, 
				pre_check, pre_status) 
					VALUES ('".$pre_id."','".$_GET["log_date"]."','".$pd_name."' ,'".$resultPN['part_id']."' ,'".$_GET["log_type"]."'
					,'".$_GET["amount_sp$i"]."','".$_GET["log_by"]."' ,'".$_GET["log_remarks"]."' ,'".$_GET["search_id"]."' , 'NO', 'pre')";
				$query = odbc_exec($connect,$sql);
			}else{
				$query = false;
			
			}
		}
	}
}
if($query){ 
	echo "OK";
} else { 
	echo "Error Save ???? [".$sql."] "; 
}

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	date_default_timezone_set("Asia/Bangkok");

//	$sToken = "07Z2gxkz9ERbt5yYPqI5hnAlVsUymtCb7LQqKsOM8pG";  
	
	
	//07Z2gxkz9ERbt5yYPqI5hnAlVsUymtCb7LQqKsOM8pG
	
	//  แ  // token กลุ่ม bit x 3
	//  bDCl7cpM3gdHyFQPqtUq8aWpL5zDCo80pYrDgylNats  // token กลุ่ม NOTE
	
	/*
ใช้เว็ปนี้ convert ภาษาไทย ไปใส่ในโค้ด
https://meyerweb.com/eric/tools/dencoder/
*/

	// $sMessage = "\r\n%E0%B8%A1%E0%B8%B5%E0%B8%A3%E0%B8%B2%E0%B8%A2%E0%B8%81%E0%B8%B2%E0%B8%A3%E0%B9%80%E0%B8%9A%E0%B8%B4%E0%B8%81%E0%B8%82%E0%B8%AD%E0%B8%87%E0%B9%83%E0%B8%AB%E0%B8%A1%E0%B9%88\r\n%E0%B8%82%E0%B8%AD%E0%B9%80%E0%B8%9A%E0%B8%B4%E0%B8%81%E0%B9%82%E0%B8%94%E0%B8%A2%20.... ".$_GET["log_by"]." \r\n
	// %E0%B8%81%E0%B8%94%E0%B8%A5%E0%B8%B4%E0%B8%87%E0%B8%84%E0%B9%8C%E0%B8%94%E0%B8%B9%E0%B8%A3%E0%B8%B2%E0%B8%A2%E0%B8%A5%E0%B8%B0%E0%B9%80%E0%B8%AD%E0%B8%B5%E0%B8%A2%E0%B8%94\r\n\r\n http://192.168.2.199/inventory/pickup_overview.php";

	// $chOne = curl_init(); 
	// curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify"); 
	// curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0); 
	// curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0); 
	// curl_setopt( $chOne, CURLOPT_POST, 1); 
	// curl_setopt( $chOne, CURLOPT_POSTFIELDS, "message=".$sMessage); 
	// $headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
	// curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers); 
	// curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1); 
	// $result = curl_exec( $chOne );

	// //Result error 
	// if(curl_error($chOne)) 
	// { 
	// 	echo 'error:' . curl_error($chOne); 
	// } 
	// else { 
	// 	$result_ = json_decode($result, true); 
	// 	echo "status : ".$result_['status']; echo "message : ". $result_['message'];
	// } 
	// curl_close( $chOne );   
	
odbc_close($connect);

?>

<script language="javascript1.2" type="text/javascript">
	document.getElementById('subToNode').submit();
</script>

</body>
</html>
