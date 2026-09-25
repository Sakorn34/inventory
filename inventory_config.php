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
