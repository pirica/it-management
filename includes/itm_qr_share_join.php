<?php
/**
 * Shared join-page renderer for QR share sessions.
 */

function itm_qr_share_render_join_page($moduleLabel, $joinScriptPath, $accessToken, $submittedCode, $error, $session, $payload)
{
    $moduleLabel = (string)$moduleLabel;
    $joinScriptPath = trim((string)$joinScriptPath, '/');
    $accessToken = trim((string)$accessToken);
    $submittedCode = itm_qr_share_normalize_code($submittedCode);
    $error = (string)$error;
    $expiresAtIso = $session ? (string)($session['expires_at'] ?? '') : '';
    $shareCode = $session ? (string)($session['share_code'] ?? '') : '';
    $joinBase = rtrim((string)BASE_URL, '/') . '/' . $joinScriptPath;
    $payload = is_array($payload) ? $payload : null;
    $payloadType = $payload ? (string)($payload['type'] ?? '') : '';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join shared <?php echo sanitize($moduleLabel); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        body { background: var(--bg-secondary); }
        .join-wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
        .join-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 24px; }
        .join-code-input { letter-spacing: 0.35em; font-size: 24px; text-align: center; max-width: 220px; }
        .join-expiry { color: var(--text-secondary); font-size: 14px; margin-top: 12px; }
        .join-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .join-table th, .join-table td { border: 1px solid var(--border); padding: 8px 10px; text-align: left; vertical-align: top; }
        .join-table th { width: 34%; background: var(--bg-secondary); }
    </style>
