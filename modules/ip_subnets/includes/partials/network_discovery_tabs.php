<?php
/** Shared tab navigation for network discovery UI. */
?>
<div class="card" style="margin-bottom:16px;padding:12px;display:flex;gap:8px;flex-wrap:wrap;">
    <a class="btn btn-sm <?php echo $activeTab === 'staging' ? 'btn-primary' : ''; ?>" href="<?php echo sanitize($ndStagingTabUrl); ?>">Staging</a>
    <a class="btn btn-sm <?php echo $activeTab === 'profiles' ? 'btn-primary' : ''; ?>" href="<?php echo sanitize($ndProfilesTabUrl); ?>">Profiles</a>
    <a class="btn btn-sm" href="<?php echo sanitize($ndSubnetListUrl); ?>">IP Subnets list</a>
    <?php if ($ndExternalModuleUrl !== '' && strpos($ndExternalModuleUrl, 'network_discovery') !== false): ?>
    <a class="btn btn-sm" href="<?php echo sanitize($ndExternalModuleUrl); ?>" title="Open network discovery module">🧭</a>
    <?php endif; ?>
</div>
