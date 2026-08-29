/**
 * Amenity icon slugs → booking/images/amenities/*.svg (Lucide, see images/amenities/ATTRIBUTION.md).
 */
(function (global) {
  function amenityIconSlug(name) {
    var n = String(name || '').toLowerCase();
    if (n.indexOf('wifi') >= 0) return 'wifi';
    if (n.indexOf('pool') >= 0) return 'pool';
    if (n.indexOf('fitness') >= 0 || n.indexOf('gym') >= 0) return 'fitness';
    if (n.indexOf('spa') >= 0) return 'spa';
    if (n.indexOf('parking') >= 0) return 'parking';
    if (n.indexOf('restaurant') >= 0 || n.indexOf('dining') >= 0) return 'restaurant';
    return 'default';
  }

  function amenityIconMarkup(name, iconSlug) {
    var base = global.HB_APPURL || '';
    var slug = amenityIconSlug(name);
    if (iconSlug && String(iconSlug).trim() !== '') {
      slug = amenityIconSlug(String(iconSlug).trim());
    }
    var src = base + '/images/amenities/' + slug + '.svg';
    return '<img class="hb-amenity-icon-img" src="' + src.replace(/"/g, '&quot;') + '" alt="" width="28" height="28" loading="lazy" decoding="async" aria-hidden="true">';
  }

  global.HB_amenityIconSlug = amenityIconSlug;
  global.HB_amenityIconMarkup = amenityIconMarkup;
})(typeof window !== 'undefined' ? window : this);