</head>
<body>
<div class="join-wrap">
    <div class="join-card">
        <?php if ($session && $payload): ?>
            <h1 title="Shared <?php echo sanitize($moduleLabel); ?>"><?php echo sanitize((string)($payload['heading'] ?? $moduleLabel)); ?></h1>
            <?php if (!empty($payload['owner_username'])): ?>
                <p class="join-expiry">Shared by <?php echo sanitize((string)$payload['owner_username']); ?></p>
            <?php endif; ?>
            <?php if ($shareCode !== ''): ?>
                <p class="join-expiry">Code: <strong><?php echo sanitize($shareCode); ?></strong></p>
            <?php endif; ?>
            <?php if ($expiresAtIso !== ''): ?>
                <p class="join-expiry" id="join-expiry" data-expires="<?php echo sanitize($expiresAtIso); ?>">Session ends soon.</p>
            <?php endif; ?>

            <?php if ($payloadType === 'password'): ?>
                <table class="join-table">
                    <tr><th>Account</th><td><?php echo sanitize((string)($payload['account'] ?? '')); ?></td></tr>
                    <tr><th>Login Name</th><td><?php echo sanitize((string)($payload['login_name'] ?? '')); ?></td></tr>
                    <tr><th>Password</th><td><code><?php echo sanitize((string)($payload['password'] ?? '')); ?></code></td></tr>
                    <tr><th>Website</th><td><?php if (!empty($payload['website'])): ?><a href="<?php echo sanitize((string)$payload['website']); ?>" rel="nofollow noreferrer noopener" target="_blank"><?php echo sanitize((string)$payload['website']); ?></a><?php else: ?>—<?php endif; ?></td></tr>
                    <?php if (!empty($payload['comments'])): ?><tr><th>Comments</th><td style="white-space:pre-wrap;"><?php echo sanitize((string)$payload['comments']); ?></td></tr><?php endif; ?>
                </table>
            <?php elseif ($payloadType === 'bookmark'): ?>
                <table class="join-table">
                    <tr><th>Title</th><td><?php echo sanitize((string)($payload['title'] ?? '')); ?></td></tr>
                    <tr><th>URL</th><td><?php if (!empty($payload['url'])): ?><a href="<?php echo sanitize((string)$payload['url']); ?>" rel="nofollow noreferrer noopener" target="_blank"><?php echo sanitize((string)$payload['url']); ?></a><?php else: ?>—<?php endif; ?></td></tr>
                    <?php if (!empty($payload['notes'])): ?><tr><th>Notes</th><td style="white-space:pre-wrap;"><?php echo sanitize((string)$payload['notes']); ?></td></tr><?php endif; ?>
                    <tr><th>Folder</th><td><?php echo sanitize((string)($payload['folder_name'] ?? 'Root')); ?></td></tr>
                </table>
            <?php elseif ($payloadType === 'todo'): ?>
                <table class="join-table">
                    <tr><th>Title</th><td><?php echo sanitize((string)($payload['title'] ?? '')); ?></td></tr>
                    <?php if (!empty($payload['description'])): ?><tr><th>Description</th><td style="white-space:pre-wrap;"><?php echo sanitize((string)$payload['description']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['due_date'])): ?><tr><th>Due Date</th><td><?php echo sanitize((string)$payload['due_date']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['reminder_at'])): ?><tr><th>Reminder</th><td><?php echo sanitize((string)$payload['reminder_at']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['repeat_pattern'])): ?><tr><th>Repeat</th><td><?php echo sanitize((string)$payload['repeat_pattern']); ?></td></tr><?php endif; ?>
                    <tr><th>Important</th><td><?php echo !empty($payload['importance']) ? 'Yes' : 'No'; ?></td></tr>
                    <tr><th>Completed</th><td><?php echo !empty($payload['completed']) ? 'Yes' : 'No'; ?></td></tr>
                    <?php if (!empty($payload['categories'])): ?><tr><th>Categories</th><td><?php echo sanitize((string)$payload['categories']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['departments'])): ?><tr><th>Departments</th><td><?php echo sanitize((string)$payload['departments']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['assignees'])): ?><tr><th>Assigned To</th><td><?php echo sanitize((string)$payload['assignees']); ?></td></tr><?php endif; ?>
                </table>
            <?php elseif ($payloadType === 'event'): ?>
                <table class="join-table">
                    <tr><th>Title</th><td><?php echo sanitize((string)($payload['title'] ?? '')); ?></td></tr>
                    <?php if (!empty($payload['description'])): ?><tr><th>Description</th><td style="white-space:pre-wrap;"><?php echo sanitize((string)$payload['description']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['start_datetime'])): ?><tr><th>Start</th><td><?php echo sanitize((string)$payload['start_datetime']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['end_datetime'])): ?><tr><th>End</th><td><?php echo sanitize((string)$payload['end_datetime']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['location'])): ?><tr><th>Location</th><td><?php echo sanitize((string)$payload['location']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['category_name'])): ?><tr><th>Category</th><td><?php echo sanitize((string)$payload['category_name']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['assignee_name'])): ?><tr><th>Assigned To</th><td><?php echo sanitize((string)$payload['assignee_name']); ?></td></tr><?php endif; ?>
                </table>
            <?php elseif ($payloadType === 'private_contact'): ?>
                <table class="join-table">
                    <tr><th>Name</th><td><?php echo sanitize((string)($payload['name'] ?? '')); ?></td></tr>
                    <?php if (!empty($payload['email'])): ?><tr><th>Email</th><td><?php echo sanitize((string)$payload['email']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['phone'])): ?><tr><th>Phone</th><td><?php echo sanitize((string)$payload['phone']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['organization'])): ?><tr><th>Organization</th><td><?php echo sanitize((string)$payload['organization']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['labels'])): ?><tr><th>Labels</th><td><?php echo sanitize((string)$payload['labels']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['website'])): ?><tr><th>Website</th><td><a href="<?php echo sanitize((string)$payload['website']); ?>" rel="nofollow noreferrer noopener" target="_blank"><?php echo sanitize((string)$payload['website']); ?></a></td></tr><?php endif; ?>
                    <?php if (!empty($payload['address_street']) || !empty($payload['address_city'])): ?>
                        <tr><th>Address</th><td><?php echo sanitize(trim((string)($payload['address_street'] ?? '') . ' ' . (string)($payload['address_city'] ?? '') . ' ' . (string)($payload['address_region'] ?? '') . ' ' . (string)($payload['address_postcode'] ?? '') . ' ' . (string)($payload['address_country'] ?? ''))); ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($payload['notes'])): ?><tr><th>Notes</th><td style="white-space:pre-wrap;"><?php echo sanitize((string)$payload['notes']); ?></td></tr><?php endif; ?>
                </table>
            <?php elseif ($payloadType === 'explorer'): ?>
                <?php
                $explorerFiles = is_array($payload['files'] ?? null) ? $payload['files'] : [];
                $explorerToken = $accessToken;
                ?>
                <p class="join-expiry"><?php echo (int)($payload['file_count'] ?? count($explorerFiles)); ?> file(s) in <code><?php echo sanitize((string)($payload['scope_path'] ?? '')); ?></code></p>
                <?php if ($explorerFiles !== []): ?>
                    <table class="join-table">
                        <thead>
                            <tr><th>File</th><th>Size</th><th>Download</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($explorerFiles as $explorerFile): ?>
                            <?php if (!is_array($explorerFile)) { continue; } ?>
                            <?php
                            $fileId = (string)($explorerFile['id'] ?? '');
                            $fileName = (string)($explorerFile['name'] ?? '');
                            $fileSize = (int)($explorerFile['size'] ?? 0);
                            $fileUrl = $explorerToken !== '' && $fileId !== '' && function_exists('explorer_share_file_download_url')
                                ? explorer_share_file_download_url($explorerToken, $fileId)
                                : '';
                            $fileMime = (string)($explorerFile['mime'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo sanitize($fileName); ?></td>
                                <td><?php echo $fileSize > 0 ? sanitize(number_format($fileSize / 1024, 1) . ' KB') : '—'; ?></td>
                                <td>
                                    <?php if ($fileUrl !== ''): ?>
                                        <a href="<?php echo sanitize($fileUrl); ?>" rel="nofollow noreferrer noopener" target="_blank">Open / download</a>
                                        <?php if (strpos($fileMime, 'image/') === 0): ?>
                                            <div style="margin-top:8px;"><img src="<?php echo sanitize($fileUrl); ?>" alt="<?php echo sanitize($fileName); ?>" style="max-width:100%;height:auto;"></div>
                                        <?php elseif ($fileMime === 'application/pdf'): ?>
                                            <div style="margin-top:8px;"><iframe src="<?php echo sanitize($fileUrl); ?>" title="<?php echo sanitize($fileName); ?>" style="width:100%;min-height:420px;border:1px solid var(--border);"></iframe></div>
                                        <?php endif; ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php elseif ($payloadType === 'floor_plan'): ?>
                <?php
                $floorAssetUrl = ($accessToken !== '' && function_exists('floor_plans_share_asset_url'))
                    ? floor_plans_share_asset_url($accessToken)
                    : '';
                $floorPreviewKind = (string)($payload['preview_kind'] ?? '');
                ?>
                <table class="join-table">
                    <tr><th>Name</th><td><?php echo sanitize((string)($payload['display_name'] ?? '')); ?></td></tr>
                    <tr><th>Type</th><td><?php echo sanitize((string)($payload['mime_type'] ?? '')); ?> (<?php echo sanitize(strtoupper((string)($payload['file_ext'] ?? ''))); ?>)</td></tr>
                    <?php if (!empty($payload['file_size'])): ?><tr><th>Size</th><td><?php echo sanitize(number_format(((int)$payload['file_size']) / 1024, 1) . ' KB'); ?></td></tr><?php endif; ?>
                </table>
                <?php if ($floorAssetUrl !== ''): ?>
                    <p style="margin-top:16px;"><a class="btn btn-primary" href="<?php echo sanitize($floorAssetUrl); ?>" rel="nofollow noreferrer noopener" target="_blank">Open / download file</a></p>
                    <?php if ($floorPreviewKind === 'image'): ?>
                        <p style="margin-top:12px;"><img src="<?php echo sanitize($floorAssetUrl); ?>" alt="<?php echo sanitize((string)($payload['display_name'] ?? '')); ?>" style="max-width:100%;height:auto;"></p>
                    <?php elseif ($floorPreviewKind === 'pdf'): ?>
                        <p style="margin-top:12px;"><iframe src="<?php echo sanitize($floorAssetUrl); ?>" title="<?php echo sanitize((string)($payload['display_name'] ?? '')); ?>" style="width:100%;min-height:480px;border:1px solid var(--border);"></iframe></p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php elseif ($payloadType === 'rack_planner'): ?>
                <table class="join-table">
                    <tr><th>Name</th><td><?php echo sanitize((string)($payload['name'] ?? '')); ?></td></tr>
                    <?php if (!empty($payload['status_name'])): ?><tr><th>Status</th><td><?php echo sanitize((string)$payload['status_name']); ?></td></tr><?php endif; ?>
                    <tr><th>Units</th><td><?php echo (int)($payload['rack_units'] ?? 0); ?> U</td></tr>
                    <?php if (!empty($payload['total_amount'])): ?><tr><th>Total</th><td><?php echo sanitize((string)$payload['total_amount']); ?></td></tr><?php endif; ?>
                    <?php if (!empty($payload['notes'])): ?><tr><th>Notes</th><td style="white-space:pre-wrap;"><?php echo sanitize((string)$payload['notes']); ?></td></tr><?php endif; ?>
                </table>
                <?php $rackRows = is_array($payload['unit_rows'] ?? null) ? $payload['unit_rows'] : []; ?>
                <?php if ($rackRows !== []): ?>
                    <table class="join-table" style="margin-top:16px;">
                        <thead><tr><th>U</th><th>Device</th><th>Code</th><th>Size</th><th>Price</th></tr></thead>
                        <tbody>
                        <?php foreach ($rackRows as $rackRow): ?>
                            <?php if (!is_array($rackRow)) { continue; } ?>
                            <tr>
                                <td><?php echo (int)($rackRow['unit'] ?? 0); ?></td>
                                <td><?php echo sanitize((string)($rackRow['label'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($rackRow['code'] ?? '')); ?></td>
                                <td><?php echo (int)($rackRow['size'] ?? 1); ?></td>
                                <td><?php echo sanitize((string)($rackRow['price'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php elseif ($payloadType === 'crud_record'): ?>
                <table class="join-table">
                    <?php $crudFields = is_array($payload['fields'] ?? null) ? $payload['fields'] : []; ?>
                    <?php foreach ($crudFields as $crudField): ?>
                        <?php if (!is_array($crudField)) { continue; } ?>
                        <tr>
                            <th><?php echo sanitize((string)($crudField['label'] ?? '')); ?></th>
                            <td style="white-space:pre-wrap;"><?php echo sanitize((string)($crudField['value'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php elseif ($payloadType === 'equipment_switch_ports'): ?>
                <p class="join-expiry"><?php echo sanitize((string)($payload['hostname'] ?? '')); ?></p>
                <?php $equipPorts = is_array($payload['ports'] ?? null) ? $payload['ports'] : []; ?>
                <?php if ($equipPorts !== []): ?>
                    <table class="join-table" style="margin-top:16px;">
                        <thead><tr><th>Port</th><th>Label</th><th>Status</th><th>Color</th><th>Notes</th></tr></thead>
                        <tbody>
                        <?php foreach ($equipPorts as $equipPort): ?>
                            <?php if (!is_array($equipPort)) { continue; } ?>
                            <tr>
                                <td><?php echo (int)($equipPort['port_number'] ?? 0); ?></td>
                                <td><?php echo sanitize((string)($equipPort['label'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($equipPort['status'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($equipPort['color'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($equipPort['notes'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php elseif ($payloadType === 'ops_report'): ?>
                <table class="join-table">
                    <tr><th>Company</th><td><?php echo sanitize((string)($payload['company'] ?? '')); ?></td></tr>
                    <tr><th>Report Date</th><td><?php echo sanitize((string)($payload['report_date'] ?? '')); ?></td></tr>
                </table>
                <?php $oprSections = is_array($payload['sections'] ?? null) ? $payload['sections'] : []; ?>
                <?php foreach ($oprSections as $oprSection): ?>
                    <?php if (!is_array($oprSection)) { continue; } ?>
                    <h2 style="margin-top:20px;font-size:18px;"><?php echo sanitize((string)($oprSection['label'] ?? 'Section')); ?></h2>
                    <?php $oprRows = is_array($oprSection['rows'] ?? null) ? $oprSection['rows'] : []; ?>
                    <?php if ($oprRows !== []): ?>
                        <table class="join-table">
                            <tbody>
                            <?php foreach ($oprRows as $oprRow): ?>
                                <?php if (!is_array($oprRow)) { continue; } ?>
                                <tr>
                                    <td colspan="2"><pre style="margin:0;white-space:pre-wrap;font-family:inherit;"><?php echo sanitize(json_encode($oprRow, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="join-expiry">Unsupported share payload.</p>
            <?php endif; ?>
        <?php else: ?>
            <h1 title="Join shared <?php echo sanitize($moduleLabel); ?>">Join shared <?php echo sanitize($moduleLabel); ?></h1>
            <p>Enter the 6-digit code from the device that is sharing.</p>
            <?php if ($error !== ''): ?>
                <p style="color:var(--danger);"><?php echo sanitize($error); ?></p>
            <?php endif; ?>
            <form method="POST" action="<?php echo sanitize(basename($joinScriptPath)); ?>" style="margin-top:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <input class="join-code-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" value="<?php echo sanitize($submittedCode); ?>" required autofocus>
                <button type="submit" class="btn btn-primary">Join</button>
            </form>
            <p class="join-expiry" style="margin-top:20px;">Or open <code><?php echo sanitize($joinBase); ?></code> on your device.</p>
        <?php endif; ?>
    </div>
</div>
<?php if ($session && $expiresAtIso !== ''): ?>
<script>
(function () {
    var el = document.getElementById('join-expiry');
    if (!el) return;
    var expires = new Date(el.getAttribute('data-expires').replace(' ', 'T'));
    function tick() {
        var diff = expires - new Date();
        if (diff <= 0) {
            el.textContent = 'This session has ended.';
            return;
        }
        var mins = Math.floor(diff / 60000);
        var secs = Math.floor((diff % 60000) / 1000);
        el.textContent = 'Session ends in ' + mins + ':' + String(secs).padStart(2, '0');
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
<?php endif; ?>
</body>
</html>
    <?php
}
