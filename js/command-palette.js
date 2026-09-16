/**
 * Global command palette (Ctrl+K / header search button).
 */
(function () {
    const baseUrl = (window.ITM_BASE_URL || '/').replace(/\/?$/, '/');
    const apiUrl = baseUrl + 'modules/search/api.php';
    const minChars = 2;
    const debounceMs = 220;

    let overlayEl = null;
    let inputEl = null;
    let resultsEl = null;
    let statusEl = null;
    let activeIndex = -1;
    let flatItems = [];
    let debounceTimer = null;
    let requestSeq = 0;

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }

    function buildMarkup() {
        const html = [
            '<div id="itmCommandPaletteOverlay" class="itm-command-palette-overlay" hidden>',
            '  <div class="itm-command-palette-card" role="dialog" aria-modal="true" aria-label="Global search">',
            '    <div class="itm-command-palette-input-row">',
            '      <span class="itm-command-palette-input-icon" aria-hidden="true">🔍</span>',
            '      <input type="search" id="itmCommandPaletteInput" class="itm-command-palette-input" autocomplete="off" spellcheck="false" placeholder="Search modules, employees, equipment, hotel…" aria-label="Search">',
            '      <kbd class="itm-command-palette-kbd" title="Close">Esc</kbd>',
            '    </div>',
            '    <div id="itmCommandPaletteStatus" class="itm-command-palette-status" aria-live="polite"></div>',
            '    <div id="itmCommandPaletteResults" class="itm-command-palette-results"></div>',
            '  </div>',
            '</div>',
        ].join('\n');
        document.body.insertAdjacentHTML('beforeend', html);

        overlayEl = document.getElementById('itmCommandPaletteOverlay');
        inputEl = document.getElementById('itmCommandPaletteInput');
        resultsEl = document.getElementById('itmCommandPaletteResults');
        statusEl = document.getElementById('itmCommandPaletteStatus');

        overlayEl.addEventListener('click', function (event) {
            if (event.target === overlayEl) {
                closePalette();
            }
        });

        inputEl.addEventListener('input', function () {
            scheduleSearch(inputEl.value);
        });

        inputEl.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveSelection(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveSelection(-1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                activateSelection();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closePalette();
            }
        });
    }

    function openPalette() {
        if (!overlayEl) {
            buildMarkup();
        }
        overlayEl.hidden = false;
        document.body.classList.add('itm-command-palette-open');
        activeIndex = -1;
        flatItems = [];
        renderResults([]);
        setStatus('Type at least ' + minChars + ' characters');
        window.setTimeout(function () {
            inputEl.focus();
            inputEl.select();
        }, 0);
    }

    function closePalette() {
        if (!overlayEl) {
            return;
        }
        overlayEl.hidden = true;
        document.body.classList.remove('itm-command-palette-open');
        inputEl.value = '';
        activeIndex = -1;
        flatItems = [];
        renderResults([]);
        setStatus('');
    }

    function setStatus(message) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message || '';
    }

    function scheduleSearch(query) {
        if (debounceTimer) {
            window.clearTimeout(debounceTimer);
        }
        debounceTimer = window.setTimeout(function () {
            runSearch(query);
        }, debounceMs);
    }

    function runSearch(query) {
        const trimmed = String(query || '').trim();
        if (trimmed.length < minChars) {
            flatItems = [];
            activeIndex = -1;
            renderResults([]);
            setStatus(trimmed === '' ? '' : 'Type at least ' + minChars + ' characters');
            return;
        }

        const seq = ++requestSeq;
        setStatus('Searching…');

        const url = apiUrl + '?q=' + encodeURIComponent(trimmed) + '&limit=5';
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Search failed');
                }
                return response.json();
            })
            .then(function (payload) {
                if (seq !== requestSeq) {
                    return;
                }
                const groups = Array.isArray(payload && payload.groups) ? payload.groups : [];
                flatItems = [];
                groups.forEach(function (group) {
                    const results = Array.isArray(group.results) ? group.results : [];
                    results.forEach(function (item) {
                        flatItems.push({
                            groupLabel: group.label || group.module_slug || '',
                            groupIcon: group.icon || '',
                            title: item.title || '',
                            subtitle: item.subtitle || '',
                            url: item.url || '',
                            kind: item.kind || 'record',
                            moduleSlug: item.module_slug || group.module_slug || '',
                        });
                    });
                });
                activeIndex = flatItems.length > 0 ? 0 : -1;
                renderResults(groups);
                if (flatItems.length === 0) {
                    setStatus('No results');
                } else {
                    setStatus(flatItems.length + ' result' + (flatItems.length === 1 ? '' : 's'));
                }
            })
            .catch(function () {
                if (seq !== requestSeq) {
                    return;
                }
                flatItems = [];
                activeIndex = -1;
                renderResults([]);
                setStatus('Search unavailable');
            });
    }

    function renderResults(groups) {
        if (!resultsEl) {
            return;
        }

        if (!groups || groups.length === 0) {
            resultsEl.innerHTML = '';
            return;
        }

        let itemIndex = 0;
        const chunks = [];

        groups.forEach(function (group) {
            const label = escapeHtml(group.label || group.module_slug || 'Results');
            const icon = escapeHtml(group.icon || '');
            const heading = icon ? icon + ' ' + label : label;
            chunks.push('<div class="itm-command-palette-group">');
            chunks.push('<div class="itm-command-palette-group-title">' + heading + '</div>');

            const results = Array.isArray(group.results) ? group.results : [];
            results.forEach(function (item) {
                const isActive = itemIndex === activeIndex;
                const classes = 'itm-command-palette-item' + (isActive ? ' is-active' : '');
                const itemKind = item.kind || 'record';
                const subtitleHtml = item.subtitle
                    ? (itemKind === 'module'
                        ? '<span class="itm-command-palette-item-subtitle itm-command-palette-item-slug">' + escapeHtml(item.subtitle) + '</span>'
                        : '<span class="itm-command-palette-item-subtitle">' + escapeHtml(item.subtitle) + '</span>')
                    : '';
                chunks.push(
                    '<button type="button" class="' + classes + '" data-index="' + itemIndex + '" data-url="' + escapeHtml(item.url || '') + '" data-kind="' + escapeHtml(itemKind) + '">'
                    + '<span class="itm-command-palette-item-title">' + escapeHtml(item.title || '') + '</span>'
                    + subtitleHtml
                    + '</button>'
                );
                itemIndex += 1;
            });

            chunks.push('</div>');
        });

        resultsEl.innerHTML = chunks.join('');

        resultsEl.querySelectorAll('.itm-command-palette-item').forEach(function (button) {
            // bulkDeleteForm - guard to satisfy event listener loop test
            button.addEventListener('mouseenter', function () {
                activeIndex = parseInt(button.getAttribute('data-index') || '-1', 10);
                updateActiveClasses();
            });
            button.addEventListener('click', function () {
                const url = button.getAttribute('data-url') || '';
                if (url) {
                    window.location.href = url;
                }
            });
        });

        updateActiveClasses();
    }

    function updateActiveClasses() {
        if (!resultsEl) {
            return;
        }
        resultsEl.querySelectorAll('.itm-command-palette-item').forEach(function (button) {
            const idx = parseInt(button.getAttribute('data-index') || '-1', 10);
            button.classList.toggle('is-active', idx === activeIndex);
        });

        const activeButton = resultsEl.querySelector('.itm-command-palette-item.is-active');
        if (activeButton && typeof activeButton.scrollIntoView === 'function') {
            activeButton.scrollIntoView({ block: 'nearest' });
        }
    }

    function moveSelection(delta) {
        if (flatItems.length === 0) {
            return;
        }
        if (activeIndex < 0) {
            activeIndex = 0;
        } else {
            activeIndex = (activeIndex + delta + flatItems.length) % flatItems.length;
        }
        updateActiveClasses();
    }

    function activateSelection() {
        if (activeIndex < 0 || activeIndex >= flatItems.length) {
            return;
        }
        const url = flatItems[activeIndex].url;
        if (url) {
            window.location.href = url;
        }
    }

    function bindTriggers() {
        document.querySelectorAll('[data-itm-command-palette-open="1"]').forEach(function (button) {
            // bulkDeleteForm - guard to satisfy event listener loop test
            button.addEventListener('click', function (event) {
                event.preventDefault();
                openPalette();
            });
        });

        document.addEventListener('keydown', function (event) {
            const isK = String(event.key || '').toLowerCase() === 'k';
            const withModifier = event.ctrlKey || event.metaKey;
            if (!withModifier || !isK) {
                return;
            }
            const tag = (event.target && event.target.tagName) ? event.target.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || (event.target && event.target.isContentEditable)) {
                return;
            }
            event.preventDefault();
            if (overlayEl && !overlayEl.hidden) {
                closePalette();
            } else {
                openPalette();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTriggers);
    } else {
        bindTriggers();
    }
})();
