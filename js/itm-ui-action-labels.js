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

    global.ITM_UI_ACTION_EMOJI = ITM_UI_ACTION_EMOJI;
    global.itmUiActionEmoji = itmUiActionEmoji;
    global.itmUiActionTitle = itmUiActionTitle;
}(typeof window !== 'undefined' ? window : this));
