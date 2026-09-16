<?php
/**
 * Add or refresh the View audit meta bullet in module AGENT_NOTES.md files.
 *
 * Scope: bespoke list + status-driven slugs that have a matching CREATE TABLE in db/.
 * Skips modules without a same-named table (calendar, org_chart, is_*, passwords, settings, etc.).
 *
 * CLI: php scripts/apply_agent_notes_view_audit_meta.php [--apply]
 * Browser: scripts/apply_agent_notes_view_audit_meta.php?apply=1 (Admin)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/apply_agent_notes_view_audit_meta.php --apply</code><br>
Browser: dry-runs or applies View audit meta bullets to module agent notes (Admin).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$boot = itm_apply_script_bootstrap('Apply View Audit Meta to Agent Notes');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');
$conn = $boot['conn'];

require_once __DIR__ . '/lib/itm_fields_missing_report.php';

$schema = itm_fields_missing_parse_database_sql_table_columns($root);
$bespokePath = $root . '/docs/list_bespoke_UI.txt';
$lines = is_readable($bespokePath) ? file($bespokePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$slugs = [];
if (is_array($lines)) {
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $slugs[] = $line;
    }
}
foreach (itm_fields_missing_status_driven_slugs() as $slug) {
    $slugs[] = $slug;
}
$slugs = array_values(array_unique($slugs));

$updated = 0;
$skipped = 0;
$missingNotes = [];

foreach ($slugs as $slug) {
    if (!isset($schema[$slug])) {
        $skipped++;
        echo "[skip-no-table] {$slug}" . $nl;
        continue;
    }

    $notesPath = $root . '/modules/' . $slug . '/AGENT_NOTES.md';
    if (!is_readable($notesPath)) {
        $missingNotes[] = $slug;
        echo "[skip-no-notes] {$slug}" . $nl;
        continue;
    }

    $content = file_get_contents($notesPath);
    if ($content === false) {
        echo "[fail-read] {$slug}" . $nl;
        continue;
    }

    if (preg_match('/\*\*View audit meta:\*\*/', $content)) {
        echo "[ok-exists] {$slug}" . $nl;
        continue;
    }

    $files = itm_fields_missing_module_file_bundle($slug, $root);
    $passes = true;
    foreach (['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'deleted_by'] as $field) {
        if (!itm_fields_missing_module_view_covers_audit_meta_field($field, $files, $slug)) {
            $passes = false;
            break;
        }
    }

    $privateData = in_array($slug, ['emails', 'notes', 'todo', 'bookmarks', 'private_contacts'], true);

    if ($passes) {
        $bundleText = file_get_contents($files['view']);
        if ($bundleText !== false && is_readable($files['view']) && strpos($bundleText, 'itm_crud_render_view_audit_meta_rows(') !== false) {
            $bullet = '- **View audit meta:** Detail view renders all six scaffold audit columns via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`).';
        } elseif ($slug === 'employees' || $slug === 'equipment' || $slug === 'tickets' || $slug === 'patches_updates') {
            $bullet = '- **View audit meta:** Detail view lists all six scaffold audit columns (`deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) with employee names and `d-m-Y - H:i:s` timestamps; list hides meta fields. Employment/equipment/ticket **status** badges are separate from row `active` (soft-delete mirror).';
        } else {
            $bullet = '- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.';
        }
    } else {
        $bullet = '- **View audit meta:** Detail view must expose all six scaffold audit columns (`deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` — required by `fields_missing.php` bespoke view gate.';
    }

    if ($privateData) {
        $bullet .= ' Row meta is for soft-delete display only; this module stays **private-data exempt** from `audit_logs` triggers.';
    }

    if (!preg_match('/## 5\. UI Behavior Requirements\r?\n/', $content)) {
        echo "[fail-no-section-5] {$slug}" . $nl;
        continue;
    }

    $newContent = preg_replace(
        '/(## 5\. UI Behavior Requirements\r?\n)/',
        '$1' . $bullet . "\n",
        $content,
        1
    );

    if ($newContent === null || $newContent === $content) {
        echo "[fail-insert] {$slug}" . $nl;
        continue;
    }

    if ($apply) {
        file_put_contents($notesPath, $newContent);
    }
    $updated++;
    echo ($apply ? '[updated]' : '[would-update]') . " {$slug}" . $nl;
}

echo $nl . "Updated: {$updated}, skipped (no table): {$skipped}" . $nl;
if ($missingNotes !== []) {
    echo 'Missing AGENT_NOTES.md: ' . implode(', ', $missingNotes) . $nl;
}

itm_apply_script_finish_hint($apply, $boot['is_cli'], $updated, $nl, 'apply_agent_notes_view_audit_meta.php');

itm_script_output_end();
exit(0);
