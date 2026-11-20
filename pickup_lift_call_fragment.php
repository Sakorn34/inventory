<?php
/**
 * HTML ปุ่มช่วยยกของ (ร่วมกับ js/pickup-lift-call.js)
 *
 * ก่อน include อาจตั้ง $pickupLiftUi สำหรับ class ของแต่ละหน้า
 */
declare(strict_types=1);

require_once __DIR__ . '/inventory_config.php';

/**
 * รายการภาพ/วิดีโอใน dialog ยืนยัน — แยกตามจำนวนคน 1–7
 *
 * ชื่อไฟล์: images/pickup-lift-walk-{N}.mp4 หรือ .gif (fallback ร่วม: pickup-lift-walk.gif)
 *
 * @return array{maxClip:int,fallback:array<string,string>,byCount:array<int,array<string,string>>}
 */
function pickup_lift_dialog_walk_media_manifest()
{
    $imagesDir = __DIR__ . '/images';
    $cacheVer = '1';
    $maxClip = 7;
    $fallback = array();
    if (is_readable($imagesDir . '/pickup-lift-walk.gif')) {
        $fallback['gif'] = 'images/pickup-lift-walk.gif?v=' . $cacheVer;
    }
    if (is_readable($imagesDir . '/pickup-lift-walk.mp4')) {
        $fallback['mp4'] = 'images/pickup-lift-walk.mp4?v=' . $cacheVer;
    }
    $byCount = array();
    for ($i = 1; $i <= $maxClip; $i++) {
        $entry = array();
        $gifPath = $imagesDir . '/pickup-lift-walk-' . $i . '.gif';
        $mp4Path = $imagesDir . '/pickup-lift-walk-' . $i . '.mp4';
        if (is_readable($gifPath)) {
            $entry['gif'] = 'images/pickup-lift-walk-' . $i . '.gif?v=' . $cacheVer;
        }
        if (is_readable($mp4Path)) {
            $entry['mp4'] = 'images/pickup-lift-walk-' . $i . '.mp4?v=' . $cacheVer;
        }
        if ($entry !== array()) {
            $byCount[$i] = $entry;
        }
    }

    return array(
        'maxClip' => $maxClip,
        'fallback' => $fallback,
        'byCount' => $byCount,
    );
}

$pickupLiftUi = isset($pickupLiftUi) && is_array($pickupLiftUi) ? $pickupLiftUi : array();
$pickupLiftUi += array(
    'section' => 'inv-card pickup-overview__lift',
    'body' => 'inv-card__body pickup-overview__lift-body',
    'form' => 'pickup-overview__lift-form',
    'label' => 'pickup-overview__lift-label',
    'stepper' => 'pickup-lift-qty-stepper pickup-overview__lift-stepper',
    'stepper_btn' => 'pickup-lift-qty-stepper__btn',
    'input' => 'pickup-lift-qty-stepper__input inv-input pickup-overview__lift-input',
    'button_shell' => 'pickup-overview__lift-btn-shell',
    'button' => 'inv-btn inv-btn--primary pickup-overview__lift-btn',
    'status' => 'pickup-overview__lift-status',
    'hint' => 'pickup-overview__lift-hint',
    'ping' => 'pickup-overview__lift-ping',
    'crew' => '',
);

$pickupNodeRedCallLiftUrl = inventory_config_node_red_call_lift_url();
$pickupLiftCallEnabled = inventory_config_is_safe_http_url($pickupNodeRedCallLiftUrl);
$pickupLiftVisibility = inventory_config_node_red_call_lift_visibility();
$pickupLiftSectionRender = $pickupLiftCallEnabled
    && ($pickupLiftVisibility === 'always'
        || ($pickupLiftVisibility === 'auto' && inventory_config_client_is_office_network()));

if (!$pickupLiftSectionRender) {
    return;
}

$pickupLiftPreludeUrl = inventory_config_node_red_call_lift_prelude_url();
$pickupLiftPreludeEnabled = inventory_config_is_safe_http_url($pickupLiftPreludeUrl);
$pickupLiftPreludeDelayMs = inventory_config_node_red_call_lift_prelude_delay_ms();
$pickupLiftVolume = inventory_config_node_red_call_lift_volume();
$pickupLiftDelivery = inventory_config_node_red_call_lift_delivery();
$pickupLiftWalkMedia = pickup_lift_dialog_walk_media_manifest();
?>
<section id="pickup_lift_section" class="<?php echo htmlspecialchars($pickupLiftUi['section'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="ช่วยยกของ">
  <div class="<?php echo htmlspecialchars($pickupLiftUi['body'], ENT_QUOTES, 'UTF-8'); ?>">
    <iframe id="pickup_lift_ping" name="pickup_lift_ping" class="<?php echo htmlspecialchars($pickupLiftUi['ping'], ENT_QUOTES, 'UTF-8'); ?>" title="" hidden></iframe>
    <p id="pickup_lift_status" class="<?php echo htmlspecialchars($pickupLiftUi['status'], ENT_QUOTES, 'UTF-8'); ?>" hidden></p>
    <form id="pickup_call_person" class="<?php echo htmlspecialchars($pickupLiftUi['form'], ENT_QUOTES, 'UTF-8'); ?>" method="get" action="<?php echo htmlspecialchars($pickupNodeRedCallLiftUrl, ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return pickupLiftOnFormSubmit(event);">
      <label class="<?php echo htmlspecialchars($pickupLiftUi['label'], ENT_QUOTES, 'UTF-8'); ?>" for="pickup_lift_need_no">ช่วยยกของ :</label>
