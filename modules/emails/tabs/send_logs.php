<div class="card" style="margin-bottom:16px;">
    <form method="GET" class="table-search-inline" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <input type="hidden" name="tab" value="send_logs">
        <?php if ($status_filter !== ''): ?>
            <input type="hidden" name="status" value="<?php echo sanitize($status_filter); ?>">
        <?php endif; ?>
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
                    <th>To</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sendLogs)): ?>
                    <tr><td colspan="5">No send log entries yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($sendLogs as $logRow): ?>
                        <?php
                        $sentDisplay = '—';
                        if (!empty($logRow['sent_at'])) {
                            $sentDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$logRow['sent_at']);
                            if ($sentDt instanceof DateTimeImmutable) {
                                $sentDisplay = $sentDt->format('d M Y H:i');
                            } else {
                                $sentDisplay = (string)$logRow['sent_at'];
                            }
                        }
                        $statusLabel = ucfirst((string)($logRow['status'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo sanitize((string)$logRow['to_email']); ?></td>
                            <td><?php echo sanitize((string)$logRow['subject']); ?></td>
                            <td><?php echo sanitize($statusLabel); ?></td>
                            <td><?php echo sanitize($sentDisplay); ?></td>
                            <td><?php echo sanitize((string)($logRow['details'] ?? '')); ?></td>
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
                    <a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl(['page' => $page - 1])); ?>" title="◀️ Previous">Previous</a>
                <?php endif; ?>
                <?php if ($page < $sendLogsTotalPages): ?>
                    <a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl(['page' => $page + 1])); ?>" title="▶️ Next">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
