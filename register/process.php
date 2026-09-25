<?php
/**
 * ลงทะเบียนและตรวจสอบ username ซ้ำ (ใช้ MariaDB ผ่าน connect.php)
 */

require dirname(__DIR__) . '/connect.php';

header('Content-Type: text/plain; charset=UTF-8');

if (isset($_POST['username'])) {
    $username = db_escape($connect, trim($_POST['username']));
    $sql = "SELECT user_id FROM users WHERE username = '" . $username . "' LIMIT 1";
    $results = db_exec($connect, $sql);

    if ($results && db_num_rows($results) > 0) {
        echo 'taken';
    } else {
        echo 'not_taken';
    }
    exit();
}

if (isset($_POST['save'])) {
    $username = db_escape($connect, trim($_POST['username']));
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $sql = "SELECT user_id FROM users WHERE username = '" . $username . "' LIMIT 1";
    $results = db_exec($connect, $sql);

    if ($results && db_num_rows($results) > 0) {
        echo 'exists';
        exit();
    }

    $hash = md5($password);
    $query = "INSERT INTO users (username, password) VALUES ('" . $username . "', '" . db_escape($connect, $hash) . "')";
    $ok = db_exec($connect, $query);

    echo $ok ? 'Saved' : 'Error';
    exit();
}

