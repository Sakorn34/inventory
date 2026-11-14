โฟลเดอร์ deploy — ชุดไฟล์สำหรับนำขึ้น server (production)

================================================================



แต่ละครั้ง build จะได้โฟลเดอร์แยกตาม **เวอร์ชัน** เช่น:



  deploy/2026-11-03-pickup/

  deploy/2026-11-03-r2-pickup/



ภายในแพ็ก:



  www/           → copy ไปทับที่ root ของ inventory บน server (รวม css/)

  DEPLOY.txt     → ขั้นตอน backup / upload / ทดสอบ / rollback

  MANIFEST.txt   → MD5 ไฟล์ใน www/ ใช้ตรวจหลัง upload

  VERSION.txt    → วันที่ build แพ็ก

  docs/          → เอกสารอ้างอิง (ไม่บังคับ copy ขึ้น server)



สร้างแพ็กจากโค้ดล่าสุด:



  php tools/build_deploy_pickup_package.php 2026-11-03-r8

  php tools/build_deploy_pickup_package.php 2026-11-03-r8 --full   ← ครบทุกไฟล์ deploy



ค่าเริ่มต้น (incremental): รวมเฉพาะไฟล์ที่ MD5 เปลี่ยนจาก deploy/LAST_UPLOADED.txt



หลังอัปโหลดขึ้น server แล้ว (ผู้ใช้แจ้ง「อัปโหลดแล้ว」):



  php tools/mark_deploy_pickup_uploaded.php

  php tools/mark_deploy_pickup_uploaded.php 2026-11-03-r7-pickup



PowerShell (เรียก PHP เหมือนกัน ไม่สร้าง zip):



  powershell -File tools\build_deploy_pickup_package.ps1 -Version 2026-11-03-r8



ส่ง server โดย copy โฟลเดอร์ deploy\<เวอร์ชัน>-pickup\www\ ตาม DEPLOY.txt (ไม่ใช้ zip)



หน้ามือถือจัดของรอเบิก (pickup_prep_board.php):



  ตั้ง pickup_prep_board_token ใน inventory_config.local.php บน server (ดู inventory_config.local.example.php)

  เปิด pickup_prep_board.php?token=<ค่าเดียวกัน> — bookmark บนมือถือคลัง


