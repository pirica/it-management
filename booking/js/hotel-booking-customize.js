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
