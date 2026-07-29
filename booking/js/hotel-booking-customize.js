(function () {
  var cfg = window.HB_CUSTOMIZE_UPGRADE;
  if (!cfg) {
    return;
  }
  var checkbox = document.getElementById('hb-accept-room-upgrade');
  var totalEl = document.getElementById('hb-customize-total');
  var upgradeLine = document.getElementById('hb-customize-upgrade-line');
  if (!checkbox || !totalEl) {
    return;
  }

  function formatMoney(amount) {
    var sym = cfg.currencySymbol || '€';
    var n = Math.round(amount * 100) / 100;
    var parts = n.toFixed(2).split('.');
    return sym + parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + parts[1];
  }

  function refreshTotal() {
    var total = cfg.baseTotal;
    if (checkbox.checked) {
      total += (cfg.upgradePerNight || 0) * (cfg.nights || 1);
    }
    totalEl.textContent = formatMoney(total);
    if (upgradeLine) {
      upgradeLine.hidden = !checkbox.checked;
    }
  }

  checkbox.addEventListener('change', refreshTotal);
})();
