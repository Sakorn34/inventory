/**
 * ปุ่มช่วยยกของ — ส่ง GET ไป Node-RED ในพื้นหลัง (iframe)
 */
(function (global) {
  "use strict";

  var pickupLiftSubmitAllowed = false;
  var pickupLiftStatusHideTimer = null;

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
        global.alert("เบราว์เซอร์บล็อกหน้าต่างใหม่ — อนุญาต popup หรือเปลี่ยน delivery เป็น iframe");
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

  function pickupLiftHelp() {
    var input = document.getElementById("pickup_lift_need_no");
    if (!input) {
      return false;
    }
    var checkPer = input.value;
    if (checkPer === "" || checkPer <= 0) {
      global.alert("โปรดระบุจำนวนคน !");
      return false;
    }
    if (!global.confirm("ต้องการเรียกเลยหรือไม่ ?")) {
      return false;
    }
    pickupLiftPingPrelude(pickupLiftSubmitMain);
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

  global.pickupLiftInitOfficeVisibility = pickupLiftInitOfficeVisibility;
  global.pickupLiftHelp = pickupLiftHelp;
  global.pickupLiftOnFormSubmit = pickupLiftOnFormSubmit;
})(window);
