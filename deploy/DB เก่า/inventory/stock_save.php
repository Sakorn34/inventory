<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<META HTTP-EQUIV="Refresh"  CONTENT="1; URL='stock_overview.php' "> 
</head>
<body>
<?

ini_set('display_errors', 1);
error_reporting(~0);

echo "Record Date = ".$_GET['log_date']."<br>";
echo "Record by = ".$_GET['log_by']."<br>";
echo "Record count = ".$_GET['count_sp']."<br>";

for ($i = 1; $i<= (int)$_GET["count_sp"]; $i++){
	if(isset($_GET["pd_sp$i"]))
	{
		if($_GET["pd_sp$i"] != "" && $_GET["sn_sp$i"] != "" && $_GET["amount_sp$i"] != "" )
		{
			$part_id = $_GET["sn_sp$i"];
			
			$sql_part1 = "SELECT * FROM part WHERE part_id = ".$part_id." ";
			$query_part1 = odbc_exec($connect, $sql_part1) or die("Error Query [" . $sql_part1 . "]");
			$result_part1= odbc_fetch_array($query_part1);
			
			$sql_pd = "SELECT * FROM product WHERE pd_id = ".$_GET["pd_sp$i"]." ";
			$query_pd = odbc_exec($connect, $sql_pd) or die("Error Query [" . $sql_pd . "]");
			$result_pd= odbc_fetch_array($query_pd);
			

			$sql = "INSERT INTO log_stock (log_date, log_product, log_part, log_type, log_amount, log_by, log_price, log_vendor, log_remarks) 
				VALUES ('".$_GET["log_date"]."','".$result_pd['pd_name']."' ,'".$result_part1['part_name']."' ,'".$_GET["log_type"]."'
				,'".$_GET["amount_sp$i"]."','".$_GET["log_by"]."','".$_GET["price_sp$i"]."', '".$_GET["vendor_com"]."', '".$_GET["remarks_sp$i"]."'  )";
				
				// เพิ่มข้อมูลเข้า Log stock ไว้เรียกรายงานสรุปย้อนหลังได้
				//
				
			$query = odbc_exec($connect,$sql);
			if($query){ 
				echo "OK";
			} else { 
				echo "Error Save ???? [".$sql."] "; 
			}
			
			
			// Query เพื่อคำนวณ diff stock 
			$part_amount;
			$sql_part = "SELECT * FROM part WHERE part_id = ".$part_id." ";
			$query_part = odbc_exec($connect, $sql_part) or die("Error Query [" . $sql_part . "]");
			while ($result_part = odbc_fetch_array($query_part)) {
				
					$log_amount = $_GET["amount_sp$i"];
					
					$part_amount = $result_part['part_amount'];
					$total_amount = $part_amount + $log_amount;
					$diff =  $total_amount -  $result_part['part_min_alert'];
					
					echo "TOTAL Amount ". $result_part['part_name']." = ".$total_amount."   Diff = ".$diff." <br>";
					
					$status_stock = "";
					if($diff > 0){
						$status_stock = "ปกติ";
					}else if($diff <= 0  && $result_part['part_status_purchase'] != "สั่งซื้อแล้ว"){
						$status_stock = "ยังไม่ได้สั่งซื้อ";
					}
					
					// Update ใน Table part 
					$sql_update_part = "UPDATE part SET part_amount = ".$total_amount."  ,  part_diff_stock = ".$diff."  ,  part_status_purchase = '".$status_stock."' WHERE  part_id = ".$part_id." ";
					$query_update_part = odbc_exec($connect, $sql_update_part)  or die("Error Query [" . $sql_update_part . "]");
					if($query_update_part){ 
						echo "Update part Success <br>";
					} else { 
						echo "Error Update ???? [".$sql_update."] <br>"; 
					}
			
			}
			
		}
	}
}

odbc_close($connect);

?>
</body>
</html>
