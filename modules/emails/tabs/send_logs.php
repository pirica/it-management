<div class="card">
    <div class="email-toolbar">
        <h2>Send Log</h2>
        <button type="button" class="btn btn-sm btn-success" onclick="exportEmailLogsXlsx()">📗 Export Excel</button>
    </div>
    <p><?php echo sanitize((string)count($sendLogs)); ?> email log entries<?php if ($status_filter !== ''): ?> (<?php echo sanitize(ucfirst($status_filter)); ?> only)<?php endif; ?></p>
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
    <p><strong>Total:</strong> <?php echo sanitize((string)count($sendLogs)); ?> entries</p>
</div>
