(function () {
  var modal = document.getElementById('hb-detail-modal');
  if (!modal) return;
  var body = document.getElementById('hb-modal-body');
  var hotels = window.HB_HOTELS || [];
  var map = {};
  hotels.forEach(function (h) { map[h.id] = h; });

  function openModal(hotelId) {
    var h = map[hotelId];
    if (!h || !body) return;
    var photos = h.photos || [];
    var img = photos[0] ? (window.HB_APPURL + '/../images/hotel_booking/' + h.company_id + '/hotels/' + h.id + '/' + photos[0].stored_filename) : '';
    body.innerHTML =
      '<div class="hb-modal-layout">' +
      '<div><div class="hb-gallery" style="background-image:url(\'' + img + '\')"><span class="hb-gallery-nav">1/' + Math.max(photos.length, 1) + '</span></div>' +
      '<h2>' + escapeHtml(h.name) + '</h2>' +
      '<p>' + escapeHtml(h.description || '') + '</p>' +
      '<p><a href="https://maps.google.com/?q=' + encodeURIComponent(h.location || '') + '" target="_blank" rel="noopener">Directions</a>' +
      (h.website_url ? ' · <a href="' + escapeHtml(h.website_url) + '" target="_blank" rel="noopener">Website</a>' : '') +
      (h.phone ? ' · <a href="tel:' + escapeHtml(h.phone) + '">' + escapeHtml(h.phone) + '</a>' : '') + '</p>' +
      '<button type="button" class="hb-btn hb-btn-primary hb-select-dates" data-hotel-id="' + h.id + '" title="Select dates">Select dates</button></div>' +
      '<div class="hb-overview"><table>' +
      '<tr><td>Check-in</td><td>' + escapeHtml((h.check_in_time || '').substring(0, 5)) + '</td></tr>' +
      '<tr><td>Check-out</td><td>' + escapeHtml((h.check_out_time || '').substring(0, 5)) + '</td></tr>' +
      '<tr><td>Currency</td><td>' + escapeHtml(h.currency_code || 'EUR') + '</td></tr>' +
      '<tr><td>Parking</td><td>' + escapeHtml(h.parking_info || '—') + '</td></tr>' +
      '<tr><td>Pets</td><td>' + escapeHtml(h.pets_policy || '—') + '</td></tr>' +
      '</table></div></div>';
    modal.hidden = false;
    body.querySelector('.hb-select-dates').addEventListener('click', function () {
      window.location.href = window.HB_APPURL + '/rooms.php?id=' + h.id;
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  document.querySelectorAll('.hb-open-hotel').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(parseInt(btn.getAttribute('data-hotel-id'), 10));
    });
  });
  modal.querySelector('.hb-modal-close').addEventListener('click', function () {
    modal.hidden = true;
  });
  var deep = parseInt(new URLSearchParams(window.location.search).get('hotel') || '0', 10);
  if (deep > 0) openModal(deep);
})();
