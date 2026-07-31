(function (global) {
  function bindGallery(root, urls) {
    if (!root || !urls || !urls.length) {
      return;
    }
    var idx = 0;
    var el = root.querySelector('.hb-gallery');
    var counter = root.querySelector('.hb-gallery-counter');
    var wrap = root.querySelector('.hb-gallery-wrap') || root;
    var prevBtn = root.querySelector('.hb-gallery-prev');
    var nextBtn = root.querySelector('.hb-gallery-next');
    var multi = urls.length > 1;
    wrap.classList.toggle('is-single', !multi);
    function show(i) {
      idx = (i + urls.length) % urls.length;
      if (el) {
        el.style.backgroundImage = "url('" + String(urls[idx]).replace(/'/g, '%27') + "')";
      }
      if (counter) {
        counter.textContent = (idx + 1) + ' / ' + urls.length;
      }
    }
    function onNavClick(e, delta) {
      e.preventDefault();
      e.stopPropagation();
      if (multi) {
        show(idx + delta);
      }
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function (e) {
        onNavClick(e, -1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        onNavClick(e, 1);
      });
    }
    show(0);
  }

  function initGalleries(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-gallery-urls]').forEach(function (node) {
      var raw = node.getAttribute('data-gallery-urls');
      if (!raw) {
        return;
      }
      try {
        var urls = JSON.parse(raw);
        if (Array.isArray(urls) && urls.length) {
          bindGallery(node, urls);
        }
      } catch (err) {
        /* ignore malformed JSON */
      }
    });
  }

  global.HB_bindGallery = bindGallery;
  global.HB_initGalleries = initGalleries;
})(window);
