<?php
/**
 * Repair known UTF-8 mojibake literals in tracked source (dry-run default).
 *
 * Browser: selection mode — pick files, preview or apply (Admin) selected rows.
 * CLI: php scripts/fix_source_utf8_mojibake.php [--path=modules/patches_updates]
 *       php scripts/fix_source_utf8_mojibake.php --files=modules/a.php,modules/b.php [--apply]
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_fix_script_report.php';
require_once __DIR__ . '/lib/itm_mojibake_audit.php';

$boot = itm_apply_script_bootstrap('Fix UTF-8 / mojibake');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');
$isCli = $boot['is_cli'];

$pathFilter = '';
$fileFilter = [];
$argvLocal = $boot['argv'] ?? [];
if (!$isCli && isset($_GET['path'])) {
    $argvLocal[] = '--path=' . (string)$_GET['path'];
}
foreach ($argvLocal as $arg) {
    $arg = (string)$arg;
    if (strpos($arg, '--path=') === 0) {
        $pathFilter = trim(str_replace('\\', '/', substr($arg, 7)), '/');
    } elseif (strpos($arg, '--files=') === 0) {
        $raw = substr($arg, 8);
        foreach (explode(',', $raw) as $piece) {
            $piece = itm_mojibake_normalize_repo_relative_path($piece);
            if ($piece !== '') {
                $fileFilter[] = $piece;
            }
        }
    }
}

$scanRoots = $pathFilter !== '' ? [$pathFilter] : itm_mojibake_default_scan_roots();
$selectedFromPost = [];

if (!$isCli && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $apply = isset($_POST['apply']) && (string)$_POST['apply'] === '1';
    if ($apply) {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        if (!function_exists('itm_is_admin') || !itm_is_admin($GLOBALS['conn'] ?? null, $employeeId)) {
            http_response_code(403);
            echo 'Forbidden: administrator login required to apply fixes.' . $nl;
            itm_script_output_end();
            exit(1);
        }
    }

    $posted = $_POST['files'] ?? [];
    if (!is_array($posted)) {
        $posted = [$posted];
    }
    foreach ($posted as $piece) {
        $piece = itm_mojibake_normalize_repo_relative_path((string)$piece);
        if ($piece !== '') {
            $selectedFromPost[] = $piece;
        }
    }
    $selectedFromPost = array_values(array_unique($selectedFromPost));
}

$candidates = itm_mojibake_collect_repair_candidates($root, $scanRoots);
$candidateMap = [];
foreach ($candidates as $row) {
    $candidateMap[(string)$row['file']] = $row;
}

/**
 * @param array<int,array<string,mixed>>|array<int,string> $rows
 * @return array<int,string>
 */
function itm_fix_mojibake_fix_items_from_rows(array $rows)
{
    $items = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $items[] = (string)$row['file'] . ' (' . (int)($row['replacement_count'] ?? 0) . ' replacement(s))';
            continue;
        }
        $items[] = (string)$row;
    }
    return $items;
}

/**
 * @param bool $apply
 * @param bool $isCli
 * @param bool $stillNeedsFixes
 * @param string $nl
 * @param array<int,string> $fixItems
 * @return void
 */
function itm_fix_mojibake_finish_report($apply, $isCli, $stillNeedsFixes, $nl, array $fixItems)
{
    itm_fix_script_report_finish(
        $apply,
        $isCli,
        $stillNeedsFixes,
        $nl,
        'fix_source_utf8_mojibake.php',
        [itm_fix_script_report_na_item()],
        [itm_fix_script_report_sql_na_item()],
        $fixItems
    );
}

if ($selectedFromPost !== []) {
    $targets = [];
    foreach ($selectedFromPost as $file) {
        if (isset($candidateMap[$file])) {
            $targets[] = $file;
        }
    }
    $result = itm_mojibake_repair_repo_files($root, $targets, $apply);
    $mode = $apply ? 'APPLY' : 'PREVIEW';
    echo $mode . ': selected mojibake repair (' . count($targets) . ' file(s))' . $nl;
    $fixItems = itm_fix_mojibake_fix_items_from_rows($apply ? $result['changed'] : $result['preview']);
    if ($fixItems === [] && $result['skipped'] !== []) {
        foreach ($result['skipped'] as $skipped) {
            $fixItems[] = (string)$skipped;
        }
    }
    if ($apply && $result['changed'] !== []) {
        echo colorText('[PASS] Selected files repaired. Re-run verify_source_utf8_mojibake.php.', 'pass') . $nl;
        $candidates = itm_mojibake_collect_repair_candidates($root, $scanRoots);
    }
    echo $nl;
    itm_fix_mojibake_finish_report($apply, $isCli, $fixItems !== [], $nl, $fixItems);
    itm_script_output_end();
    exit(0);
}

if ($isCli) {
    $cliTargets = $fileFilter;
    if ($cliTargets === [] && $apply) {
        $cliTargets = array_map(static function (array $row): string {
            return (string)$row['file'];
        }, $candidates);
    }

    if ($cliTargets !== []) {
        $result = itm_mojibake_repair_repo_files($root, $cliTargets, $apply);
        echo ($apply ? 'APPLY' : 'DRY-RUN') . ': mojibake repair' . $nl;
        echo 'Roots: ' . implode(', ', $scanRoots) . $nl;
        $fixItems = itm_fix_mojibake_fix_items_from_rows($apply ? $result['changed'] : $result['preview']);
        itm_fix_mojibake_finish_report($apply, $isCli, $fixItems !== [], $nl, $fixItems);
        itm_script_output_end();
        exit(0);
    }

    echo 'Roots: ' . implode(', ', $scanRoots) . $nl;
    $fixItems = itm_fix_mojibake_fix_items_from_rows($candidates);
    itm_fix_mojibake_finish_report(false, $isCli, $fixItems !== [], $nl, $fixItems);
    itm_script_output_end();
    exit(0);
}

