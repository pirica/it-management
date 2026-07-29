(function () {
  var cfg = window.HB_CUSTOMIZE_UPGRADE;
  if (!cfg) {
    return;
  }
  var checkbox = document.getElementById('hb-accept-room-upgrade');
  var roomChargesEl = document.getElementById('hb-reservation-room-charges');
  var stayTotalEl = document.getElementById('hb-reservation-stay-total');
  if (!roomChargesEl || !stayTotalEl) {
    return;
  }
  if (!cfg.hasUpgradeCheckbox || !checkbox) {
    return;
  }

  function formatDecimal(amount) {
    var n = Math.round(amount * 100) / 100;
    var parts = n.toFixed(2).split('.');
    var code = (cfg.currencyCode || 'EUR').toUpperCase();
    if (code === 'EUR') {
      return parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + parts[1] + '€';
    }
    return code + ' ' + parts[0] + '.' + parts[1];
  }

  function refreshTotals() {
    var roomCharges = cfg.roomChargesBase || 0;
    if (checkbox.checked) {
      roomCharges += (cfg.upgradePerNight || 0) * (cfg.nights || 1);
    }
    var total = roomCharges + (cfg.touristTax || 0);
    roomChargesEl.textContent = formatDecimal(roomCharges);
    stayTotalEl.textContent = formatDecimal(total);
  }

  checkbox.addEventListener('change', refreshTotals);
})();
