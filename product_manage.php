<?php
/**
 * หน้าจัดการชื่อ Product สำหรับ dropdown ในระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือเพิ่มชื่อ Product ลงตาราง `product` เพื่อให้ dropdown
 * Product ในหน้าจัดชุดรายการเบิกและหน้าเบิกของเลือกใช้งานได้ โดยใช้ prepared
 * statement, validate input, escape output และ CSRF token สำหรับการบันทึก
 *
 * Flow สั้น:
 * 1. ผู้ใช้กรอกชื่อ Product ใหม่
 * 2. ระบบตรวจชื่อซ้ำและสร้าง `pd_id` ถัดไปจากข้อมูลปัจจุบัน
 * 3. ระบบบันทึก Product แล้วแสดงในรายการด้านล่าง
 *
 * ตัวอย่าง: เพิ่ม `bitVisitor Accessories` เพื่อให้ปรากฏใน dropdown Product
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/function.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// ─ Helpers ──────────────────────────────────────────────────────────────────

/**
 * escape ข้อความก่อน render HTML เพื่อลดความเสี่ยง XSS
 *
 * @param mixed $value ค่าที่ต้องแสดงผลบนหน้าเว็บ
 * @return string ข้อความที่ผ่าน htmlspecialchars แล้ว
 */
function product_manage_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * เตรียมและ execute SQL ผ่าน mysqli prepared statement
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL ที่ใช้ placeholder เมื่อมี input
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind เข้า statement
 * @return mysqli_stmt statement ที่ execute แล้ว
 */
function product_manage_execute($conn, $sql, $types = '', $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('เตรียม SQL ไม่สำเร็จ');
    }

    if ($types !== '') {
        $bindParams = array($stmt, $types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('bind SQL parameter ไม่สำเร็จ');
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('execute SQL ไม่สำเร็จ');
    }

    return $stmt;
}

/**
 * ดึงผลลัพธ์ทั้งหมดจาก prepared statement เป็น associative array
 *
 * @param mysqli_stmt $stmt statement ที่ execute แล้ว
 * @return array รายการแถวข้อมูล
 */
function product_manage_fetch_all($stmt)
{
    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) {
        return array();
    }

    $fields = array();
    $row = array();
    $bind = array($stmt);
    while ($field = mysqli_fetch_field($meta)) {
        $fields[] = $field->name;
        $row[$field->name] = null;
        $bind[] = &$row[$field->name];
    }
    call_user_func_array('mysqli_stmt_bind_result', $bind);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $item = array();
        foreach ($fields as $fieldName) {
            $item[$fieldName] = $row[$fieldName];
        }
        $rows[] = $item;
    }

    mysqli_free_result($meta);

    return $rows;
}

/**
 * execute SQL แล้วคืนผลลัพธ์ทั้งหมดแบบ array
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $sql คำสั่ง SQL
 * @param string $types ชนิดข้อมูลสำหรับ bind_param
 * @param array $params ค่าที่ต้อง bind เข้า statement
 * @return array รายการแถวข้อมูล
 */
