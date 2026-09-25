<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<!--<META HTTP-EQUIV="Refresh"  CONTENT="60;URL='' "> --> 
</head>
<body>
<?
//ใช้นับจำนวน ตัวอักษรที่กำหนดใน string
//substr_count ( "bamboolabcode", "b" );
//update_everything.php?table=Tablename&id=id_name$12&field=field_name1$value1@field_name2$value2@field_nameN$valueN
	$tablename = $_GET['table'];
	$id = explode("$", $_GET['id']);
	$field_arr = explode("@", $_GET['field']);
	echo $tablename."<br>";
	echo count($field_arr)."<br>";
	
	$sql = "UPDATE ".$tablename." SET ";
	$i = 0;
	foreach ($field_arr as $value) {
		$i++;
		$t_field = array();
  		$t_field =  explode("$", $value);
		if($i == 1){
			$sql .= $t_field[0]." = '".$t_field[1]."' ";
		}else{
			$sql .= ", ".$t_field[0]." = '".$t_field[1]."' ";
		}
		unset($t_field);
	}

$sql .= "WHERE ".$id[0]." = ".$id[1]."  ";

echo $sql;
/*
$exec = db_exec($connect, $sql_update); 
if($exec){ 
	
} else { 
	echo "Error Save ???? [".$sql."] "; 
}
db_close($connect);
*/
?>
</body>
</html>

