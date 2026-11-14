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

    // token สำหรับหน้ามือถือ pickup_prep_board.php?token=... (สุ่มยาว ≥32 ตัวอักษร)
    'pickup_prep_board_token' => 'b7f3c9e2b1d84f6a0e5c8d2b9f1a4e7c3d6b8f0a2e5c1d9b4f7a0e3c6d8b1f4',

    // ปุ่มช่วยยกของ → Node-RED ใน Wi‑Fi ออฟฟิศ (ตั้งเป็นค่าว่างบน cloud เพื่อซ่อนปุ่ม)
    'node_red_call_lift_url' => 'http://192.168.2.208:1880/endpoint/call_Lift',

    // (ถ้าต้องการ) URL เสียงเตือนก่อนพูดข้อความยกของ — ตั้งตาม flow Node-RED จริง ปล่อยว่างถ้า call_Lift ทำครบในคำขอเดียว
    'node_red_call_lift_prelude_url' => '',
    'node_red_call_lift_prelude_delay_ms' => '1200',

    // ส่ง call_Lift: iframe = อยู่หน้าเดิม (อาจไม่มีเสียงคลิกเหมือนเปิดลิงก์ตรง) | tab = แท็บใหม่ใกล้ของเดิม/ทดสอบ URL | same = กระโดดไป Node-RED แบบ legacy
    'node_red_call_lift_delivery' => 'tab',

    // auto = แสดงเมื่อเปิด inventory จาก IP ภายใน (เช่น 192.168.x) | always = แสดงบน cloud ด้วย (ใช้ในออฟฟิศผ่าน bit-online.net)
    // ไม่ใช้การ ping Node-RED (จะโดน popup ล็อกอิน ถ้า Node-RED มี Basic Auth)
    'node_red_call_lift_visibility' => 'always',

    // ระดับเสียงตอนแจ้งยกของ 0–100 (ส่งเป็น ?volume= ไป call_Lift) — ต้องให้ flow Node-RED ใช้ค่านี้ก่อน Cast/TTS
    // ปล่อยว่างถ้าปรับที่ลำโพงหรือใน Node-RED อย่างเดียว (ถ้าหมุนลดเสียงที่ลำโพงแล้วพูดยังดัง มักมี node “ตั้ง volume” ใน flow)
    'node_red_call_lift_volume' => '',
);
