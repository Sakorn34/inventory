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

  /** การแสดงการ์ดตัดสินที่ server แล้ว (IP ออฟฟิศ) — คงไว้ให้หน้าที่เรียกอยู่ */
  function pickupLiftInitOfficeVisibility() {
    var section = document.getElementById("pickup_lift_section");
    if (section) {
      section.hidden = pickupLiftConfig().visibility === "never";
    }
  }

  function pickupLiftDelivery() {
    var cfg = pickupLiftConfig();
    return cfg.delivery ? String(cfg.delivery).toLowerCase() : "iframe";
  }

  function pickupLiftPreludeDelayMs() {
    var cfg = pickupLiftConfig();
    return cfg.preludeDelayMs > 0 ? cfg.preludeDelayMs : 1200;
  }

  /**
   * หน้า https เรียก http://192.168.x (Node-RED) แบบพื้นหลังไม่ได้ — โดนบล็อก mixed content
   * ทางเดียวที่ผ่านคือเปิดเป็นแท็บใหม่ (top-level navigation)
   */
  function pickupLiftNeedsTab(url) {
    if (pickupLiftDelivery() === "tab") {
      return true;
    }
    return global.location.protocol === "https:" && /^http:/i.test(url);
  }

  function pickupLiftFireBackground(url) {
    var pingFrame = document.getElementById("pickup_lift_ping");
    if (pingFrame) {
      pingFrame.src = url;
    }
  }

  function pickupLiftPingPrelude(done) {
    var cfg = pickupLiftConfig();
    if (!cfg.preludeUrl) {
      done();
      return;
    }
    pickupLiftFireBackground(cfg.preludeUrl);
    global.setTimeout(done, pickupLiftPreludeDelayMs());
  }

  /**
   * ต้องเรียกตรงจาก handler ของการแตะ — Safari iOS อนุญาต popup เฉพาะช่วงนั้น
   * (ห้ามใส่ "noopener" ใน window.open เพราะจะคืน null เสมอแม้แท็บเปิดได้)
   */
  function pickupLiftSubmitViaTab(url) {
    var tab = global.open("", "_blank");
    if (!tab) {
      // popup ถูกบล็อก — ไปที่ Node-RED ในแท็บนี้แทน กด back กลับมาได้
      global.location.href = url;
      return;
    }
    try {
      tab.opener = null;
    } catch (ignore) {
      // intentional
    }
    var cfg = pickupLiftConfig();
    if (cfg.preludeUrl) {
      tab.location.href = cfg.preludeUrl;
      global.setTimeout(function () {
        tab.location.href = url;
      }, pickupLiftPreludeDelayMs());
    } else {
      tab.location.href = url;
    }
    pickupLiftShowSentNotice();
  }

  function pickupLiftStartCall() {
    var url = pickupLiftBuildMainUrl();
    if (url === "") {
      return;
    }
    if (pickupLiftDelivery() !== "same" && pickupLiftNeedsTab(url)) {
      pickupLiftSubmitViaTab(url);
      return;
    }
    pickupLiftPingPrelude(pickupLiftSubmitMain);
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
    if (pickupLiftDelivery() === "same") {
      var form = document.getElementById("pickup_call_person");
      if (!form) {
        return;
      }
      pickupLiftSubmitAllowed = true;
      form.submit();
      return;
    }
    pickupLiftFireBackground(url);
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
      onOk: pickupLiftStartCall,
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
    pickupLiftInitOfficeVisibility();
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
