<?php
/**
 * Canonical QA runner tier lists (bespoke smoke + skip clear).
 *
 * Why: Runners and build-report markdown must cite the same module sets so Tier D
 * Pass/N/A notes (e.g. companies) are explainable without reading runner source.
 */
declare(strict_types=1);

/**
 * Tier D bespoke modules — index navigation smoke only (list/search/sort).
 *
 * @return list<string>
 */
function mbqa_runner_bespoke_smoke_modules(): array
{
    return [
        'budget_report',
        'expiring',
        'rack_planner',
        'floor_plans',
        'org_chart',
        'companies',
    ];
}

/**
 * Tables never FK-aware cleared during QA (shared auth / tenant root).
 *
 * @return list<string>
 */
function mbqa_runner_skip_clear_modules(): array
{
    return [
        'companies',
        'users',
    ];
}

/**
 * Markdown block for build-report summary (pipe-safe, no HTML entities).
 */
function mbqar_runner_tier_reference_markdown(): string
{
    $bespoke = mbqa_runner_bespoke_smoke_modules();
    $skipClear = mbqa_runner_skip_clear_modules();

    $md = "### QA runner tier reference\n\n";
    $md .= "Modules in **`\$bespokeSmoke` (Tier D)** run navigation smoke only: ";
    $md .= "`list`, `search`, and `sort` on the index; other steps are recorded as Pass with notes ";
    $md .= "`N/A smoke`, `Skip (bespoke smoke)`, or `N/A`.\n\n";
    $md .= "| Runner variable | Modules |\n|---|---|\n";
    $md .= '| `$bespokeSmoke` | `' . implode('`, `', $bespoke) . "` |\n";
    $md .= '| `$skipClear` | `' . implode('`, `', $skipClear) . "` |\n\n";
    $md .= "**`\$skipClear`:** tenant FK-aware clear is never run on these tables (shared auth). ";
    $md .= "Tier D modules also skip the start-of-module clear step with note `Skip (bespoke smoke)`.\n\n";

    return $md;
}

/**
 * Browser HTML reference block for build-report form and help pages.
 */
function mbqar_echo_runner_tier_reference_html(): void
{
    $bespoke = mbqa_runner_bespoke_smoke_modules();
    $skipClear = mbqa_runner_skip_clear_modules();

    echo '<section style="margin-top:24px;font-size:0.92rem;line-height:1.5;">';
    echo '<h2 style="font-size:1.05rem;margin:0 0 8px;">QA runner tier reference</h2>';
    echo '<p>Modules in <code>$bespokeSmoke</code> (Tier D) run index navigation smoke only ';
    echo '(<code>list</code>, <code>search</code>, <code>sort</code>). Other steps appear as Pass with ';
    echo '<code>N/A smoke</code>, <code>Skip (bespoke smoke)</code>, or <code>N/A</code>.</p>';
    echo '<table style="border-collapse:collapse;font-size:0.88rem;margin:8px 0 12px;">';
    echo '<thead><tr><th style="text-align:left;padding:4px 12px 4px 0;border-bottom:1px solid #d0d7de;">Variable</th>';
    echo '<th style="text-align:left;padding:4px 0;border-bottom:1px solid #d0d7de;">Modules</th></tr></thead><tbody>';
    echo '<tr><td style="padding:4px 12px 4px 0;vertical-align:top;"><code>$bespokeSmoke</code></td><td style="padding:4px 0;"><code>'
        . htmlspecialchars(implode('</code>, <code>', $bespoke), ENT_QUOTES, 'UTF-8') . '</code></td></tr>';
    echo '<tr><td style="padding:4px 12px 4px 0;vertical-align:top;"><code>$skipClear</code></td><td style="padding:4px 0;"><code>'
        . htmlspecialchars(implode('</code>, <code>', $skipClear), ENT_QUOTES, 'UTF-8') . '</code></td></tr>';
    echo '</tbody></table>';
    echo '<p style="margin:0;color:#57606a;"><code>$skipClear</code> — tenant FK-aware clear is never run on these tables (shared auth). ';
    echo 'Tier D modules also skip start-of-module clear with note <code>Skip (bespoke smoke)</code>.</p>';
    echo '</section>';
}
