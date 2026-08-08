/**
 * Select Dates modal (price calendar) for public booking portal.
 * First click = check-in; second click = check-out (1 night if only check-in).
 */
(function () {
  var datesModal = document.getElementById('hb-dates-modal');
  if (!datesModal) return;

  var datesBody = document.getElementById('hb-dates-body');
  var hotelsMap = {};
  (window.HB_HOTELS || []).forEach(function (h) { hotelsMap[h.id] = h; });

  var state = {
    hotel: null,
    year: 0,
    month: 0,
    checkInYmd: '',
    checkOutYmd: '',
    calendar: null,
    calendarCache: {}
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function moneySym(code) {
    return (code || 'EUR').toUpperCase() === 'EUR' ? '€' : (code || '') + ' ';
  }

  function formatMoney(amount, code) {
    var n = Math.round(parseFloat(amount) || 0);
    return moneySym(code) + n;
  }

  function monthLabel(y, m) {
    var d = new Date(y, m - 1, 1);
    return d.toLocaleString('en-GB', { month: 'short', year: 'numeric' });
  }

  function parseYmd(ymd) {
    return new Date(ymd + 'T12:00:00');
  }

  function addDaysYmd(ymd, days) {
    var d = parseYmd(ymd);
    d.setDate(d.getDate() + days);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function nightsBetween(checkInYmd, checkOutYmd) {
    var inD = parseYmd(checkInYmd);
    var outD = parseYmd(checkOutYmd);
    var diff = Math.round((outD - inD) / 86400000);
    return Math.max(1, diff);
  }

  var HOTEL_DATE_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function formatHotelDateYmd(ymd) {
    if (typeof window.itmHotelDateFormatYmd === 'function') {
      return window.itmHotelDateFormatYmd(ymd);
    }
    var parts = String(ymd || '').split('-');
    if (parts.length !== 3) {
      return '';
    }
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10) - 1;
    var d = parseInt(parts[2], 10);
    if (!y || m < 0 || m > 11 || d < 1) {
      return '';
    }
    return String(d).padStart(2, '0') + '/' + HOTEL_DATE_MONTHS[m] + '/' + y;
  }

  function displayRange(checkInYmd, checkOutYmd) {
    var inn = formatHotelDateYmd(checkInYmd);
    var out = formatHotelDateYmd(checkOutYmd);
    if (inn && out) {
      return inn + ' - ' + out;
    }
    return '';
  }

  function effectiveCheckOut() {
    if (!state.checkInYmd) return '';
    if (state.checkOutYmd) return state.checkOutYmd;
    return addDaysYmd(state.checkInYmd, 1);
  }

  function effectiveNights() {
    if (!state.checkInYmd) return 0;
    return nightsBetween(state.checkInYmd, effectiveCheckOut());
  }

  function cacheKey(year, month) {
    var occ = state.occupancy || {};
    return year + '-' + month
      + '-r' + String(occ.rooms != null ? occ.rooms : 1)
      + '-a' + String(occ.adults != null ? occ.adults : 1)
      + '-c' + String(occ.children != null ? occ.children : 0)
      + '-b' + String(occ.babies != null ? occ.babies : 0);
  }

  function buildMonthTabs() {
    var tabs = [];
    var now = new Date();
    for (var i = 0; i < 14; i++) {
      var d = new Date(now.getFullYear(), now.getMonth() + i, 1);
      tabs.push({ year: d.getFullYear(), month: d.getMonth() + 1 });
    }
    return tabs;
  }

  function monthTabsBounds() {
    var tabs = buildMonthTabs();
    return { min: tabs[0], max: tabs[tabs.length - 1] };
  }

  function shiftMonth(year, month, delta) {
    var d = new Date(year, month - 1 + delta, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
  }

  function compareMonth(aY, aM, bY, bM) {
    if (aY !== bY) return aY < bY ? -1 : 1;
    if (aM === bM) return 0;
    return aM < bM ? -1 : 1;
  }

  function daysLeftInMonth(ymd) {
    var parts = ymd.split('-');
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var d = parseInt(parts[2], 10);
    return new Date(y, m, 0).getDate() - d;
  }

  function syncMonthTabUi() {
    if (!datesBody) return;
    datesBody.querySelectorAll('.hb-dates-month-tab').forEach(function (tab) {
      var ty = parseInt(tab.getAttribute('data-year'), 10);
      var tm = parseInt(tab.getAttribute('data-month'), 10);
      tab.classList.toggle('is-active', ty === state.year && tm === state.month);
    });
    var activeTab = datesBody.querySelector('.hb-dates-month-tab.is-active');
    if (activeTab && typeof activeTab.scrollIntoView === 'function') {
      activeTab.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    }
    var bounds = monthTabsBounds();
    var prevBtn = datesBody.querySelector('.hb-dates-cal-prev');
    var nextBtn = datesBody.querySelector('.hb-dates-cal-next');
    if (prevBtn) {
      prevBtn.disabled = compareMonth(state.year, state.month, bounds.min.year, bounds.min.month) <= 0;
    }
    if (nextBtn) {
      nextBtn.disabled = compareMonth(state.year, state.month, bounds.max.year, bounds.max.month) >= 0;
    }
  }

  function setViewMonth(year, month) {
    state.year = year;
    state.month = month;
    syncMonthTabUi();
    loadCalendar();
  }

  function prefetchMonth(year, month) {
    if (!state.hotel) return;
    fetchCalendar(state.hotel.id, year, month);
  }

  function maybeAdvanceMonthForCheckOut(checkInYmd) {
    if (!checkInYmd || state.checkOutYmd) return;
    if (daysLeftInMonth(checkInYmd) >= 7) return;
    var parts = checkInYmd.split('-');
    var next = shiftMonth(parseInt(parts[0], 10), parseInt(parts[1], 10), 1);
    var bounds = monthTabsBounds();
    if (compareMonth(next.year, next.month, bounds.max.year, bounds.max.month) > 0) return;
    setViewMonth(next.year, next.month);
  }

  function prefetchAdjacentMonthsForCheckOut(checkInYmd) {
    if (!checkInYmd || state.checkOutYmd || !state.hotel) return;
    var parts = checkInYmd.split('-');
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var next = shiftMonth(y, m, 1);
    var bounds = monthTabsBounds();
    if (compareMonth(next.year, next.month, bounds.max.year, bounds.max.month) <= 0) {
      prefetchMonth(next.year, next.month);
    }
  }

  function fetchCalendar(hotelId, year, month) {
    var key = cacheKey(year, month);
    if (state.calendarCache[key]) {
      return Promise.resolve(state.calendarCache[key]);
    }
    var url = window.HB_APPURL + '/calendar.php?hotel_id=' + hotelId + '&year=' + year + '&month=' + month;
    var occ = state.occupancy || {};
    ['rooms', 'adults', 'children', 'babies'].forEach(function (k) {
      if (occ[k] != null && occ[k] !== '') {
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(String(occ[k]));
      }
    });
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (data) {
      state.calendarCache[key] = data;
      return data;
    });
  }

  function dayInfo(ymd) {
    var parts = ymd.split('-');
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var key = cacheKey(y, m);
    var cal = state.calendarCache[key];
    if (!cal || !cal.days) return null;
    return cal.days[ymd] || null;
  }

  function isDaySelectable(ymd) {
    var info = dayInfo(ymd);
    return info && info.available && !info.past;
  }

  function ensureRangeCalendars(checkInYmd, checkOutYmd) {
    var monthsNeeded = {};
    var cursor = checkInYmd;
    var lastNight = addDaysYmd(checkOutYmd, -1);
    while (cursor <= lastNight) {
      var parts = cursor.split('-');
      monthsNeeded[cacheKey(parseInt(parts[0], 10), parseInt(parts[1], 10))] = true;
      cursor = addDaysYmd(cursor, 1);
    }
    var promises = [];
    Object.keys(monthsNeeded).forEach(function (key) {
      var km = key.split('-');
      promises.push(fetchCalendar(state.hotel.id, parseInt(km[0], 10), parseInt(km[1], 10)));
    });
    return promises.length ? Promise.all(promises) : Promise.resolve([]);
  }

  function isRangeAvailable(checkInYmd, checkOutYmd) {
    if (!checkInYmd || !checkOutYmd || checkOutYmd <= checkInYmd) return false;
    var cursor = checkInYmd;
    var lastNight = addDaysYmd(checkOutYmd, -1);
    while (cursor <= lastNight) {
      if (!isDaySelectable(cursor)) return false;
      cursor = addDaysYmd(cursor, 1);
    }
    return true;
  }

  function rangeCellClass(ymd) {
    if (!state.checkInYmd) return '';
    if (ymd === state.checkInYmd) {
      if (!state.checkOutYmd || state.checkOutYmd === addDaysYmd(state.checkInYmd, 1)) {
        return ' is-range-start is-range-end';
      }
      return ' is-range-start';
    }
    if (state.checkOutYmd && ymd === state.checkOutYmd) {
      return ' is-range-end';
    }
    var effOut = effectiveCheckOut();
    if (ymd > state.checkInYmd && ymd < effOut) {
      return ' is-in-range';
    }
    return '';
  }

  function handleDayClick(ymd) {
    if (!isDaySelectable(ymd)) return;

    if (!state.checkInYmd || (state.checkInYmd && state.checkOutYmd)) {
      state.checkInYmd = ymd;
      state.checkOutYmd = '';
      prefetchAdjacentMonthsForCheckOut(ymd);
      maybeAdvanceMonthForCheckOut(ymd);
      if (compareMonth(state.year, state.month, parseInt(ymd.split('-')[0], 10), parseInt(ymd.split('-')[1], 10)) === 0) {
        renderCalendarGrid();
      }
      updateFooter();
      return;
    }

    if (ymd <= state.checkInYmd) {
      state.checkInYmd = ymd;
      state.checkOutYmd = '';
      prefetchAdjacentMonthsForCheckOut(ymd);
      maybeAdvanceMonthForCheckOut(ymd);
      if (compareMonth(state.year, state.month, parseInt(ymd.split('-')[0], 10), parseInt(ymd.split('-')[1], 10)) === 0) {
        renderCalendarGrid();
      }
      updateFooter();
      return;
    }

    var checkOut = ymd;
    ensureRangeCalendars(state.checkInYmd, checkOut).then(function () {
      if (!isRangeAvailable(state.checkInYmd, checkOut)) {
        state.checkInYmd = ymd;
        state.checkOutYmd = '';
      } else {
        state.checkOutYmd = checkOut;
      }
      renderCalendarGrid();
      updateFooter();
    });
  }

  function renderShell() {
    if (!state.hotel || !datesBody) return;
    var h = state.hotel;
    var tabs = buildMonthTabs();
    var tabsHtml = tabs.map(function (t) {
      var active = t.year === state.year && t.month === state.month;
      return '<button type="button" class="hb-dates-month-tab' + (active ? ' is-active' : '') + '" data-year="' + t.year + '" data-month="' + t.month + '">' + escapeHtml(monthLabel(t.year, t.month)) + '</button>';
    }).join('');

    datesBody.innerHTML =
      '<div class="hb-dates-hotel"><strong>' + escapeHtml(h.name) + '</strong></div>' +
      '<p class="hb-dates-copy">Select your check-in date, then your check-out date. One night is selected when only check-in is chosen.</p>' +
      '<p class="hb-dates-copy">We\'re showing the best price per room based on the number of guests. Prices include tourist tax and fees.</p>' +
      '<p class="hb-dates-explore"><a href="' + escapeHtml(window.HB_APPURL + '/rooms.php?id=' + h.id) + '">Explore all filters and search options &gt;</a></p>' +
      '<div class="hb-dates-months-wrap"><div class="hb-dates-months">' + tabsHtml + '</div></div>' +
      '<div class="hb-dates-cal-nav">' +
      '<button type="button" class="hb-dates-cal-prev" title="Previous month">◀</button>' +
      '<div class="hb-dates-cal-head" id="hb-dates-cal-title"></div>' +
      '<button type="button" class="hb-dates-cal-next" title="Next month">▶</button>' +
      '</div>' +
      '<div class="hb-dates-cal-grid" id="hb-dates-cal-grid"><p class="hb-dates-loading">Loading…</p></div>' +
      '<p class="hb-dates-range-hint" id="hb-dates-range-hint"></p>' +
      '<div class="hb-dates-footer">' +
      '<div class="hb-dates-summary" id="hb-dates-summary"></div>' +
      '<div class="hb-dates-actions">' +
      '<button type="button" class="hb-btn hb-dates-choose" disabled title="Choose room">Choose Room</button>' +
      '<button type="button" class="hb-btn hb-dates-cancel" title="Cancel">🔙</button>' +
      '</div></div>';

    datesBody.querySelectorAll('.hb-dates-month-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setViewMonth(parseInt(btn.getAttribute('data-year'), 10), parseInt(btn.getAttribute('data-month'), 10));
      });
    });
    var calPrev = datesBody.querySelector('.hb-dates-cal-prev');
    var calNext = datesBody.querySelector('.hb-dates-cal-next');
    if (calPrev) {
      calPrev.addEventListener('click', function () {
        if (calPrev.disabled) return;
        var shifted = shiftMonth(state.year, state.month, -1);
        setViewMonth(shifted.year, shifted.month);
      });
    }
    if (calNext) {
      calNext.addEventListener('click', function () {
        if (calNext.disabled) return;
        var shifted = shiftMonth(state.year, state.month, 1);
        setViewMonth(shifted.year, shifted.month);
      });
    }
    syncMonthTabUi();
    datesBody.querySelector('.hb-dates-cancel').addEventListener('click', closeDatesModal);
    datesBody.querySelector('.hb-dates-choose').addEventListener('click', function () {
      if (!state.checkInYmd || !state.hotel) return;
      var nights = effectiveNights();
      window.location.href = window.HB_APPURL + '/rooms.php?id=' + state.hotel.id
        + '&check_in=' + encodeURIComponent(state.checkInYmd)
        + '&nights=' + encodeURIComponent(String(nights));
    });
    loadCalendar();
    updateFooter();
  }

  function loadCalendar() {
    var grid = document.getElementById('hb-dates-cal-grid');
    var title = document.getElementById('hb-dates-cal-title');
    if (!grid || !state.hotel) return;
    grid.innerHTML = '<p class="hb-dates-loading">Loading…</p>';
    if (title) title.textContent = monthLabel(state.year, state.month);
    fetchCalendar(state.hotel.id, state.year, state.month).then(function (data) {
      state.calendar = data;
      renderCalendarGrid();
      syncMonthTabUi();
    }).catch(function () {
      grid.innerHTML = '<p class="hb-dates-error">Could not load calendar.</p>';
    });
  }

  function renderCalendarGrid() {
    var grid = document.getElementById('hb-dates-cal-grid');
    if (!grid || !state.calendar) return;
    var y = state.calendar.year;
    var m = state.calendar.month;
    var days = state.calendar.days || {};
    var code = state.calendar.currency_code || state.hotel.currency_code || 'EUR';
    var firstDow = new Date(y, m - 1, 1).getDay();
    var dim = new Date(y, m, 0).getDate();
    var headers = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var html = '<div class="hb-cal-week hb-cal-head">' + headers.map(function (h) { return '<span>' + h + '</span>'; }).join('') + '</div>';
    html += '<div class="hb-cal-week">';
    for (var pad = 0; pad < firstDow; pad++) {
      html += '<span class="hb-cal-cell hb-cal-empty"></span>';
    }
    for (var d = 1; d <= dim; d++) {
      var ymd = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      var info = days[ymd] || { available: false };
      var cls = 'hb-cal-cell';
      if (info.past) cls += ' hb-cal-past';
      else if (!info.available) cls += ' hb-cal-unavail';
      else cls += ' hb-cal-available';
      cls += rangeCellClass(ymd);
      var priceHtml = info.available && info.price != null ? '<span class="hb-cal-price">' + escapeHtml(formatMoney(info.price, code)) + '</span>' : '';
      var disabled = !info.available || info.past;
      html += '<button type="button" class="' + cls + '" data-ymd="' + ymd + '" ' + (disabled ? 'disabled' : '') + '><span class="hb-cal-day">' + d + '</span>' + priceHtml + '</button>';
    }
    html += '</div>';
    grid.innerHTML = html;
    grid.querySelectorAll('.hb-cal-available').forEach(function (btn) {
      btn.addEventListener('click', function () {
        handleDayClick(btn.getAttribute('data-ymd'));
      });
    });
  }

  function averageNightlyPrice(checkInYmd, checkOutYmd) {
    var total = 0;
    var count = 0;
    var cursor = checkInYmd;
    var lastNight = addDaysYmd(checkOutYmd, -1);
    while (cursor <= lastNight) {
      var info = dayInfo(cursor);
      if (info && info.price != null) {
        total += parseFloat(info.price) || 0;
        count++;
      }
      cursor = addDaysYmd(cursor, 1);
    }
    if (count === 0) {
      var single = dayInfo(checkInYmd);
      return single ? (parseFloat(single.price) || 0) : 0;
    }
    return total / count;
  }

  function updateFooter() {
    var summary = document.getElementById('hb-dates-summary');
    var hint = document.getElementById('hb-dates-range-hint');
    var choose = datesBody && datesBody.querySelector('.hb-dates-choose');
    if (!summary || !choose) return;

    if (hint) {
      if (!state.checkInYmd) {
        hint.textContent = 'Select your check-in date.';
      } else if (!state.checkOutYmd) {
        hint.textContent = 'Select your check-out date (use ▶ or the month tabs for the next month), or choose room for 1 night.';
      } else {
        hint.textContent = '';
      }
    }

    if (!state.checkInYmd) {
      summary.innerHTML = '';
      choose.disabled = true;
      return;
    }

    var checkOut = effectiveCheckOut();
    var nights = effectiveNights();
    var finish = function () {
      if (!isRangeAvailable(state.checkInYmd, checkOut)) {
        summary.innerHTML = '';
        choose.disabled = true;
        if (hint) hint.textContent = 'Some nights in this range are unavailable. Choose different dates.';
        return;
      }
      var code = (state.calendar && state.calendar.currency_code) || state.hotel.currency_code || 'EUR';
      var avg = averageNightlyPrice(state.checkInYmd, checkOut);
      var nightWord = nights === 1 ? 'night' : 'nights';
      summary.innerHTML =
        '<p class="hb-dates-sum-price">' + escapeHtml(formatMoney(avg, code)) + '</p>' +
        '<p class="hb-dates-sum-meta">avg/night for ' + nights + ' ' + nightWord + ' (incl. taxes)</p>' +
        '<p class="hb-dates-sum-label">Best available rate</p>' +
        '<p class="hb-dates-sum-range">' + escapeHtml(displayRange(state.checkInYmd, checkOut)) + '</p>';
      choose.disabled = false;
    };

    ensureRangeCalendars(state.checkInYmd, checkOut).then(finish).catch(function () {
      summary.innerHTML = '';
      choose.disabled = true;
    });
  }

  function openDatesModal(hotel) {
    if (!hotel) return;
    state.hotel = hotel;
    state.checkInYmd = '';
    state.checkOutYmd = '';
    state.calendarCache = {};
    // Why: Calendar day prices include tourist tax for occupancy (default 1 adult on home Select Dates).
    state.occupancy = {
      rooms: 1,
      adults: 1,
      children: 0,
      babies: 0
    };
    var now = new Date();
    state.year = now.getFullYear();
    state.month = now.getMonth() + 1;
    datesModal.hidden = false;
    document.body.classList.add('hb-modal-open');
    renderShell();
  }

  function closeDatesModal() {
    datesModal.hidden = true;
    var detail = document.getElementById('hb-detail-modal');
    if (!detail || detail.hidden) {
      document.body.classList.remove('hb-modal-open');
    }
  }

  var closeBtn = datesModal.querySelector('.hb-dates-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeDatesModal);
  }
  datesModal.addEventListener('click', function (e) {
    if (e.target === datesModal) closeDatesModal();
  });

  window.HB_openDatesModal = openDatesModal;
})();
