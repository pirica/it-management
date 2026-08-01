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

  function sanitizeRateCode(value) {
    return String(value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 8);
  }

  function specialRateFieldsFromOccupancy(o) {
    o = o || {};
    return {
      use_points: o.use_points ? 1 : 0,
      travel_agents: o.travel_agents ? 1 : 0,
      aaa_rate: o.aaa_rate ? 1 : 0,
      senior_rate: o.senior_rate ? 1 : 0,
      gov_military: o.gov_military ? 1 : 0,
      promo_code: sanitizeRateCode(o.promo_code),
      group_code: sanitizeRateCode(o.group_code),
      corporate_account: sanitizeRateCode(o.corporate_account),
      member_account: sanitizeRateCode(o.member_account)
    };
  }

  function currentOccupancy() {
    var o = cfg.occupancy || {};
    var special = specialRateFieldsFromOccupancy(o);
    return Object.assign({
      rooms: parseIntSafe(o.rooms, 1),
      adults: parseIntSafe(o.adults, 1),
      children: parseIntSafe(o.children, 0),
      babies: parseIntSafe(o.babies, 0),
      rate: String(o.rate || '')
    }, special);
  }

  var SPECIAL_RATE_BOOL_KEYS = ['use_points', 'travel_agents', 'aaa_rate', 'senior_rate', 'gov_military'];
  var SPECIAL_RATE_CODE_KEYS = ['promo_code', 'group_code', 'corporate_account', 'member_account'];

  function applySpecialRateParams(params, occ) {
    SPECIAL_RATE_BOOL_KEYS.forEach(function (key) {
      if (occ[key]) {
        params.set(key, '1');
      } else {
        params.delete(key);
      }
    });
    SPECIAL_RATE_CODE_KEYS.forEach(function (key) {
      var code = sanitizeRateCode(occ[key]);
      if (code) {
        params.set(key, code);
      } else {
        params.delete(key);
      }
    });
  }

  function resolvedRateSlugFromOcc(occ) {
    occ = occ || {};
    var rate = String(occ.rate || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
    if (rate) {
      return rate;
    }
    if (sanitizeRateCode(occ.promo_code)) {
      return 'promo';
    }
    if (sanitizeRateCode(occ.group_code)) {
      return 'group';
    }
    if (sanitizeRateCode(occ.corporate_account)) {
      return 'corporate';
    }
    if (sanitizeRateCode(occ.member_account)) {
      return 'member';
    }
    if (occ.use_points) {
      return 'points';
    }
    if (occ.travel_agents) {
      return 'travel_agent';
    }
    if (occ.aaa_rate) {
      return 'aaa';
    }
    if (occ.senior_rate) {
      return 'senior';
    }
    if (occ.gov_military) {
      return 'government';
    }
    return '';
  }

  function discountPercentForOcc(occ) {
    var map = cfg.rateDiscountPercents || {};
    var slug = resolvedRateSlugFromOcc(occ);
    if (!slug || !Object.prototype.hasOwnProperty.call(map, slug)) {
      return 0;
    }
    return parseFloat(map[slug]) || 0;
  }

  function syncOccupancyToUrl() {
    var qs = buildQuery({});
    window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
  }

  function applySpecialRates(overrides) {
    var merged = Object.assign({}, currentOccupancy(), overrides || {});
    SPECIAL_RATE_BOOL_KEYS.forEach(function (key) {
      merged[key] = merged[key] ? 1 : 0;
    });
    var exclusiveKeep = null;
    SPECIAL_RATE_BOOL_KEYS.forEach(function (key) {
      if (merged[key]) {
        if (exclusiveKeep === null) {
          exclusiveKeep = key;
        } else {
          merged[key] = 0;
        }
      }
    });
    cfg.occupancy = merged;
    cfg.discountPercent = discountPercentForOcc(merged);
    cfg.resolvedRateSlug = resolvedRateSlugFromOcc(merged);
    var qs = buildQuery({});
    window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
    updatePrices();
    closeModal('hb-rates-modal');
  }

  function discountPercent() {
    return parseFloat(cfg.discountPercent || 0) || 0;
  }

  function portalPricing() {
    var p = cfg.portalPricing || {};
    var d = cfg.pricingDefaults || {};
    return {
      child_nightly_supplement: parseFloat(p.child_nightly_supplement != null ? p.child_nightly_supplement : d.child_nightly_supplement) || 0,
      extra_adult_supplement_percent: parseFloat(p.extra_adult_supplement_percent != null ? p.extra_adult_supplement_percent : d.extra_adult_supplement_percent) || 0
    };
  }

  function quoteNightlyUndiscounted(base) {
    var occ = currentOccupancy();
    var pricing = portalPricing();
    var rooms = Math.max(1, Math.min(4, occ.rooms));
    var adults = Math.max(1, Math.min(12, occ.adults));
    var children = Math.max(0, Math.min(6, occ.children));
    var baseF = parseFloat(base) || 0;
    var included = 2 * rooms;
    var extraAdults = Math.max(0, adults - included);
    var extraPct = (parseFloat(pricing.extra_adult_supplement_percent) || 0) / 100;
    var childSupp = parseFloat(pricing.child_nightly_supplement) || 0;
    return Math.round(baseF * rooms + extraAdults * (baseF * extraPct) + children * childSupp);
  }

  function quoteNightly(base) {
    var nightly = quoteNightlyUndiscounted(base);
    var disc = discountPercent();
    if (disc > 0) {
      nightly *= (1 - disc / 100);
    }
    return Math.round(nightly);
  }

  function renderRoomPrice(card) {
    var base = card.getAttribute('data-base-price');
    var priceEl = card.querySelector('.hb-room-price-value');
    var compareEl = card.querySelector('.hb-room-price-compare');
    if (!priceEl || base === null || base === '') {
      return;
    }
    var list = quoteNightlyUndiscounted(base);
    var sale = quoteNightly(base);
    var disc = discountPercent();
    if (compareEl) {
      if (disc > 0 && list > sale) {
        compareEl.textContent = formatMoney(list);
        compareEl.hidden = false;
      } else {
        compareEl.textContent = '';
        compareEl.hidden = true;
      }
    }
    priceEl.textContent = formatMoney(sale);
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
    applySpecialRateParams(params, occ);
    return params.toString();
  }

  function reloadWith(overrides) {
    window.location.search = buildQuery(overrides);
  }

  function readRatesFormOverrides() {
    var overrides = {
      rate: '',
      use_points: 0,
      travel_agents: 0,
      aaa_rate: 0,
      senior_rate: 0,
      gov_military: 0,
      promo_code: '',
      group_code: '',
      corporate_account: '',
      member_account: ''
    };
    var form = document.getElementById('hb-rates-form');
    if (!form) {
      return overrides;
    }
    SPECIAL_RATE_BOOL_KEYS.forEach(function (key) {
      var el = form.querySelector('input[name="' + key + '"]');
      if (el && el.checked) {
        overrides[key] = 1;
      }
    });
    SPECIAL_RATE_CODE_KEYS.forEach(function (key) {
      var el = form.querySelector('input[name="' + key + '"]');
      if (el) {
        overrides[key] = sanitizeRateCode(el.value);
      }
    });
    return overrides;
  }

  function clearRatesForm() {
    var form = document.getElementById('hb-rates-form');
    if (!form) {
      return;
    }
    form.querySelectorAll('[data-hb-rate-exclusive]').forEach(function (el) {
      el.checked = false;
    });
    SPECIAL_RATE_CODE_KEYS.forEach(function (key) {
      var el = form.querySelector('input[name="' + key + '"]');
      if (el) {
        el.value = '';
      }
    });
  }

  function updatePrices() {
    cards.forEach(function (card) {
      renderRoomPrice(card);
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

  var ratesApply = document.getElementById('hb-rates-apply');
  if (ratesApply) {
    ratesApply.addEventListener('click', function () {
      applySpecialRates(readRatesFormOverrides());
    });
  }

  var rateClear = document.getElementById('hb-rates-clear');
  if (rateClear) {
    rateClear.addEventListener('click', function () {
      clearRatesForm();
      applySpecialRates({
        rate: '',
        use_points: 0,
        travel_agents: 0,
        aaa_rate: 0,
        senior_rate: 0,
        gov_military: 0,
        promo_code: '',
        group_code: '',
        corporate_account: '',
        member_account: ''
      });
    });
  }

  var ratesForm = document.getElementById('hb-rates-form');
  if (ratesForm) {
    ratesForm.querySelectorAll('[data-hb-rate-exclusive]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        if (!cb.checked) {
          return;
        }
        ratesForm.querySelectorAll('[data-hb-rate-exclusive]').forEach(function (other) {
          if (other !== cb) {
            other.checked = false;
          }
        });
      });
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
          var compareEl = card.querySelector('.hb-room-price-compare');
          var bookBtn = detail.querySelector('.hb-room-detail-book');
          var selectLink = card.querySelector('.hb-room-select');
          if (bookBtn && bookBtn.tagName === 'A') {
            var bookValue = bookBtn.querySelector('.hb-rd-price-value');
            var bookCompare = bookBtn.querySelector('.hb-rd-price-compare');
            if (bookValue && priceEl) {
              bookValue.textContent = priceEl.textContent.trim();
            }
            if (bookCompare && compareEl) {
              if (!compareEl.hidden && compareEl.textContent.trim() !== '') {
                bookCompare.textContent = compareEl.textContent.trim();
                bookCompare.hidden = false;
              } else {
                bookCompare.textContent = '';
                bookCompare.hidden = true;
              }
            }
          }
          if (bookBtn && selectLink && bookBtn.tagName === 'A') {
            bookBtn.href = selectLink.href;
          }
        }
        openModal('hb-room-detail-modal');
        if (typeof window.HB_initGalleries === 'function') {
          window.HB_initGalleries(detail);
        }
      }
    });
  });

  document.getElementById('hb-room-detail-body') && document.getElementById('hb-room-detail-body').addEventListener('click', function (e) {
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

  updatePrices();

  if (typeof window.HB_initGalleries === 'function') {
    HB_initGalleries(document);
  }
})();