function product_manage_query($conn, $sql, $types = '', $params = array())
{
    $stmt = product_manage_execute($conn, $sql, $types, $params);
    $rows = product_manage_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * สร้างหรืออ่าน CSRF token สำหรับฟอร์มบันทึก
 *
 * @return string token ปัจจุบันใน session
 */
function product_manage_csrf_token()
{
    if (empty($_SESSION['product_manage_csrf'])) {
        $_SESSION['product_manage_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['product_manage_csrf'];
}

/**
 * ตรวจ CSRF token จาก POST ก่อนแก้ไขข้อมูล
 *
 * @param string $token token ที่ส่งมาจากฟอร์ม
 * @return void ไม่มี return value และ throw exception เมื่อ token ไม่ถูกต้อง
 */
function product_manage_validate_csrf($token)
{
    $sessionToken = isset($_SESSION['product_manage_csrf']) ? $_SESSION['product_manage_csrf'] : '';
    if (!is_string($token) || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

/**
 * normalize ชื่อ Product ก่อนบันทึก
 *
 * @param mixed $value ชื่อ Product จากฟอร์ม
 * @return string ชื่อที่ trim และตัด whitespace ซ้ำแล้ว
 */
function product_manage_normalize_name($value)
{
    $name = trim((string) $value);
    $name = preg_replace('/\s+/u', ' ', $name);

    return $name === null ? '' : $name;
}

/**
 * นับความยาวข้อความโดยรองรับ server ที่ไม่ได้เปิด mbstring
 *
 * @param string $value ข้อความที่ต้องนับความยาว
 * @return int จำนวนตัวอักษรหรือ byte length เมื่อไม่มี mbstring
 */
function product_manage_strlen($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

/**
 * ดึงรายการ Product ทั้งหมดเพื่อแสดงด้านล่างฟอร์ม
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ Product
 */
function product_manage_get_products($conn)
{
    return product_manage_query(
        $conn,
        "SELECT
            p.`pd_id`,
            p.`pd_name`,
            COUNT(pf.`part_id`) AS `part_count`
        FROM `product` p
        LEFT JOIN `production_formula` pf ON p.`pd_id` = pf.`pd_id`
        GROUP BY p.`pd_id`, p.`pd_name`
        ORDER BY p.`pd_name` ASC"
    );
}

/**
 * ตรวจว่ามีชื่อ Product นี้อยู่แล้วหรือไม่
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $productName ชื่อ Product ที่ต้องการตรวจ
 * @return bool true เมื่อพบชื่อซ้ำ
 */
function product_manage_product_exists($conn, $productName)
{
    $rows = product_manage_query(
        $conn,
        'SELECT `pd_id` FROM `product` WHERE LOWER(`pd_name`) = LOWER(?) LIMIT 1',
        's',
        array($productName)
    );

    return count($rows) > 0;
}

/**
 * ดึง pd_id ถัดไปสำหรับตาราง product ที่ schema เดิมไม่ได้ใช้ auto increment
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return int id ถัดไปสำหรับ insert
 */
function product_manage_next_product_id($conn)
{
    $rows = product_manage_query(
        $conn,
        'SELECT COALESCE(MAX(`pd_id`), 0) + 1 AS `next_id` FROM `product` FOR UPDATE'
    );

    return isset($rows[0]['next_id']) ? (int) $rows[0]['next_id'] : 1;
}

/**
 * เพิ่มชื่อ Product ใหม่ลง dropdown
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param string $productName ชื่อ Product ใหม่
 * @return int pd_id ที่สร้างใหม่
 */
function product_manage_add_product($conn, $productName)
{
    $name = product_manage_normalize_name($productName);
    if ($name === '') {
        throw new RuntimeException('กรุณากรอกชื่อ Product');
    }

    if (product_manage_strlen($name) > 255) {
        throw new RuntimeException('ชื่อ Product ต้องไม่เกิน 255 ตัวอักษร');
    }

    if (product_manage_product_exists($conn, $name)) {
        throw new RuntimeException('มีชื่อ Product นี้อยู่แล้ว');
    }

    mysqli_begin_transaction($conn);
    try {
        $nextId = product_manage_next_product_id($conn);
        $stmt = product_manage_execute(
            $conn,
            'INSERT INTO `product` (`pd_id`, `pd_name`, `pd_amount`, `pd_min_alert`) VALUES (?, ?, 0, 0)',
            'is',
            array($nextId, $name)
        );
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);

        return $nextId;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

// ─ Request Handling ──────────────────────────────────────────────────────────

$message = '';
$messageType = 'info';
$productName = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        product_manage_validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '');
        $productName = product_manage_normalize_name(isset($_POST['pd_name']) ? $_POST['pd_name'] : '');
        $newProductId = product_manage_add_product($connect, $productName);
        $message = 'เพิ่ม Product สำเร็จ: ' . $productName . ' (ID: ' . $newProductId . ')';
        $messageType = 'success';
        $productName = '';
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

$csrfToken = product_manage_csrf_token();
$products = product_manage_get_products($connect);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มชื่อ Product</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
    <link type="text/css" rel="stylesheet" href="css.css">
    <style>
        body {
            margin: 0;
            color: #555;
            font-family: Arial, Tahoma, sans-serif;
            background: #fff;
        }

        .navbar {
            background-color: #E9E9E9;
            overflow: hidden;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 10;
        }

        .content {
            max-width: 900px;
            margin: 120px auto 40px;
            padding: 0 20px;
            font-size: 16px;
        }

        .panel {
            border: 1px solid #d8eee9;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            background: #fff;
        }

        .page-title {
            display: inline-block;
            border-radius: 10px;
            background: #FFCCCC;
            padding: 7px 14px;
            margin-bottom: 18px;
            color: #555;
        }

        .form-row {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        input[type="text"],
        button,
        .link-button {
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 15px;
            background-color: #e8f5e9;
        }

        input[type="text"] {
            min-width: 320px;
        }

        button,
        .link-button {
            color: #145c2c;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
        }

        .message {
            border-radius: 8px;
            margin: 12px 0;
            padding: 10px 12px;
        }

        .message--success {
            background: #e5f7e8;
            color: #1f6b2c;
        }

        .message--error {
            background: #fde7e7;
            color: #a22222;
        }

        table {
            border-collapse: collapse;
            margin-top: 18px;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #e8e8e8;
            padding: 9px;
            text-align: left;
        }

        th {
            background: #f4fbf8;
        }
    </style>
</head>
<body>
    <div class="navbar"><?php require __DIR__ . '/title-h.php'; ?></div>

    <main class="content">
        <section class="panel">
            <h1 class="page-title">เพิ่มชื่อ Product</h1>

            <?php if ($message !== '') { ?>
                <div class="message message--<?php echo product_manage_h($messageType); ?>">
                    <?php echo product_manage_h($message); ?>
                </div>
            <?php } ?>

            <form method="post" action="product_manage.php">
                <input type="hidden" name="csrf_token" value="<?php echo product_manage_h($csrfToken); ?>">
                <div class="form-row">
                    <label for="pd_name"><b>ชื่อ Product :</b></label>
                    <input type="text" name="pd_name" id="pd_name" value="<?php echo product_manage_h($productName); ?>" maxlength="255" required>
                    <button type="submit"><i class="fa fa-plus-circle"></i> เพิ่ม Product</button>
                    <a class="link-button" href="formula_manage.php"><i class="fa fa-sitemap"></i> กลับไปจัดชุดรายการเบิก</a>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th style="width: 100px;">Product ID</th>
                        <th>ชื่อ Product</th>
                        <th style="width: 160px;">จำนวน Part ในชุด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product) { ?>
                        <tr>
                            <td><?php echo product_manage_h($product['pd_id']); ?></td>
                            <td><?php echo product_manage_h($product['pd_name']); ?></td>
                            <td><?php echo product_manage_h($product['part_count']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
