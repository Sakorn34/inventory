<?php
/**
 * การตั้งค่า local บนเซิร์ฟเวอร์ — แก้ค่าด้านล่างให้เป็นของจริง
 *
 * อย่า commit ไฟล์นี้ถ้ามี token จริงใน repository สาธารณะ
 */
return array(
    // รหัสเรียก line_stock_alert_url.php?token=...
    'line_stock_url_token' => '',

    // LINE Developers → Messaging API → Channel access token (ยาวมาก)
    'line_channel_access_token' => '',

    // User ID (U...) หรือ Group ID (C...) ที่จะรับข้อความ
    'line_to_id' => 'Ca921f77e83f896748e5959bbf915c84e',
);
