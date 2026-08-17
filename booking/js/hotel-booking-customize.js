(function () {
  var cfg = window.HB_CUSTOMIZE_UPGRADE;
  if (!cfg) {
    return;
  }
  var upgradeCheckbox = document.getElementById('hb-accept-room-upgrade');
  var petCheckbox = document.getElementById('hb-traveling-with-pet');
  var roomChargesEl = document.getElementById('hb-reservation-room-charges');
  var stayTotalEl = document.getElementById('hb-reservation-stay-total');
  var petRowEl = document.getElementById('hb-reservation-pet-row');
  var petFeeEl = document.getElementById('hb-reservation-pet-fee');
  var roomPriceEl = document.querySelector('.hb-reservation-summary-room .hb-reservation-room-price');
  var roomNameEl = document.querySelector('.hb-reservation-summary-room .hb-reservation-room-name');

  if (!roomChargesEl || !stayTotalEl) {
    return;
  }

  function refreshRoomTitle() {
    if (!roomNameEl || !cfg.baseRoomTitle || !cfg.upgradeRoomTitle) {
      return;
    }
    var upgradeSelected = upgradeCheckbox && upgradeCheckbox.checked;
    roomNameEl.textContent = upgradeSelected ? cfg.upgradeRoomTitle : cfg.baseRoomTitle;
  }

  function formatDecimal(amount) {
    if (typeof window.hbPortalFormatMoneyDecimal === 'function') {
      return window.hbPortalFormatMoneyDecimal(amount, cfg);
    }
    var n = Math.round(amount * 100) / 100;
    var parts = n.toFixed(2).split('.');
    var sym = cfg.money_symbol || '€';
    var suffix = cfg.money_suffix !== 0 && cfg.money_suffix !== false;
    if (cfg.money_prefix === 1 || cfg.money_prefix === true) {
      suffix = false;
    }
    var body = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + parts[1];
    if (suffix) {
      return body + sym;
    }
    return sym + body;
  }

  function refreshTotals() {
    var roomCharges = cfg.roomChargesBase || 0;

    // Normalizing roomCharges to never include pet fee
    var baseWithoutPet = roomCharges;
    if (cfg.initialTravelingWithPet) {
      baseWithoutPet -= (cfg.petDailyFee || 0) * (cfg.nights || 1);
    }

    var currentRoomCharges = baseWithoutPet;
    if (upgradeCheckbox && upgradeCheckbox.checked) {
      currentRoomCharges += (cfg.upgradePerNight || 0) * (cfg.nights || 1);
    }

    // Display total room charges (excluding pet fee)
    roomChargesEl.textContent = formatDecimal(currentRoomCharges);
    if (roomPriceEl) {
      roomPriceEl.textContent = formatDecimal(currentRoomCharges);
    }

    // Calculate pet fee if checked
    var petFeeTotal = 0;
    if (petCheckbox && petCheckbox.checked) {
      petFeeTotal = (cfg.petDailyFee || 0) * (cfg.nights || 1);
      if (petRowEl) {
        petRowEl.style.display = '';
      }
      if (petFeeEl) {
        petFeeEl.textContent = formatDecimal(petFeeTotal);
      }
    } else {
      if (petRowEl) {
        petRowEl.style.display = 'none';
      }
    }

    var total = currentRoomCharges + petFeeTotal + (cfg.touristTax || 0);
    stayTotalEl.textContent = formatDecimal(total);
    refreshRoomTitle();
  }

  if (upgradeCheckbox) {
    upgradeCheckbox.addEventListener('change', refreshTotals);
  }
  if (petCheckbox) {
    petCheckbox.addEventListener('change', refreshTotals);
  }
  refreshTotals();
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
    if (typeof window.HB_initGalleries === 'function') {
      window.HB_initGalleries(body);
    }
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

(function () {
  var cfg = window.HB_CUSTOMIZE_ACCESSIBILITY;
  if (!cfg || !cfg.enabled) {
    return;
  }
  var needSelect = document.getElementById('hb-accessibility-need');
  var pepWrap = document.getElementById('hb-accessibility-pep-wrap');
  var pepCheckbox = document.getElementById('hb-accessibility-pep-ack');
  var continueBtn = document.getElementById('hb-customize-continue');
  var form = document.getElementById('hb-customize-form');
  if (!needSelect || !pepWrap || !pepCheckbox || !continueBtn || !form) {
    return;
  }

  function pepRequired() {
    return (needSelect.value || 'none') !== 'none';
  }

  function refreshAccessibilityGate() {
    var required = pepRequired();
    pepWrap.hidden = !required;
    if (!required) {
      pepCheckbox.checked = false;
    }
    var blocked = required && !pepCheckbox.checked;
    continueBtn.disabled = blocked;
    continueBtn.setAttribute('aria-disabled', blocked ? 'true' : 'false');
  }

  needSelect.addEventListener('change', refreshAccessibilityGate);
  pepCheckbox.addEventListener('change', refreshAccessibilityGate);
  form.addEventListener('submit', function (e) {
    if (pepRequired() && !pepCheckbox.checked) {
      e.preventDefault();
      refreshAccessibilityGate();
    }
  });
  refreshAccessibilityGate();
})();
