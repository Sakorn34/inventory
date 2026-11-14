<?php
/**
 * Legacy URL — เปลี่ยนเส้นทางไปหน้าเบิกของรวม (โหมดเซต)
 *
 * หน้าเดิม production_pre_pickup ถูกรวมเข้า pickup_ma.php (U1)
 * ตัวอย่าง: production_pre_pickup.php → pickup_ma.php?mode=set
 */
header('Location: pickup_ma.php?mode=set', true, 302);
exit;
