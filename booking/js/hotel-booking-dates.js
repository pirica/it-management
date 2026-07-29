/**
 * Select Dates modal (price calendar) for public booking portal.
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
    selectedYmd: '',
    calendar: null,
    loading: false
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

  function displayRange(checkInYmd) {
    var inD = new Date(checkInYmd + 'T12:00:00');
    var outD = new Date(inD);
    outD.setDate(outD.getDate() + 1);
    var opts = { month: 'short', day: 'numeric' };
    return inD.toLocaleString('en-GB', opts) + ' - ' + outD.toLocaleString('en-GB', opts);
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

  function fetchCalendar(hotelId, year, month) {
    var url = window.HB_APPURL + '/calendar.php?hotel_id=' + hotelId + '&year=' + year + '&month=' + month;
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
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
      '<p class="hb-dates-copy">We\'re showing the best price per room for 1 night, based on the number of guests.</p>' +
      '<p class="hb-dates-copy">Price includes fees</p>' +
      '<p class="hb-dates-explore"><a href="' + escapeHtml(window.HB_APPURL + '/rooms.php?id=' + h.id) + '">Explore all filters and search options &gt;</a></p>' +
      '<div class="hb-dates-months-wrap"><div class="hb-dates-months">' + tabsHtml + '</div></div>' +
      '<div class="hb-dates-cal-head" id="hb-dates-cal-title"></div>' +
      '<div class="hb-dates-cal-grid" id="hb-dates-cal-grid"><p class="hb-dates-loading">Loading…</p></div>' +
      '<div class="hb-dates-footer">' +
      '<div class="hb-dates-summary" id="hb-dates-summary"></div>' +
      '<div class="hb-dates-actions">' +
      '<button type="button" class="hb-btn hb-dates-choose" disabled title="Choose room">Choose Room</button>' +
      '<button type="button" class="hb-btn hb-dates-cancel" title="Cancel">Cancel</button>' +
      '</div></div>';

    datesBody.querySelectorAll('.hb-dates-month-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.year = parseInt(btn.getAttribute('data-year'), 10);
        state.month = parseInt(btn.getAttribute('data-month'), 10);
        state.selectedYmd = '';
        renderShell();
        loadCalendar();
      });
    });
    datesBody.querySelector('.hb-dates-cancel').addEventListener('click', closeDatesModal);
    datesBody.querySelector('.hb-dates-choose').addEventListener('click', function () {
      if (!state.selectedYmd || !state.hotel) return;
      window.location.href = window.HB_APPURL + '/rooms.php?id=' + state.hotel.id + '&check_in=' + encodeURIComponent(state.selectedYmd);
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
      if (state.selectedYmd === ymd) cls += ' is-selected';
      var priceHtml = info.available && info.price != null ? '<span class="hb-cal-price">' + escapeHtml(formatMoney(info.price, code)) + '</span>' : '';
      var disabled = !info.available || info.past;
      html += '<button type="button" class="' + cls + '" data-ymd="' + ymd + '" ' + (disabled ? 'disabled' : '') + '><span class="hb-cal-day">' + d + '</span>' + priceHtml + '</button>';
    }
    html += '</div>';
    grid.innerHTML = html;
    grid.querySelectorAll('.hb-cal-available').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.selectedYmd = btn.getAttribute('data-ymd');
        renderCalendarGrid();
        updateFooter();
      });
    });
  }

  function updateFooter() {
    var summary = document.getElementById('hb-dates-summary');
    var choose = datesBody && datesBody.querySelector('.hb-dates-choose');
    if (!summary || !choose) return;
    if (!state.selectedYmd || !state.calendar) {
      summary.innerHTML = '';
      choose.disabled = true;
      return;
    }
    var info = (state.calendar.days || {})[state.selectedYmd];
    if (!info || !info.available) {
      summary.innerHTML = '';
      choose.disabled = true;
      return;
    }
    var code = state.calendar.currency_code || 'EUR';
    summary.innerHTML =
      '<p class="hb-dates-sum-price">' + escapeHtml(formatMoney(info.price, code)) + '</p>' +
      '<p class="hb-dates-sum-meta">avg/night for 1 night</p>' +
      '<p class="hb-dates-sum-label">Best available rate</p>' +
      '<p class="hb-dates-sum-range">' + escapeHtml(displayRange(state.selectedYmd)) + '</p>';
    choose.disabled = false;
  }

  function openDatesModal(hotel) {
    if (!hotel) return;
    state.hotel = hotel;
    state.selectedYmd = '';
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
