<?php
/**
 * หน้าจัดชุดรายการเบิก Part ตาม Product สำหรับระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือเลือก Product แล้วกำหนดรายการ Part ที่อยู่ใน
 * `production_formula` เพื่อให้หน้าเบิกของสามารถแสดง dropdown Part ได้ถูกต้อง
 * โดยใช้ prepared statement, validate input และ CSRF token สำหรับการบันทึก
 *
 * Flow สั้น:
 * 1. ผู้ใช้เลือก Product จาก dropdown
 * 2. ระบบโหลด Part ทั้งหมดและติ๊ก Part ที่อยู่ในชุดรายการเบิกปัจจุบัน
 * 3. เมื่อบันทึก ระบบลบ mapping เดิมของ Product นั้นแล้วเพิ่ม mapping ใหม่
 *
 * ตัวอย่าง: เลือก `bitVisitor Accessories` แล้วติ๊ก Part ที่ควรแสดงในหน้าเบิกของ
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
 * @param mixed $value ข้อความหรือค่าที่ต้องแสดงผลบนหน้าเว็บ
 * @return string ข้อความที่ผ่าน htmlspecialchars แล้ว
 */
function formula_manage_h($value)
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
function formula_manage_execute($conn, $sql, $types = '', $params = array())
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
function formula_manage_fetch_all($stmt)
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
function formula_manage_query($conn, $sql, $types = '', $params = array())
{
    $stmt = formula_manage_execute($conn, $sql, $types, $params);
    $rows = formula_manage_fetch_all($stmt);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * สร้างหรืออ่าน CSRF token สำหรับฟอร์มบันทึก
 *
 * @return string token ปัจจุบันใน session
 */
function formula_manage_csrf_token()
{
    if (empty($_SESSION['formula_manage_csrf'])) {
        $_SESSION['formula_manage_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['formula_manage_csrf'];
}

/**
 * ตรวจ CSRF token จาก POST ก่อนแก้ไขข้อมูล
 *
 * @param string $token token ที่ส่งมาจากฟอร์ม
 * @return void ไม่มี return value และ throw exception เมื่อ token ไม่ถูกต้อง
 */
function formula_manage_validate_csrf($token)
{
    $sessionToken = isset($_SESSION['formula_manage_csrf']) ? $_SESSION['formula_manage_csrf'] : '';
    if (!is_string($token) || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

/**
 * แปลงค่า id จาก request ให้เป็น int ที่ปลอดภัย
 *
 * @param mixed $value ค่า id จาก GET/POST
 * @return int id ที่มากกว่า 0 หรือ 0 เมื่อไม่ถูกต้อง
 */
function formula_manage_positive_int($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

    return $id === false ? 0 : (int) $id;
}

/**
 * ดึงรายการ Product ทั้งหมดสำหรับ dropdown
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ Product
 */
function formula_manage_get_products($conn)
{
    return formula_manage_query(
        $conn,
        'SELECT `pd_id`, `pd_name` FROM `product` ORDER BY `pd_name` ASC'
    );
}

/**
 * ดึง Product ตาม id เพื่อยืนยันว่ามีอยู่จริงก่อนบันทึก formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array|null ข้อมูล Product หรือ null เมื่อไม่พบ
 */
function formula_manage_get_product($conn, $productId)
{
    $rows = formula_manage_query(
        $conn,
        'SELECT `pd_id`, `pd_name` FROM `product` WHERE `pd_id` = ? LIMIT 1',
        'i',
        array($productId)
    );

    return count($rows) > 0 ? $rows[0] : null;
}

/**
 * ดึงรายการ Part ทั้งหมดสำหรับ checkbox
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array รายการ Part พร้อมหมวดหมู่และจำนวน stock
 */
function formula_manage_get_parts($conn)
{
    return formula_manage_query(
        $conn,
        "SELECT
            p.`part_id`,
            p.`part_name`,
            p.`part_amount`,
            p.`part_categories`,
            COALESCE(c.`ct_name`, '') AS `ct_name`
        FROM `part` p
        LEFT JOIN `categories_product` c ON p.`part_categories` = c.`ct_id`
        ORDER BY c.`ct_name` ASC, p.`part_name` ASC"
    );
}

/**
 * ดึง part_id ที่ถูกผูกกับ Product ใน production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @return array map ของ part_id ที่เลือกแล้ว
 */
function formula_manage_get_selected_part_map($conn, $productId)
{
    if ($productId <= 0) {
        return array();
    }

    $rows = formula_manage_query(
        $conn,
        'SELECT `part_id` FROM `production_formula` WHERE `pd_id` = ?',
        'i',
        array($productId)
    );

    $map = array();
    foreach ($rows as $row) {
        $map[(int) $row['part_id']] = true;
    }

    return $map;
}

/**
 * ดึง metadata ของ primary key เพื่อรองรับฐานข้อมูลเดิมที่ใช้ production_id
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return array ชื่อ primary key และสถานะ auto_increment
 */
function formula_manage_get_formula_primary_key($conn)
{
    $rows = formula_manage_query(
        $conn,
        "SELECT `COLUMN_NAME`, `EXTRA`
        FROM `INFORMATION_SCHEMA`.`COLUMNS`
        WHERE `TABLE_SCHEMA` = DATABASE()
            AND `TABLE_NAME` = 'production_formula'
            AND `COLUMN_NAME` IN ('formula_id', 'production_id')"
    );

    $primaryKey = array('name' => '', 'auto_increment' => true);
    foreach ($rows as $row) {
        $name = (string) $row['COLUMN_NAME'];
        if ($name === 'formula_id' || $name === 'production_id') {
            $primaryKey['name'] = $name;
            $primaryKey['auto_increment'] = strpos((string) $row['EXTRA'], 'auto_increment') !== false;
            if ($name === 'formula_id') {
                break;
            }
        }
    }

    return $primaryKey;
}

/**
 * ดึงเลข production_id ถัดไปสำหรับ schema เดิมที่ primary key ไม่ auto increment
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @return int id ถัดไปสำหรับ insert
 */
function formula_manage_next_production_id($conn)
{
    $rows = formula_manage_query(
        $conn,
        'SELECT COALESCE(MAX(`production_id`), 0) + 1 AS `next_id` FROM `production_formula` FOR UPDATE'
    );

    return isset($rows[0]['next_id']) ? (int) $rows[0]['next_id'] : 1;
}

/**
 * normalize และ validate รายการ part_id ที่ผู้ใช้เลือกจากฟอร์ม
 *
 * @param array $partIds รายการ id จาก POST
 * @return array รายการ part_id ที่เป็น int และไม่ซ้ำ
 */
function formula_manage_normalize_part_ids($partIds)
{
    $selected = array();
    foreach ($partIds as $partId) {
        $cleanPartId = formula_manage_positive_int($partId);
        if ($cleanPartId > 0) {
            $selected[$cleanPartId] = true;
        }
    }

    return array_keys($selected);
}

/**
 * บันทึก mapping ของ Product กับ Part ลง production_formula
 *
 * @param mysqli $conn connection ฐานข้อมูล
 * @param int $productId id ของ Product
 * @param array $partIds รายการ part_id ที่เลือก
 * @return int จำนวน Part ที่บันทึกสำเร็จ
 */
function formula_manage_save_formula($conn, $productId, $partIds)
{
    $product = formula_manage_get_product($conn, $productId);
    if ($product === null) {
        throw new RuntimeException('ไม่พบ Product ที่เลือก');
    }

    $cleanPartIds = formula_manage_normalize_part_ids($partIds);
    $validParts = array();

    if (count($cleanPartIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($cleanPartIds), '?'));
        $types = str_repeat('i', count($cleanPartIds));
        $rows = formula_manage_query(
            $conn,
            "SELECT `part_id`, `part_categories`
            FROM `part`
            WHERE `part_id` IN ($placeholders)",
            $types,
            $cleanPartIds
        );

        foreach ($rows as $row) {
            $validParts[(int) $row['part_id']] = $row;
        }
    }

    $primaryKey = formula_manage_get_formula_primary_key($conn);

    mysqli_begin_transaction($conn);
    try {
        $deleteStmt = formula_manage_execute(
            $conn,
            'DELETE FROM `production_formula` WHERE `pd_id` = ?',
            'i',
            array($productId)
        );
        mysqli_stmt_close($deleteStmt);

        $savedCount = 0;
        foreach ($cleanPartIds as $partId) {
            if (!isset($validParts[$partId])) {
                continue;
            }

            $categoryId = isset($validParts[$partId]['part_categories']) ? (int) $validParts[$partId]['part_categories'] : 0;
            if ($primaryKey['name'] === 'production_id' && !$primaryKey['auto_increment']) {
                $nextId = formula_manage_next_production_id($conn);
                $insertStmt = formula_manage_execute(
                    $conn,
                    'INSERT INTO `production_formula`
                        (`production_id`, `pd_id`, `pd_name`, `part_id`, `formula_name_id`, `ct_id`)
                    VALUES (?, ?, ?, ?, ?, ?)',
                    'iisiii',
                    array($nextId, $productId, $product['pd_name'], $partId, $productId, $categoryId)
                );
            } else {
                $insertStmt = formula_manage_execute(
                    $conn,
                    'INSERT INTO `production_formula`
                        (`pd_id`, `pd_name`, `part_id`, `formula_name_id`, `ct_id`)
                    VALUES (?, ?, ?, ?, ?)',
                    'isiii',
                    array($productId, $product['pd_name'], $partId, $productId, $categoryId)
                );
            }
            mysqli_stmt_close($insertStmt);
            $savedCount++;
        }

        mysqli_commit($conn);

        return $savedCount;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

// ─ Request Handling ──────────────────────────────────────────────────────────

$message = '';
$messageType = 'info';
$selectedProductId = 0;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        formula_manage_validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '');
        $selectedProductId = formula_manage_positive_int(isset($_POST['pd_id']) ? $_POST['pd_id'] : 0);
        $postedParts = isset($_POST['part_ids']) && is_array($_POST['part_ids']) ? $_POST['part_ids'] : array();
        $savedCount = formula_manage_save_formula($connect, $selectedProductId, $postedParts);
        $message = 'บันทึกชุดรายการเบิกสำเร็จ จำนวน Part ที่เลือก: ' . $savedCount . ' รายการ';
        $messageType = 'success';
    } else {
        $selectedProductId = formula_manage_positive_int(isset($_GET['pd_id']) ? $_GET['pd_id'] : 0);
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

$products = formula_manage_get_products($connect);
$parts = formula_manage_get_parts($connect);
$selectedPartMap = formula_manage_get_selected_part_map($connect, $selectedProductId);
$csrfToken = formula_manage_csrf_token();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดชุดรายการเบิก</title>
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
            max-width: 1200px;
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

        .toolbar {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        select,
        button,
        input[type="search"] {
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 15px;
            background-color: #e8f5e9;
        }

        button {
            cursor: pointer;
            color: #145c2c;
            font-weight: bold;
        }

        .link-button {
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 15px;
            background-color: #e8f5e9;
            color: #145c2c;
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

        .part-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 16px 0;
        }

        .part-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .part-item {
            border: 1px solid #e4e4e4;
            border-radius: 10px;
            padding: 10px;
            background: #fafafa;
        }

        .part-item label {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            cursor: pointer;
        }

        .part-meta {
            color: #777;
            display: block;
            font-size: 13px;
            margin-top: 3px;
        }

        .empty-state {
            border: 1px dashed #ccc;
            border-radius: 10px;
            color: #777;
            padding: 18px;
            text-align: center;
        }
    </style>
    <script>
        function formulaManageSubmitProduct() {
            var select = document.getElementById('pd_id_filter');
            if (select.value === '') {
                window.location = 'formula_manage.php';
                return;
            }
            window.location = 'formula_manage.php?pd_id=' + encodeURIComponent(select.value);
        }

        function formulaManageSetAll(checked) {
            var boxes = document.querySelectorAll('input[name="part_ids[]"]');
            for (var i = 0; i < boxes.length; i++) {
                if (boxes[i].closest('.part-item').style.display !== 'none') {
                    boxes[i].checked = checked;
                }
            }
            formulaManageUpdateCount();
        }

        function formulaManageFilterParts() {
            var keyword = document.getElementById('part_search').value.toLowerCase();
            var items = document.querySelectorAll('.part-item');
            for (var i = 0; i < items.length; i++) {
                var text = items[i].getAttribute('data-search').toLowerCase();
                items[i].style.display = text.indexOf(keyword) !== -1 ? '' : 'none';
            }
        }

        function formulaManageUpdateCount() {
            var checked = document.querySelectorAll('input[name="part_ids[]"]:checked').length;
            document.getElementById('selected_count').innerText = checked;
        }
    </script>
</head>
<body onload="formulaManageUpdateCount()">
    <div class="navbar"><?php require __DIR__ . '/title-h.php'; ?></div>

    <main class="content">
        <section class="panel">
            <h1 class="page-title">จัดชุดรายการเบิก</h1>

            <div class="toolbar">
                <label for="pd_id_filter"><b>Product :</b></label>
                <select id="pd_id_filter" onchange="formulaManageSubmitProduct()">
                    <option value="">-- เลือก Product --</option>
                    <?php foreach ($products as $product) { ?>
                        <option value="<?php echo formula_manage_h($product['pd_id']); ?>" <?php echo (int) $product['pd_id'] === $selectedProductId ? 'selected' : ''; ?>>
                            <?php echo formula_manage_h($product['pd_name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <span>เลือกแล้ว <b id="selected_count">0</b> รายการ</span>
                <a class="link-button" href="product_manage.php"><i class="fa fa-plus-circle"></i> เพิ่มชื่อ Product</a>
            </div>

            <?php if ($message !== '') { ?>
                <div class="message message--<?php echo formula_manage_h($messageType); ?>">
                    <?php echo formula_manage_h($message); ?>
                </div>
            <?php } ?>

            <?php if ($selectedProductId <= 0) { ?>
                <div class="empty-state">กรุณาเลือก Product เพื่อจัดชุด Part สำหรับรายการเบิก</div>
            <?php } else { ?>
                <form method="post" action="formula_manage.php">
                    <input type="hidden" name="csrf_token" value="<?php echo formula_manage_h($csrfToken); ?>">
                    <input type="hidden" name="pd_id" value="<?php echo formula_manage_h($selectedProductId); ?>">

                    <div class="part-actions">
                        <input type="search" id="part_search" onkeyup="formulaManageFilterParts()" placeholder="ค้นหา Part">
                        <button type="button" onclick="formulaManageSetAll(true)">เลือกทั้งหมดที่แสดง</button>
                        <button type="button" onclick="formulaManageSetAll(false)">ยกเลิกทั้งหมดที่แสดง</button>
                        <button type="submit"><i class="fa fa-save"></i> บันทึกชุดรายการเบิก</button>
                    </div>

                    <?php if (count($parts) === 0) { ?>
                        <div class="empty-state">ยังไม่มีข้อมูล Part</div>
                    <?php } else { ?>
                        <div class="part-grid">
                            <?php foreach ($parts as $part) {
                                $partId = (int) $part['part_id'];
                                $isChecked = isset($selectedPartMap[$partId]);
                                $searchText = $part['part_name'] . ' ' . $part['ct_name'] . ' ' . $partId;
                                ?>
                                <div class="part-item" data-search="<?php echo formula_manage_h($searchText); ?>">
                                    <label>
                                        <input type="checkbox" name="part_ids[]" value="<?php echo formula_manage_h($partId); ?>" <?php echo $isChecked ? 'checked' : ''; ?> onchange="formulaManageUpdateCount()">
                                        <span>
                                            <b><?php echo formula_manage_h($part['part_name']); ?></b>
                                            <span class="part-meta">
                                                ID: <?php echo formula_manage_h($partId); ?>
                                                | หมวด: <?php echo formula_manage_h($part['ct_name'] !== '' ? $part['ct_name'] : '-'); ?>
                                                | Stock: <?php echo formula_manage_h($part['part_amount']); ?>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </form>
            <?php } ?>
        </section>
    </main>
</body>
</html>
