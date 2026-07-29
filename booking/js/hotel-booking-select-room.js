(function () {
  var cfg = window.HB_SELECT_ROOM || {};
  var cards = document.querySelectorAll('.hb-room-card');
  if (!cards.length && !document.getElementById('hb-occupancy-modal')) {
    return;
  }

  function parseIntSafe(v, fallback) {
    var n = parseInt(v, 10);
    return Number.isFinite(n) ? n : fallback;
  }

  function currentOccupancy() {
    var o = cfg.occupancy || {};
    return {
      rooms: parseIntSafe(o.rooms, 1),
      adults: parseIntSafe(o.adults, 1),
      children: parseIntSafe(o.children, 0),
      babies: parseIntSafe(o.babies, 0),
      rate: String(o.rate || '')
    };
  }

  function discountPercent() {
    return parseFloat(cfg.discountPercent || 0) || 0;
  }

  function quoteNightly(base) {
    var occ = currentOccupancy();
    var rooms = Math.max(1, Math.min(4, occ.rooms));
    var adults = Math.max(1, Math.min(12, occ.adults));
    var children = Math.max(0, Math.min(6, occ.children));
    var baseF = parseFloat(base) || 0;
    var included = 2 * rooms;
    var extraAdults = Math.max(0, adults - included);
    var nightly = baseF * rooms + extraAdults * (baseF * 0.35) + children * 22;
    var disc = discountPercent();
    if (disc > 0) {
      nightly *= (1 - disc / 100);
    }
    return Math.round(nightly);
  }

  function formatMoney(amount) {
    var sym = cfg.currencySymbol || '€';
    return sym + amount;
  }

  function buildQuery(overrides) {
    var occ = Object.assign({}, currentOccupancy(), overrides || {});
    var params = new URLSearchParams(window.location.search);
    params.set('rooms', String(occ.rooms));
    params.set('adults', String(occ.adults));
    params.set('children', String(occ.children));
    params.set('babies', String(occ.babies));
    if (occ.rate) {
      params.set('rate', occ.rate);
    } else {
      params.delete('rate');
    }
    return params.toString();
  }

  function reloadWith(overrides) {
    window.location.search = buildQuery(overrides);
  }

  function updatePrices() {
    cards.forEach(function (card) {
      var base = card.getAttribute('data-base-price');
      var priceEl = card.querySelector('.hb-room-price-value');
      if (priceEl && base) {
        priceEl.textContent = formatMoney(quoteNightly(base));
      }
      var select = card.querySelector('.hb-room-select');
      if (select && select.href) {
        try {
          var u = new URL(select.href, window.location.origin);
          var q = buildQuery({});
          q.split('&').forEach(function (pair) {
            var p = pair.split('=');
            if (p[0]) {
              u.searchParams.set(decodeURIComponent(p[0]), decodeURIComponent(p[1] || ''));
            }
          });
          select.href = u.pathname + u.search;
        } catch (e) { /* ignore */ }
      }
    });
    var occBtn = document.getElementById('hb-stay-occupancy-trigger');
    if (occBtn && cfg.occupancyLabel) {
      occBtn.textContent = '👤 ' + cfg.occupancyLabel;
    }
  }

  function openModal(id) {
    var el = document.getElementById(id);
    if (el) {
      el.hidden = false;
    }
  }

  function closeModal(id) {
    var el = document.getElementById(id);
    if (el) {
      el.hidden = true;
    }
  }

  document.querySelectorAll('[data-hb-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = btn.getAttribute('data-hb-modal-close');
      if (target) {
        closeModal(target);
      }
    });
  });

  var occTrigger = document.getElementById('hb-stay-occupancy-trigger');
  if (occTrigger) {
    occTrigger.addEventListener('click', function () {
      openModal('hb-occupancy-modal');
    });
  }

  var filterBtn = document.getElementById('hb-room-filters-btn');
  if (filterBtn) {
    filterBtn.addEventListener('click', function () {
      openModal('hb-filters-modal');
    });
  }

  var ratesBtn = document.getElementById('hb-special-rates-btn');
  if (ratesBtn) {
    ratesBtn.addEventListener('click', function () {
      openModal('hb-rates-modal');
    });
  }

  function bindStepper(minusId, plusId, inputId, min, max) {
    var input = document.getElementById(inputId);
    var minus = document.getElementById(minusId);
    var plus = document.getElementById(plusId);
    if (!input) {
      return;
    }
    function setVal(v) {
      v = Math.max(min, Math.min(max, v));
      input.value = String(v);
    }
    if (minus) {
      minus.addEventListener('click', function () {
        setVal(parseIntSafe(input.value, min) - 1);
      });
    }
    if (plus) {
      plus.addEventListener('click', function () {
        setVal(parseIntSafe(input.value, min) + 1);
      });
    }
  }

  bindStepper('hb-occ-rooms-minus', 'hb-occ-rooms-plus', 'hb-occ-rooms', 1, 4);
  bindStepper('hb-occ-adults-minus', 'hb-occ-adults-plus', 'hb-occ-adults', 1, 12);
  bindStepper('hb-occ-children-minus', 'hb-occ-children-plus', 'hb-occ-children', 0, 6);
  bindStepper('hb-occ-babies-minus', 'hb-occ-babies-plus', 'hb-occ-babies', 0, 3);

  var occApply = document.getElementById('hb-occupancy-apply');
  if (occApply) {
    occApply.addEventListener('click', function () {
      reloadWith({
        rooms: parseIntSafe(document.getElementById('hb-occ-rooms').value, 1),
        adults: parseIntSafe(document.getElementById('hb-occ-adults').value, 1),
        children: parseIntSafe(document.getElementById('hb-occ-children').value, 0),
        babies: parseIntSafe(document.getElementById('hb-occ-babies').value, 0)
      });
    });
  }

  var filterApply = document.getElementById('hb-filters-apply');
  if (filterApply) {
    filterApply.addEventListener('click', function () {
      var selected = [];
      document.querySelectorAll('#hb-filters-modal input[type="checkbox"][data-filter-tag]:checked').forEach(function (cb) {
        selected.push(cb.getAttribute('data-filter-tag'));
      });
      cards.forEach(function (card) {
        var tags = (card.getAttribute('data-filter-tags') || '').split(',').map(function (t) {
          return t.trim();
        }).filter(Boolean);
        var show = selected.length === 0 || selected.every(function (tag) {
          return tags.indexOf(tag) !== -1;
        });
        card.style.display = show ? '' : 'none';
      });
      closeModal('hb-filters-modal');
      var countEl = document.getElementById('hb-room-count-visible');
      if (countEl) {
        var visible = 0;
        cards.forEach(function (c) {
          if (c.style.display !== 'none') {
            visible++;
          }
        });
        countEl.textContent = visible + ' room types shown.';
      }
    });
  }

  var filterClear = document.getElementById('hb-filters-clear');
  if (filterClear) {
    filterClear.addEventListener('click', function () {
      document.querySelectorAll('#hb-filters-modal input[type="checkbox"]').forEach(function (cb) {
        cb.checked = false;
      });
    });
  }

  document.querySelectorAll('.hb-rate-pick').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var slug = btn.getAttribute('data-rate-slug') || '';
      reloadWith({ rate: slug });
    });
  });

  var rateClear = document.getElementById('hb-rates-clear');
  if (rateClear) {
    rateClear.addEventListener('click', function () {
      reloadWith({ rate: '' });
    });
  }

  document.querySelectorAll('.hb-room-details-open').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var typeId = link.getAttribute('data-type-id');
      var detail = document.getElementById('hb-room-detail-body');
      var map = cfg.typeDetails || {};
      if (detail && typeId && map[typeId]) {
        detail.innerHTML = map[typeId];
        var card = document.querySelector('.hb-room-card[data-type-id="' + typeId + '"]');
        if (card) {
          var priceEl = card.querySelector('.hb-room-price-value');
          var bookBtn = detail.querySelector('.hb-room-detail-book');
          var selectLink = card.querySelector('.hb-room-select');
          if (bookBtn && priceEl && bookBtn.tagName === 'A') {
            bookBtn.textContent = 'Book From ' + priceEl.textContent.trim();
          }
          if (bookBtn && selectLink && bookBtn.tagName === 'A') {
            bookBtn.href = selectLink.href;
          }
        }
        openModal('hb-room-detail-modal');
      }
    });
  });

  document.getElementById('hb-room-detail-body') && document.getElementById('hb-room-detail-body').addEventListener('click', function (e) {
    var readMore = e.target.closest('[data-hb-read-more]');
    if (!readMore) {
      return;
    }
    var desc = readMore.parentElement.querySelector('.hb-rd-desc');
    if (desc) {
      desc.classList.remove('hb-rd-desc-short');
      readMore.style.display = 'none';
    }
  });

  updatePrices();
})();
