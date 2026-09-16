<?php
/**
 * CMDB relationship quick-add card for equipment / IDF view pages.
 */
require_once __DIR__ . '/itm_cmdb.php';

if (!function_exists('itm_cmdb_render_relationship_card')) {
    function itm_cmdb_render_relationship_card(
        mysqli $conn,
        int $companyId,
        int $employeeId,
        string $moduleSlug,
        int $recordId,
        string $recordName
    ): void {
        if ($companyId <= 0 || $recordId <= 0 || $moduleSlug === '') {
            return;
        }

        if ($moduleSlug === 'equipment') {
            itm_cmdb_sync_equipment($conn, $companyId, $recordId, $employeeId);
        } elseif ($moduleSlug === 'idfs') {
            itm_cmdb_sync_idf($conn, $companyId, $recordId, $employeeId);
        } elseif ($moduleSlug === 'ip_subnets') {
            itm_cmdb_sync_ip_subnet($conn, $companyId, $recordId, $employeeId);
        } elseif ($moduleSlug === 'system_access') {
            itm_cmdb_sync_system_access($conn, $companyId, $recordId, $employeeId);
        } elseif ($moduleSlug === 'racks') {
            itm_cmdb_sync_rack($conn, $companyId, $recordId, $employeeId);
        }

        $ci = itm_cmdb_find_ci_by_record($conn, $companyId, $moduleSlug, $recordId);
        if (!$ci) {
            echo '<div class="card" style="margin-top:20px;"><h3 title="CMDB relationships">🧩</h3>';
            echo '<p>No configuration item synced yet. Save this record to auto-create a CI.</p></div>';
            return;
        }

        $ciId = (int)($ci['id'] ?? 0);
        $rels = itm_cmdb_list_relationships_for_ci($conn, $companyId, $ciId);
        $options = itm_cmdb_list_ci_options($conn, $companyId, $ciId);
        $csrf = itm_get_csrf_token();
        $types = itm_cmdb_relationship_types();
        ?>
<div class="card" style="margin-top:20px;" id="cmdb-relationship-card" data-ci-id="<?php echo (int)$ciId; ?>">
    <h3 title="CMDB relationships">🧩</h3>
    <p>
        Linked CI:
        <a class="itm-plain-link" href="<?php echo sanitize(BASE_URL . 'modules/configuration_items/view.php?id=' . $ciId); ?>">
            <?php echo sanitize((string)($ci['name'] ?? '')); ?>
        </a>
        (<?php echo sanitize((string)($ci['ci_type_name'] ?? '')); ?>)
    </p>
    <div style="display:flex;gap:24px;flex-wrap:wrap;">
        <div style="flex:1;min-width:220px;">
            <strong>Upstream (depends on)</strong>
            <ul class="cmdb-rel-list" style="margin:8px 0;padding-left:18px;">
                <?php foreach ($rels['upstream'] as $rel): ?>
                <li>
                    <a class="itm-plain-link" href="<?php echo sanitize(BASE_URL . 'modules/configuration_items/view.php?id=' . (int)($rel['related_ci_id'] ?? 0)); ?>"><?php echo sanitize((string)($rel['related_name'] ?? '')); ?></a>
                    <span class="badge"><?php echo sanitize(itm_cmdb_relationship_type_label((string)($rel['relationship_type'] ?? ''))); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (!$rels['upstream']): ?><li><em>None</em></li><?php endif; ?>
            </ul>
        </div>
        <div style="flex:1;min-width:220px;">
            <strong>Downstream (impacted)</strong>
            <ul class="cmdb-rel-list" style="margin:8px 0;padding-left:18px;">
                <?php foreach ($rels['downstream'] as $rel): ?>
                <li>
                    <a class="itm-plain-link" href="<?php echo sanitize(BASE_URL . 'modules/configuration_items/view.php?id=' . (int)($rel['related_ci_id'] ?? 0)); ?>"><?php echo sanitize((string)($rel['related_name'] ?? '')); ?></a>
                    <span class="badge"><?php echo sanitize(itm_cmdb_relationship_type_label((string)($rel['relationship_type'] ?? ''))); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (!$rels['downstream']): ?><li><em>None</em></li><?php endif; ?>
            </ul>
        </div>
    </div>
    <form class="cmdb-quick-add-form" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
        <input type="hidden" name="ci_id" value="<?php echo (int)$ciId; ?>">
        <div>
            <label>Related CI</label>
            <select name="related_ci_id" class="form-control" required>
                <option value="">-- Select --</option>
                <?php foreach ($options as $opt): ?>
                <option value="<?php echo (int)($opt['id'] ?? 0); ?>">
                    <?php echo sanitize((string)($opt['name'] ?? '') . ' (' . (string)($opt['ci_type_name'] ?? '') . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Direction</label>
            <select name="direction" class="form-control">
                <option value="upstream">This depends on selected</option>
                <option value="downstream">Selected depends on this</option>
            </select>
        </div>
        <div>
            <label>Type</label>
            <select name="relationship_type" class="form-control">
                <?php foreach ($types as $slug => $label): ?>
                <option value="<?php echo sanitize($slug); ?>"><?php echo sanitize($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" title="Add relationship">➕</button>
        <a class="btn btn-sm" href="<?php echo sanitize(BASE_URL . 'modules/configuration_items/view.php?id=' . $ciId . '&tab=impact'); ?>" title="Open impact graph">🔎</a>
    </form>
</div>
<script>
(function () {
    var card = document.getElementById('cmdb-relationship-card');
    if (!card) return;
    var form = card.querySelector('.cmdb-quick-add-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fd.append('action', 'add_relationship');
        fetch('<?php echo sanitize(BASE_URL . 'modules/configuration_items/api.php'); ?>', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) { location.reload(); return; }
                alert((data && data.error) ? data.error : 'Could not add relationship.');
            })
            .catch(function () { alert('Request failed.'); });
    });
})();
</script>
        <?php
    }
}
