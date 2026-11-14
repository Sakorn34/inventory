<?php 
    $DsnName = "inventory";
    $UserName = "";
    $Password = "";
    $db = 'D:\AppServ\www\inventory\_db\inventory.mdb';
    // ODBC Connect
    $connect = odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq=$db", null, null);
    

    if (isset($_POST['username'])) {
        $username = $_POST['username'];
        $sql = "SELECT * FROM users WHERE username = '$username' ";
        $results = odbc_exec($connect, $sql);
        if (odbc_num_rows($results) > 0) {
            echo 'taken';
        } else {
            echo 'not_taken';
        }
        exit();
    }

    if (isset($_POST['save'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $sql = "SELECT * FROM users WHERE username = '$username' ";
        $results = odbc_exec($db, $sql);
        if (odbc_num_rows($results) > 0) {
            echo "exists";
            exit();
        } else {
            $query = "INSERT INTO users (username, password) VALUES ('$username',  '".md5($password)."')";
            $results = odbc_exec($db, $query);
            echo 'Saved';
            exit();
        }
    }


?>