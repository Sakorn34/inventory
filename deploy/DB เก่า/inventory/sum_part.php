<?
	//session_start();
require "connect.php";
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');

$search = "";
if (isset($_GET['pre_date'])) {
  $search = $_GET['pre_date'];
}


if (isset($search)) {
 // echo '<script>alert(". $search . ")</script>';
  $sql = "SELECT * FROM part ORDER BY part_id";
  $exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]");
  $data = array();
  while ($myRow = odbc_fetch_array($exec)) {
    $data[] = $myRow;
  }
  
  odbc_close($connect);
  echo json_encode($data);

}
