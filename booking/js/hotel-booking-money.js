(function (w) {
  function moneyOptions(cfg) {
    cfg = cfg || w.HB_SETTINGS || w.HB_SELECT_ROOM || w.HB_CUSTOMIZE_UPGRADE || {};
    var suffix = cfg.money_suffix !== 0 && cfg.money_suffix !== false;
    if (cfg.money_prefix === 1 || cfg.money_prefix === true) {
      suffix = false;
    }
    return {
      symbol: cfg.money_symbol || cfg.currencySymbol || '€',
      suffix: suffix
    };
  }

  function formatAmount(amount, style, cfg) {
    var opts = moneyOptions(cfg);
    var n = Math.round((parseFloat(amount) || 0) * 100) / 100;
    var formatted = n.toFixed(2);
    if (style === 'short') {
      formatted = formatted.replace(/\.00$/, '');
    }
    if (opts.suffix) {
      return formatted + opts.symbol;
    }
    return opts.symbol + formatted;
  }

  w.hbPortalFormatMoney = function (amount, cfg, style) {
    return formatAmount(amount, style || 'short', cfg);
  };

  w.hbPortalFormatMoneyDecimal = function (amount, cfg) {
    return formatAmount(amount, 'decimal', cfg);
  };
})(window);
