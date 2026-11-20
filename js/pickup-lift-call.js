/**
 * ปุ่มช่วยยกของ — ส่ง GET ไป Node-RED ในพื้นหลัง (iframe)
 */
(function (global) {
  "use strict";

  var pickupLiftSubmitAllowed = false;
  var pickupLiftStatusHideTimer = null;
  var pickupLiftDialogOnOk = null;
  var pickupLiftDialogBound = false;

  function pickupLiftConfig() {
    return global.pickupLiftConfig || {};
  }

  function pickupLiftIsPrivateHost(hostname) {
    if (!hostname) {
      return false;
    }
    if (hostname === "localhost" || hostname === "127.0.0.1") {
      return true;
    }
    if (/^192\.168\.\d{1,3}\.\d{1,3}$/.test(hostname)) {
      return true;
    }
    if (/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(hostname)) {
      return true;
    }
    return /^172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}$/.test(hostname);
  }

  function pickupLiftSetSectionVisible(show) {
    var section = document.getElementById("pickup_lift_section");
    if (!section) {
      return;
    }
    section.hidden = !show;
  }

  function pickupLiftInitOfficeVisibility() {
    var section = document.getElementById("pickup_lift_section");
    if (!section) {
      return;
    }
    var cfg = pickupLiftConfig();
    if (cfg.visibility === "always") {
      pickupLiftSetSectionVisible(true);
      return;
    }
    if (cfg.visibility === "never") {
      pickupLiftSetSectionVisible(false);
      return;
    }
    pickupLiftSetSectionVisible(pickupLiftIsPrivateHost(global.location.hostname));
  }

  function pickupLiftIsIOS() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent);
  }

  function pickupLiftFireRequest(url) {
    // iOS Local Network Privacy (iOS 14+) silently blocks background fetch/XHR/iframe
    // requests to private-network IPs from web pages. window.open() is a user-visible
    // navigation so iOS allows it; the tab may briefly appear and close is handled by UX.
    if (pickupLiftIsIOS()) {
      var tab = global.open(url, "_blank", "noopener,noreferrer");
      if (!tab) {
        // Popup blocker active on iOS — last resort: iframe (better than nothing)
        var pingFrame = document.getElementById("pickup_lift_ping");
        if (pingFrame) {
          pingFrame.src = url;
        }
      }
      return;
    }
    if (global.fetch) {
      try {
        global.fetch(url, { mode: "no-cors" });
      } catch (ignore) {
        // intentional
      }
      return;
    }
    // Legacy fallback: browsers without fetch
    var pingFrameLegacy = document.getElementById("pickup_lift_ping");
    if (pingFrameLegacy) {
      pingFrameLegacy.src = url;
    }
  }

  function pickupLiftPingPrelude(done) {
    var cfg = pickupLiftConfig();
    if (!cfg.preludeUrl) {
      if (typeof done === "function") {
        done();
      }
      return;
    }
    pickupLiftFireRequest(cfg.preludeUrl);
    global.setTimeout(function () {
      if (typeof done === "function") {
        done();
      }
    }, cfg.preludeDelayMs > 0 ? cfg.preludeDelayMs : 1200);
  }

  function pickupLiftShowSentNotice() {
    var status = document.getElementById("pickup_lift_status");
    if (!status) {
      return;
    }
    if (pickupLiftStatusHideTimer !== null) {
      global.clearTimeout(pickupLiftStatusHideTimer);
      pickupLiftStatusHideTimer = null;
    }
    status.textContent = "ส่งคำขอช่วยยกของแล้ว — รอเสียงแจ้งจากลำโพง";
    status.hidden = false;
    status.classList.add("is-flashing");
    pickupLiftStatusHideTimer = global.setTimeout(function () {
      status.classList.remove("is-flashing");
      status.hidden = true;
      status.textContent = "";
      pickupLiftStatusHideTimer = null;
    }, 5000);
  }

  function pickupLiftBuildMainUrl() {
    var form = document.getElementById("pickup_call_person");
    var input = document.getElementById("pickup_lift_need_no");
    if (!form || !input) {
      return "";
    }
    var action = form.getAttribute("action") || "";
    if (action === "") {
      return "";
    }
    var join = action.indexOf("?") >= 0 ? "&" : "?";
    var url = action + join + "amount=" + encodeURIComponent(input.value);
    var cfg = pickupLiftConfig();
    if (cfg.volume && String(cfg.volume) !== "") {
      url += "&volume=" + encodeURIComponent(String(cfg.volume));
    }
    return url;
  }

  function pickupLiftSubmitMain() {
    var url = pickupLiftBuildMainUrl();
    if (url === "") {
      return;
    }
    var cfg = pickupLiftConfig();
    var delivery = cfg.delivery ? String(cfg.delivery).toLowerCase() : "iframe";
    if (delivery === "same") {
      var form = document.getElementById("pickup_call_person");
      if (!form) {
        return;
      }
      pickupLiftSubmitAllowed = true;
      form.submit();
      return;
    }
    if (delivery === "tab") {
      var tab = global.open(url, "_blank", "noopener,noreferrer");
      if (!tab) {
        pickupLiftBindDialog();
        pickupLiftDialogOpen({
          mode: "alert",
          title: "เปิดหน้าต่างไม่ได้",
          message: "เบราว์เซอร์บล็อกหน้าต่างใหม่ — อนุญาต popup หรือตั้ง delivery เป็น iframe ใน config",
        });
        return;
      }
      pickupLiftShowSentNotice();
      return;
    }
    pickupLiftFireRequest(url);
    pickupLiftShowSentNotice();
  }

  function pickupLiftDialogResolveMedia(amount) {
    var cfg = pickupLiftConfig();
    var maxClip = cfg.walkMediaMaxClip > 0 ? cfg.walkMediaMaxClip : 7;
    var n = parseInt(amount, 10);
    if (isNaN(n) || n < 1) {
      n = 1;
    }
    if (n > maxClip) {
      n = maxClip;
    }
    var byCount = cfg.walkMediaByCount || {};
    var fallback = cfg.walkMediaFallback || {};
    var picked = byCount[n] || byCount[String(n)] || {};
    return {
      count: n,
      mp4: picked.mp4 || fallback.mp4 || "",
      gif: picked.gif || fallback.gif || "",
    };
  }

  function pickupLiftDialogApplyMedia(amount) {
    var media = pickupLiftDialogResolveMedia(amount);
    var video = document.getElementById("pickup_lift_dialog_video");
    var img = document.getElementById("pickup_lift_dialog_img");
    var source = document.getElementById("pickup_lift_dialog_video_src");
    if (video) {
      video.hidden = true;
      video.pause();
    }
    if (img) {
      img.hidden = true;
    }
    if (media.mp4 !== "" && video && source) {
      if (source.getAttribute("src") !== media.mp4) {
        source.setAttribute("src", media.mp4);
        video.load();
      }
      if (media.gif !== "") {
        video.setAttribute("poster", media.gif);
      } else {
        video.removeAttribute("poster");
      }
      video.hidden = false;
      return;
    }
    if (media.gif !== "" && img) {
      if (img.getAttribute("src") !== media.gif) {
        img.setAttribute("src", media.gif);
      }
      img.hidden = false;
    }
  }

  function pickupLiftDialogSyncVideo(play) {
    var video = document.getElementById("pickup_lift_dialog_video");
    if (!video || video.hidden) {
      return;
    }
    if (!play) {
      video.pause();
      return;
    }
    if (global.matchMedia && global.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      video.pause();
      return;
    }
    try {
      video.currentTime = 0;
    } catch (ignore) {
      // intentional
    }
    var started = video.play();
    if (started && typeof started.catch === "function") {
      started.catch(function () {
        // autoplay อาจถูกบล็อกบนบางเบราว์เซอร์ — ยังเห็น poster
      });
    }
  }

  function pickupLiftDialogClose() {
    pickupLiftDialogSyncVideo(false);
    var root = document.getElementById("pickup_lift_dialog");
    if (!root) {
      return;
    }
    root.hidden = true;
    root.classList.remove("is-open");
    root.setAttribute("aria-hidden", "true");
    global.document.body.classList.remove("pickup-lift-dialog-open");
    pickupLiftDialogOnOk = null;
  }

  function pickupLiftDialogOpen(options) {
    var root = document.getElementById("pickup_lift_dialog");
    if (!root) {
      return false;
    }
    var opts = options || {};
    var titleEl = document.getElementById("pickup_lift_dialog_title");
    var msgEl = document.getElementById("pickup_lift_dialog_msg");
    var cancelBtn = document.getElementById("pickup_lift_dialog_cancel");
    var okBtn = document.getElementById("pickup_lift_dialog_ok");
    if (!titleEl || !msgEl || !cancelBtn || !okBtn) {
      return false;
    }
    var isAlert = opts.mode === "alert";
    titleEl.textContent = opts.title || (isAlert ? "แจ้งเตือน" : "เรียกความช่วยเหลือยกของ");
    msgEl.textContent = opts.message || "";
    cancelBtn.hidden = isAlert;
    okBtn.textContent = isAlert ? "ตกลง" : "เรียกเลย";
    pickupLiftDialogOnOk = typeof opts.onOk === "function" ? opts.onOk : null;
    var visual = document.getElementById("pickup_lift_dialog_visual");
    if (visual) {
      visual.hidden = !!isAlert;
    }
    if (!isAlert) {
      pickupLiftDialogApplyMedia(opts.amount != null ? opts.amount : 1);
    }
    root.hidden = false;
    root.classList.add("is-open");
    root.setAttribute("aria-hidden", "false");
    global.document.body.classList.add("pickup-lift-dialog-open");
    pickupLiftDialogSyncVideo(true);
    global.setTimeout(function () {
      okBtn.focus();
    }, 0);
    return true;
  }

  function pickupLiftBindDialog() {
    if (pickupLiftDialogBound) {
      return;
    }
    var root = document.getElementById("pickup_lift_dialog");
    if (!root) {
      return;
    }
    var backdrop = root.querySelector(".pickup-lift-dialog__backdrop");
    var cancelBtn = document.getElementById("pickup_lift_dialog_cancel");
    var okBtn = document.getElementById("pickup_lift_dialog_ok");
    if (backdrop) {
      backdrop.addEventListener("click", pickupLiftDialogClose);
    }
    if (cancelBtn) {
      cancelBtn.addEventListener("click", pickupLiftDialogClose);
    }
    if (okBtn) {
      okBtn.addEventListener("click", function () {
        var fn = pickupLiftDialogOnOk;
        pickupLiftDialogClose();
        if (typeof fn === "function") {
          fn();
        }
      });
    }
    global.document.addEventListener("keydown", function (event) {
      if (!root || root.hidden) {
        return;
      }
      if (event.key === "Escape") {
        pickupLiftDialogClose();
      }
    });
    pickupLiftDialogBound = true;
  }

  function pickupLiftHelp() {
    pickupLiftBindDialog();
    var input = document.getElementById("pickup_lift_need_no");
    if (!input) {
      return false;
    }
    var checkPer = input.value;
    if (checkPer === "" || checkPer <= 0) {
      pickupLiftDialogOpen({
        mode: "alert",
        title: "ระบุจำนวนคน",
        message: "โปรดเลือกจำนวนคนที่ต้องการเรียกให้มาช่วยยกของ",
      });
      return false;
    }
    var amount = parseInt(checkPer, 10);
    if (isNaN(amount) || amount < 1) {
      pickupLiftDialogOpen({
        mode: "alert",
        title: "ระบุจำนวนคน",
        message: "โปรดเลือกจำนวนคนที่ต้องการเรียกให้มาช่วยยกของ",
      });
      return false;
    }
    pickupLiftDialogOpen({
      title: "ยืนยันการเรียก",
      amount: amount,
      message: "แจ้งขอความช่วยเหลือยกของ " + String(amount) + " คน\nผ่านลำโพงในออฟฟิศหรือไม่",
      onOk: function () {
        pickupLiftPingPrelude(pickupLiftSubmitMain);
      },
    });
    return false;
  }

  function pickupLiftOnFormSubmit(event) {
    if (pickupLiftSubmitAllowed) {
      pickupLiftSubmitAllowed = false;
      return true;
    }
    if (event && typeof event.preventDefault === "function") {
      event.preventDefault();
    }
    pickupLiftHelp();
    return false;
  }

  /**
   * ล็อกช่องจำนวน — ปรับได้เฉพาะปุ่ม + / −
   */
  function pickupLiftInitQtyInput() {
    var input = document.getElementById("pickup_lift_need_no");
    if (!input) {
      return;
    }
    input.readOnly = true;
    input.addEventListener("keydown", function (event) {
      event.preventDefault();
    });
    input.addEventListener("beforeinput", function (event) {
      event.preventDefault();
    });
    input.addEventListener("paste", function (event) {
      event.preventDefault();
    });
    input.addEventListener("wheel", function (event) {
      if (global.document.activeElement === input) {
        event.preventDefault();
      }
    }, { passive: false });
    input.addEventListener("mousedown", function (event) {
      event.preventDefault();
    });
    input.addEventListener("focus", function () {
      input.blur();
    });
  }

  function pickupLiftQtyStep(delta) {
    var input = document.getElementById("pickup_lift_need_no");
    if (!input) {
      return;
    }
    var minVal = parseInt(input.getAttribute("min"), 10);
    var maxVal = parseInt(input.getAttribute("max"), 10);
    if (isNaN(minVal)) {
      minVal = 1;
    }
    if (isNaN(maxVal)) {
      maxVal = 99;
    }
    var current = parseInt(input.value, 10);
    if (isNaN(current)) {
      current = minVal;
    }
    current = current + delta;
    if (current < minVal) {
      current = minVal;
    }
    if (current > maxVal) {
      current = maxVal;
    }
    input.value = String(current);
  }

  /**
   * หมุนมุม conic border ช้าๆ ตาม CSS — เมื่อ hover หยุดแอนิเมชันแล้วไล่มุมตามเมาส์
   */
  function pickupLiftInitCallButtonGlow() {
    if (!document.body.classList.contains("pickup-overview")) {
      return;
    }
    if (global.matchMedia && global.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      return;
    }
    var btn = document.querySelector(".pickup-overview__lift-btn");
    if (!btn) {
      return;
    }
    var shell = btn.closest(".pickup-overview__lift-btn-shell");
    if (!shell) {
      return;
    }

    var currentAngle = 0;
    var targetAngle = 0;
    var glowRaf = null;
    var glowHovering = false;

    function pickupLiftGlowApplyAngle(deg) {
      shell.style.setProperty("--pickup-lift-glow-angle", String(deg) + "deg");
    }

    function pickupLiftGlowReadAngle() {
      var raw = global.getComputedStyle(shell).getPropertyValue("--pickup-lift-glow-angle").trim();
      var num = parseFloat(raw);
      return isNaN(num) ? 0 : num;
    }

    function pickupLiftGlowShortestDelta(fromDeg, toDeg) {
      var delta = (toDeg - fromDeg) % 360;
      if (delta > 180) {
        delta -= 360;
      }
      if (delta < -180) {
        delta += 360;
      }
      return delta;
    }

    function pickupLiftGlowFrame() {
      if (!glowHovering) {
        glowRaf = null;
        return;
      }
      var delta = pickupLiftGlowShortestDelta(currentAngle, targetAngle);
      if (Math.abs(delta) > 0.15) {
        currentAngle += delta * 0.16;
      } else {
        currentAngle = targetAngle;
      }
      pickupLiftGlowApplyAngle(currentAngle);
      glowRaf = global.requestAnimationFrame(pickupLiftGlowFrame);
    }

    btn.addEventListener("mouseenter", function () {
      glowHovering = true;
      shell.classList.add("pickup-overview__lift-btn-shell--glow-active");
      currentAngle = pickupLiftGlowReadAngle();
      targetAngle = currentAngle;
      if (glowRaf === null) {
        glowRaf = global.requestAnimationFrame(pickupLiftGlowFrame);
      }
    });

    btn.addEventListener("mousemove", function (event) {
      var rect = btn.getBoundingClientRect();
      var cx = rect.left + rect.width * 0.5;
      var cy = rect.top + rect.height * 0.5;
      targetAngle =
        Math.atan2(event.clientY - cy, event.clientX - cx) * (180 / Math.PI) + 90;
    });

    btn.addEventListener("mouseleave", function () {
      glowHovering = false;
      shell.classList.remove("pickup-overview__lift-btn-shell--glow-active");
      shell.style.removeProperty("--pickup-lift-glow-angle");
      if (glowRaf !== null) {
        global.cancelAnimationFrame(glowRaf);
        glowRaf = null;
      }
    });
  }

  function pickupLiftInitDom() {
    pickupLiftInitQtyInput();
    pickupLiftInitCallButtonGlow();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", pickupLiftInitDom);
  } else {
    pickupLiftInitDom();
  }

  global.pickupLiftInitOfficeVisibility = pickupLiftInitOfficeVisibility;
  global.pickupLiftHelp = pickupLiftHelp;
  global.pickupLiftOnFormSubmit = pickupLiftOnFormSubmit;
  global.pickupLiftQtyStep = pickupLiftQtyStep;
})(window);
