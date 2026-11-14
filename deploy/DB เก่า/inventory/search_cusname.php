<?php
header("Content-type : text/html; charset=TIS-620");

$con_cus = odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq=D:\AppServ\www\inventory\_db\inventory.mdb", "", "");
//$con_cus = odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq=D:\AppServ\www\maintenance\_db\maintenance.mdb", "", "");

//echo iconv( 'UTF-8', 'TIS-620', $_REQUEST["term"]);

// Check connection
//echo  "RS : ". $_REQUEST["term"];
if($con_cus == true){
//echo  "RS : ". $_REQUEST["term"];
	$i = 0;
    $sql = "SELECT vname FROM tbl_vendor WHERE vname LIKE '%".iconv( 'UTF-8', 'TIS-620', $_REQUEST["term"])."%'";
	//$sql = "SELECT pn_name FROM Product_Name WHERE pn_name LIKE '%".$_REQUEST["term"] ."%'";
	$result = odbc_exec($con_cus,$sql); //or die ("Error Query [".$sql."]")
	while($row = odbc_fetch_array($result)){
		if(strpos($row["vname"], "รักษาความปลอดภัย") == false){
			if($i <= 3){
				echo "<p>".$row["vname"]."</p>";
			}
			$i++;
		}
	}
		
}else{
	echo "<p>Connect fail..</p>";
}
 
odbc_close($con_cus);

?>

<!DOCTYPE html PUBLIC"-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns = "http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=TIS-620"> 
</head>
<body>
</body>
</html>