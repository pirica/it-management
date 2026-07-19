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
