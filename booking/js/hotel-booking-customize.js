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

(function () {
  var detailCfg = window.HB_CUSTOMIZE_ROOM_DETAIL;
  if (!detailCfg || !detailCfg.html) {
    return;
  }

  var openBtn = document.getElementById('hb-customize-room-details');
  var modal = document.getElementById('hb-room-detail-modal');
  var body = document.getElementById('hb-room-detail-body');
  if (!openBtn || !modal || !body) {
    return;
  }

  function openModal() {
    modal.hidden = false;
  }

  function closeModal() {
    modal.hidden = true;
  }

  openBtn.addEventListener('click', function (e) {
    e.preventDefault();
    body.innerHTML = detailCfg.html;
    openModal();
  });

  document.querySelectorAll('[data-hb-modal-close="hb-room-detail-modal"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeModal();
    });
  });

  body.addEventListener('click', function (e) {
    var readMore = e.target.closest('[data-hb-read-more]');
    if (!readMore) {
      return;
    }
    e.preventDefault();
    var wrap = readMore.closest('.hb-rd-desc-wrap');
    if (!wrap) {
      return;
    }
    var more = wrap.querySelector('.hb-rd-desc-more');
    var expanded = wrap.classList.toggle('is-expanded');
    if (more) {
      more.hidden = !expanded;
    }
    readMore.textContent = expanded ? 'Read less' : 'Read more';
    readMore.setAttribute('title', expanded ? 'Read less' : 'Read more');
  });
})();
