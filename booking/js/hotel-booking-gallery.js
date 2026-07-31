(function () {
  function escapeUrlForCss(url) {
    return String(url == null ? '' : url).replace(/'/g, '%27');
  }

  function formatCounter(idx, total) {
    return (idx + 1) + ' / ' + total;
  }

  function normalizeUrls(urls) {
    return (urls || []).filter(function (u) {
      return String(u || '').trim() !== '';
    });
  }

  window.HB_galleryMarkup = function (urls) {
    urls = normalizeUrls(urls);
    if (!urls.length) {
      var base = window.HB_APPURL || '';
      urls = [base + '/images/image_2.jpg'];
    }
    var singleClass = urls.length <= 1 ? ' hb-gallery-wrap--single' : '';
    var first = escapeUrlForCss(urls[0]);
    return (
      '<div class="hb-gallery-wrap' + singleClass + '">' +
      '<button type="button" class="hb-gallery-prev" title="Previous image" aria-label="Previous image"><span aria-hidden="true">‹</span></button>' +
      '<div class="hb-gallery" tabindex="0" role="img" aria-label="Photo gallery"></div>' +
      '<button type="button" class="hb-gallery-next" title="Next image" aria-label="Next image"><span aria-hidden="true">›</span></button>' +
      '<span class="hb-gallery-counter">' + formatCounter(0, urls.length) + '</span>' +
      '</div>'
    );
  };

  window.HB_bindGallery = function (root, urls) {
    if (!root) {
      return;
    }
    urls = normalizeUrls(urls);
    if (!urls.length) {
      return;
    }
    var wrap = root.classList && root.classList.contains('hb-gallery-wrap') ? root : root.querySelector('.hb-gallery-wrap');
    if (!wrap || wrap.getAttribute('data-hb-gallery-bound') === '1') {
      return;
    }
    wrap.setAttribute('data-hb-gallery-bound', '1');

    var idx = 0;
    var el = wrap.querySelector('.hb-gallery');
    var counter = wrap.querySelector('.hb-gallery-counter');
    var prevBtn = wrap.querySelector('.hb-gallery-prev');
    var nextBtn = wrap.querySelector('.hb-gallery-next');
    var single = urls.length <= 1;
    wrap.classList.toggle('hb-gallery-wrap--single', single);

    function show(i) {
      idx = (i + urls.length) % urls.length;
      if (el) {
        el.style.backgroundImage = "url('" + escapeUrlForCss(urls[idx]) + "')";
      }
      if (counter) {
        counter.textContent = formatCounter(idx, urls.length);
      }
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        show(idx - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        show(idx + 1);
      });
    }

    function onKeydown(e) {
      if (single) {
        return;
      }
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        show(idx - 1);
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        show(idx + 1);
      }
    }

    wrap.addEventListener('keydown', onKeydown);
    if (el) {
      el.addEventListener('keydown', onKeydown);
    }

    show(0);
  };

  window.HB_initGalleries = function (root) {
    root = root || document;
    root.querySelectorAll('.hb-gallery-wrap[data-hb-gallery-urls]').forEach(function (wrap) {
      if (wrap.getAttribute('data-hb-gallery-bound') === '1') {
        return;
      }
      var raw = wrap.getAttribute('data-hb-gallery-urls');
      if (!raw) {
        return;
      }
      try {
        var urls = JSON.parse(raw);
        window.HB_bindGallery(wrap, urls);
      } catch (err) {
        /* ignore malformed JSON */
      }
    });
  };
})();
