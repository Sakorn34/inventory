<?php
/**
 * หน้ารายการจัดของรอเบิก — มือถือ (แยกจากเมนู inventory)
 *
 * แสดงอะไหล่ในเอกสาร pre_status = pre จัดกลุ่มตามชื่ออะไหล่ (หยิบครั้งเดียวต่อ part)
 * ติ๊กจัดเตรียมแล้ว/ยังไม่จัด ผ่าน pickup_prep_api.php
 * ต้องเปิดด้วย ?token= ตรงกับ pickup_prep_board_token ใน config
 *
 * Flow สั้น: pickup_prep_board.php?token=SECRET → JS โหลด list → กดแถว toggle pre_check
 * ปุ่มช่วยยกของ: pickup_lift_call_fragment.php (เมื่ออยู่ใน Wi‑Fi ออฟฟิศ)
 *
 * ตัวอย่าง: https://example.com/inventory/pickup_prep_board.php?token=...
 */

require_once __DIR__ . '/pickup_prep_board_auth.php';

$token = pickup_prep_board_request_token();
$tokenOk = pickup_prep_board_token_valid($token);
$unlocked = $tokenOk && pickup_prep_board_is_unlocked($token);

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="theme-color" content="#ffd98a" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<title>จัดของรอเบิก</title>
<link rel="icon" type="image/png" sizes="32x32" href="images/inventory-icon-32.png" />
<link rel="icon" type="image/png" sizes="192x192" href="images/inventory-icon-192.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/inventory-apple-touch-icon.png" />
<?php require_once __DIR__ . '/inventory_ui_fonts.php'; ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Mitr:wght@500&amp;display=swap" />
<link rel="stylesheet" href="css/pickup-prep.css?v=23" />
</head>
<body class="prep-board">
<?php if (!$tokenOk) {
    $gateHint = pickup_prep_board_gate_hint($token);
    ?>
<div class="prep-board__shell">
  <div class="prep-board__gate">
    <h1 class="prep-board__gate-title">ไม่สามารถเปิดหน้านี้ได้</h1>
    <p><?php echo $h($gateHint); ?></p>
  </div>
</div>
<?php } elseif (!$unlocked) {
    $pinLength = strlen(inventory_config_pickup_prep_board_pin());
    // ยังอยู่ในช่วงล็อกจากการใส่ผิด — รีเฟรชหน้าแล้วต้องยังนับถอยหลังต่อ
    require_once __DIR__ . '/connect.php';
    $pinStatus = pickup_prep_board_pin_status($connect);
    db_close($connect);
    $pinLockedFor = $pinStatus['locked_for'];
    $pinTriesLeft = $pinStatus['has_fails'] ? $pinStatus['tries_left'] : 0;
    ?>
<div class="prep-board__sparkles" aria-hidden="true">
  <?php foreach (array(array(8, 0), array(30, 5), array(52, 10), array(74, 2.5), array(90, 7.5)) as $sp) { ?>
  <span class="prep-board__sparkle" style="left:<?php echo $sp[0]; ?>%;animation-delay:<?php echo $sp[1]; ?>s"></span>
  <?php } ?>
</div>
<div class="prep-lock" id="prep_lock" data-token="<?php echo $h($token); ?>" data-len="<?php echo (int) $pinLength; ?>" data-locked-for="<?php echo (int) $pinLockedFor; ?>" data-tries-left="<?php echo (int) $pinTriesLeft; ?>">
  <div class="prep-lock__panel">
    <h1 class="prep-lock__title">จัดของรอเบิก</h1>
    <p class="prep-lock__hint">ใส่รหัส <?php echo (int) $pinLength; ?> หลักเพื่อเข้าห้องคลัง</p>
    <div class="prep-lock__mascot" aria-hidden="true">
      <div class="prep-lock__mascot-body">
        <span class="prep-lock__mascot-eye prep-lock__mascot-eye--l"><i></i></span>
        <span class="prep-lock__mascot-eye prep-lock__mascot-eye--r"><i></i></span>
        <span class="prep-lock__mascot-cheek prep-lock__mascot-cheek--l"></span>
        <span class="prep-lock__mascot-cheek prep-lock__mascot-cheek--r"></span>
        <span class="prep-lock__mascot-mouth"></span>
      </div>
      <span class="prep-lock__mascot-arm prep-lock__mascot-arm--l"></span>
      <span class="prep-lock__mascot-arm prep-lock__mascot-arm--r"></span>
      <span class="prep-lock__mascot-feet"><span></span><span></span></span>
    </div>
    <div class="prep-lock__dots" id="prep_lock_dots" role="img" aria-label="ใส่รหัสแล้ว 0 หลัก">
      <?php for ($d = 0; $d < $pinLength; $d++) { ?><span class="prep-lock__dot"></span><?php } ?>
    </div>
    <p class="prep-lock__msg" id="prep_lock_msg" role="status" aria-live="polite"></p>
    <div class="prep-lock__pad" id="prep_lock_pad">
      <?php
      $padLetters = array('2' => 'ABC', '3' => 'DEF', '4' => 'GHI', '5' => 'JKL', '6' => 'MNO', '7' => 'PQRS', '8' => 'TUV', '9' => 'WXYZ');
      foreach (array('1', '2', '3', '4', '5', '6', '7', '8', '9') as $digit) { ?>
      <button type="button" class="prep-lock__key" data-key="<?php echo $digit; ?>"><?php echo $digit; ?><?php if (isset($padLetters[$digit])) { ?><small aria-hidden="true"><?php echo $padLetters[$digit]; ?></small><?php } ?></button>
      <?php } ?>
      <button type="button" class="prep-lock__key prep-lock__key--ghost" data-key="clear">ล้าง</button>
      <button type="button" class="prep-lock__key" data-key="0">0</button>
      <button type="button" class="prep-lock__key prep-lock__key--ghost" data-key="back" aria-label="ลบตัวล่าสุด">ลบ</button>
    </div>
  </div>
  <div class="prep-lock__done" aria-hidden="true">
    <span class="prep-lock__ring"></span>
    <svg class="prep-lock__padlock" viewBox="0 0 120 140">
      <g class="prep-lock__shackle"><path d="M32 64V42a28 28 0 0 1 56 0v38" fill="none" stroke="#4a2c05" stroke-width="12" stroke-linecap="round"/></g>
      <rect x="16" y="58" width="88" height="72" rx="16" fill="#ff8a3d" stroke="#4a2c05" stroke-width="5"/>
      <circle cx="60" cy="88" r="9" fill="#4a2c05"/>
      <rect x="56" y="92" width="8" height="18" rx="4" fill="#4a2c05"/>
    </svg>
    <span class="prep-lock__welcome">ยินดีต้อนรับ!</span>
  </div>
</div>
<script>
(function () {
  "use strict";

  var root = document.getElementById("prep_lock");
  var token = root.getAttribute("data-token") || "";
  var pinLen = parseInt(root.getAttribute("data-len"), 10) || 4;
  var dots = document.querySelectorAll(".prep-lock__dot");
  var dotsBox = document.getElementById("prep_lock_dots");
  var msgEl = document.getElementById("prep_lock_msg");
  var pad = document.getElementById("prep_lock_pad");
  var reduceMotion = !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
  var code = "";
  var busy = false;

  // น้องกล่องปิดตาตอนกำลังกด แง้มดูตอนกดครบ
  function paint() {
    dots.forEach(function (d, i) {
      d.classList.toggle("is-on", i < code.length);
    });
    root.classList.toggle("is-shy", code.length > 0 && code.length < pinLen);
    root.classList.toggle("is-peek", code.length === pinLen);
    dotsBox.setAttribute("aria-label", "ใส่รหัสแล้ว " + code.length + " หลัก");
  }

  function say(text, kind) {
    msgEl.textContent = text;
    msgEl.className = "prep-lock__msg" + (kind ? " is-" + kind : "");
  }

  function confetti() {
    if (reduceMotion) {
      return;
    }
    var colors = ["#f59e0b", "#22c55e", "#ff8a3d", "#60a5fa", "#f472b6"];
    for (var i = 0; i < 40; i++) {
      var piece = document.createElement("span");
      piece.className = "prep-board__confetti";
      piece.style.left = Math.round(Math.random() * 100) + "vw";
      piece.style.background = colors[i % colors.length];
      piece.style.animationDelay = Math.round(Math.random() * 450) + "ms";
      piece.style.setProperty("--x", Math.round(Math.random() * 80 - 40) + "px");
      document.body.appendChild(piece);
      window.setTimeout(piece.remove.bind(piece), 2400);
    }
  }

  function wrong() {
    root.classList.remove("is-shy", "is-peek", "is-wrong");
    void root.offsetWidth;
    root.classList.add("is-wrong");
    if (navigator.vibrate) {
      navigator.vibrate([90, 50, 90]);
    }
    window.setTimeout(function () {
      root.classList.remove("is-wrong");
      paint();
    }, 1000);
  }

  function lockout(seconds) {
    busy = true;
    pad.classList.add("is-disabled");
    var endAt = Date.now() + seconds * 1000;
    function tick() {
      var left = Math.ceil((endAt - Date.now()) / 1000);
      if (left <= 0) {
        pad.classList.remove("is-disabled");
        busy = false;
        say("", "");
        return;
      }
      say("ใส่ผิดหลายครั้ง — รอ " + left + " วินาทีแล้วลองใหม่", "wait");
      window.setTimeout(tick, 500);
    }
    tick();
  }

  function submit() {
    busy = true;
    fetch("pickup_prep_api.php?token=" + encodeURIComponent(token) + "&action=unlock", {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { "Content-Type": "application/json", "X-Prep-Board-Token": token },
      body: JSON.stringify({ pin: code })
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (body) {
        if (body && body.ok) {
          root.classList.remove("is-shy", "is-peek");
          root.classList.add("is-open");
          say("ถูกต้อง!", "ok");
          confetti();
          window.setTimeout(function () {
            window.location.reload();
          }, reduceMotion ? 300 : 3000);
          return;
        }
        code = "";
        wrong();
        if (body && body.locked_for > 0) {
          lockout(body.locked_for);
          return;
        }
        say("รหัสไม่ถูกต้อง" + (body && body.tries_left ? " · เหลืออีก " + body.tries_left + " ครั้ง" : ""), "bad");
        window.setTimeout(function () {
          busy = false;
        }, 600);
      })
      .catch(function () {
        code = "";
        paint();
        busy = false;
        say("เชื่อมต่อไม่ได้ ลองใหม่อีกครั้ง", "bad");
      });
  }

  // กดได้จากปุ่มบนจอเท่านั้น — ไม่มีช่องให้พิมพ์และไม่รับคีย์บอร์ด
  pad.addEventListener("click", function (ev) {
    var btn = ev.target.closest(".prep-lock__key");
    if (!btn || busy) {
      return;
    }
    var key = btn.getAttribute("data-key");
    if (key === "clear") {
      code = "";
      say("", "");
    } else if (key === "back") {
      code = code.slice(0, -1);
    } else if (code.length < pinLen) {
      code += key;
      if (navigator.vibrate) {
        navigator.vibrate(12);
      }
    }
    paint();
    if (code.length === pinLen) {
      busy = true;
      window.setTimeout(submit, 350);
    }
  });

  paint();
  var lockedOnLoad = parseInt(root.getAttribute("data-locked-for"), 10) || 0;
  var triesOnLoad = parseInt(root.getAttribute("data-tries-left"), 10) || 0;
  if (lockedOnLoad > 0) {
    lockout(lockedOnLoad);
  } else if (triesOnLoad > 0) {
    say("ใส่รหัสผิดไปแล้ว · เหลืออีก " + triesOnLoad + " ครั้งก่อนถูกล็อก", "bad");
  }
})();
</script>
<?php } else { ?>
<div class="prep-board__sparkles" aria-hidden="true">
  <?php foreach (array(array(8, 0), array(30, 5), array(52, 10), array(74, 2.5), array(90, 7.5)) as $sp) { ?>
  <span class="prep-board__sparkle" style="left:<?php echo $sp[0]; ?>%;animation-delay:<?php echo $sp[1]; ?>s"></span>
  <?php } ?>
</div>
<div class="prep-board__shell" id="prep_app" data-token="<?php echo $h($token); ?>">
  <header class="prep-board__header">
    <div class="prep-board__title-row">
      <div>
        <h1 class="prep-board__title">จัดของรอเบิก</h1>
        <p class="prep-board__subtitle prep-board__subtitle--hint">จัดกลุ่มตามชื่ออะไหล่ — หยิบครั้งเดียวต่อตัว</p>
        <p class="prep-board__subtitle" id="prep_subtitle">กำลังโหลด…</p>
      </div>
      <div class="prep-board__head-tools">
        <span class="prep-board__bubble" aria-hidden="true">เย้! ครบแล้ว</span>
        <div class="prep-board__mascot" id="prep_mascot" aria-hidden="true">
          <span class="prep-board__mascot-body"></span>
          <span class="prep-board__mascot-eye prep-board__mascot-eye--l"></span>
          <span class="prep-board__mascot-eye prep-board__mascot-eye--r"></span>
          <span class="prep-board__mascot-mouth"></span>
        </div>
        <button type="button" class="prep-board__refresh" id="prep_refresh_btn" aria-label="โหลดใหม่">↻</button>
      </div>
    </div>
    <div class="prep-board__stats" id="prep_stats" hidden>
      <div class="prep-board__stat">
        <span class="prep-board__stat-value" id="prep_stat_prepared">0</span>
        <span class="prep-board__stat-label">จัดแล้ว</span>
      </div>
      <div class="prep-board__stat">
        <span class="prep-board__stat-value" id="prep_stat_pending">0</span>
        <span class="prep-board__stat-label">คงเหลือ</span>
      </div>
      <div class="prep-board__stat">
        <span class="prep-board__stat-value" id="prep_stat_docs">0</span>
        <span class="prep-board__stat-label">เอกสาร</span>
      </div>
    </div>
    <div class="prep-board__lane" aria-hidden="true">
      <svg class="prep-board__forklift" viewBox="0 0 54 27">
        <rect x="31" y="9" width="13" height="11" rx="1.5" fill="#c98a4b" stroke="#4a2c05" stroke-width="1.4"/>
        <path d="M37.5 9v4.5" stroke="#e9c08f" stroke-width="3"/>
        <rect x="27" y="2" width="3" height="19" fill="#4a2c05"/>
        <rect x="27" y="19.5" width="18" height="2" fill="#4a2c05"/>
        <path d="M10 11V4h11l3 7" fill="none" stroke="#4a2c05" stroke-width="1.6" stroke-linejoin="round"/>
        <rect x="4" y="11" width="23" height="10" rx="2" fill="#f59e0b" stroke="#4a2c05" stroke-width="1.4"/>
        <rect x="2" y="13" width="4" height="7" fill="#4a2c05"/>
        <circle cx="9.5" cy="22" r="4" fill="#3d2606"/>
        <circle cx="9.5" cy="22" r="1.5" fill="#ffd98a"/>
        <circle cx="22" cy="22" r="4" fill="#3d2606"/>
        <circle cx="22" cy="22" r="1.5" fill="#ffd98a"/>
      </svg>
    </div>
  </header>
  <div class="prep-board__filters" role="tablist" aria-label="กรองรายการ">
    <button type="button" class="prep-board__filter is-active" data-filter="all" role="tab">ทั้งหมด</button>
    <button type="button" class="prep-board__filter" data-filter="pending" role="tab">ยังไม่จัด</button>
    <button type="button" class="prep-board__filter" data-filter="prepared" role="tab">จัดแล้ว</button>
  </div>
  <main class="prep-board__main" id="prep_main" aria-live="polite">
    <span class="prep-board__sr-only">กำลังโหลดรายการ…</span>
    <?php for ($sk = 0; $sk < 3; $sk++) { ?>
    <div class="prep-board__skeleton" aria-hidden="true"><div class="prep-board__skeleton-bar prep-board__skeleton-bar--title"></div><div class="prep-board__skeleton-bar prep-board__skeleton-bar--short"></div><div class="prep-board__skeleton-bar"></div></div>
    <?php } ?>
  </main>
  <?php
  $pickupLiftUi = array(
      'section' => 'prep-board__lift prep-board__lift--like-overview',
      'body' => 'prep-board__lift-body',
      'form' => 'prep-board__lift-form',
      'label' => 'prep-board__lift-label',
      'stepper' => 'pickup-lift-qty-stepper prep-board__lift-stepper',
      'stepper_btn' => 'pickup-lift-qty-stepper__btn',
      'input' => 'pickup-lift-qty-stepper__input prep-board__lift-input',
      'button' => 'prep-board__lift-btn',
      'status' => 'prep-board__lift-status',
      'hint' => 'prep-board__lift-hint',
      'ping' => 'prep-board__lift-ping',
      'crew' => 'prep-board__lift-crew',
  );
  require __DIR__ . '/pickup_lift_call_fragment.php';
  ?>
  <footer class="prep-board__footer">อัปเดตอัตโนมัติทุก 60 วินาที</footer>
  <div class="prep-board__toast" id="prep_toast" role="status" aria-live="polite" hidden></div>
</div>
<script>
(function () {
  "use strict";

  var app = document.getElementById("prep_app");
  if (!app) {
    return;
  }

  var token = app.getAttribute("data-token") || "";
  var filterMode = "all";
  var documents = [];
  var techPartGroups = [];
  var refreshTimer = null;
  var toastTimer = null;
  var hasRendered = false;

  function bump(el) {
    if (!el) {
      return;
    }
    el.classList.remove("is-bump");
    void el.offsetWidth;
    el.classList.add("is-bump");
  }

  function setStatValue(id, value) {
    var el = document.getElementById(id);
    if (!el) {
      return;
    }
    var next = String(value);
    if (el.textContent !== next) {
      el.textContent = next;
      if (hasRendered) {
        bump(el);
      }
    }
  }

  var reduceMotion = !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);

  function restartClass(el, cls) {
    if (!el) {
      return;
    }
    el.classList.remove(cls);
    void el.offsetWidth;
    el.classList.add(cls);
  }

  function burstStars(anchor) {
    if (reduceMotion || !anchor) {
      return;
    }
    var r = anchor.getBoundingClientRect();
    var cx = r.left + r.width / 2;
    var cy = r.top + r.height / 2;
    for (var i = 0; i < 6; i++) {
      var star = document.createElement("span");
      var angle = (i / 6) * Math.PI * 2;
      star.className = "prep-board__burst";
      star.style.left = cx + "px";
      star.style.top = cy + "px";
      star.style.setProperty("--x", Math.round(Math.cos(angle) * 38) + "px");
      star.style.setProperty("--y", Math.round(Math.sin(angle) * 38) + "px");
      document.body.appendChild(star);
      window.setTimeout(star.remove.bind(star), 800);
    }
  }

  function celebrate() {
    if (reduceMotion) {
      return;
    }
    var colors = ["#f59e0b", "#22c55e", "#ff8a3d", "#60a5fa", "#f472b6"];
    for (var i = 0; i < 36; i++) {
      var piece = document.createElement("span");
      piece.className = "prep-board__confetti";
      piece.style.left = Math.round(Math.random() * 100) + "vw";
      piece.style.background = colors[i % colors.length];
      piece.style.animationDelay = Math.round(Math.random() * 450) + "ms";
      piece.style.setProperty("--x", Math.round(Math.random() * 80 - 40) + "px");
      document.body.appendChild(piece);
      window.setTimeout(piece.remove.bind(piece), 2400);
    }
  }

  /** ความคืบหน้าของการ์ดตาม part_key — นับเฉพาะรายการจากเอกสาร (ไม่รวมคลังช่าง) */
  function partProgress(partKey) {
    var prepared = 0;
    var total = 0;
    documents.forEach(function (doc) {
      doc.lines.forEach(function (line) {
        var key = line.pre_part ? String(line.pre_part) : line.part_name;
        if (key === partKey) {
          total++;
          if (line.prepared) {
            prepared++;
          }
        }
      });
    });
    return { prepared: prepared, total: total };
  }

  function syncCard(card) {
    if (!card) {
      return;
    }
    var p = partProgress(card.getAttribute("data-part-key"));
    var bar = card.querySelector(".prep-board__progress-bar");
    var text = card.querySelector(".prep-board__progress-text");
    if (bar) {
      bar.style.width = (p.total > 0 ? Math.round((p.prepared / p.total) * 100) : 100) + "%";
    }
    if (text) {
      text.textContent = "จัดแล้ว " + p.prepared + " / " + p.total + " รายการ";
    }
    var done = p.total > 0 && p.prepared === p.total;
    var wasDone = card.classList.contains("is-done");
    card.classList.toggle("is-done", done);
    if (done && !wasDone && !reduceMotion) {
      restartClass(card, "is-stamping");
    }
  }

  function skeletonHtml() {
    var bar = '<div class="prep-board__skeleton" aria-hidden="true">'
      + '<div class="prep-board__skeleton-bar prep-board__skeleton-bar--title"></div>'
      + '<div class="prep-board__skeleton-bar prep-board__skeleton-bar--short"></div>'
      + '<div class="prep-board__skeleton-bar"></div></div>';
    return '<span class="prep-board__sr-only">กำลังโหลดรายการ…</span>' + bar + bar + bar;
  }

  function showPrepToast(message) {
    var toast = document.getElementById("prep_toast");
    if (!toast) {
      return;
    }
    toast.textContent = message;
    toast.hidden = false;
    toast.classList.add("is-visible");
    if (toastTimer) {
      window.clearTimeout(toastTimer);
    }
    toastTimer = window.setTimeout(function () {
      toast.classList.remove("is-visible");
      window.setTimeout(function () {
        toast.hidden = true;
      }, 220);
    }, 1600);
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  /**
   * ชื่ออะไหล่คลังช่างบนมือถือ — ไม่แสดงรหัส Pxxxxx (รองรับข้อมูลเก่า)
   *
   * @param {string} name
   * @returns {string}
   */
  function prepBoardDisplayPartName(name) {
    var raw = String(name || "").trim();
    if (raw === "") {
      return "";
    }
    var stripped = raw
      .replace(/^P\d{3,}\s*[—\-–:]+\s*/iu, "")
      .replace(/^P\d{3,}[—\-–](.+)$/iu, "$1")
      .replace(/^P\d{3,}\s+/iu, "");
    if (/^P\d{3,}$/iu.test(stripped)) {
      return "";
    }
    return stripped !== "" ? stripped : raw;
  }

  function apiUrl(action) {
    return "pickup_prep_api.php?token=" + encodeURIComponent(token) + "&action=" + encodeURIComponent(action);
  }

  function setSubtitle(text) {
    var el = document.getElementById("prep_subtitle");
    if (el) {
      el.textContent = text;
    }
  }

  function updateStats(summary) {
    var stats = document.getElementById("prep_stats");
    if (!summary || !stats) {
      return;
    }
    stats.hidden = false;
    app.classList.toggle("is-all-done", summary.lines > 0 && summary.pending === 0);
    setStatValue("prep_stat_prepared", summary.prepared);
    setStatValue("prep_stat_pending", summary.pending);
    setStatValue("prep_stat_docs", summary.documents);
    setSubtitle("จัดแล้ว " + summary.prepared + " / " + summary.lines + " รายการ");
  }

  function lineMatchesFilter(line) {
    if (filterMode === "pending") {
      return !line.prepared;
    }
    if (filterMode === "prepared") {
      return line.prepared;
    }
    return true;
  }

  function parseAmount(value) {
    var n = parseFloat(String(value).replace(/,/g, ""));
    return isNaN(n) ? 0 : n;
  }

  function formatAmount(n) {
    if (Math.abs(n - Math.round(n)) < 0.0001) {
      return String(Math.round(n));
    }
    return n.toFixed(2);
  }

  function mergePrepPartGroup(map, group) {
    if (!group || !group.part_key) {
      return;
    }
    var key = String(group.part_key);
    if (!map[key]) {
      map[key] = {
        part_key: key,
        part_name: prepBoardDisplayPartName(group.part_name),
        lines: []
      };
    }
    (group.lines || []).forEach(function (line) {
      map[key].lines.push(line);
    });
  }

  function buildPartGroups() {
    var map = {};
    documents.forEach(function (doc) {
      doc.lines.forEach(function (line) {
        var key = line.pre_part ? String(line.pre_part) : line.part_name;
        if (!map[key]) {
          map[key] = {
            part_key: key,
            part_name: line.part_name,
            lines: []
          };
        }
        map[key].lines.push({
          pre_running: line.pre_running,
          pre_part: line.pre_part,
          part_name: line.part_name,
          pre_product: line.pre_product,
          pre_amount: line.pre_amount,
          prepared: line.prepared,
          stock: line.stock,
          paid_amount: line.paid_amount,
          pre_id: doc.pre_id,
          pre_date: doc.pre_date,
          pre_by: doc.pre_by,
          pre_type: doc.pre_type,
          is_tech: false
        });
      });
    });
    techPartGroups.forEach(function (group) {
      mergePrepPartGroup(map, group);
    });

    var groups = [];
    Object.keys(map).forEach(function (key) {
      groups.push(map[key]);
    });
    groups.sort(function (a, b) {
      return a.part_name.localeCompare(b.part_name, "th");
    });
    return groups;
  }

  function partGroupHasVisibleLines(group) {
    for (var i = 0; i < group.lines.length; i++) {
      var ln = group.lines[i];
      if (ln.is_tech) {
        if (filterMode !== "pending") {
          return true;
        }
        continue;
      }
      if (lineMatchesFilter(ln)) {
        return true;
      }
    }
    return false;
  }

  function renderList(animate) {
    var main = document.getElementById("prep_main");
    if (!main) {
      return;
    }
    main.classList.toggle("is-enter", !!animate);
    hasRendered = true;

    if (!documents.length && !techPartGroups.length) {
      var hint = window.prepEmptyHint || "";
      var emptyHtml = '<p class="prep-board__state">ไม่มีรายการรอจัด</p>';
      if (hint !== "") {
        emptyHtml += '<p class="prep-board__state">' + escapeHtml(hint) + "</p>";
      } else {
        emptyHtml += '<p class="prep-board__state">แสดงเฉพาะเอกสารที่ยังไม่จ่าย (pre_status = pre) — เปรียบเทียบกับ pickup_overview บน server เดียวกัน</p>';
      }
      main.innerHTML = emptyHtml;
      return;
    }

    var html = "";
    var cardIndex = 0;
    buildPartGroups().forEach(function (group) {
      if (!partGroupHasVisibleLines(group)) {
        return;
      }
      var staggerIndex = Math.min(cardIndex++, 8);

      var preparedCount = 0;
      var pickableCount = 0;
      var totalQty = 0;
      var needQty = 0;
      var stockQty = null;
      var docIds = {};
      group.lines.forEach(function (line) {
        if (!line.is_tech) {
          pickableCount++;
          needQty += parseAmount(line.pre_amount);
          if (stockQty === null) {
            stockQty = parseAmount(line.stock);
          }
          if (line.prepared) {
            preparedCount++;
          }
        }
        totalQty += parseAmount(line.pre_amount);
        docIds[line.pre_id] = true;
      });
      // สต็อกยังไม่ถูกตัดจนกว่าคลังกดยืนยันจ่าย — เทียบกับยอดที่ต้องจัดทั้งหมดของอะไหล่ตัวนี้
      var stockLeft = Math.max(0, stockQty || 0);
      var stockState = stockLeft <= 0 ? "out" : (stockLeft < needQty ? "short" : "ok");
      var stockTag = { ok: "พอ", short: "ของไม่พอ", out: "หมดคลัง" }[stockState];
      var lineTotal = group.lines.length;
      var pct = pickableCount > 0 ? Math.round((preparedCount / pickableCount) * 100) : 100;
      var docCount = Object.keys(docIds).length;
      var displayPartName = prepBoardDisplayPartName(group.part_name);
      var techOnlyGroup = pickableCount === 0 && lineTotal > 0;

      var groupDone = pickableCount > 0 && preparedCount === pickableCount;
      html += '<article class="prep-board__doc prep-board__part-group' + (techOnlyGroup ? " prep-board__part-group--tech-only" : "") + (groupDone ? " is-done" : "") + '" style="--i:' + staggerIndex + '" data-part-key="' + escapeHtml(group.part_key) + '">';
      if (!techOnlyGroup) {
        html += '<div class="prep-board__doc-head">';
        html += '<span class="prep-board__stamp" aria-hidden="true">จัดแล้ว!</span>';
        html += '<div class="prep-board__part-title">' + escapeHtml(displayPartName || group.part_name) + "</div>";
        html += '<div class="prep-board__part-summary">';
        html += '<span class="prep-board__part-total">รวม ' + escapeHtml(formatAmount(totalQty)) + " ชิ้น</span>";
        html += "<span>" + docCount + " เอกสาร · " + lineTotal + " รายการ</span>";
        html += "</div>";
        html += '<div class="prep-board__stock">ต้องจัด <b>' + escapeHtml(formatAmount(needQty)) + "</b> · มีในคลัง " + escapeHtml(formatAmount(stockQty || 0))
          + ' <span class="prep-board__stock-tag is-' + stockState + '">' + stockTag + "</span></div>";
        html += '<div class="prep-board__progress"><div class="prep-board__progress-bar" style="width:' + pct + '%"></div></div>';
        html += '<p class="prep-board__progress-text">จัดแล้ว ' + preparedCount + " / " + pickableCount + " รายการ</p>";
        html += "</div>";
      }

      var sortedLines = group.lines.slice().sort(function (a, b) {
        return parseAmount(b.pre_amount) - parseAmount(a.pre_amount);
      });
      var allocLeft = stockLeft;
      sortedLines.forEach(function (line) {
        if (line.is_tech) {
          if (filterMode === "pending") {
            return;
          }
          var techMeta = [];
          if (line.line_sn) {
            techMeta.push(escapeHtml(line.line_sn));
          }
          if (line.parts_doc_no) {
            techMeta.push(escapeHtml(line.parts_doc_no));
          }
          var techName = displayPartName || prepBoardDisplayPartName(group.part_name);
          html += '<div class="prep-board__line prep-board__line--tech-ref" role="presentation">';
          html += '<span class="prep-board__tech-badge">คลังช่าง</span>';
          html += '<div class="prep-board__line-body">';
          if (techName) {
            html += '<div class="prep-board__line-name">' + escapeHtml(techName) + "</div>";
          }
          if (line.pre_type) {
            html += '<div class="prep-board__line-sub">' + escapeHtml(line.pre_type) + "</div>";
          }
          if (techMeta.length) {
            html += '<div class="prep-board__line-sub">' + techMeta.join(" · ") + "</div>";
          }
          html += "</div>";
          html += '<div class="prep-board__line-qty prep-board__line-qty--tech">' + escapeHtml(formatAmount(parseAmount(line.pre_amount))) + " ชิ้น</div>";
          html += "</div>";
          return;
        }
        // แบ่งสต็อกให้แถวตามลำดับที่แสดง (คิดทุกแถว แม้ถูกกรองออก) — แถวที่ได้ไม่ครบบอก "จัดได้ N"
        var lineAmt = parseAmount(line.pre_amount);
        var canPick = Math.min(lineAmt, allocLeft);
        allocLeft -= canPick;
        if (!lineMatchesFilter(line)) {
          return;
        }
        var paidAmt = parseAmount(line.paid_amount);
        var backTag = paidAmt > 0 ? ' <span class="prep-board__back-tag">ค้างจ่าย</span>' : "";
        var paidNote = paidAmt > 0
          ? " · จ่ายไปแล้ว " + escapeHtml(formatAmount(paidAmt)) + " จาก " + escapeHtml(formatAmount(paidAmt + lineAmt))
          : "";
        var canNote = canPick + 0.0001 < lineAmt
          ? '<small class="prep-board__line-can">' + (canPick > 0 ? "จัดได้ " + escapeHtml(formatAmount(canPick)) : "รอของเข้า") + "</small>"
          : "";
        var preparedClass = line.prepared ? " is-prepared" : "";
        html += '<div class="prep-board__line prep-board__line--alloc' + preparedClass + '" role="button" tabindex="0" data-pre-running="' + line.pre_running + '" data-prepared="' + (line.prepared ? "1" : "0") + '">';
        html += '<span class="prep-board__check" aria-hidden="true"><svg class="prep-board__check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>';
        html += '<div class="prep-board__line-body">';
        html += '<div class="prep-board__line-name">' + escapeHtml(line.pre_id) + backTag + "</div>";
        html += '<div class="prep-board__line-sub">' + escapeHtml(line.pre_by) + " · " + escapeHtml(line.pre_type) + " · " + escapeHtml(line.pre_product) + paidNote + "</div>";
        html += "</div>";
        html += '<div class="prep-board__line-qty">' + escapeHtml(formatAmount(lineAmt)) + " ชิ้น" + canNote + "</div>";
        html += "</div>";
      });

      html += "</article>";
    });

    if (html === "") {
      main.innerHTML = '<p class="prep-board__state">ไม่มีรายการในตัวกรองนี้</p>';
      return;
    }

    main.innerHTML = html;
  }

  function findLine(preRunning) {
    var id = parseInt(preRunning, 10);
    for (var d = 0; d < documents.length; d++) {
      var lines = documents[d].lines;
      for (var i = 0; i < lines.length; i++) {
        if (lines[i].pre_running === id) {
          return { doc: documents[d], line: lines[i], docIndex: d, lineIndex: i };
        }
      }
    }
    return null;
  }

  function recomputeDocProgress(doc) {
    var prepared = 0;
    doc.lines.forEach(function (ln) {
      if (ln.prepared) {
        prepared++;
      }
    });
    doc.progress = { prepared: prepared, total: doc.lines.length };
  }

  function recomputeSummary() {
    var lines = 0;
    var prepared = 0;
    documents.forEach(function (doc) {
      doc.lines.forEach(function (ln) {
        lines++;
        if (ln.prepared) {
          prepared++;
        }
      });
    });
    return {
      lines: lines,
      prepared: prepared,
      pending: lines - prepared,
      documents: documents.length
    };
  }

  function loadList() {
    var main = document.getElementById("prep_main");
    var firstLoad = !hasRendered;
    var refreshEl = document.getElementById("prep_refresh_btn");
    if (main && firstLoad && !main.querySelector(".prep-board__skeleton")) {
      main.innerHTML = skeletonHtml();
    }
    if (refreshEl) {
      refreshEl.classList.add("is-loading");
    }
    function doneLoading() {
      if (refreshEl) {
        refreshEl.classList.remove("is-loading");
      }
    }

    fetch(apiUrl("list"), {
      credentials: "same-origin",
      cache: "no-store",
      headers: { "X-Prep-Board-Token": token }
    })
      .then(function (res) {
        return res.json().then(function (body) {
          return { ok: res.ok, body: body };
        });
      })
      .then(function (result) {
        doneLoading();
        if (result.body && result.body.locked) {
          window.location.reload();
          return;
        }
        if (!result.body || !result.body.ok) {
          var msg = (result.body && result.body.message) ? result.body.message : "โหลดไม่สำเร็จ";
          if (main) {
            main.innerHTML = '<p class="prep-board__state prep-board__state--error">' + escapeHtml(msg) + "</p>";
          }
          return;
        }
        documents = result.body.documents || [];
        techPartGroups = result.body.tech_part_groups || [];
        updateStats(result.body.summary);
        window.prepEmptyHint = result.body.empty_hint || "";
        renderList(firstLoad);
      })
      .catch(function () {
        doneLoading();
        if (main) {
          main.innerHTML = '<p class="prep-board__state prep-board__state--error">เชื่อมต่อไม่ได้</p>';
        }
      });
  }

  function togglePrepared(preRunning, nextPrepared, rowEl) {
    var found = findLine(preRunning);
    if (!found) {
      return;
    }

    var prev = found.line.prepared;
    var wasAllDone = app.classList.contains("is-all-done");
    found.line.prepared = nextPrepared;
    recomputeDocProgress(found.doc);
    updateStats(recomputeSummary());
    if (rowEl) {
      rowEl.classList.toggle("is-prepared", nextPrepared);
      rowEl.setAttribute("data-prepared", nextPrepared ? "1" : "0");
      rowEl.classList.add("is-busy");
      syncCard(rowEl.closest(".prep-board__doc"));
      if (nextPrepared) {
        restartClass(rowEl, "is-pop");
        burstStars(rowEl.querySelector(".prep-board__check"));
        restartClass(document.getElementById("prep_mascot"), "is-jump");
      }
    }
    if (nextPrepared && !wasAllDone && app.classList.contains("is-all-done")) {
      celebrate();
    }

    fetch(apiUrl("set_prepared"), {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-Prep-Board-Token": token
      },
      body: JSON.stringify({ pre_running: preRunning, prepared: nextPrepared })
    })
      .then(function (res) {
        return res.json().then(function (body) {
          return { ok: res.ok, body: body };
        });
      })
      .then(function (result) {
        if (rowEl) {
          rowEl.classList.remove("is-busy");
        }
        if (result.body && result.body.locked) {
          window.location.reload();
          return;
        }
        if (!result.body || !result.body.ok) {
          found.line.prepared = prev;
          recomputeDocProgress(found.doc);
          updateStats(recomputeSummary());
          renderList();
          alert((result.body && result.body.message) ? result.body.message : "บันทึกไม่สำเร็จ");
          return;
        }
        showPrepToast("บันทึกแล้ว");
        // แถว/การ์ดอัปเดตในที่แล้ว — วาดใหม่เฉพาะตอนกรอง (ให้แถวหาย) และรอเอฟเฟกต์เล่นจบก่อน
        if (filterMode !== "all") {
          window.setTimeout(function () {
            renderList();
          }, 700);
        }
      })
      .catch(function () {
        if (rowEl) {
          rowEl.classList.remove("is-busy");
        }
        found.line.prepared = prev;
        recomputeDocProgress(found.doc);
        updateStats(recomputeSummary());
        renderList();
        alert("บันทึกไม่สำเร็จ");
      });
  }

  document.querySelectorAll(".prep-board__filter").forEach(function (btn) {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".prep-board__filter").forEach(function (b) {
        b.classList.remove("is-active");
      });
      btn.classList.add("is-active");
      filterMode = btn.getAttribute("data-filter") || "all";
      renderList(true);
    });
  });

  var liftQtyInput = document.getElementById("pickup_lift_need_no");
  var crewEl = document.getElementById("pickup_lift_crew");
  var KID_W = 30;
  var KID_H = 32;
  var CELL = 6;
  var GRAVITY = 900;
  var CREW_KID_SVG = '<svg viewBox="0 0 32 34">'
    + '<rect class="kid-foot-l" x="8" y="28" width="5" height="5" rx="1.5" fill="#4a2c05"/>'
    + '<rect class="kid-foot-r" x="19" y="28" width="5" height="5" rx="1.5" fill="#4a2c05"/>'
    + '<rect x="2" y="4" width="28" height="25" rx="5" fill="#c98a4b"/>'
    + '<rect x="13" y="4" width="6" height="8" fill="#e9c08f"/>'
    + '<g class="kid-eyes"><circle cx="11" cy="15" r="3.6" fill="#fff"/><circle cx="21" cy="15" r="3.6" fill="#fff"/>'
    + '<circle cx="11.9" cy="15.6" r="1.7" fill="#2b1a07"/><circle cx="21.9" cy="15.6" r="1.7" fill="#2b1a07"/></g>'
    + '<path d="M12.5 21q3.5 3 7 0" fill="none" stroke="#2b1a07" stroke-width="1.6" stroke-linecap="round"/>'
    + '<circle cx="7" cy="20" r="1.8" fill="#f2a07a" opacity=".7"/><circle cx="25" cy="20" r="1.8" fill="#f2a07a" opacity=".7"/>'
    + "</svg>";
  var CREW_OBSTACLES = [
    ".prep-board__lift-label",
    ".prep-board__lift-stepper",
    ".pickup-overview__lift-btn-shell",
    ".prep-board__lift-hint",
    ".prep-board__lift-status",
    ".prep-board__crew-more"
  ];
  var crew = [];
  var crewLast = 0;
  var crewLeaving = false;
  var crewReturnTimer = null;
  var grid = null;

  function rand(min, max) {
    return min + Math.random() * (max - min);
  }

  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }

  /* ─ แผนที่พื้นที่ว่าง: ตาราง 6px — ช่องว่างคือวางน้องกล่อง (มุมซ้ายบน) ตรงนั้นแล้วไม่ทับข้อความ/ปุ่ม ─ */

  function crewBuildGrid() {
    var section = document.getElementById("pickup_lift_section");
    var base = crewEl.getBoundingClientRect();
    var rects = [];
    CREW_OBSTACLES.forEach(function (sel) {
      var el = section.querySelector(sel);
      if (!el || el.hidden) {
        return;
      }
      var r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) {
        return;
      }
      rects.push([r.left - base.left - 3, r.top - base.top - 3, r.right - base.left + 3, r.bottom - base.top + 3]);
    });
    var sig = Math.round(base.width) + "x" + Math.round(base.height) + "|" + rects.map(function (r) {
      return r.map(Math.round).join(",");
    }).join(";");
    if (grid && grid.sig === sig) {
      return false;
    }
    var cols = Math.max(1, Math.floor((base.width - KID_W) / CELL) + 1);
    var rows = Math.max(1, Math.floor((base.height - KID_H) / CELL) + 1);
    var total = cols * rows;
    var free = new Uint8Array(total);
    var comp = new Int16Array(total);
    var i;
    for (i = 0; i < total; i++) {
      var x = (i % cols) * CELL;
      var y = Math.floor(i / cols) * CELL;
      var ok = 1;
      for (var k = 0; k < rects.length; k++) {
        var r = rects[k];
        if (x < r[2] && x + KID_W > r[0] && y < r[3] && y + KID_H > r[1]) {
          ok = 0;
          break;
        }
      }
      free[i] = ok;
      comp[i] = -1;
    }
    var comps = [];
    for (i = 0; i < total; i++) {
      if (!free[i] || comp[i] !== -1) {
        continue;
      }
      var id = comps.length;
      var cells = [i];
      comp[i] = id;
      for (var q = 0; q < cells.length; q++) {
        crewNeighbours(cells[q], cols, rows).forEach(function (n) {
          if (free[n] && comp[n] === -1) {
            comp[n] = id;
            cells.push(n);
          }
        });
      }
      comps.push(cells);
    }
    grid = { cols: cols, rows: rows, free: free, comp: comp, comps: comps, sig: sig, freeCount: comps.reduce(function (s, c) {
      return s + c.length;
    }, 0) };
    return true;
  }

  function crewNeighbours(i, cols, rows) {
    var gx = i % cols;
    var gy = Math.floor(i / cols);
    var out = [];
    for (var dy = -1; dy <= 1; dy++) {
      for (var dx = -1; dx <= 1; dx++) {
        var nx = gx + dx;
        var ny = gy + dy;
        if ((dx || dy) && nx >= 0 && ny >= 0 && nx < cols && ny < rows) {
          out.push(ny * cols + nx);
        }
      }
    }
    return out;
  }

  function crewCellOf(x, y) {
    var gx = clamp(Math.round(x / CELL), 0, grid.cols - 1);
    var gy = clamp(Math.round(y / CELL), 0, grid.rows - 1);
    return gy * grid.cols + gx;
  }

  function crewCellXY(i) {
    return [(i % grid.cols) * CELL, Math.floor(i / grid.cols) * CELL];
  }

  /** ช่องว่างที่ใกล้ที่สุด (กรณีปุ่ม/ข้อความขยับมาทับตัวที่ยืนอยู่) */
  function crewNearestFree(start) {
    var seen = {};
    var queue = [start];
    seen[start] = true;
    for (var q = 0; q < queue.length; q++) {
      if (grid.free[queue[q]]) {
        return queue[q];
      }
      crewNeighbours(queue[q], grid.cols, grid.rows).forEach(function (n) {
        if (!seen[n]) {
          seen[n] = true;
          queue.push(n);
        }
      });
    }
    return -1;
  }

  /** สุ่มพื้นที่ตามขนาด แล้วสุ่มช่องในพื้นที่นั้น */
  function crewRandomCell() {
    var pick = Math.random() * grid.freeCount;
    for (var c = 0; c < grid.comps.length; c++) {
      if (pick < grid.comps[c].length) {
        return grid.comps[c][Math.floor(pick)];
      }
      pick -= grid.comps[c].length;
    }
    return -1;
  }

  /** เส้นทางเดินอ้อมสิ่งกีดขวาง (BFS ในช่องว่าง) — คืนจุดแวะเป็นพิกัด px */
  function crewPath(from, to) {
    if (from === to) {
      return [];
    }
    var prev = {};
    prev[from] = -1;
    var queue = [from];
    for (var q = 0; q < queue.length && prev[to] === undefined; q++) {
      crewNeighbours(queue[q], grid.cols, grid.rows).forEach(function (n) {
        if (grid.free[n] && prev[n] === undefined) {
          prev[n] = queue[q];
          queue.push(n);
        }
      });
    }
    if (prev[to] === undefined) {
      return [];
    }
    var cells = [];
    for (var c = to; c !== from; c = prev[c]) {
      cells.unshift(c);
    }
    return cells.filter(function (cell, idx) {
      return idx % 3 === 2 || idx === cells.length - 1;
    }).map(crewCellXY);
  }

  function crewCapacity() {
    return grid ? clamp(Math.floor(grid.freeCount / 28), 1, 12) : 1;
  }

  /* ─ ตัวละคร ─ */

  /** z = ความสูงจากพื้น, vz > 0 คือพุ่งขึ้น */
  function crewHop(kid, vz) {
    kid.mode = "hop";
    kid.vz = vz;
  }

  /** กระโดดได้สูงไม่เกินช่องว่างเหนือหัว (ไม่ให้หัวโผล่ทับข้อความ/ปุ่ม) — ไม่มีที่ว่างก็ไม่กระโดด */
  function crewHopSafe(kid, wantVz) {
    var cell = crewCellOf(kid.x, kid.y);
    var room = 0;
    while (room < 40 && cell - grid.cols >= 0 && grid.free[cell - grid.cols]) {
      cell -= grid.cols;
      room += CELL;
    }
    if (room < 6) {
      return false;
    }
    crewHop(kid, Math.min(wantVz, Math.sqrt(2 * GRAVITY * room)));
    return true;
  }

  function crewGoTo(kid, cell, mode, speed) {
    var path = cell < 0 ? [] : crewPath(crewCellOf(kid.x, kid.y), cell);
    if (!path.length) {
      return false;
    }
    kid.path = path;
    kid.mode = mode;
    kid.speed = speed;
    kid.t = 12;
    return true;
  }

  /** ท่าต่อไปแบบสุ่ม: เดิน 40% วิ่ง 22% หยุดมองรอบตัว 26% กระโดด 12% — เดินได้เฉพาะในพื้นที่ว่างเดียวกัน */
  function crewPickMove(kid) {
    var r = Math.random();
    var here = crewCellOf(kid.x, kid.y);
    var area = grid.comp[here] >= 0 ? grid.comps[grid.comp[here]] : null;
    if (r < 0.62 && area && area.length > 1) {
      var target = area[Math.floor(Math.random() * area.length)];
      var run = r >= 0.4;
      if (crewGoTo(kid, target, run ? "run" : "walk", run ? rand(65, 100) : rand(18, 34))) {
        return;
      }
    }
    kid.path = [];
    kid.speed = 0;
    if (r >= 0.88 && area && crewHopSafe(kid, rand(160, 220))) {
      return;
    }
    kid.mode = "idle";
    kid.t = rand(0.5, 2);
    if (Math.random() < 0.5) {
      kid.dir = -kid.dir;
    }
  }

  function crewPlace(kid) {
    var cell = crewRandomCell();
    var xy = cell >= 0 ? crewCellXY(cell) : [0, 0];
    kid.x = xy[0];
    kid.y = xy[1];
    kid.z = 0;
    kid.vz = 0;
    kid.path = [];
    kid.mode = "idle";
    kid.t = rand(0.3, 1.2);
    kid.grow = 0;
  }

  function crewAdd() {
    var el = document.createElement("span");
    el.className = "prep-board__crew-kid";
    el.innerHTML = CREW_KID_SVG;
    el.querySelector(".kid-eyes").style.animationDelay = rand(0, 4).toFixed(2) + "s";
    crewEl.insertBefore(el, crewEl.querySelector(".prep-board__crew-more"));
    var kid = {
      el: el,
      svg: el.firstChild,
      footL: el.querySelector(".kid-foot-l"),
      footR: el.querySelector(".kid-foot-r"),
      dir: Math.random() < 0.5 ? -1 : 1,
      speed: 0,
      cool: 0,
      phase: rand(0, 6)
    };
    crewPlace(kid);
    kid.grow = reduceMotion ? 1 : 0;
    crew.push(kid);
    crewDraw(kid);
  }

  function crewDraw(kid) {
    var moving = kid.mode === "walk" || kid.mode === "run" || kid.mode === "leave";
    var amp = kid.mode === "walk" ? 1 : 1.5;
    var s = Math.sin(kid.phase);
    var bob = moving ? -Math.abs(s) * 2.2 * amp : 0;
    var tilt = moving ? s * 5 * amp : (kid.mode === "hop" ? -8 : 0);
    var g = kid.grow >= 1 ? 1 : 1 + 2.7 * Math.pow(kid.grow - 1, 3) + 1.7 * Math.pow(kid.grow - 1, 2);
    kid.el.style.transform = "translate(" + kid.x.toFixed(1) + "px," + (kid.y - kid.z + bob).toFixed(1) + "px)";
    kid.el.style.zIndex = String(Math.round(kid.y) + 1);
    kid.svg.style.transform = "scale(" + g.toFixed(3) + ") scaleX(" + kid.dir + ") rotate(" + tilt.toFixed(1) + "deg)";
    kid.footL.style.transform = "translateY(" + (moving ? Math.min(0, s) * 2.5 : 0).toFixed(1) + "px)";
    kid.footR.style.transform = "translateY(" + (moving ? Math.min(0, -s) * 2.5 : 0).toFixed(1) + "px)";
  }

  /** เดินตามจุดแวะ — คืน true เมื่อถึงปลายทาง */
  function crewFollow(kid, dt) {
    var budget = kid.speed * dt;
    while (kid.path.length && budget > 0) {
      var p = kid.path[0];
      var dx = p[0] - kid.x;
      var dy = p[1] - kid.y;
      var d = Math.sqrt(dx * dx + dy * dy);
      if (Math.abs(dx) > 0.5) {
        kid.dir = dx > 0 ? 1 : -1;
      }
      if (d <= budget) {
        kid.x = p[0];
        kid.y = p[1];
        budget -= d;
        kid.path.shift();
      } else {
        kid.x += (dx / d) * budget;
        kid.y += (dy / d) * budget;
        budget = 0;
      }
    }
    return kid.path.length === 0;
  }

  /** ชนกันแล้วแตกฮือวิ่งหนีไปจุดที่ไกลจากอีกตัว ตัวหนึ่งกระโดดตกใจ */
  function crewInteract() {
    for (var i = 0; i < crew.length; i++) {
      for (var j = i + 1; j < crew.length; j++) {
        var a = crew[i];
        var b = crew[j];
        if (a.z > 0 || b.z > 0 || a.cool > 0 || b.cool > 0 || a.mode === "hop" || b.mode === "hop") {
          continue;
        }
        if (a.mode === "idle" && b.mode === "idle") {
          continue;
        }
        var dx = b.x - a.x;
        var dy = b.y - a.y;
        if (dx * dx + dy * dy > 18 * 18) {
          continue;
        }
        [[a, b], [b, a]].forEach(function (pair) {
          var me = pair[0];
          var other = pair[1];
          var here = crewCellOf(me.x, me.y);
          var area = grid.comp[here] >= 0 ? grid.comps[grid.comp[here]] : null;
          me.cool = 1.2;
          if (!area) {
            return;
          }
          var best = -1;
          var bestD = -1;
          for (var n = 0; n < 5; n++) {
            var cand = area[Math.floor(Math.random() * area.length)];
            var xy = crewCellXY(cand);
            var dd = Math.pow(xy[0] - other.x, 2) + Math.pow(xy[1] - other.y, 2);
            if (dd > bestD) {
              bestD = dd;
              best = cand;
            }
          }
          crewGoTo(me, best, "run", rand(70, 100));
        });
        crewHopSafe(Math.random() < 0.5 ? a : b, 170);
      }
    }
  }

  /** แบ่งเวลาที่ผ่านไปเป็นช่วงละ 20ms — เฟรมกระตุกก็ยังเคลื่อนที่ตามเวลาจริง (เพดาน 1 วินาที กันกระโดดหลังสลับแท็บ) */
  function crewStep(now) {
    var elapsed = Math.min(1, Math.max(0, (now - crewLast) / 1000));
    crewLast = now;
    while (elapsed > 0) {
      var dt = Math.min(0.02, elapsed);
      elapsed -= dt;
      crewTick(dt);
    }
    crew.forEach(crewDraw);
    window.requestAnimationFrame(crewStep);
  }

  function crewTick(dt) {
    crew.forEach(function (kid) {
      kid.t -= dt;
      kid.cool = Math.max(0, kid.cool - dt);
      if (kid.grow < 1) {
        kid.grow = Math.min(1, kid.grow + dt / 0.35);
      }
      if (kid.mode === "gone") {
        return;
      }
      if (kid.mode === "hop") {
        kid.vz -= GRAVITY * dt;
        kid.z += kid.vz * dt;
        if (kid.speed > 0) {
          crewFollow(kid, dt);
        }
        if (kid.z <= 0 && kid.vz < 0) {
          kid.z = 0;
          kid.vz = 0;
          if (crewLeaving) {
            kid.mode = "leave";
          } else if (kid.path.length) {
            kid.mode = "run";
          } else {
            crewPickMove(kid);
          }
        }
      } else if (kid.mode === "leave") {
        if (crewFollow(kid, dt)) {
          kid.mode = "gone";
          kid.el.classList.add("is-out");
        }
      } else if (kid.mode === "walk" || kid.mode === "run") {
        if (crewFollow(kid, dt) || kid.t <= 0) {
          crewPickMove(kid);
        }
      } else if (kid.t <= 0) {
        crewPickMove(kid);
      }
      if (kid.mode !== "idle") {
        kid.phase += dt * Math.max(6, kid.speed * 0.32);
      }
    });
    if (!crewLeaving) {
      crewInteract();
    } else if (crew.every(function (k) { return k.mode === "gone"; })) {
      crewReturn();
    }
  }

  /** ออกไปครบแล้ว — โผล่กลับมาทีละตัวตามจุดว่างทั่วการ์ด */
  function crewReturn() {
    if (crewReturnTimer) {
      window.clearTimeout(crewReturnTimer);
      crewReturnTimer = null;
    }
    crewLeaving = false;
    crewBuildGrid();
    crew.forEach(function (kid, i) {
      crewPlace(kid);
      kid.t = 0.4 + i * 0.25;
      kid.el.classList.remove("is-out");
    });
  }

  /** ปุ่ม/ข้อความขยับ (หมุนจอ, แถบสถานะโผล่) — สร้างแผนที่ใหม่ ย้ายตัวที่โดนทับไปช่องว่างใกล้สุด */
  function crewRelocate() {
    crew.forEach(function (kid) {
      var cell = crewCellOf(kid.x, kid.y);
      kid.path = [];
      if (!grid.free[cell]) {
        var near = crewNearestFree(cell);
        if (near >= 0) {
          kid.path = [crewCellXY(near)];
          kid.mode = "run";
          kid.speed = 90;
          kid.t = 3;
          return;
        }
      }
      if (kid.mode === "walk" || kid.mode === "run") {
        kid.mode = "idle";
        kid.t = rand(0.2, 0.8);
      }
    });
  }

  /** จำนวนตัวตามช่องจำนวนคน — เกินพื้นที่ว่างขึ้นป้าย +N */
  function crewSync() {
    if (!crewEl || !liftQtyInput) {
      return;
    }
    var changed = crewBuildGrid();
    var wanted = Math.max(1, parseInt(liftQtyInput.value, 10) || 1);
    var shown = Math.min(wanted, crewCapacity());
    while (crew.length < shown) {
      crewAdd();
    }
    while (crew.length > shown) {
      var gone = crew.pop();
      gone.el.classList.add("is-out");
      window.setTimeout(gone.el.remove.bind(gone.el), 320);
    }
    var more = crewEl.querySelector(".prep-board__crew-more");
    if (wanted > shown) {
      if (!more) {
        more = document.createElement("span");
        more.className = "prep-board__crew-more";
        crewEl.appendChild(more);
      }
      more.textContent = "+" + (wanted - shown);
    } else if (more) {
      more.remove();
    }
    if (changed && !crewLeaving) {
      crewRelocate();
    }
  }

  if (crewEl) {
    document.querySelectorAll("#pickup_lift_section .pickup-lift-qty-stepper__btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        bump(liftQtyInput);
        crewSync();
      });
    });
    window.addEventListener("resize", crewSync);
    crewSync();
    if (!reduceMotion) {
      window.setInterval(crewSync, 500);
      crewLast = performance.now();
      window.requestAnimationFrame(crewStep);
    }
  }

  // กดยืนยันเรียก (ไม่ใช่ปุ่มตกลงของ alert) — ทุกตัวกระโดดแล้ววิ่งไปขอบขวาของพื้นที่ตัวเอง หายไป แล้วโผล่กลับมา
  var liftOkBtn = document.getElementById("pickup_lift_dialog_ok");
  var liftCancelBtn = document.getElementById("pickup_lift_dialog_cancel");
  if (crewEl && liftOkBtn && liftCancelBtn && !reduceMotion) {
    liftOkBtn.addEventListener("click", function () {
      if (liftCancelBtn.hidden || crewLeaving) {
        return;
      }
      crewLeaving = true;
      crew.forEach(function (kid) {
        var here = crewCellOf(kid.x, kid.y);
        var area = grid.comp[here] >= 0 ? grid.comps[grid.comp[here]] : [here];
        var exit = area.reduce(function (best, c) {
          return (c % grid.cols) > (best % grid.cols) ? c : best;
        }, area[0]);
        kid.path = crewPath(here, exit);
        kid.speed = rand(200, 260);
        if (!crewHopSafe(kid, rand(170, 220))) {
          kid.mode = "leave";
        }
      });
      crewReturnTimer = window.setTimeout(crewReturn, 3500);
    });
  }

  var mascot = document.getElementById("prep_mascot");
  if (mascot) {
    mascot.addEventListener("animationend", function (ev) {
      if (ev.animationName === "prep-jump") {
        mascot.classList.remove("is-jump");
      }
    });
  }

  var refreshBtn = document.getElementById("prep_refresh_btn");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", loadList);
  }

  document.getElementById("prep_main").addEventListener("click", function (ev) {
    var toggleRemarks = ev.target.closest("[data-toggle-remarks]");
    if (toggleRemarks) {
      var remarks = toggleRemarks.parentElement.querySelector("[data-remarks]");
      if (remarks) {
        var collapsed = remarks.classList.toggle("is-collapsed");
        toggleRemarks.textContent = collapsed ? "ดูเพิ่ม" : "ย่อ";
      }
      return;
    }

    var row = ev.target.closest(".prep-board__line");
    if (!row || row.classList.contains("is-busy")) {
      return;
    }
    var preRunning = parseInt(row.getAttribute("data-pre-running"), 10);
    var prepared = row.getAttribute("data-prepared") === "1";
    togglePrepared(preRunning, !prepared, row);
  });

  document.getElementById("prep_main").addEventListener("keydown", function (ev) {
    if (ev.key !== "Enter" && ev.key !== " ") {
      return;
    }
    var row = ev.target.closest(".prep-board__line");
    if (!row) {
      return;
    }
    ev.preventDefault();
    row.click();
  });

  if (typeof pickupLiftInitOfficeVisibility === "function") {
    pickupLiftInitOfficeVisibility();
  }

  loadList();
  refreshTimer = window.setInterval(loadList, 60000);
  window.addEventListener("pagehide", function () {
    if (refreshTimer) {
      window.clearInterval(refreshTimer);
    }
  });
})();
</script>
<?php } ?>
</body>
</html>
