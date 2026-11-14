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

ini_set('display_errors', 1);
error_reporting(~0);
$count = "";
$id_ma="";
$pre_id = "";
$check = "";
$pre_type = "";

if (isset($_GET['count'])) {
	$count = $_GET['count'];
	$id_ma = $_GET['ts_mtn_number'];
	$pre_id = $_GET['pre_id'];
	$pre_type = $_GET['pre_type'];
}

$log_date = date("ymd-His");
echo "log Date = ".$log_date."<br>";
echo "id ma = ".$id_ma."<br>";
echo "Record count = ".$count."<br>";

for ($i = 1; $i<= (int)$count; $i++){
	if(isset($count))
	{
		$check_loop = $_GET["check$i"];
		$log_part = $_GET["log_part$i"];
		
		$part_id = $_GET["log_part$i"];

		$sql_part1 = "SELECT * FROM part WHERE part_id = ".$part_id." ";
		$query_part1 = odbc_exec($connect, $sql_part1) or die("Error Query [" . $sql_part1 . "]");
		$result_part1= odbc_fetch_array($query_part1);
		/*
		$sql_pd = "SELECT * FROM product WHERE pd_id = ".$_GET["log_product$i"]." ";
		$query_pd = odbc_exec($connect, $sql_pd) or die("Error Query [" . $sql_pd . "]");
		$result_pd= odbc_fetch_array($query_pd);		
		*/
				
		if($_GET["check$i"] == "YES")
		{
		//	$remark = $_GET["pre_remarks"];
			$sql = "INSERT INTO log_stock (pre_id, log_date, log_product, log_part, log_type, log_amount, 
			log_by ,log_remarks ,ts_mtn_number)  VALUES 
			('".$pre_id."','".$log_date."','".$_GET["log_product$i"]."' ,'".$result_part1['part_name']."'  ,'".$pre_type."','".$_GET["log_amount$i"]."','".$_GET["log_by"]."' 
				,'".$_GET["log_remarks$i"]."' ,'".$id_ma."')";

			//echo $sql;
			//echo;
			
			$query = odbc_exec($connect, $sql);
			if($query){ 
				echo "Insert log_stock Success<br>";
			} else {
				echo "Error Save ???? [".$sql."] <br> "; 
			}

			$sql_update = "UPDATE pre_stock SET pre_status = 'pickup' WHERE pre_id = '".$pre_id."' ";
			$query_update = odbc_exec($connect, $sql_update);
			if($query_update){ 
				echo "Update pre_stock Success <br>";
			} else { 
				echo "Error Update ???? [".$sql_update."] <br>"; 
			}
			
			$part_amount;
			$sql_part = "SELECT * FROM part WHERE part_id = ".$log_part." ";
			$query_part = odbc_exec($connect, $sql_part) or die("Error Query [" . $sql_part . "]");
			while ($result_part = odbc_fetch_array($query_part)) {
				
					$log_amount = $_GET["log_amount$i"];
					
					$part_amount = $result_part['part_amount'];
					$total_amount = $part_amount - $log_amount;
					$diff =  $total_amount -  $result_part['part_min_alert'];
					
					echo $log_part.") TOTAL Amount ". $result_part['part_name']." = ".$total_amount."  Diff = ".$diff."<br>";
					
					$status_stock = "";
					if($diff > 0){
						$status_stock = "ปกติ";
					}else if($diff <= 0  && $result_part['part_status_purchase'] != "สั่งซื้อแล้ว"){
						$status_stock = "ยังไม่ได้สั่งซื้อ";
					}
					
					$sql_update_part = "UPDATE part SET part_amount = ".$total_amount."  ,  part_diff_stock = ".$diff.",  part_status_purchase = '".$status_stock."'   WHERE  part_id = ".$log_part." ";
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
