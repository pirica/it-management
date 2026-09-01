/**
 * Stay-bar occupancy modal on checkout steps 2–4 (AJAX apply + unavailable alert).
 */
(function () {
  'use strict';

  var cfg = window.HB_OCCUPANCY || {};
  if (!cfg.applyUrl) {
    return;
  }

  function parseIntSafe(value, fallback) {
    var parsed = parseInt(value, 10);
    return isNaN(parsed) ? fallback : parsed;
  }

  function occupancyLimitsFromCfg() {
    var limits = cfg.occupancyLimits || {};
    return {
      rooms: parseIntSafe(limits.rooms, 4),
      adults: parseIntSafe(limits.adults, 8),
      children: parseIntSafe(limits.children, 4),
      babies: parseIntSafe(limits.babies, 2)
    };
  }

  function occupancyFromInputs() {
    var roomsEl = document.getElementById('hb-occ-rooms');
    var adultsEl = document.getElementById('hb-occ-adults');
    var childrenEl = document.getElementById('hb-occ-children');
    var babiesEl = document.getElementById('hb-occ-babies');
    return {
      rooms: parseIntSafe(roomsEl && roomsEl.value, 1),
      adults: parseIntSafe(adultsEl && adultsEl.value, 1),
      children: parseIntSafe(childrenEl && childrenEl.value, 0),
      babies: parseIntSafe(babiesEl && babiesEl.value, 0)
    };
  }

  function occupancyLabelFromValues(occ) {
    var rooms = parseIntSafe(occ.rooms, 1);
    var adults = parseIntSafe(occ.adults, 1);
    var children = parseIntSafe(occ.children, 0);
    var babies = parseIntSafe(occ.babies, 0);
    var roomWord = rooms === 1 ? 'room' : 'rooms';
    var parts = [];
    parts.push(adults === 1 ? '1 adult' : adults + ' adults');
    if (children > 0) {
      parts.push(children === 1 ? '1 child' : children + ' children');
    }
    if (babies > 0) {
      parts.push(babies === 1 ? '1 baby' : babies + ' babies');
    }
    return rooms + ' ' + roomWord + ' for ' + parts.join(' + ');
  }

  function updateStayBarLabel(label) {
    var occBtn = document.getElementById('hb-stay-occupancy-trigger');
    if (occBtn && label) {
      occBtn.textContent = '\uD83D\uDC64 ' + label;
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

  function bindStepper(minusId, plusId, inputId, min, max) {
    var input = document.getElementById(inputId);
    var minus = document.getElementById(minusId);
    var plus = document.getElementById(plusId);
    if (!input) {
      return;
    }
    function setVal(value) {
      var next = Math.max(min, Math.min(max, value));
      input.value = String(next);
      updateStayBarLabel(occupancyLabelFromValues(occupancyFromInputs()));
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

  var occLimits = occupancyLimitsFromCfg();
  bindStepper('hb-occ-rooms-minus', 'hb-occ-rooms-plus', 'hb-occ-rooms', 1, occLimits.rooms);
  bindStepper('hb-occ-adults-minus', 'hb-occ-adults-plus', 'hb-occ-adults', 1, occLimits.adults);
  bindStepper('hb-occ-children-minus', 'hb-occ-children-plus', 'hb-occ-children', 0, occLimits.children);
  bindStepper('hb-occ-babies-minus', 'hb-occ-babies-plus', 'hb-occ-babies', 0, occLimits.babies);

  function showUnavailable(message) {
    var msgEl = document.getElementById('hb-occupancy-unavailable-message');
    if (msgEl) {
      msgEl.textContent = message || cfg.unavailableMessage || 'This selection is not available for your dates.';
    }
    openModal('hb-occupancy-unavailable-modal');
  }

  function navigateTo(url) {
    if (!url) {
      window.location.reload();
      return;
    }
    if (url === window.location.href) {
      window.location.reload();
      return;
    }
    window.location.href = url;
  }

  var applyBtn = document.getElementById('hb-occupancy-apply');
  if (!applyBtn) {
    return;
  }

  applyBtn.addEventListener('click', function () {
    var occ = occupancyFromInputs();
    var payload = new FormData();
    payload.append('csrf_token', cfg.csrfToken || '');
    payload.append('rooms', String(occ.rooms));
    payload.append('adults', String(occ.adults));
    payload.append('children', String(occ.children));
    payload.append('babies', String(occ.babies));
    if (cfg.hotelId) {
      payload.append('hotel_id', String(cfg.hotelId));
    }
    if (cfg.roomId) {
      payload.append('room_id', String(cfg.roomId));
    }
    if (cfg.checkInIso) {
      payload.append('check_in', cfg.checkInIso);
    }
    if (cfg.nights) {
      payload.append('nights', String(cfg.nights));
    }
    payload.append('redirect_url', window.location.href);

    applyBtn.disabled = true;
    fetch(cfg.applyUrl, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('http_' + response.status);
        }
        return response.json();
      })
      .then(function (data) {
        applyBtn.disabled = false;
        closeModal('hb-occupancy-modal');
        if (data && data.occupancy_label) {
          updateStayBarLabel(data.occupancy_label);
        }
        if (data && data.ok) {
          navigateTo(data.redirect_url || '');
          return;
        }
        if (data && data.restart && data.redirect_url) {
          navigateTo(data.redirect_url);
          return;
        }
        showUnavailable(data && data.error ? data.error : '');
      })
      .catch(function () {
        applyBtn.disabled = false;
        showUnavailable(cfg.unavailableMessage || '');
      });
  });
})();
