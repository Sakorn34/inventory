<?php
header("Content-type : text/html; charset=UTF-8");

// ตรวจสอบว่ามีการส่งข้อมูลผ่าน POST หรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับข้อมูลจากฟอร์ม
    $searchTerm = $_POST['repair_no'];

    if (!empty($searchTerm)) {

        $dsn = 'inventory';       // ชื่อ DSN ที่ตั้งไว้ใน ODBC
        $username = '';           // หากไม่ใช้ username ในการเชื่อมต่อ (MS Access ไม่มี username, password จะเป็นค่าว่าง)
        $password = '';           // หากไม่ใช้ password ในการเชื่อมต่อ

        $conn = odbc_connect($dsn, $username, $password);

        if (!$conn) {
            die("ไม่สามารถเชื่อมต่อกับฐานข้อมูลได้: " . odbc_errormsg());
        }

        // สร้างคำสั่ง SQL เพื่อค้นหาคำในตาราง
        $sql = "SELECT TOP 1 * FROM pre_stock WHERE ts_mtn_number LIKE '$searchTerm'";

        // Execute query
        $result = odbc_exec($conn, $sql);

        // เช็คผลลัพธ์และแสดง
        if ($result) {
            while ($row = odbc_fetch_array($result)) {
                echo "ok";
            }
        } else {
            echo "";
        }

        // ปิดการเชื่อมต่อ
        odbc_close($conn);
    }
}
