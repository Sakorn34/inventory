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

  function pickupLiftPingPrelude(done) {
    var cfg = pickupLiftConfig();
    if (!cfg.preludeUrl) {
      if (typeof done === "function") {
        done();
      }
      return;
    }
    var pingFrame = document.getElementById("pickup_lift_ping");
    if (pingFrame) {
      pingFrame.src = cfg.preludeUrl;
    }
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
    var pingFrame = document.getElementById("pickup_lift_ping");
    if (!pingFrame) {
      return;
    }
    pingFrame.src = url;
    pickupLiftShowSentNotice();
  }

  function pickupLiftDialogClose() {
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
    root.hidden = false;
    root.classList.add("is-open");
    root.setAttribute("aria-hidden", "false");
    global.document.body.classList.add("pickup-lift-dialog-open");
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
      message: "แจ้งขอความช่วยเหลือยกของ " + String(amount) + " คน ผ่านลำโพงในออฟฟิศหรือไม่",
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

  global.pickupLiftInitOfficeVisibility = pickupLiftInitOfficeVisibility;
  global.pickupLiftHelp = pickupLiftHelp;
  global.pickupLiftOnFormSubmit = pickupLiftOnFormSubmit;
  global.pickupLiftQtyStep = pickupLiftQtyStep;
})(window);
