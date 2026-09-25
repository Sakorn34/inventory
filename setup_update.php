<?php
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<META HTTP-EQUIV="Refresh"  CONTENT="20; URL='setup_overview.php' "> 
</head>
<body>
<?php

ini_set('display_errors', 1);
error_reporting(~0);
$log_date = date("ymd-His");
//echo "log Date = ".$log_date."<br>";


if(isset($_GET["setup_site"]))
{
		$sql = "INSERT INTO setup_log";
		$sql = "UPDATE setup_log SET ";
		$sql .= "setup_name = '".$_GET["setup_name"]."' ";
		$sql .= ",setup_site = '".$_GET["setup_site"]."' ";
		$sql .= ",setup_security = '".$_GET["setup_security"]."' ";
		$sql .= ",setup_product = '".$_GET["setup_product"]."' ";
		$sql .= ",setup_amount = '".$_GET["setup_amount"]."' ";
		$sql .= ",setup_status = '".$_GET["setup_status"]."' ";
		$sql .= ",setup_name_update = '".$_GET["setup_name_update"]."' ";
		$sql .= ",setup_remarks = '".$_GET["setup_remarks"]."' ";
		$sql .= ",setup_user = '".$_GET["setup_user"]."' ";
		$sql .= ",setup_tel = '".$_GET["setup_tel"]."' ";
		$sql .= ",setup_email = '".$_GET["setup_email"]."' ";
		$sql .= "WHERE setup_id = ".$_GET['setup_id']."  ";

		$query = db_exec($connect,$sql);

		if($query){ 

			$sqlUp = "INSERT INTO setup_pd";
			$sqlUp = "UPDATE setup_pd SET ";
			$sqlUp .= "setup_pd_plus = '".$_GET["setup_pd_plus"]."' ";
			$sqlUp .= ",setup_pd_m = '".$_GET["setup_pd_m"]."' ";
			$sqlUp .= ",setup_pd_scanner = '".$_GET["setup_pd_scanner"]."' ";
			$sqlUp .= ",setup_pd_scanwireless = '".$_GET["setup_pd_scanwireless"]."' ";
			$sqlUp .= ",setup_pd_suppaper = '".$_GET["setup_pd_suppaper"]."' ";
			$sqlUp .= ",setup_pd_printer = '".$_GET["setup_pd_printer"]."' ";

			$sqlUp .= ",setup_pd_cardcam = '".$_GET["setup_pd_cardcam"]."' ";
			$sqlUp .= ",setup_pd_smc = '".$_GET["setup_pd_smc"]."' ";
			$sqlUp .= ",setup_pd_magnetic = '".$_GET["setup_pd_magnetic"]."' ";

			$sqlUp .= ",setup_pd_indoor = '".$_GET["setup_pd_indoor"]."' ";
			$sqlUp .= ",setup_pd_outdoor = '".$_GET["setup_pd_outdoor"]."' ";
			$sqlUp .= ",setup_pd_a4 = '".$_GET["setup_pd_a4"]."' ";
			$sqlUp .= ",setup_pd_router = '".$_GET["setup_pd_router"]."' ";
			$sqlUp .= ",setup_pd_mobile = '".$_GET["setup_pd_mobile"]."' ";

			$sqlUp .= ",setup_pd_bitstampg = '".$_GET["setup_pd_bitstampg"]."' ";
			$sqlUp .= ",setup_pd_numeric = '".$_GET["setup_pd_numeric"]."' ";		
			$sqlUp .= ",setup_pd_ewritter = '".$_GET["setup_pd_ewritter"]."' ";		
			$sqlUp .= ",setup_pd_visitorcard = '".$_GET["setup_pd_visitorcard"]."' ";		
			$sqlUp .= ",setup_pd_visitorrfid = '".$_GET["setup_pd_visitorrfid"]."' ";		
			$sqlUp .= ",setup_pd_bitstampf = '".$_GET["setup_pd_bitstampf"]."' ";
			$sqlUp .= ",setup_pd_switchconfirm = '".$_GET["setup_pd_switchconfirm"]."' ";
			$sqlUp .= " WHERE setup_id = '".db_escape($connect, $_GET['setup_id'])."' ";			

			$query = db_exec($connect,$sqlUp);

		} else { 
			echo "Error save setup_log [".$sql."] "; 
		}
			
}

if($query){ 
	echo "�ѹ�֡����� ";
} else { 
	echo "Error Save ???? [".$sql."] "; 
}
db_close($connect);

?>
</body>
</html>

