(function () {
    var mount = document.getElementById('qr-preview-mount');
    if (!mount || !window.QRCode) {
        return;
    }

    var form = document.getElementById('qr-wizard-form');
    var csrf = (window.ITM_CSRF_TOKEN || window.CSRF_TOKEN || '');

    function getCorrectLevel(level) {
        var map = window.QRCode.CorrectLevel || {};
        var key = String(level || 'H').toUpperCase();
        if (map[key]) return map[key];
        return map.H || 2;
    }

    function readDesign() {
        if (mount.dataset.qrText) {
            return {
                text: mount.dataset.qrText || '',
                size: parseInt(mount.dataset.size || '256', 10),
                colorDark: mount.dataset.dark || '#000000',
                colorLight: mount.dataset.light || '#ffffff',
                correctLevel: mount.dataset.level || 'H',
                logo: mount.dataset.logo || ''
            };
        }
        var sizeEl = document.querySelector('[name="design[size]"]');
        var darkEl = document.querySelector('[name="design[colorDark]"]');
        var lightEl = document.querySelector('[name="design[colorLight]"]');
        var levelEl = document.querySelector('[name="design[correctLevel]"]');
        var modeEl = document.querySelector('[name="encoding_mode"]');
        var typeEl = document.querySelector('[name="type_slug"]');
        var text = '';
        if (modeEl && modeEl.value === 'static') {
            text = buildStaticPreview(typeEl ? typeEl.value : '', form);
        }
        return {
            text: text,
            size: parseInt(sizeEl && sizeEl.value ? sizeEl.value : '256', 10),
            colorDark: darkEl ? darkEl.value : '#000000',
            colorLight: lightEl ? lightEl.value : '#ffffff',
            correctLevel: levelEl ? levelEl.value : 'H',
            logo: document.getElementById('qr-logo-path') ? document.getElementById('qr-logo-path').value : ''
        };
    }

    function buildStaticPreview(type, f) {
        if (!f || !type) return '';
        function val(name) {
            var el = f.querySelector('[name="' + name + '"]');
            return el ? el.value : '';
        }
        if (type === 'website') {
            var u = val('payload[url]');
            if (!u) return '';
            return /^https?:\/\//i.test(u) ? u : 'https://' + u;
        }
        if (type === 'text') return val('payload[text]');
        if (type === 'phone') return 'tel:' + val('payload[number]').replace(/[^\d+]/g, '');
        if (type === 'whatsapp') {
            var n = val('payload[number]').replace(/\D/g, '');
            var m = val('payload[message]');
            return n ? 'https://wa.me/' + n + (m ? '?text=' + encodeURIComponent(m) : '') : '';
        }
        return '';
    }

    function drawQr() {
        var cfg = readDesign();
        if (!cfg.text) {
            mount.innerHTML = '<p style="color:var(--text-secondary);">Enter content to preview (static), or save to get dynamic URL.</p>';
            return;
        }
        mount.innerHTML = '';
        try {
            new QRCode(mount, {
                text: cfg.text,
                width: cfg.size,
                height: cfg.size,
                colorDark: cfg.colorDark,
                colorLight: cfg.colorLight,
                correctLevel: getCorrectLevel(cfg.correctLevel)
            });
            setTimeout(function () { applyLogoOverlay(cfg); }, 50);
        } catch (e) {
            mount.textContent = 'Preview unavailable.';
        }
    }

    function applyLogoOverlay(cfg) {
        if (!cfg.logo) return;
        var canvas = mount.querySelector('canvas');
        if (!canvas) return;
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            var ctx = canvas.getContext('2d');
            var s = Math.floor(canvas.width * 0.22);
            var x = (canvas.width - s) / 2;
            var y = (canvas.height - s) / 2;
            ctx.fillStyle = cfg.colorLight;
            ctx.fillRect(x - 2, y - 2, s + 4, s + 4);
            ctx.drawImage(img, x, y, s, s);
        };
        img.src = cfg.logo;
    }

    function getCanvas() {
        return mount.querySelector('canvas');
    }

    function downloadPng() {
        var c = getCanvas();
        if (!c) return;
        var a = document.createElement('a');
        a.href = c.toDataURL('image/png');
        a.download = 'qr-code.png';
        a.click();
    }

    function downloadJpg() {
        var c = getCanvas();
        if (!c) return;
        var a = document.createElement('a');
        a.href = c.toDataURL('image/jpeg', 0.92);
        a.download = 'qr-code.jpg';
        a.click();
    }

    function downloadSvg() {
        var cfg = readDesign();
        if (!cfg.text) return;
        var svg = mount.querySelector('svg');
        if (svg) {
            var blob = new Blob([svg.outerHTML], { type: 'image/svg+xml' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'qr-code.svg';
            a.click();
            return;
        }
        var c = getCanvas();
        if (!c) return;
        downloadPng();
    }

    function handleQrUpload(input) {
        if (!input.files || !input.files[0]) return;
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('qr_action', 'upload_asset');
        fd.append('file', input.files[0]);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    if (input.classList.contains('itm-qr-upload')) {
                        alert((data && data.error) || 'Upload failed');
                    }
                    return;
                }
                if (input.id === 'qr-logo-upload') {
                    var pathInput = document.getElementById('qr-logo-path');
                    if (pathInput) pathInput.value = data.path;
                    drawQr();
                    return;
                }
                var target = input.getAttribute('data-target');
                if (target) {
                    var hidden = document.querySelector('[name="' + target + '"]');
                    if (hidden) hidden.value = data.path;
                }
                var label = document.getElementById('qr-file-label');
                if (label) label.textContent = data.path;
            });
    }

    if (form) {
        form.addEventListener('input', function (ev) {
            var t = ev.target;
            if (t && t.classList && (t.classList.contains('itm-qr-field') || t.classList.contains('itm-qr-design'))) {
                drawQr();
            }
        });
        form.addEventListener('change', function (ev) {
            var t = ev.target;
            if (!t) return;
            if (t.id === 'qr-encoding-mode' || (t.classList && (t.classList.contains('itm-qr-field') || t.classList.contains('itm-qr-design')))) {
                drawQr();
            }
            if (t.classList && t.classList.contains('itm-qr-upload')) {
                handleQrUpload(t);
            }
        });
    }

    var logoUpload = document.getElementById('qr-logo-upload');
    if (logoUpload) {
        logoUpload.addEventListener('change', function () {
            handleQrUpload(logoUpload);
        });
    }

    var pngBtn = document.getElementById('qr-download-png');
    var jpgBtn = document.getElementById('qr-download-jpg');
    var svgBtn = document.getElementById('qr-download-svg');
    if (pngBtn) pngBtn.addEventListener('click', downloadPng);
    if (jpgBtn) jpgBtn.addEventListener('click', downloadJpg);
    if (svgBtn) svgBtn.addEventListener('click', downloadSvg);

    drawQr();
})();
