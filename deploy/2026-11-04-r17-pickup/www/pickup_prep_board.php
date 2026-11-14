<?php
/**
 * หน้ารายการจัดของรอเบิก — มือถือ (แยกจากเมนู inventory)
 *
 * แสดงอะไหล่ในเอกสาร pre_status = pre จัดกลุ่มตาม pre_id
 * ติ๊กจัดเตรียมแล้ว/ยังไม่จัด ผ่าน pickup_prep_api.php
 * ต้องเปิดด้วย ?token= ตรงกับ pickup_prep_board_token ใน config
 *
 * Flow สั้น: pickup_prep_board.php?token=SECRET → JS โหลด list → กดแถว toggle pre_check
 *
 * ตัวอย่าง: https://example.com/inventory/pickup_prep_board.php?token=...
 */

require_once __DIR__ . '/inventory_config.php';

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$expected = inventory_config_get('pickup_prep_board_token', '');
$tokenOk = ($expected !== '' && $token !== '' && hash_equals($expected, $token));

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
<meta name="theme-color" content="#1e3a5f" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<title>จัดของรอเบิก</title>
<?php require_once __DIR__ . '/inventory_ui_fonts.php'; ?>
<link rel="stylesheet" href="css/pickup-prep.css?v=1" />
</head>
<body class="prep-board">
<?php if (!$tokenOk) { ?>
<div class="prep-board__shell">
  <div class="prep-board__gate">
    <h1 class="prep-board__gate-title">ไม่สามารถเปิดหน้านี้ได้</h1>
    <p><?php echo $expected === '' ? 'ยังไม่ได้ตั้งค่า token บน server' : 'ลิงก์ไม่ถูกต้องหรือไม่มี token'; ?></p>
  </div>
</div>
<?php } else { ?>
<div class="prep-board__shell" id="prep_app" data-token="<?php echo $h($token); ?>">
  <header class="prep-board__header">
    <div class="prep-board__title-row">
      <div>
        <h1 class="prep-board__title">จัดของรอเบิก</h1>
        <p class="prep-board__subtitle" id="prep_subtitle">กำลังโหลด…</p>
      </div>
      <button type="button" class="prep-board__refresh" id="prep_refresh_btn" aria-label="โหลดใหม่">↻</button>
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
  </header>
  <div class="prep-board__filters" role="tablist" aria-label="กรองรายการ">
    <button type="button" class="prep-board__filter is-active" data-filter="all" role="tab">ทั้งหมด</button>
    <button type="button" class="prep-board__filter" data-filter="pending" role="tab">ยังไม่จัด</button>
    <button type="button" class="prep-board__filter" data-filter="prepared" role="tab">จัดแล้ว</button>
  </div>
  <main class="prep-board__main" id="prep_main" aria-live="polite">
    <p class="prep-board__state">กำลังโหลดรายการ…</p>
  </main>
  <footer class="prep-board__footer">อัปเดตอัตโนมัติทุก 60 วินาที</footer>
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
  var refreshTimer = null;

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
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
    document.getElementById("prep_stat_prepared").textContent = String(summary.prepared);
    document.getElementById("prep_stat_pending").textContent = String(summary.pending);
    document.getElementById("prep_stat_docs").textContent = String(summary.documents);
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

  function docHasVisibleLines(doc) {
    for (var i = 0; i < doc.lines.length; i++) {
      if (lineMatchesFilter(doc.lines[i])) {
        return true;
      }
    }
    return false;
  }

  function renderList() {
    var main = document.getElementById("prep_main");
    if (!main) {
      return;
    }

    if (!documents.length) {
      main.innerHTML = '<p class="prep-board__state">ไม่มีรายการรอจัด</p>';
      return;
    }

    var html = "";
    documents.forEach(function (doc) {
      if (!docHasVisibleLines(doc)) {
        return;
      }

      var progress = doc.progress || { prepared: 0, total: doc.lines.length };
      var pct = progress.total > 0 ? Math.round((progress.prepared / progress.total) * 100) : 0;
      var remarks = doc.remarks || "";
      var remarksHtml = "";
      if (remarks !== "") {
        remarksHtml =
          '<p class="prep-board__doc-remarks is-collapsed" data-remarks>' + escapeHtml(remarks) + "</p>" +
          '<button type="button" class="prep-board__remarks-toggle" data-toggle-remarks>ดูเพิ่ม</button>';
      }

      html += '<article class="prep-board__doc" data-pre-id="' + escapeHtml(doc.pre_id) + '">';
      html += '<div class="prep-board__doc-head">';
      html += '<div class="prep-board__doc-id">' + escapeHtml(doc.pre_id) + "</div>";
      html += '<div class="prep-board__doc-meta">';
      html += "<span>" + escapeHtml(doc.pre_date) + "</span>";
      html += "<span>" + escapeHtml(doc.pre_by) + "</span>";
      html += "<span>" + escapeHtml(doc.pre_type) + "</span>";
      html += "</div>";
      html += remarksHtml;
      html += '<div class="prep-board__progress"><div class="prep-board__progress-bar" style="width:' + pct + '%"></div></div>';
      html += '<p class="prep-board__progress-text">จัดแล้ว ' + progress.prepared + " / " + progress.total + "</p>";
      html += "</div>";

      doc.lines.forEach(function (line) {
        if (!lineMatchesFilter(line)) {
          return;
        }
        var preparedClass = line.prepared ? " is-prepared" : "";
        html += '<div class="prep-board__line' + preparedClass + '" role="button" tabindex="0" data-pre-running="' + line.pre_running + '" data-prepared="' + (line.prepared ? "1" : "0") + '">';
        html += '<span class="prep-board__check" aria-hidden="true"><svg class="prep-board__check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>';
        html += '<div class="prep-board__line-body">';
        html += '<div class="prep-board__line-name">' + escapeHtml(line.part_name) + "</div>";
        html += '<div class="prep-board__line-sub">' + escapeHtml(line.pre_product) + "</div>";
        html += "</div>";
        html += '<div class="prep-board__line-qty">×' + escapeHtml(line.pre_amount) + "</div>";
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
    if (main) {
      main.innerHTML = '<p class="prep-board__state">กำลังโหลดรายการ…</p>';
    }

    fetch(apiUrl("list"), { credentials: "same-origin", cache: "no-store" })
      .then(function (res) {
        return res.json().then(function (body) {
          return { ok: res.ok, body: body };
        });
      })
      .then(function (result) {
        if (!result.body || !result.body.ok) {
          var msg = (result.body && result.body.message) ? result.body.message : "โหลดไม่สำเร็จ";
          if (main) {
            main.innerHTML = '<p class="prep-board__state prep-board__state--error">' + escapeHtml(msg) + "</p>";
          }
          return;
        }
        documents = result.body.documents || [];
        updateStats(result.body.summary);
        renderList();
      })
      .catch(function () {
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
    found.line.prepared = nextPrepared;
    recomputeDocProgress(found.doc);
    updateStats(recomputeSummary());
    if (rowEl) {
      rowEl.classList.toggle("is-prepared", nextPrepared);
      rowEl.setAttribute("data-prepared", nextPrepared ? "1" : "0");
      rowEl.classList.add("is-busy");
    }

    fetch(apiUrl("set_prepared"), {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
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
        renderList();
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
      renderList();
    });
  });

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
