<?php
/**
 * โมดูลอ่านการตั้งค่า inventory จาก environment หรือไฟล์ local
 *
 * ลำดับความสำคัญ: getenv (ชื่อ env ตาม mapping) → inventory_config.local.php → ค่า default
 *
 * คัดลอก inventory_config.local.php.example เป็น inventory_config.local.php แล้วแก้ค่าจริง
 * อย่า commit ไฟล์ .local.php ที่มี secret
 *
 * ตัวอย่าง:
 * inventory_config_get('line_stock_url_token');
 */

// ─ Local file ───────────────────────────────────────────────────────────────

/**
 * โหลดค่าจาก inventory_config.local.php (cache ครั้งเดียวต่อ request)
 *
 * @return array<string, string> ค่าที่ตั้งในไฟล์ local
 */
function inventory_config_local_data()
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }

    $data = array();
    $file = __DIR__ . '/inventory_config.local.php';
    if (is_readable($file)) {
        $loaded = include $file;
        if (is_array($loaded)) {
            foreach ($loaded as $key => $value) {
                if (is_string($key) && (is_string($value) || is_numeric($value))) {
                    $data[$key] = trim((string) $value);
                }
            }
        }
    }

    return $data;
}

/**
 * mapping key ใน local file → ชื่อ environment variable
 *
 * @return array<string, string>
 */
function inventory_config_env_names()
{
    return array(
        'line_stock_url_token' => 'INVENTORY_LINE_STOCK_URL_TOKEN',
        'low_stock_url_token' => 'INVENTORY_LOW_STOCK_URL_TOKEN',
        'line_channel_access_token' => 'LINE_CHANNEL_ACCESS_TOKEN',
        'line_to_id' => 'LINE_TO_ID',
        'line_messaging_to_id' => 'LINE_MESSAGING_TO_ID',
        'low_stock_mail_from' => 'INVENTORY_LOW_STOCK_MAIL_FROM',
        'low_stock_base_url' => 'INVENTORY_LOW_STOCK_BASE_URL',
        'pickup_prep_board_token' => 'INVENTORY_PICKUP_PREP_BOARD_TOKEN',
        'pickup_prep_board_pin' => 'INVENTORY_PICKUP_PREP_BOARD_PIN',
        'node_red_call_lift_url' => 'INVENTORY_NODE_RED_CALL_LIFT_URL',
        'node_red_call_lift_prelude_url' => 'INVENTORY_NODE_RED_CALL_LIFT_PRELUDE_URL',
        'node_red_call_lift_prelude_delay_ms' => 'INVENTORY_NODE_RED_CALL_LIFT_PRELUDE_DELAY_MS',
        'node_red_call_lift_delivery' => 'INVENTORY_NODE_RED_CALL_LIFT_DELIVERY',
        'node_red_call_lift_visibility' => 'INVENTORY_NODE_RED_CALL_LIFT_VISIBILITY',
        'node_red_call_lift_probe_url' => 'INVENTORY_NODE_RED_CALL_LIFT_PROBE_URL',
        'node_red_call_lift_volume' => 'INVENTORY_NODE_RED_CALL_LIFT_VOLUME',
        'node_red_call_lift_office_ips' => 'INVENTORY_NODE_RED_CALL_LIFT_OFFICE_IPS',
    );
}

/**
 * อ่านค่าการตั้งค่าตาม key
 *
 * @param string $key ชื่อ key ใน local file เช่น line_stock_url_token
 * @param string $default ค่าเมื่อไม่พบทั้ง env และ local
 * @return string ค่าที่ normalize แล้ว
 */
function inventory_config_get($key, $default = '')
{
    $envMap = inventory_config_env_names();
    if (isset($envMap[$key])) {
        $fromEnv = getenv($envMap[$key]);
        if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
            return trim((string) $fromEnv);
        }
    }

    $local = inventory_config_local_data();
    if (isset($local[$key]) && $local[$key] !== '') {
        return $local[$key];
    }

    return $default;
}

/**
 * token สำหรับเรียก line_stock_alert_url.php (และ fallback จาก low_stock)
 *
 * @return string
 */
function inventory_config_line_stock_url_token()
{
    $token = inventory_config_get('line_stock_url_token', '');
    if ($token !== '') {
        return $token;
    }

    return inventory_config_get('low_stock_url_token', '');
}

/**
 * token สำหรับหน้ามือถือ pickup_prep_board.php
 *
 * ใช้ pickup_prep_board_token ก่อน ถ้าว่างใช้ line_stock_url_token (มักมีอยู่แล้วบน production)
 *
 * @return string รหัสลับหรือค่าว่างถ้ายังไม่ตั้ง
 */
