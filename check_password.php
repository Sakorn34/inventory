<?php 
include('connect.php');
   // echo "check_password : ".$_POST['password'];


if (isset($_POST['password'])) {
    $password = db_escape($connect, $_POST['password']);
    $sql_check = "SELECT * FROM users WHERE password = '" . $password . "'";
    $exec = db_exec($connect, $sql_check);

    while (db_fetch_row($exec)) {
          //  echo db_result($exec, "password");
        $query = db_result($exec, "password");
        $name = db_result($exec, "username");
        if ($query == "") {
            echo "";
        } else {
            echo $name;
        }
    }

    /*    while ($result = db_fetch_array($exec)){
            
            if($result['password'] == ""){
                echo "not_taken";
            }else{
                echo "taken";
            }
            
        }*/
     /*   if (db_num_rows($results) > 0) {
            echo 'taken';
        } else {
            echo 'not_taken';
        }*/
    exit();
}



?>
