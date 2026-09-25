<?php
/**
 * ตัวอย่างการตั้งค่า path สำหรับ migrate_mdb_to_mariadb.php
 *
 * คัดลอกเป็น migrate.config.php แล้วแก้ path ให้ตรงเครื่อง
 * ใช้เมื่อ PHP อ่านโฟลเดอร์ OneDrive ไม่ได้ (ไฟล์ยังอยู่บน cloud)
 */

/** root โปรเจกต์ inventory — ต้องมี _db/inventory.mdb และ database/ */
$MIGRATE_ROOT = 'C:\\Users\\admin\\OneDrive\\Music\\www\\inventory';