function inventory_config_pickup_prep_board_token()
{
    $token = inventory_config_get('pickup_prep_board_token', '');
    if ($token !== '') {
        return $token;
    }

    $tokenFile = __DIR__ . '/pickup_prep_board.token.php';
    if (is_readable($tokenFile)) {
        $loaded = include $tokenFile;
        if (is_string($loaded) && trim($loaded) !== '') {
            return trim($loaded);
        }
    }

    return inventory_config_line_stock_url_token();
}

/**
 * รหัส PIN ปลดล็อกหน้ามือถือจัดของรอเบิก (ตัวเลข 4–8 หลัก) — ค่าว่างหรือไม่ใช่ตัวเลข = ไม่ล็อก
 *
 * อ่านจาก inventory_config.local.php (pickup_prep_board_pin) ก่อน แล้วจึงไฟล์ pickup_prep_board_pin.local.php
 * (return '1234';) — ทั้งสองไฟล์ไม่เข้า git; เปลี่ยนรหัสแล้วทุกเครื่องต้องใส่ใหม่
 *
 * @return string
 */
function inventory_config_pickup_prep_board_pin()
{
    $pin = trim((string) inventory_config_get('pickup_prep_board_pin', ''));
    if ($pin === '') {
        $pinFile = __DIR__ . '/pickup_prep_board_pin.local.php';
        if (is_readable($pinFile)) {
            $loaded = include $pinFile;
            $pin = is_scalar($loaded) ? trim((string) $loaded) : '';
        }
    }

    return preg_match('/^\d{4,8}$/', $pin) ? $pin : '';
}

/**
 * แหล่งที่มาของ token หน้ามือถือ (สำหรับ diag ไม่เปิดเผยค่า token)
 *
 * @return string dedicated | token_file | line_stock | none
 */
function inventory_config_pickup_prep_board_token_source()
{
    if (inventory_config_get('pickup_prep_board_token', '') !== '') {
        return 'dedicated';
    }

    $tokenFile = __DIR__ . '/pickup_prep_board.token.php';
    if (is_readable($tokenFile)) {
        $loaded = include $tokenFile;
        if (is_string($loaded) && trim($loaded) !== '') {
            return 'token_file';
        }
    }

    if (inventory_config_line_stock_url_token() !== '') {
        return 'line_stock';
    }

    return 'none';
}

/**
 * ตรวจว่า URL ปลอดภัยสำหรับใส่ใน action ของฟอร์ม (http/https เท่านั้น)
 *
 * @param string $url URL ที่ต้องการตรวจ
 * @return bool true เมื่อใช้เป็น action ได้
 */
function inventory_config_is_safe_http_url($url)
{
    if (!is_string($url) || trim($url) === '') {
        return false;
    }
    $parts = parse_url(trim($url));
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return false;
    }
    $scheme = strtolower((string) $parts['scheme']);

    return $scheme === 'http' || $scheme === 'https';
}

/**
 * URL endpoint Node-RED สำหรับปุ่มช่วยยกของ (call_Lift) — เรียกจากเบราว์เซอร์ใน LAN ออฟฟิศ
 *
 * @return string ค่าว่าง = ปิดปุ่มบนหน้า pickup_overview
 */
function inventory_config_node_red_call_lift_url()
{
    return inventory_config_get(
        'node_red_call_lift_url',
        'http://192.168.2.208:1880/endpoint/call_Lift'
    );
}

/**
 * URL เรียกเสียงเตือนก่อนส่งคำขอยกของหลัก (ถ้า Node-RED แยก endpoint / พารามิเตอร์)
 *
 * @return string ค่าว่าง = ข้ามขั้นนี้
 */
function inventory_config_node_red_call_lift_prelude_url()
{
    return inventory_config_get('node_red_call_lift_prelude_url', '');
}

/**
 * หน่วง (มิลลิวินาที) หลังเรียก prelude ก่อน submit call_Lift หลัก
 *
 * @return int ค่าไม่ต่ำกว่า 0
 */
function inventory_config_node_red_call_lift_prelude_delay_ms()
{
    $raw = inventory_config_get('node_red_call_lift_prelude_delay_ms', '1200');
    $ms = (int) $raw;

    return $ms < 0 ? 0 : $ms;
}

/**
 * ระดับเสียง (0–100) แนบกับ GET call_Lift เมื่อ flow Node-RED อ่าน query volume
 *
 * @return string ค่าว่าง = ไม่ส่ง — ให้ปรับที่ flow หรือที่ลำโพงโดยตรง
 */
