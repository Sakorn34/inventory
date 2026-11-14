<?php
/**
 * HTML ปุ่มช่วยยกของ (ร่วมกับ js/pickup-lift-call.js)
 *
 * ก่อน include อาจตั้ง $pickupLiftUi สำหรับ class ของแต่ละหน้า
 */
declare(strict_types=1);

require_once __DIR__ . '/inventory_config.php';

$pickupLiftUi = isset($pickupLiftUi) && is_array($pickupLiftUi) ? $pickupLiftUi : array();
$pickupLiftUi += array(
    'section' => 'inv-card pickup-overview__lift',
    'body' => 'inv-card__body pickup-overview__lift-body',
    'form' => 'pickup-overview__lift-form',
    'label' => 'pickup-overview__lift-label',
    'input' => 'inv-input pickup-overview__lift-input',
    'button' => 'inv-btn inv-btn--primary pickup-overview__lift-btn',
    'status' => 'pickup-overview__lift-status',
    'hint' => 'pickup-overview__lift-hint',
    'ping' => 'pickup-overview__lift-ping',
);

$pickupNodeRedCallLiftUrl = inventory_config_node_red_call_lift_url();
$pickupLiftCallEnabled = inventory_config_is_safe_http_url($pickupNodeRedCallLiftUrl);
$pickupLiftVisibility = inventory_config_node_red_call_lift_visibility();
$pickupLiftSectionRender = $pickupLiftCallEnabled && $pickupLiftVisibility !== 'never';
$pickupLiftSectionHiddenInitially = $pickupLiftSectionRender && $pickupLiftVisibility === 'auto';

if (!$pickupLiftSectionRender) {
    return;
}

$pickupLiftPreludeUrl = inventory_config_node_red_call_lift_prelude_url();
$pickupLiftPreludeEnabled = inventory_config_is_safe_http_url($pickupLiftPreludeUrl);
$pickupLiftPreludeDelayMs = inventory_config_node_red_call_lift_prelude_delay_ms();
?>
<section id="pickup_lift_section" class="<?php echo htmlspecialchars($pickupLiftUi['section'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="ช่วยยกของ"<?php echo $pickupLiftSectionHiddenInitially ? ' hidden' : ''; ?>>
  <div class="<?php echo htmlspecialchars($pickupLiftUi['body'], ENT_QUOTES, 'UTF-8'); ?>">
    <iframe id="pickup_lift_ping" name="pickup_lift_ping" class="<?php echo htmlspecialchars($pickupLiftUi['ping'], ENT_QUOTES, 'UTF-8'); ?>" title="" hidden></iframe>
    <p id="pickup_lift_status" class="<?php echo htmlspecialchars($pickupLiftUi['status'], ENT_QUOTES, 'UTF-8'); ?>" hidden></p>
    <form id="pickup_call_person" class="<?php echo htmlspecialchars($pickupLiftUi['form'], ENT_QUOTES, 'UTF-8'); ?>" method="get" action="<?php echo htmlspecialchars($pickupNodeRedCallLiftUrl, ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return pickupLiftOnFormSubmit(event);">
      <label class="<?php echo htmlspecialchars($pickupLiftUi['label'], ENT_QUOTES, 'UTF-8'); ?>" for="pickup_lift_need_no">ช่วยยกของ :</label>
      <input class="<?php echo htmlspecialchars($pickupLiftUi['input'], ENT_QUOTES, 'UTF-8'); ?>" type="number" id="pickup_lift_need_no" name="amount" min="1" max="99" step="1" placeholder="ระบุจำนวนคน" inputmode="numeric" autocomplete="off" />
      <button type="button" class="<?php echo htmlspecialchars($pickupLiftUi['button'], ENT_QUOTES, 'UTF-8'); ?>" onclick="pickupLiftHelp();">เรียกเลย</button>
    </form>
    <p class="<?php echo htmlspecialchars($pickupLiftUi['hint'], ENT_QUOTES, 'UTF-8'); ?>">ใช้เมื่อเชื่อมต่อ Wi‑Fi ออฟฟิศ — ส่งคำขอไป Node-RED ให้ลำโพงแจ้งขอความช่วยเหลือยกของ</p>
  </div>
</section>
<script>
window.pickupLiftConfig = <?php echo json_encode(array(
    'preludeUrl' => $pickupLiftPreludeEnabled ? $pickupLiftPreludeUrl : '',
    'preludeDelayMs' => (int) $pickupLiftPreludeDelayMs,
    'visibility' => $pickupLiftVisibility,
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="js/pickup-lift-call.js"></script>