<?php if ($pickupLiftUi['crew'] !== '') { ?>
      <div class="<?php echo htmlspecialchars($pickupLiftUi['crew'], ENT_QUOTES, 'UTF-8'); ?>" id="pickup_lift_crew" aria-hidden="true"></div>
<?php } ?>
      <div class="<?php echo htmlspecialchars($pickupLiftUi['stepper'], ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="<?php echo htmlspecialchars($pickupLiftUi['stepper_btn'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="ลดจำนวนคน" onclick="pickupLiftQtyStep(-1); return false;">−</button>
        <input class="<?php echo htmlspecialchars($pickupLiftUi['input'], ENT_QUOTES, 'UTF-8'); ?>" type="number" id="pickup_lift_need_no" name="amount" min="1" max="99" step="1" value="1" inputmode="numeric" autocomplete="off" aria-label="จำนวนคนที่ต้องการ" readonly aria-readonly="true" tabindex="-1" />
        <button type="button" class="<?php echo htmlspecialchars($pickupLiftUi['stepper_btn'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="เพิ่มจำนวนคน" onclick="pickupLiftQtyStep(1); return false;">+</button>
      </div>
      <span class="<?php echo htmlspecialchars($pickupLiftUi['button_shell'], ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="<?php echo htmlspecialchars($pickupLiftUi['button'], ENT_QUOTES, 'UTF-8'); ?>" onclick="pickupLiftHelp();">เรียกเลย</button>
      </span>
    </form>
    <p class="<?php echo htmlspecialchars($pickupLiftUi['hint'], ENT_QUOTES, 'UTF-8'); ?>">ใช้เมื่อเชื่อมต่อ Wi‑Fi ออฟฟิศ — ส่งคำขอไป Node-RED ให้ลำโพงแจ้งขอความช่วยเหลือยกของ</p>
  </div>
</section>
<div id="pickup_lift_dialog" class="pickup-lift-dialog" hidden aria-hidden="true" role="presentation">
  <div class="pickup-lift-dialog__backdrop" tabindex="-1" aria-hidden="true"></div>
  <div class="pickup-lift-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="pickup_lift_dialog_title" aria-describedby="pickup_lift_dialog_msg">
    <div class="pickup-lift-dialog__visual" id="pickup_lift_dialog_visual" aria-hidden="true">
      <video
        id="pickup_lift_dialog_video"
        class="pickup-lift-dialog__media"
        autoplay
        loop
        muted
        playsinline
        preload="metadata"
        hidden
      >
        <source id="pickup_lift_dialog_video_src" src="" type="video/mp4" />
      </video>
      <img
        id="pickup_lift_dialog_img"
        class="pickup-lift-dialog__media pickup-lift-dialog__media--gif"
        src=""
        alt=""
        decoding="async"
        hidden
      />
    </div>
    <h2 id="pickup_lift_dialog_title" class="pickup-lift-dialog__title">เรียกความช่วยเหลือยกของ</h2>
    <p id="pickup_lift_dialog_msg" class="pickup-lift-dialog__msg"></p>
    <div class="pickup-lift-dialog__actions">
      <button type="button" id="pickup_lift_dialog_cancel" class="pickup-lift-dialog__btn pickup-lift-dialog__btn--ghost">ยกเลิก</button>
      <button type="button" id="pickup_lift_dialog_ok" class="pickup-lift-dialog__btn pickup-lift-dialog__btn--primary">เรียกเลย</button>
    </div>
  </div>
</div>
<script>
window.pickupLiftConfig = <?php echo json_encode(array(
    'preludeUrl' => $pickupLiftPreludeEnabled ? $pickupLiftPreludeUrl : '',
    'preludeDelayMs' => (int) $pickupLiftPreludeDelayMs,
    'visibility' => 'always',
    'volume' => $pickupLiftVolume,
    'delivery' => $pickupLiftDelivery,
    'walkMediaMaxClip' => (int) $pickupLiftWalkMedia['maxClip'],
    'walkMediaFallback' => $pickupLiftWalkMedia['fallback'],
    'walkMediaByCount' => $pickupLiftWalkMedia['byCount'],
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="js/pickup-lift-call.js?v=9"></script>