function inventory_config_node_red_call_lift_volume()
{
    $raw = trim(inventory_config_get('node_red_call_lift_volume', ''));
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d+$/', $raw)) {
        $n = (int) $raw;
        if ($n < 0) {
            $n = 0;
        }
        if ($n > 100) {
            $n = 100;
        }

        return (string) $n;
    }
    $f = filter_var($raw, FILTER_VALIDATE_FLOAT);
    if ($f === false) {
        return '';
    }
    if ($f >= 0.0 && $f <= 1.0) {
        $n = (int) round($f * 100.0);
        if ($n < 0) {
            $n = 0;
        }
        if ($n > 100) {
            $n = 100;
        }

        return (string) $n;
    }
    if ($f >= 0.0 && $f <= 100.0) {
        return (string) (int) round($f);
    }

    return '';
}

/**
 * วิธีส่ง call_Lift จากเบราว์เซอร์: iframe | tab | same
 *
 * tab — เปิด URL ในแท็บใหม่ (ใกล้พฤติกรรมพิมพ์ลิงก์ตรง เช่นมีเสียงคลิกก่อนพูด)
 * same — submit ฟอร์มทั้งหน้าแบบ legacy
 *
 * @return string iframe, tab หรือ same
 */
function inventory_config_node_red_call_lift_delivery()
{
    $mode = strtolower(trim(inventory_config_get('node_red_call_lift_delivery', 'iframe')));
    if ($mode === 'tab' || $mode === 'same') {
        return $mode;
    }

    return 'iframe';
}

/**
 * การแสดงปุ่มช่วยยกของ: auto (ค่าเริ่มต้น — เฉพาะผู้ใช้ที่มาจาก IP ออฟฟิศ) | always | never
 *
 * @return string auto, always หรือ never
 */
function inventory_config_node_red_call_lift_visibility()
{
    $mode = strtolower(trim(inventory_config_get('node_red_call_lift_visibility', 'auto')));
    if ($mode === 'always' || $mode === 'never') {
        return $mode;
    }

    return 'auto';
}

/**
 * IP สาธารณะของ Wi‑Fi ออฟฟิศ (คั่นด้วย comma) — ใส่เป็น IP เดี่ยวหรือช่วงแบบ CIDR เช่น 183.89.0.0/16
 *
 * ค่าเริ่มต้นเป็นช่วง เพราะ ISP เปลี่ยน IP ออฟฟิศเป็นระยะ (เคยเป็น 183.89.61.125 → 183.89.58.154)
 *
 * @return string[]
 */
function inventory_config_node_red_call_lift_office_ips()
{
    $raw = inventory_config_get('node_red_call_lift_office_ips', '183.89.0.0/16');
    $ips = array();
    foreach (explode(',', (string) $raw) as $ip) {
        $ip = trim($ip);
        if ($ip !== '') {
            $ips[] = $ip;
        }
    }

    return $ips;
}

/**
 * ผู้ใช้เปิดหน้าจาก Wi‑Fi ออฟฟิศหรือไม่ — ดูจาก IP ที่ server เห็น (REMOTE_ADDR)
 *
 * นับ IP ภายใน/loopback ด้วย (กรณีเปิดผ่าน server ใน LAN หรือทดสอบ localhost)
 *
 * @return bool
 */
function inventory_config_client_is_office_network()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    foreach (inventory_config_node_red_call_lift_office_ips() as $rule) {
        if (inventory_config_ip_matches($ip, $rule)) {
            return true;
        }
    }

    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * IP ตรงกับกฎหรือไม่ — กฎเป็น IP เดี่ยว (IPv4/IPv6) หรือช่วง IPv4 แบบ CIDR
 *
 * @param string $ip IP ของผู้ใช้
 * @param string $rule เช่น 183.89.58.154 หรือ 183.89.0.0/16
 * @return bool
 */
function inventory_config_ip_matches($ip, $rule)
{
    if (strpos($rule, '/') === false) {
        return $ip === $rule;
    }
    $parts = explode('/', $rule, 2);
    $bits = filter_var($parts[1], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 32)));
    $ipLong = ip2long($ip);
    $netLong = ip2long(trim($parts[0]));
    if ($bits === false || $ipLong === false || $netLong === false) {
        return false;
    }
    $mask = $bits === 0 ? 0 : ((0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF);

    return ($ipLong & $mask) === ($netLong & $mask);
}

/**
 * URL สำหรับทดสอบว่าเบราว์เซอร์ถึง Node-RED ในออฟฟิศได้หรือไม่
 *
 * @return string ค่าว่างเมื่อไม่สามารถ derive ได้
 */
function inventory_config_node_red_call_lift_probe_url()
{
    $custom = trim(inventory_config_get('node_red_call_lift_probe_url', ''));
    if ($custom !== '' && inventory_config_is_safe_http_url($custom)) {
        return $custom;
    }

    $lift = trim(inventory_config_node_red_call_lift_url());
    $parts = parse_url($lift);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return '';
    }
    $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';

    return $parts['scheme'] . '://' . $parts['host'] . $port . '/';
}
