<?
$DsnName = "inventory";
$UserName = "";
$Password = "";
$arr_username = array();
$arr_password = array();


date_default_timezone_set('Asia/Bangkok');

$db = 'D:\AppServ\www\inventory\_db\inventory.mdb';
// ODBC Connect
$connect = odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq=$db", null, null);

//$connect = odbc_connect($DsnName,$UserName,$Password) ;
//$conn_sst = odbc_connect("salesystem2", $UserName, $Password);

$sql_user = "SELECT * FROM users";
$exec_user = odbc_exec($connect,$sql_user) or die ("Error Query [".$sql_user."]");	
				
while($result_user=odbc_fetch_array($exec_user)){
    
   array_push( $arr_username, $result_user["username"]);
   array_push( $arr_password, $result_user["password"]);
}

//print_r ($arr_username);
//echo "I like " . $arr_username[0] . ", " . $arr_username[1] . " and " . $arr_username[2] . ".";

?>
