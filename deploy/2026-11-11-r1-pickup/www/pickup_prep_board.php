<?php
/**
 * หน้ารายการจัดของรอเบิก — มือถือ (แยกจากเมนู inventory)
 *
 * แสดงอะไหล่ในเอกสาร pre_status = pre จัดกลุ่มตามชื่ออะไหล่ (หยิบครั้งเดียวต่อ part)
 * ติ๊กจัดเตรียมแล้ว/ยังไม่จัด ผ่าน pickup_prep_api.php
 * ต้องเปิดด้วย ?token= ตรงกับ pickup_prep_board_token ใน config
 *
 * Flow สั้น: pickup_prep_board.php?token=SECRET → JS โหลด list → กดแถว toggle pre_check
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
<meta name="theme-color" content="#1e3a5f" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<title>จัดของรอเบิก</title>
<link rel="icon" type="image/png" href="images/bitcombine-logo.png" />
<link rel="apple-touch-icon" href="images/bitcombine-logo.png" />
<?php require_once __DIR__ . '/inventory_ui_fonts.php'; ?>
<link rel="stylesheet" href="css/pickup-prep.css?v=3" />
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
<div class="prep-board__shell" id="prep_app" data-token="<?php echo $h($token); ?>">
  <header class="prep-board__header">
    <div class="prep-board__title-row">
      <div>
        <h1 class="prep-board__title">จัดของรอเบิก</h1>
        <p class="prep-board__subtitle prep-board__subtitle--hint">จัดกลุ่มตามชื่ออะไหล่ — หยิบครั้งเดียวต่อตัว</p>
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
  var refreshTimer = null;
  var toastTimer = null;

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
          pre_type: doc.pre_type
        });
      });
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
      if (lineMatchesFilter(group.lines[i])) {
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
    buildPartGroups().forEach(function (group) {
      if (!partGroupHasVisibleLines(group)) {
        return;
      }

      var preparedCount = 0;
      var totalQty = 0;
      var docIds = {};
      group.lines.forEach(function (line) {
        if (line.prepared) {
          preparedCount++;
        }
        totalQty += parseAmount(line.pre_amount);
        docIds[line.pre_id] = true;
      });
      var lineTotal = group.lines.length;
      var pct = lineTotal > 0 ? Math.round((preparedCount / lineTotal) * 100) : 0;
      var docCount = Object.keys(docIds).length;

      html += '<article class="prep-board__doc prep-board__part-group" data-part-key="' + escapeHtml(group.part_key) + '">';
      html += '<div class="prep-board__doc-head">';
      html += '<div class="prep-board__part-title">' + escapeHtml(group.part_name) + "</div>";
      html += '<div class="prep-board__part-summary">';
      html += '<span class="prep-board__part-total">รวม ' + escapeHtml(formatAmount(totalQty)) + " ชิ้น</span>";
      html += "<span>" + docCount + " เอกสาร · " + lineTotal + " รายการ</span>";
      html += "</div>";
      html += '<div class="prep-board__progress"><div class="prep-board__progress-bar" style="width:' + pct + '%"></div></div>';
      html += '<p class="prep-board__progress-text">จัดแล้ว ' + preparedCount + " / " + lineTotal + " รายการ</p>";
      html += "</div>";

      var sortedLines = group.lines.slice().sort(function (a, b) {
        return parseAmount(b.pre_amount) - parseAmount(a.pre_amount);
      });
      sortedLines.forEach(function (line) {
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
    if (main) {
      main.innerHTML = '<p class="prep-board__state">กำลังโหลดรายการ…</p>';
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
        if (!result.body || !result.body.ok) {
          var msg = (result.body && result.body.message) ? result.body.message : "โหลดไม่สำเร็จ";
          if (main) {
            main.innerHTML = '<p class="prep-board__state prep-board__state--error">' + escapeHtml(msg) + "</p>";
          }
          return;
        }
        documents = result.body.documents || [];
        updateStats(result.body.summary);
        window.prepEmptyHint = result.body.empty_hint || "";
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
