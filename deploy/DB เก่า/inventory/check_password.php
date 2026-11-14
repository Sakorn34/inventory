<?php 
include('connect.php');
   // echo "check_password : ".$_POST['password'];


if (isset($_POST['password'])) {
    $password = $_POST['password'];
    $sql_check = "SELECT * FROM users WHERE password = '$password'  ";
    $exec = odbc_exec($connect, $sql_check) or die("Error Query [" . $sql_check . "]");

    while (odbc_fetch_row($exec)) {
          //  echo odbc_result($exec, "password");
        $query = odbc_result($exec, "password");
        $name = odbc_result($exec, "username");
        if ($query == "") {
            echo "";
        } else {
            echo $name;
        }
    }

    /*    while ($result = odbc_fetch_array($exec)){
            
            if($result['password'] == ""){
                echo "not_taken";
            }else{
                echo "taken";
            }
            
        }*/
     /*   if (odbc_num_rows($results) > 0) {
            echo 'taken';
        } else {
            echo 'not_taken';
        }*/
    exit();
}

$close($exec);


?>