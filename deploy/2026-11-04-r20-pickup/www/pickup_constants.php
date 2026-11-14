<?php
/**
 * ค่าคงที่ร่วมของโมดูลเบิกของ (pickup)
 *
 * ใช้กำหนดขีดจำกัดการค้นหาและพฤติกรรม UI ที่ใช้ร่วมกันหลายไฟล์
 */

if (!defined('PICKUP_SEARCH_MAX_RANGE_DAYS')) {
    /** ช่วงค้นหาสูงสุด (นับรวมวันเริ่มและวันสิ้นสุด) */
    define('PICKUP_SEARCH_MAX_RANGE_DAYS', 366);
}
