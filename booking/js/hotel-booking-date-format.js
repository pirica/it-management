/**
 * Portal date/time display helpers driven by HB_SETTINGS / HB_SELECT_ROOM keys.
 */
(function (w) {
  'use strict';

  var MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var MONTHS_UPPER = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

  function portalCfg() {
    return w.HB_SETTINGS || w.HB_PORTAL_SETTINGS || w.HB_SELECT_ROOM || w.HB_CUSTOMIZE_UPGRADE || {};
  }

  function parseYmdParts(ymd) {
    var parts = String(ymd || '').split('-');
    if (parts.length !== 3) {
      return null;
    }
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var d = parseInt(parts[2], 10);
    if (!y || m < 1 || m > 12 || d < 1) {
      return null;
    }
    return { y: y, m: m, d: d };
  }

  function pad2(n) {
    return String(n).padStart(2, '0');
  }

  function parseDateText(raw, cfg) {
    cfg = cfg || portalCfg();
    var text = String(raw || '').trim();
    if (text === '') {
      return '';
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
      return text;
    }
    var slashMon = /^(\d{1,2})\/([A-Za-z]{3})\/(\d{4})$/.exec(text);
    if (slashMon) {
      var monthIdx = MONTHS_SHORT.findIndex(function (m) {
        return m.toLowerCase() === slashMon[2].toLowerCase();
      });
      if (monthIdx >= 0) {
        return slashMon[3] + '-' + pad2(monthIdx + 1) + '-' + pad2(parseInt(slashMon[1], 10));
      }
    }
    var fmt = String(cfg.date_format || 'european_ddmmyyyy');
    var slashNum = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(text);
    if (slashNum) {
      var a = parseInt(slashNum[1], 10);
      var b = parseInt(slashNum[2], 10);
      var y = parseInt(slashNum[3], 10);
      if (fmt === 'us_mmddyyyy') {
        return y + '-' + pad2(a) + '-' + pad2(b);
      }
      return y + '-' + pad2(b) + '-' + pad2(a);
    }
    return '';
  }

  function formatDateYmd(ymd, cfg) {
    cfg = cfg || portalCfg();
    var p = parseYmdParts(ymd);
    if (!p) {
      return '';
    }
    var fmt = String(cfg.date_format || 'european_ddmmyyyy');
    if (fmt === 'us_mmddyyyy') {
      return pad2(p.m) + '/' + pad2(p.d) + '/' + p.y;
    }
    if (fmt === 'iso_yyyymmdd') {
      return p.y + '-' + pad2(p.m) + '-' + pad2(p.d);
    }
    if (fmt === 'european_ddmmmyyyy') {
      return pad2(p.d) + '/' + MONTHS_SHORT[p.m - 1] + '/' + p.y;
    }
    return pad2(p.d) + '/' + pad2(p.m) + '/' + p.y;
  }

  function formatTimeHm(h, m, cfg) {
    cfg = cfg || portalCfg();
    h = parseInt(h, 10) || 0;
    m = parseInt(m, 10) || 0;
    if (String(cfg.time_format || 'h24') === 'h12') {
      var suffix = h >= 12 ? ' PM' : ' AM';
      var h12 = h % 12;
      if (h12 === 0) {
        h12 = 12;
      }
      return h12 + ':' + pad2(m) + suffix;
    }
    return pad2(h) + ':' + pad2(m);
  }

  function resolveDatetimeDefault(cfg) {
    cfg = cfg || portalCfg();
    var enabled = {
      european1: !!cfg.datetime_european1_enabled,
      european2: cfg.datetime_european2_enabled === undefined ? true : !!cfg.datetime_european2_enabled,
      iso: !!cfg.datetime_iso_enabled,
      readable: !!cfg.datetime_readable_enabled
    };
    var def = String(cfg.datetime_format_default || 'european2');
    if (enabled[def]) {
      return def;
    }
    var order = ['european2', 'european1', 'readable', 'iso'];
    for (var i = 0; i < order.length; i++) {
      if (enabled[order[i]]) {
        return order[i];
      }
    }
    return 'european2';
  }

  function formatDatetimeIso(isoValue, cfg) {
    cfg = cfg || portalCfg();
    var raw = String(isoValue || '').trim();
    if (!raw) {
      return '';
    }
    var normalized = raw.replace(' ', 'T');
    var dt = new Date(normalized);
    if (isNaN(dt.getTime())) {
      return raw;
    }
    var y = dt.getFullYear();
    var mo = dt.getMonth();
    var d = dt.getDate();
    var h = dt.getHours();
    var mi = dt.getMinutes();
    var timePart = formatTimeHm(h, mi, cfg);
    var kind = resolveDatetimeDefault(cfg);
    if (kind === 'iso') {
      return y + '-' + pad2(mo + 1) + '-' + pad2(d) + 'T' + pad2(h) + ':' + pad2(mi) + ':00Z';
    }
    if (kind === 'readable') {
      return d + ' ' + MONTHS_SHORT[mo] + ' ' + y + ', ' + timePart;
    }
    if (kind === 'european1') {
      return pad2(d) + '/' + pad2(mo + 1) + '/' + y + ' ' + timePart;
    }
    return pad2(d) + '/' + MONTHS_UPPER[mo] + '/' + y + ' ' + timePart;
  }

  w.hbPortalFormatDateYmd = formatDateYmd;
  w.hbPortalParseDateText = parseDateText;
  w.hbPortalFormatDatetimeIso = formatDatetimeIso;
  w.itmHotelDateFormatYmd = function (ymd) {
    return formatDateYmd(ymd, portalCfg());
  };
  w.itmHotelDateParseText = function (text) {
    return parseDateText(text, portalCfg());
  };
})(window);
