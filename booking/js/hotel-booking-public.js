(function () {
  var modal = document.getElementById('hb-detail-modal');
  if (!modal) return;
  var body = document.getElementById('hb-modal-body');
  var hotels = window.HB_HOTELS || [];
  var settings = window.HB_SETTINGS || {};
  var map = {};
  hotels.forEach(function (h) { map[h.id] = h; });

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function formatPrice(h) {
    var n = parseFloat(h.min_price || 0);
    var code = (h.currency_code || 'EUR').toUpperCase();
    var sym = code === 'EUR' ? '€' : code + ' ';
    return sym + (Number.isFinite(n) ? Math.round(n) : '0');
  }

  function currencyLabel(code) {
    var c = (code || 'EUR').toUpperCase();
    if (c === 'EUR') return 'Euro';
    return c;
  }

  function resolveReviewsUrl(h) {
    var fromHotel = String((h && h.reviews_url) || '').trim();
    if (fromHotel) {
      return fromHotel;
    }
    return String(settings.reviews_url || '').trim();
  }

  function reviewsLinkHtml(h) {
    var reviewsUrl = resolveReviewsUrl(h);
    if (!reviewsUrl) {
      return '';
    }
    return '<a href="' + escapeHtml(reviewsUrl) + '" class="hb-reviews-link" target="_blank" rel="noopener noreferrer" title="Read reviews (opens in new tab)">Read reviews <span class="hb-external-icon" aria-hidden="true">↗</span></a>';
  }

  function photoUrls(h) {
    var photos = h.photos || [];
    if (!photos.length) {
      return [window.HB_APPURL + '/images/image_2.jpg'];
    }
    return photos.map(function (p) {
      return p.public_url || (window.HB_APPURL + '/images/image_2.jpg');
    });
  }

  function amenityIcon(name, iconSlug) {
    if (typeof window.HB_amenityIconMarkup === 'function') {
      return window.HB_amenityIconMarkup(name, iconSlug || '');
    }
    return '';
  }

  function splitParkingLines(text) {
    var t = String(text || '').trim();
    if (!t) return [];
    if (t.indexOf('\n') >= 0) {
      return t.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
    }
    if (/self.*valet|valet.*self/i.test(t)) {
      return ['Self parking: Complimentary', 'Valet parking: Complimentary'];
    }
    return [t];
  }

  function nearbyLists(h) {
    var all = h.nearby || [];
    var nearby = [];
    var airport = [];
    all.forEach(function (row) {
      var name = String(row.place_name || '');
      if (/airport/i.test(name)) {
        airport.push(row);
      } else {
        nearby.push(row);
      }
    });
    return { nearby: nearby, airport: airport };
  }

  function bindGallery(root, urls) {
    if (typeof window.HB_bindGallery === 'function') {
      window.HB_bindGallery(root, urls);
    }
  }

  function bindReadMore(root) {
    var desc = root.querySelector('.hb-desc-text');
    var btn = root.querySelector('.hb-read-more');
    if (!desc || !btn) return;
    var full = desc.textContent || '';
    var shortLen = 220;
    if (full.length <= shortLen) {
      btn.hidden = true;
      return;
    }
    desc.textContent = full.substring(0, shortLen).trim() + '…';
    var open = false;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      open = !open;
      desc.textContent = open ? full : full.substring(0, shortLen).trim() + '…';
      btn.textContent = open ? 'Read less' : 'Read more';
    });
  }

  function bindTabs(root) {
    var tabs = root.querySelectorAll('.hb-loc-tab');
    var panels = root.querySelectorAll('.hb-loc-panel');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = tab.getAttribute('data-tab');
        tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
        panels.forEach(function (p) {
          p.hidden = p.getAttribute('data-panel') !== id;
        });
      });
    });
  }

  function bindAccordion(root) {
    var head = root.querySelector('.hb-accordion-head');
    var panel = root.querySelector('.hb-accordion-panel');
    if (!head || !panel) return;
    head.addEventListener('click', function () {
      var open = head.getAttribute('aria-expanded') === 'true';
      head.setAttribute('aria-expanded', open ? 'false' : 'true');
      panel.hidden = open;
    });
  }

  function bindFavorite(btn, hotelId) {
    var key = 'hb_fav_' + hotelId;
    function sync() {
      var on = localStorage.getItem(key) === '1';
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    }
    sync();
    btn.addEventListener('click', function () {
      var on = localStorage.getItem(key) === '1';
      localStorage.setItem(key, on ? '0' : '1');
      sync();
    });
  }

  function openModal(hotelId) {
    var h = map[hotelId];
    if (!h || !body) return;
    var urls = photoUrls(h);
    var lists = nearbyLists(h);
    var parkingRows = splitParkingLines(h.parking_info);
    var amenities = h.amenities || [];
    if (!amenities.length) {
      amenities = [
        { name: 'Free WiFi', icon_class: '' },
        { name: 'Indoor pool', icon_class: '' },
        { name: 'Fitness center', icon_class: '' }
      ];
    }
    var amenityHtml = amenities.map(function (a) {
      var slug = a.icon_slug || '';
      return '<div class="hb-amenity-item"><span class="hb-amenity-icon" aria-hidden="true">' + amenityIcon(a.name, slug) + '</span><span>' + escapeHtml(a.name) + '</span></div>';
    }).join('');

    var parkingHtml = '';
    if (!parkingRows.length) {
      parkingHtml = '<tr><th>Parking</th><td>—</td></tr>';
    } else {
      parkingRows.forEach(function (line, i) {
        parkingHtml += '<tr><th>' + (i === 0 ? 'Parking' : '') + '</th><td>' + escapeHtml(line) + '</td></tr>';
      });
    }

    var nearbyHtml = lists.nearby.length
      ? lists.nearby.map(function (r) {
        return '<li><span>' + escapeHtml(r.place_name) + '</span><span>' + escapeHtml(parseFloat(r.distance_km).toFixed(2)) + ' km</span></li>';
      }).join('')
      : '<li class="hb-muted">No places listed.</li>';

    var airportHtml = '';
    if (lists.airport.length) {
      airportHtml = lists.airport.map(function (r) {
        return '<li><span>' + escapeHtml(r.place_name) + '</span><span>' + escapeHtml(parseFloat(r.distance_km).toFixed(2)) + ' km</span></li>';
      }).join('');
    }
    if (settings.airport_info) {
      airportHtml += '<li class="hb-airport-note">' + escapeHtml(settings.airport_info) + '</li>';
    }
    if (!airportHtml) {
      airportHtml = '<li class="hb-muted">No airport information.</li>';
    }

    var accessible = (settings.accessible_features_default || '').trim();
    var footnote = (settings.price_footnote || '*Prices are based on current availability and may change.').trim();
    var descFull = String(h.description || '');

    body.innerHTML =
      '<div class="hb-detail">' +
      '<div class="hb-detail-left">' +
      (typeof window.HB_galleryMarkup === 'function' ? window.HB_galleryMarkup(urls) :
        '<div class="hb-gallery-wrap"><div class="hb-gallery"></div></div>') +
      '<div class="hb-title-row">' +
      '<h2 class="hb-detail-title">' + escapeHtml(h.name) + '</h2>' +
      '<button type="button" class="hb-fav" title="Save to favorites" aria-label="Favorite">♡</button>' +
      '</div>' +
      '<div class="hb-action-links">' +
      (h.location ? '<a href="https://maps.google.com/?q=' + encodeURIComponent(h.location) + '" target="_blank" rel="noopener"><span aria-hidden="true">📍</span> Directions</a>' : '') +
      (h.website_url ? '<a href="' + escapeHtml(h.website_url) + '" target="_blank" rel="noopener"><span aria-hidden="true">🌐</span> Visit website</a>' : '') +
      (h.phone ? '<a href="tel:' + escapeHtml(h.phone) + '"><span aria-hidden="true">📞</span> ' + escapeHtml(h.phone) + '</a>' : '') +
      '</div>' +
      '<section class="hb-block"><h3>Description</h3>' +
      '<p class="hb-desc-text">' + escapeHtml(descFull) + '</p>' +
      (descFull ? '<a href="#" class="hb-read-more">Read more</a>' : '') +
      '</section>' +
      '<section class="hb-block hb-rating-block">' +
      '<div class="hb-rating-bubbles" aria-hidden="true"><span></span><span></span><span></span><span></span><span class="partial"></span></div>' +
      '<div class="hb-rating-meta">' +
      '<p class="hb-rating-copy"><strong>Guest rating</strong><span class="hb-rating-sub"> — based on recent stays</span></p>' +
      reviewsLinkHtml(h) +
      '</div>' +
      '</section>' +
      '<div class="hb-price-cta">' +
      '<p class="hb-from-price">From<sup>*</sup> <strong>' + escapeHtml(formatPrice(h)) + '</strong></p>' +
      '<p class="hb-price-label">Best available rate</p>' +
      '<button type="button" class="hb-btn hb-btn-primary hb-btn-block hb-select-dates" data-hotel-id="' + h.id + '" title="Select dates">Select Dates</button>' +
      '</div>' +
      '</div>' +
      '<div class="hb-detail-right">' +
      '<section class="hb-block"><h3>Amenities</h3>' +
      '<div class="hb-amenities-scroll">' + amenityHtml + '</div></section>' +
      '<section class="hb-block"><h3>Overview</h3>' +
      '<table class="hb-overview-table">' +
      '<tr><th>Check-in</th><td>' + escapeHtml(h.check_in_display || '—') + '</td></tr>' +
      '<tr><th>Check-out</th><td>' + escapeHtml(h.check_out_display || '—') + '</td></tr>' +
      '<tr><th>Currency</th><td>' + escapeHtml(currencyLabel(h.currency_code)) + '</td></tr>' +
      parkingHtml +
      '<tr><th>Pets</th><td>' + escapeHtml(h.pets_policy || '—') + '</td></tr>' +
      '</table></section>' +
      '<section class="hb-block hb-location-block">' +
      '<h3>Location and transportation</h3>' +
      '<div class="hb-loc-tabs">' +
      '<button type="button" class="hb-loc-tab is-active" data-tab="nearby">What\'s nearby</button>' +
      '<button type="button" class="hb-loc-tab" data-tab="airport">Airport info</button>' +
      '</div>' +
      '<ul class="hb-loc-list hb-loc-panel" data-panel="nearby">' + nearbyHtml + '</ul>' +
      '<ul class="hb-loc-list hb-loc-panel" data-panel="airport" hidden>' + airportHtml + '</ul>' +
      '</section>' +
      (accessible ? '<section class="hb-block hb-accordion">' +
        '<button type="button" class="hb-accordion-head" aria-expanded="false">Accessible features <span class="hb-chevron">▼</span></button>' +
        '<div class="hb-accordion-panel" hidden><p>' + escapeHtml(accessible) + '</p></div></section>' : '') +
      '<p class="hb-modal-footnote">' + escapeHtml(footnote) + '</p>' +
      '</div></div>';

    modal.hidden = false;
    document.body.classList.add('hb-modal-open');

    bindGallery(body, urls);
    bindReadMore(body);
    bindTabs(body);
    bindAccordion(body);
    bindFavorite(body.querySelector('.hb-fav'), h.id);

    body.querySelector('.hb-select-dates').addEventListener('click', function () {
      if (typeof window.HB_openDatesModal === 'function') {
        window.HB_openDatesModal(h);
      } else {
        window.location.href = window.HB_APPURL + '/rooms.php?id=' + h.id;
      }
    });
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('hb-modal-open');
  }

  document.querySelectorAll('.hb-open-hotel').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(parseInt(btn.getAttribute('data-hotel-id'), 10));
    });
  });
  modal.querySelector('.hb-modal-close').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

  var deep = parseInt(new URLSearchParams(window.location.search).get('hotel') || '0', 10);
  var openDates = new URLSearchParams(window.location.search).get('dates') === '1';
  if (deep > 0 && openDates && typeof window.HB_openDatesModal === 'function' && map[deep]) {
    window.HB_openDatesModal(map[deep]);
  } else if (deep > 0) {
    openModal(deep);
  }
})();
