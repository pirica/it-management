<?php
$sendLogsColumnLabels = [
    'to_email' => 'To',
    'subject' => 'Subject',
    'status' => 'Status',
    'sent_at' => 'Date',
    'details' => 'Details',
];
?>
<?php if ($showSendLogsBulkActions): ?>
<div class="card" style="margin-bottom:16px;">
    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
        <input type="hidden" name="tab" value="send_logs">
        <?php if ($status_filter !== ''): ?>
            <input type="hidden" name="status" value="<?php echo sanitize($status_filter); ?>">
        <?php endif; ?>
        <?php if ($searchRaw !== ''): ?>
            <input type="hidden" name="search" value="<?php echo sanitize($searchRaw); ?>">
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
        <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger"
            onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
    </form>
</div>
<?php endif; ?>
<div class="card" style="margin-bottom:16px;">
    <form method="GET" class="table-search-inline" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <input type="hidden" name="tab" value="send_logs">
        <?php if ($status_filter !== ''): ?>
            <input type="hidden" name="status" value="<?php echo sanitize($status_filter); ?>">
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
        <div class="form-group" style="margin:0;">
            <label for="search">Search (all fields)</label>
            <input type="search" name="search" id="search" class="form-control" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search...">
        </div>
        <button type="submit" class="btn btn-primary" title="🔎 Search">Search</button>
        <?php if ($searchRaw !== ''): ?>
            <a class="btn" href="<?php echo sanitize($sendLogsClearUrl); ?>" title="Clear">🔙</a>
        <?php endif; ?>
    </form>
</div>
<div class="card">
    <div class="email-toolbar">
        <h2>Send Log</h2>
        <button type="button" class="btn btn-sm btn-success" onclick="exportEmailLogsXlsx()">📗 Export Excel</button>
    </div>
    <p>
        <?php if ($sendLogsTotalRows > 0): ?>
            Showing <?php echo sanitize((string)($sendLogsOffset + 1)); ?>-<?php echo sanitize((string)min($sendLogsOffset + $perPage, $sendLogsTotalRows)); ?> of <?php echo sanitize((string)$sendLogsTotalRows); ?> email log entries
        <?php else: ?>
            0 email log entries
        <?php endif; ?>
        <?php if ($status_filter !== ''): ?> (<?php echo sanitize(ucfirst($status_filter)); ?> only)<?php endif; ?>
        <?php if ($searchRaw !== ''): ?> matching <strong><?php echo sanitize($searchRaw); ?></strong><?php endif; ?>
    </p>
    <div class="table-responsive">
        <table class="data-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
            <thead>
                <tr>
                    <?php if ($showSendLogsBulkActions): ?>
                        <th style="display:none;" id="select-all-send-logs-header">
                            <input type="checkbox" id="select-all-rows" form="bulk-delete-form" title="Select all rows">
                        </th>
                    <?php endif; ?>
                    <?php foreach ($sendLogsColumnLabels as $field => $label): ?>
                        <?php
                        $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC';
                        $sortIndicator = ($sort === $field) ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
                        ?>
                        <th>
                            <a href="<?php echo sanitize($emailsSendLogsPageUrl(['sort' => $field, 'dir' => $nextDir, 'page' => 1])); ?>" style="text-decoration:none;color:inherit;">
                                <?php echo sanitize($label . $sortIndicator); ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                    <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sendLogs)): ?>
                    <tr><td colspan="<?php echo $showSendLogsBulkActions ? 7 : 6; ?>">No send log entries yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($sendLogs as $logRow): ?>
                        <?php
                        $sentDisplay = '—';
                        if (!empty($logRow['sent_at'])) {
                            $sentDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$logRow['sent_at']);
                            if ($sentDt instanceof DateTimeImmutable) {
                                $sentDisplay = $sentDt->format('d/m/Y H:i:s');
                            } else {
                                $sentDisplay = (string)$logRow['sent_at'];
                            }
                        }
                        $statusLabel = ucfirst((string)($logRow['status'] ?? ''));
                        $logId = (int)($logRow['id'] ?? 0);
                        ?>
                        <tr>
                            <?php if ($showSendLogsBulkActions): ?>
                                <td style="display:none;">
                                    <input type="checkbox" name="ids[]" value="<?php echo $logId; ?>" form="bulk-delete-form" style="display:none;">
                                </td>
                            <?php endif; ?>
                            <td><?php echo sanitize((string)$logRow['to_email']); ?></td>
                            <td><?php echo sanitize((string)$logRow['subject']); ?></td>
                            <td><?php echo sanitize($statusLabel); ?></td>
                            <td><?php echo sanitize($sentDisplay); ?></td>
                            <td><?php echo sanitize((string)($logRow['details'] ?? '')); ?></td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo $logId; ?>" title="View">🔎</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($sendLogsTotalRows > $perPage): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:12px;">
            <div>Page <?php echo (int)$page; ?> of <?php echo (int)$sendLogsTotalPages; ?></div>
            <div style="display:flex;gap:8px;">
                <?php if ($page > 1): ?>
                    <a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl(['page' => $page - 1])); ?>" title="Previous page">◀️</a>
                <?php endif; ?>
                <?php if ($page < $sendLogsTotalPages): ?>
                    <a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl(['page' => $page + 1])); ?>" title="Next page">▶️</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
