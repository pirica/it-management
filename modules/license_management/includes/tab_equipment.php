<?php
/**
 * License Management — Equipment tab (included from index.php).
 * Why: Show tenant equipment that has catalog software, filtered by Software select.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

$licenseEqCompanyId = (int)($company_id ?? 0);
$licenseEqSoftwareFilter = (int)($licenseEqSoftwareFilter ?? 0);
$licenseEqSearchRaw = trim((string)($searchRaw ?? ($_GET['search'] ?? '')));
$licenseEqPerPage = (int)($perPage ?? 25);
if ($licenseEqPerPage < 1) {
    $licenseEqPerPage = 25;
}

$licenseEqSoftwareOptions = function_exists('itm_software_license_software_options')
    ? itm_software_license_software_options($conn, $licenseEqCompanyId)
    : [];
$validSoftwareIds = [];
foreach ($licenseEqSoftwareOptions as $opt) {
    $oid = (int)($opt['id'] ?? 0);
    if ($oid > 0) {
        $validSoftwareIds[$oid] = $oid;
    }
}
if ($licenseEqSoftwareFilter > 0 && !isset($validSoftwareIds[$licenseEqSoftwareFilter])) {
    $licenseEqSoftwareFilter = 0;
}

$licenseEqAllRows = function_exists('itm_software_license_list_equipment')
    ? itm_software_license_list_equipment($conn, $licenseEqCompanyId, $licenseEqSoftwareFilter)
    : [];

if ($licenseEqSearchRaw !== '') {
    $needle = function_exists('mb_strtolower')
        ? mb_strtolower($licenseEqSearchRaw)
        : strtolower($licenseEqSearchRaw);
    $licenseEqAllRows = array_values(array_filter($licenseEqAllRows, static function ($item) use ($needle) {
        $hay = implode(' ', [
            (string)($item['name'] ?? ''),
            (string)($item['hostname'] ?? ''),
            (string)($item['serial_number'] ?? ''),
            (string)($item['status_name'] ?? ''),
            (string)($item['assignee_label'] ?? ''),
        ]);
        foreach (($item['software'] ?? []) as $sw) {
            $hay .= ' ' . (string)($sw['name'] ?? '') . ' ' . (string)($sw['build'] ?? '');
        }
        foreach (($item['licenses'] ?? []) as $lic) {
            $hay .= ' ' . (string)($lic['name'] ?? '');
        }
        $hayLow = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
        return strpos($hayLow, $needle) !== false;
    }));
}

$licenseEqTotalRows = count($licenseEqAllRows);
$licenseEqTotalPages = max(1, (int)ceil($licenseEqTotalRows / $licenseEqPerPage));
$licenseEqPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($licenseEqPage < 1) {
    $licenseEqPage = 1;
}
if ($licenseEqPage > $licenseEqTotalPages) {
    $licenseEqPage = $licenseEqTotalPages;
}
$licenseEqOffset = ($licenseEqPage - 1) * $licenseEqPerPage;
$licenseEqPageRows = array_slice($licenseEqAllRows, $licenseEqOffset, $licenseEqPerPage);

$licenseEqQs = static function ($pageNum) use ($licenseEqSearchRaw, $licenseEqSoftwareFilter) {
    $q = 'tab=equipment&search=' . rawurlencode($licenseEqSearchRaw) . '&page=' . (int)$pageNum;
    if ($licenseEqSoftwareFilter > 0) {
        $q .= '&software_id=' . (int)$licenseEqSoftwareFilter;
    }
    return '?' . $q;
};
?>
<div class="card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="equipment">
        <input type="hidden" name="page" value="1">
        <div class="form-group" style="margin:0;min-width:220px;">
            <label for="licenseEqSoftwareFilter">Software</label>
            <select id="licenseEqSoftwareFilter" name="software_id" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach ($licenseEqSoftwareOptions as $opt): ?>
                    <?php $oid = (int)($opt['id'] ?? 0); ?>
                    <option value="<?php echo $oid; ?>"<?php echo $licenseEqSoftwareFilter === $oid ? ' selected' : ''; ?>><?php echo sanitize((string)($opt['label'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;min-width:220px;flex:1;">
            <label for="licenseEqSearch">Search</label>
            <input type="text" id="licenseEqSearch" name="search" value="<?php echo sanitize($licenseEqSearchRaw); ?>" placeholder="Name, hostname, serial, software…">
        </div>
        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="index.php?tab=equipment" class="btn" title="Clear">🔙</a>
        </div>
    </form>
</div>
<div class="card" style="overflow:auto;">
    <table data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
        <thead>
        <tr>
            <th>Equipment</th>
            <th>Hostname</th>
            <th>Serial</th>
            <th>Software</th>
            <th>Licenses</th>
            <th>Status</th>
            <th>Assigned to</th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($licenseEqPageRows !== []): ?>
            <?php foreach ($licenseEqPageRows as $eqRow): ?>
                <tr>
                    <td>
                        <a class="itm-plain-link" href="../equipment/view.php?id=<?php echo (int)$eqRow['id']; ?>"><?php echo sanitize((string)$eqRow['name']); ?></a>
                    </td>
                    <td><?php echo sanitize((string)$eqRow['hostname']); ?></td>
                    <td><?php echo sanitize((string)$eqRow['serial_number']); ?></td>
                    <td>
                        <?php
                        $swBits = [];
                        foreach (($eqRow['software'] ?? []) as $sw) {
                            $swLabel = (string)($sw['name'] ?? '');
                            $swBuild = trim((string)($sw['build'] ?? ''));
                            if ($swBuild !== '') {
                                $swLabel .= ' (' . $swBuild . ')';
                            }
                            $swId = (int)($sw['id'] ?? 0);
                            if ($swId > 0) {
                                $swBits[] = '<a class="itm-plain-link" href="../software/view.php?id=' . $swId . '">' . sanitize($swLabel) . '</a>';
                            } else {
                                $swBits[] = sanitize($swLabel);
                            }
                        }
                        echo $swBits !== [] ? implode('<br>', $swBits) : '—';
                        ?>
                    </td>
                    <td>
                        <?php
                        $licBits = [];
                        foreach (($eqRow['licenses'] ?? []) as $lic) {
                            $licId = (int)($lic['id'] ?? 0);
                            $licName = sanitize((string)($lic['name'] ?? ''));
                            if ($licId > 0) {
                                $licBits[] = '<a class="itm-plain-link" href="view.php?id=' . $licId . '">' . $licName . '</a>';
                            } else {
                                $licBits[] = $licName;
                            }
                        }
                        echo $licBits !== [] ? implode('<br>', $licBits) : '—';
                        ?>
                    </td>
                    <td>
                        <?php
                        $stLabel = (string)($eqRow['status_name'] ?? '');
                        if ($stLabel !== '' && function_exists('itm_crud_render_status_label_badge')) {
                            echo itm_crud_render_status_label_badge($stLabel);
                        } else {
                            echo sanitize($stLabel !== '' ? $stLabel : '—');
                        }
                        ?>
                    </td>
                    <td><?php echo sanitize((string)($eqRow['assignee_label'] !== '' ? $eqRow['assignee_label'] : '—')); ?></td>
                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                        <div class="itm-actions-wrap">
                            <a class="btn btn-sm" href="../equipment/view.php?id=<?php echo (int)$eqRow['id']; ?>" title="View">🔎</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" style="text-align:center;">No equipment found for this software filter.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($licenseEqTotalRows > $licenseEqPerPage): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
        <div>Showing <?php echo $licenseEqOffset + 1; ?>-<?php echo min($licenseEqOffset + $licenseEqPerPage, $licenseEqTotalRows); ?> of <?php echo $licenseEqTotalRows; ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php if ($licenseEqPage > 1): ?>
                <a class="btn btn-sm" href="<?php echo sanitize($licenseEqQs(1)); ?>" title="First page">⏮️</a>
                <a class="btn btn-sm" href="<?php echo sanitize($licenseEqQs($licenseEqPage - 1)); ?>" title="Previous page">◀️</a>
            <?php endif; ?>
            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $licenseEqPage; ?> of <?php echo $licenseEqTotalPages; ?></span>
            <?php if ($licenseEqPage < $licenseEqTotalPages): ?>
                <a class="btn btn-sm" href="<?php echo sanitize($licenseEqQs($licenseEqPage + 1)); ?>" title="Next page">▶️</a>
                <a class="btn btn-sm" href="<?php echo sanitize($licenseEqQs($licenseEqTotalPages)); ?>" title="Last page">⏭️</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
