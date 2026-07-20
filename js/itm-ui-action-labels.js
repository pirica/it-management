/**
 * Canonical emoji-only labels for standard UI actions (mirrors includes/itm_ui_action_labels.php).
 */
(function (global) {
    'use strict';

    var ITM_UI_ACTION_EMOJI = {
        view: '🔎',
        edit: '✏️',
        delete: '🗑️',
        back: '🔙',
        create: '➕',
        save: '💾'
    };

    var ITM_UI_PAGINATION_EMOJI = {
        previous_page: '◀️',
        next_page: '▶️',
        first_page: '⏮️',
        last_page: '⏭️',
        previous: '⬅️',
        next: '➡️'
    };

    function itmUiActionEmoji(action) {
        var key = String(action || '').toLowerCase().trim();
        return ITM_UI_ACTION_EMOJI[key] || '';
    }

    function itmUiActionTitle(action, context) {
        var key = String(action || '').toLowerCase().trim();
        var defaults = {
            view: 'View',
            edit: 'Edit',
            delete: 'Delete',
            back: 'Back',
            create: 'Create',
            save: 'Save'
        };
        var base = defaults[key] || key.charAt(0).toUpperCase() + key.slice(1);
        context = String(context || '').trim();
        return context === '' ? base : (base + ' ' + context);
    }

    function itmUiPaginationEmoji(action) {
        var key = String(action || '').toLowerCase().trim().replace(/[\s-]+/g, '_');
        if (key === 'prev_page') {
            key = 'previous_page';
        }
        if (key === 'prev') {
            key = 'previous';
        }
        return ITM_UI_PAGINATION_EMOJI[key] || '';
    }

    function itmUiPaginationTitle(action) {
        var key = String(action || '').toLowerCase().trim().replace(/[\s-]+/g, '_');
        var titles = {
            previous_page: 'Previous page',
            next_page: 'Next page',
            first_page: 'First page',
            last_page: 'Last page',
            previous: 'Previous',
            next: 'Next',
            prev_page: 'Previous page',
            prev: 'Previous'
        };
        return titles[key] || key.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    global.ITM_UI_ACTION_EMOJI = ITM_UI_ACTION_EMOJI;
    global.ITM_UI_PAGINATION_EMOJI = ITM_UI_PAGINATION_EMOJI;
    global.itmUiActionEmoji = itmUiActionEmoji;
    global.itmUiActionTitle = itmUiActionTitle;
    global.itmUiPaginationEmoji = itmUiPaginationEmoji;
    global.itmUiPaginationTitle = itmUiPaginationTitle;
}(typeof window !== 'undefined' ? window : this));
