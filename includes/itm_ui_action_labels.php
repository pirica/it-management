<?php
/**
 * Canonical emoji-only labels for the six standard UI actions.
 *
 * Why: Visible text on action controls must be emoji-only (NO MIXED emoji+word).
 * Full phrases belong in title/aria-label via itm_ui_action_title().
 */

if (!function_exists('itm_ui_action_emoji_map')) {
    /**
     * @return array<string, string>
     */
    function itm_ui_action_emoji_map(): array
    {
        return [
            'view' => '🔎',
            'edit' => '✏️',
            'delete' => '🗑️',
            'back' => '🔙',
            'create' => '➕',
            'save' => '💾',
        ];
    }
}

if (!function_exists('itm_ui_action_emoji')) {
    function itm_ui_action_emoji(string $action): string
    {
        $key = strtolower(trim($action));
        $map = itm_ui_action_emoji_map();
        return $map[$key] ?? '';
    }
}

if (!function_exists('itm_ui_action_title')) {
    function itm_ui_action_title(string $action, string $context = ''): string
    {
        $key = strtolower(trim($action));
        $context = trim($context);

        $defaults = [
            'view' => 'View',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'back' => 'Back',
            'create' => 'Create',
            'save' => 'Save',
        ];

        $base = $defaults[$key] ?? ucfirst($key);
        if ($context === '') {
            return $base;
        }

        return $base . ' ' . $context;
    }
}

if (!function_exists('itm_ui_action_no_mixed_patterns')) {
    /**
     * Regex patterns that must never appear in source (emoji immediately followed by action word).
     *
     * @return array<string, string> pattern => human label
     */
    function itm_ui_action_no_mixed_patterns(): array
    {
        return [
            'save_mixed' => '/💾\s*Save/u',
            'back_mixed' => '/🔙\s*Back/u',
            'cancel_mixed' => '/🔙\s*Cancel/u',
            'edit_mixed' => '/✏️\s*Edit/u',
            'delete_mixed' => '/🗑️\s*Delete/u',
            'create_mixed' => '/➕\s*(Create|New|Add)/u',
            'view_mixed' => '/🔎\s*View/u',
        ];
    }
}

if (!function_exists('itm_ui_action_known_literal_violations')) {
    /**
     * @return array<int, string>
     */
    function itm_ui_action_known_literal_violations(): array
    {
        return [
            'View Ticket Details',
            'Edit Ticket',
            'New Equipment',
            'Create IDF',
            'Edit IDF',
            'View Employee System Access',
        ];
    }
}
