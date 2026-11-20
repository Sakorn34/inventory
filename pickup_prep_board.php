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

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="theme-color" content="#ffd98a" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<title>จัดของรอเบิก</title>
<link rel="icon" type="image/png" href="images/bitcombine-logo.png" />
<link rel="apple-touch-icon" href="images/bitcombine-logo.png" />
<?php require_once __DIR__ . '/inventory_ui_fonts.php'; ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Mitr:wght@500&amp;display=swap" />
<link rel="stylesheet" href="css/pickup-prep.css?v=15" />
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
      var docIds = {};
      group.lines.forEach(function (line) {
        if (!line.is_tech) {
          pickableCount++;
          if (line.prepared) {
            preparedCount++;
          }
        }
        totalQty += parseAmount(line.pre_amount);
        docIds[line.pre_id] = true;
      });
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
        html += '<div class="prep-board__progress"><div class="prep-board__progress-bar" style="width:' + pct + '%"></div></div>';
        html += '<p class="prep-board__progress-text">จัดแล้ว ' + preparedCount + " / " + pickableCount + " รายการ</p>";
        html += "</div>";
      }

      var sortedLines = group.lines.slice().sort(function (a, b) {
        return parseAmount(b.pre_amount) - parseAmount(a.pre_amount);
      });
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
        if (!lineMatchesFilter(line)) {
          return;
        }
        var preparedClass = line.prepared ? " is-prepared" : "";
        html += '<div class="prep-board__line prep-board__line--alloc' + preparedClass + '" role="button" tabindex="0" data-pre-running="' + line.pre_running + '" data-prepared="' + (line.prepared ? "1" : "0") + '">';
        html += '<span class="prep-board__check" aria-hidden="true"><svg class="prep-board__check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>';
        html += '<div class="prep-board__line-body">';
        html += '<div class="prep-board__line-name">' + escapeHtml(line.pre_id) + "</div>";
        html += '<div class="prep-board__line-sub">' + escapeHtml(line.pre_by) + " · " + escapeHtml(line.pre_type) + " · " + escapeHtml(line.pre_product) + "</div>";
        html += "</div>";
        html += '<div class="prep-board__line-qty">' + escapeHtml(formatAmount(parseAmount(line.pre_amount))) + " ชิ้น</div>";
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
  document.querySelectorAll("#pickup_lift_section .pickup-lift-qty-stepper__btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      bump(liftQtyInput);
    });
  });

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
