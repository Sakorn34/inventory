<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<META HTTP-EQUIV="Refresh"  CONTENT="0; URL='part.php' "> 
</head>
<body>
<?

$sql = "insert into log_part ";
$sql .= "(lp_date, lp_product, lp_type, lp_amount, lp_user)";
$sql .= "values ";
$sql .= "('".$_GET['lp_date']."',  '".$_GET['lp_product']."', '".$_GET['lp_type']."' , '".$_GET['lp_amount']."' , '".$_GET['lp_user']."' ) ";

$exec = odbc_exec($connect, $sql); 
if($exec){ 
	echo "OK";
} else { 
	echo "Error Save ???? [".$sql."] "; 
}
odbc_close($connect);

?>
</body>
</html>
