<?php
// สมมุติว่า URL เป็น: example.com/index.php?name=John&age=25
$no = $_GET['no_repair'];  // รับค่าจากตัวแปร name
$detail = $_GET['detail'];    // รับค่าจากตัวแปร age
$list_part = $_GET['list_repair'];  // รับค่าจากตัวแปร name

echo "รายละเอียดการซ่อม: $no <br>";
echo "รายละเอียดการซ่อม: $detail <br>";
echo "รายละเอียดการซ่อม: $list_part <br>";


?>

