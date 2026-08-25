(function () {
    'use strict';

    var mount = document.getElementById('qr-preview-mount');
    if (!mount || !window.QRCode) {
        return;
    }

    var form = document.getElementById('qr-wizard-form');
    var csrf = (window.ITM_CSRF_TOKEN || window.CSRF_TOKEN || '');
    var hintEl = document.getElementById('qr-preview-hint');
    var pngBtn = document.getElementById('qr-download-png');
    var jpgBtn = document.getElementById('qr-download-jpg');
    var svgBtn = document.getElementById('qr-download-svg');

    function getCorrectLevel(level) {
        var map = window.QRCode.CorrectLevel || {};
        var key = String(level || 'H').toUpperCase();
        if (map[key]) return map[key];
        return map.H || 2;
    }

    function formVal(f, name) {
        if (!f) return '';
        var el = f.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    }

    function getEncodingMode() {
        var modeEl = document.querySelector('[name="encoding_mode"]');
        return modeEl ? String(modeEl.value || 'dynamic') : 'static';
    }

    function escapeVcardValue(value) {
        return String(value || '').replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n');
    }

    function normalizeHttpUrl(url) {
        url = String(url || '').trim();
        if (!url) return '';
        return /^https?:\/\//i.test(url) ? url : 'https://' + url;
    }

    function buildStaticPreview(type, f) {
        if (!f || !type) return '';
        if (type === 'website' || type === 'facebook' || type === 'instagram') {
            return normalizeHttpUrl(formVal(f, 'payload[url]'));
        }
        if (type === 'text') return formVal(f, 'payload[text]');
        if (type === 'phone') {
            var phoneNum = formVal(f, 'payload[number]').replace(/[^\d+]/g, '');
            return phoneNum ? 'tel:' + phoneNum : '';
        }
        if (type === 'sms') {
            var smsNum = formVal(f, 'payload[number]').replace(/[^\d+]/g, '');
            if (!smsNum) return '';
            var smsMsg = formVal(f, 'payload[message]');
            return 'sms:' + smsNum + (smsMsg ? '?body=' + encodeURIComponent(smsMsg) : '');
        }
        if (type === 'whatsapp') {
            var waNum = formVal(f, 'payload[number]').replace(/\D/g, '');
            if (!waNum) return '';
            var waMsg = formVal(f, 'payload[message]');
            return 'https://wa.me/' + waNum + (waMsg ? '?text=' + encodeURIComponent(waMsg) : '');
        }
        if (type === 'wifi') {
            var ssid = formVal(f, 'payload[ssid]').trim();
            if (!ssid) return '';
            var enc = String(formVal(f, 'payload[encryption]') || 'WPA').toUpperCase();
            if (enc === 'NOPASS' || enc === 'NONE') enc = 'nopass';
            var pass = formVal(f, 'payload[password]');
            var hiddenEl = f.querySelector('[name="payload[hidden]"]');
            var hidden = hiddenEl && hiddenEl.checked ? 'true' : 'false';
            return 'WIFI:T:' + enc + ';S:' + ssid + ';P:' + pass + ';H:' + hidden + ';;';
        }
        if (type === 'email') {
            var to = formVal(f, 'payload[to]').trim();
            if (!to) return '';
            var q = [];
            var subject = formVal(f, 'payload[subject]');
            var body = formVal(f, 'payload[body]');
            if (subject) q.push('subject=' + encodeURIComponent(subject));
            if (body) q.push('body=' + encodeURIComponent(body));
            return 'mailto:' + to + (q.length ? '?' + q.join('&') : '');
        }
        if (type === 'vcard') {
            var lines = ['BEGIN:VCARD', 'VERSION:3.0'];
            var first = formVal(f, 'payload[first_name]');
            var last = formVal(f, 'payload[last_name]');
            var fn = (first + ' ' + last).trim();
            if (fn) lines.push('FN:' + escapeVcardValue(fn));
            if (first || last) {
                lines.push('N:' + escapeVcardValue(last) + ';' + escapeVcardValue(first) + ';;;');
            }
            var org = formVal(f, 'payload[organization]');
            if (org) lines.push('ORG:' + escapeVcardValue(org));
            var job = formVal(f, 'payload[title]');
            if (job) lines.push('TITLE:' + escapeVcardValue(job));
            var tel = formVal(f, 'payload[phone]');
            if (tel) lines.push('TEL:' + escapeVcardValue(tel));
            var mail = formVal(f, 'payload[email]');
            if (mail) lines.push('EMAIL:' + escapeVcardValue(mail));
            var site = formVal(f, 'payload[website]');
            if (site) lines.push('URL:' + escapeVcardValue(site));
            var addr = formVal(f, 'payload[address]');
            if (addr) lines.push('ADR:;;' + escapeVcardValue(addr) + ';;;;');
            lines.push('END:VCARD');
            return lines.length > 2 ? lines.join('\n') : '';
        }
        return '';
    }

    function resolveLogoUrl(path) {
        path = String(path || '').trim();
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        var base = String(window.ITM_BASE_URL || '/').replace(/\/?$/, '/');
        return base + 'modules/explorer/file.php?path=' + encodeURIComponent(path);
    }

    function readDesignFieldsFromForm() {
        return {
            size: parseInt(formVal(form, 'design[size]') || '256', 10),
            colorDark: formVal(form, 'design[colorDark]') || '#000000',
            colorLight: formVal(form, 'design[colorLight]') || '#ffffff',
            correctLevel: formVal(form, 'design[correctLevel]') || 'H',
            logo_path: document.getElementById('qr-logo-path') ? document.getElementById('qr-logo-path').value : ''
        };
    }

    function applyDesignFieldsToForm(design) {
        if (!form || !design) return;
        var sizeEl = form.querySelector('[name="design[size]"]');
        var darkEl = form.querySelector('[name="design[colorDark]"]');
        var lightEl = form.querySelector('[name="design[colorLight]"]');
        var levelEl = form.querySelector('[name="design[correctLevel]"]');
        var logoEl = document.getElementById('qr-logo-path');
        if (sizeEl && design.size) sizeEl.value = String(design.size);
        if (darkEl && design.colorDark) darkEl.value = design.colorDark;
        if (lightEl && design.colorLight) lightEl.value = design.colorLight;
        if (levelEl && design.correctLevel) levelEl.value = design.correctLevel;
        if (logoEl) logoEl.value = design.logo_path || '';
    }

    function templateStorageKey() {
        if (!form) return 'itm_qr_design_templates';
        return 'itm_qr_design_templates_' + (form.getAttribute('data-qr-template-scope') || 'default');
    }

    function loadDesignTemplates() {
        try {
            var raw = window.localStorage.getItem(templateStorageKey());
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function saveDesignTemplates(list) {
        try {
            window.localStorage.setItem(templateStorageKey(), JSON.stringify(list));
        } catch (e) { /* ignore quota */ }
    }

    function populateTemplateSelect() {
        var select = document.getElementById('qr-template-select');
        if (!select) return;
        var current = select.value;
        var templates = loadDesignTemplates();
        select.innerHTML = '<option value="">— Select saved template —</option>';
        templates.forEach(function (tpl) {
            var opt = document.createElement('option');
            opt.value = tpl.id;
            opt.textContent = tpl.name;
            select.appendChild(opt);
        });
        if (current) {
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].value === current) {
                    select.value = current;
                    break;
                }
            }
        }
    }

    function applySelectedTemplate() {
        var select = document.getElementById('qr-template-select');
        if (!select || !select.value) return false;
        var templates = loadDesignTemplates();
        var tpl = templates.filter(function (t) { return t.id === select.value; })[0];
        if (!tpl || !tpl.design) return false;
        applyDesignFieldsToForm(tpl.design);
        drawQr();
        return true;
    }

    function saveCurrentDesignTemplate() {
        var name = window.prompt('Template name');
        if (!name) return;
        name = String(name).trim();
        if (!name) return;
        var templates = loadDesignTemplates();
        if (templates.some(function (t) { return t.name.toLowerCase() === name.toLowerCase(); })) {
            window.alert('A template with that name already exists.');
            return;
        }
        var tpl = {
            id: 'tpl_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
            name: name,
            design: readDesignFieldsFromForm()
        };
        templates.push(tpl);
        saveDesignTemplates(templates);
        populateTemplateSelect();
        var select = document.getElementById('qr-template-select');
        if (select) select.value = tpl.id;
    }

    function readDesign() {
        if (mount.dataset.qrText) {
            return {
                text: mount.dataset.qrText || '',
                size: parseInt(mount.dataset.size || '256', 10),
                colorDark: mount.dataset.dark || '#000000',
                colorLight: mount.dataset.light || '#ffffff',
                correctLevel: mount.dataset.level || 'H',
                logo: resolveLogoUrl(mount.dataset.logo || '')
            };
        }
        var sizeEl = document.querySelector('[name="design[size]"]');
        var darkEl = document.querySelector('[name="design[colorDark]"]');
        var lightEl = document.querySelector('[name="design[colorLight]"]');
        var levelEl = document.querySelector('[name="design[correctLevel]"]');
        var typeEl = document.querySelector('[name="type_slug"]');
        var type = typeEl ? typeEl.value : '';
        var mode = getEncodingMode();
        var text = '';
        if (mode === 'static') {
            text = buildStaticPreview(type, form);
        }
        return {
            text: text,
            size: parseInt(sizeEl && sizeEl.value ? sizeEl.value : '256', 10),
            colorDark: darkEl ? darkEl.value : '#000000',
            colorLight: lightEl ? lightEl.value : '#ffffff',
            correctLevel: levelEl ? levelEl.value : 'H',
            logo: resolveLogoUrl(document.getElementById('qr-logo-path') ? document.getElementById('qr-logo-path').value : '')
        };
    }

    function setDownloadButtonsEnabled(enabled) {
        [pngBtn, jpgBtn, svgBtn].forEach(function (btn) {
            if (!btn) return;
            btn.disabled = !enabled;
            btn.style.opacity = enabled ? '' : '0.5';
            btn.style.cursor = enabled ? '' : 'not-allowed';
        });
    }

    function updatePreviewHint() {
        if (!hintEl || mount.dataset.qrText) return;
        if (getEncodingMode() === 'dynamic') {
            hintEl.textContent = 'Dynamic QR — save to generate the public redirect URL.';
        } else {
            hintEl.textContent = 'Preview updates as you type (static types).';
        }
    }

    function drawQr() {
        var cfg = readDesign();
        updatePreviewHint();
        if (!cfg.text) {
            var mode = mount.dataset.qrText ? '' : getEncodingMode();
            var msg = mode === 'dynamic'
                ? 'Enter content to preview (static), or save to get dynamic URL.'
                : 'Enter content above to preview the QR code.';
            mount.innerHTML = '<p style="color:var(--text-secondary);">' + msg + '</p>';
            setDownloadButtonsEnabled(false);
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
            setTimeout(function () {
                applyLogoOverlay(cfg);
                setDownloadButtonsEnabled(!!getCanvas());
            }, 50);
        } catch (e) {
            mount.textContent = 'Preview unavailable.';
            setDownloadButtonsEnabled(false);
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

    function showWizardStep(step) {
        var contentPane = document.getElementById('qr-wizard-step-content');
        var designPane = document.getElementById('qr-wizard-step-design');
        if (!contentPane || !designPane) return;
        var isDesign = step === 'design';
        contentPane.hidden = isDesign;
        designPane.hidden = !isDesign;
        document.querySelectorAll('.qr-wizard-step-btn').forEach(function (btn) {
            var active = btn.getAttribute('data-qr-step') === step;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (isDesign) {
            drawQr();
        }
    }

    function validateContentStep() {
        var titleEl = document.getElementById('qr-title');
        if (titleEl && !titleEl.value.trim()) {
            titleEl.focus();
            titleEl.reportValidity();
            return false;
        }
        return true;
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
            if (t.id === 'qr-template-select') {
                applySelectedTemplate();
                return;
            }
            if (t.id === 'qr-encoding-mode' || (t.classList && (t.classList.contains('itm-qr-field') || t.classList.contains('itm-qr-design')))) {
                drawQr();
            }
            if (t.classList && t.classList.contains('itm-qr-upload')) {
                handleQrUpload(t);
            }
        });

        form.addEventListener('click', function (ev) {
            var stepBtn = ev.target && ev.target.closest ? ev.target.closest('.qr-wizard-step-btn') : null;
            if (stepBtn) {
                ev.preventDefault();
                var step = stepBtn.getAttribute('data-qr-step') || 'content';
                if (step === 'design' && !validateContentStep()) return;
                showWizardStep(step);
                return;
            }
            if (ev.target && ev.target.id === 'qr-wizard-next') {
                ev.preventDefault();
                if (!validateContentStep()) return;
                showWizardStep('design');
                return;
            }
            if (ev.target && ev.target.id === 'qr-wizard-back') {
                ev.preventDefault();
                showWizardStep('content');
                return;
            }
            if (ev.target && ev.target.id === 'qr-template-refresh') {
                ev.preventDefault();
                if (!applySelectedTemplate()) {
                    drawQr();
                }
                return;
            }
            if (ev.target && ev.target.id === 'qr-template-save') {
                ev.preventDefault();
                saveCurrentDesignTemplate();
            }
        });

        populateTemplateSelect();
    }

    var logoUpload = document.getElementById('qr-logo-upload');
    if (logoUpload) {
        logoUpload.addEventListener('change', function () {
            handleQrUpload(logoUpload);
        });
    }

    if (pngBtn) pngBtn.addEventListener('click', downloadPng);
    if (jpgBtn) jpgBtn.addEventListener('click', downloadJpg);
    if (svgBtn) svgBtn.addEventListener('click', downloadSvg);

    drawQr();
})();
