<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<META HTTP-EQUIV="Refresh"  CONTENT="2; URL='setup_overview.php' "> 
</head>
<body>
<?

ini_set('display_errors', 1);
error_reporting(~0);
$log_date = date("ymd-His");
//echo "log Date = ".$log_date."<br>";


if(isset($_GET["setup_site"]))
{
		$sql = "INSERT INTO setup_log 
		(setup_id, setup_po, setup_name, setup_site, setup_security, setup_product,
		setup_amount, setup_status,  setup_name_update,
		setup_remarks, setup_user, setup_tel, setup_email) 
		VALUES ('$log_date','".$_GET["setup_po"]."' , '".$_GET["setup_name"]."' ,'".$_GET["setup_site"]."'
		,'".$_GET["setup_security"]."' ,'".$_GET["setup_product"]."' ,'".$_GET["setup_amount"]."'
		,'".$_GET["setup_status"]."','".$_GET["setup_name_update"]."','".$_GET["setup_remarks"]."'
		,'".$_GET["setup_user"]."','".$_GET["setup_tel"]."'	,'".$_GET["setup_email"]."'
		)";
		$query = odbc_exec($connect,$sql);

		if($query){ 

			$sql = "INSERT INTO setup_pd 
			(setup_id, setup_pd_plus, setup_pd_m, setup_pd_scanner, setup_pd_scanwireless, setup_pd_suppaper, setup_pd_printer, 
			setup_pd_cardcam, setup_pd_smc, setup_pd_magnetic, setup_pd_indoor, setup_pd_outdoor, setup_pd_a4,
			setup_pd_router, setup_pd_mobile, setup_pd_bitstampg, setup_pd_bitstampf, setup_pd_numeric, 
			setup_pd_ewritter, setup_pd_visitorcard, setup_pd_visitorrfid, setup_pd_switchconfirm) 
			VALUES ('$log_date', '".$_GET["setup_pd_plus"]."' ,'".$_GET["setup_pd_m"]."', '".$_GET["setup_pd_scanner"]."'
			,'".$_GET["setup_pd_scanwireless"]."', '".$_GET["setup_pd_suppaper"]."' ,'".$_GET["setup_pd_printer"]."'
			,'".$_GET["setup_pd_cardcam"]."' ,'".$_GET["setup_pd_smc"]."', '".$_GET["setup_pd_magnetic"]."'
			, '".$_GET["setup_pd_indoor"]."','".$_GET["setup_pd_outdoor"]."', '".$_GET["setup_pd_a4"]."'
			, '".$_GET["setup_pd_router"]."', '".$_GET["setup_pd_mobile"]."'
			,'".$_GET["setup_pd_bitstampg"]."', '".$_GET["setup_pd_bitstampf"]."', '".$_GET["setup_pd_numeric"]."'
			,'".$_GET["setup_pd_ewritter"]."' ,'".$_GET["setup_pd_visitorcard"]."' ,'".$_GET["setup_pd_visitorrfid"]."'
			,'".$_GET["setup_pd_switchconfirm"]."'
			)";
			$query = odbc_exec($connect,$sql);

		} else { 
			echo "Error save setup_log [".$sql."] "; 
		}
			
}

if($query){ 
	echo "บันทึกสำเร็จ ";
} else { 
	echo "Error Save ???? [".$sql."] "; 
}
odbc_close($connect);

?>
</body>
</html>