echo 'Repair candidates: ' . count($candidates) . ' file(s) under ' . implode(', ', $scanRoots) . '.' . $nl;
if ($pathFilter !== '') {
    echo '[INFO] Scoped scan: ' . $pathFilter . $nl;
}
$fixItems = itm_fix_mojibake_fix_items_from_rows($candidates);
itm_fix_mojibake_finish_report(false, $isCli, $fixItems !== [], $nl, $fixItems);
if ($candidates === []) {
    itm_script_output_end();
    exit(0);
}

itm_script_output_close_pre();

$csrf = itm_get_csrf_token();
$queryPath = $pathFilter !== '' ? ('?path=' . rawurlencode($pathFilter)) : '';
?>
<div class="card" style="margin-bottom:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
    <p style="margin:0 0 12px;color:#57606a;">
        Select files below, then preview or apply repairs. Default is dry-run; apply requires an Admin session.
    </p>
    <form id="mojibake-fix-form" method="post" action="fix_source_utf8_mojibake.php<?php echo htmlspecialchars($queryPath, ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="btn btn-sm" id="mojibake-select-toggle">Select to Fix</button>
        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1" style="display:none;">Cancel</button>
        <button type="submit" name="fix_action" value="preview" class="btn btn-sm" id="mojibake-preview-btn" style="display:none;">Preview Selected</button>
        <button type="submit" name="fix_action" value="apply" class="btn btn-sm btn-danger" id="mojibake-apply-btn" style="display:none;">Fix Selected</button>
    </form>
    <div style="overflow:auto;">
        <table class="table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th class="mojibake-select-col" style="display:none;width:36px;">
                        <input type="checkbox" id="mojibake-select-all" aria-label="Select all files">
                    </th>
                    <th>File</th>
                    <th>Replacements</th>
                    <th>Verify hits</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $row): ?>
                <?php
                $file = (string)$row['file'];
                $moduleSlug = '';
                if (preg_match('#^modules/([^/]+)#', $file, $matches)) {
                    $moduleSlug = (string)$matches[1];
                }
                ?>
                <tr>
                    <td class="mojibake-select-col" style="display:none;">
                        <input type="checkbox" name="files[]" value="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>" form="mojibake-fix-form">
                    </td>
                    <td>
                        <?php if ($moduleSlug !== ''): ?>
                            <?php echo itm_script_format_module_link($moduleSlug); ?>
                            <span style="color:#57606a;"> <?php echo htmlspecialchars(preg_replace('#^modules/' . preg_quote($moduleSlug, '#') . '/?#', '', $file) ?: 'index.php', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo (int)$row['replacement_count']; ?></td>
                    <td><?php echo (int)$row['violation_count']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('mojibake-fix-form');
    var toggle = document.getElementById('mojibake-select-toggle');
    var cancel = form.querySelector('[data-itm-bulk-cancel="1"]');
    var previewBtn = document.getElementById('mojibake-preview-btn');
    var applyBtn = document.getElementById('mojibake-apply-btn');
    var selectAll = document.getElementById('mojibake-select-all');
    var cols = document.querySelectorAll('.mojibake-select-col');
    var boxes = document.querySelectorAll('input[name="files[]"][form="mojibake-fix-form"]');
    var selectionMode = false;
    var selectLabel = (toggle.textContent || 'Select to Fix').trim();

    function setSelectionVisible(visible) {
        cols.forEach(function (cell) {
            cell.style.display = visible ? '' : 'none';
        });
        previewBtn.style.display = visible ? '' : 'none';
        applyBtn.style.display = visible ? '' : 'none';
        cancel.style.display = visible ? '' : 'none';
    }

    function exitSelectionMode() {
        selectionMode = false;
        setSelectionVisible(false);
        toggle.textContent = selectLabel;
        if (selectAll) {
            selectAll.checked = false;
        }
        boxes.forEach(function (box) {
            box.checked = false;
        });
    }

    toggle.addEventListener('click', function () {
        if (!selectionMode) {
            selectionMode = true;
            setSelectionVisible(true);
            toggle.textContent = 'Selection mode';
            return;
        }
    });

    cancel.addEventListener('click', exitSelectionMode);

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            boxes.forEach(function (box) {
                box.checked = selectAll.checked;
            });
        });
    }

    form.addEventListener('submit', function (event) {
        var submitter = event.submitter;
        if (!submitter || submitter === toggle || submitter === cancel) {
            event.preventDefault();
            return;
        }
        var checked = Array.prototype.filter.call(boxes, function (box) {
            return box.checked;
        });
        if (checked.length === 0) {
            event.preventDefault();
            alert('Select at least one file.');
            return;
        }
        var existingApply = form.querySelector('input[name="apply"]');
        if (existingApply) {
            existingApply.remove();
        }
        if (submitter === applyBtn) {
            if (!confirm('Repair mojibake in the selected file(s)?')) {
                event.preventDefault();
                return;
            }
            var applyInput = document.createElement('input');
            applyInput.type = 'hidden';
            applyInput.name = 'apply';
            applyInput.value = '1';
            form.appendChild(applyInput);
        }
    });

    setSelectionVisible(false);
})();
</script>
<?php
itm_script_output_end();
exit(0);
