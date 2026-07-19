<?php
/**
 * Public join page for temporary note share sessions (QR / 6-digit code).
 */

define('ITM_NOTES_SHARE_PUBLIC', true);
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/notes_share_helpers.php';

header('Content-Type: text/html; charset=utf-8');

$accessToken = trim((string)($_GET['t'] ?? ''));
$submittedCode = notes_share_normalize_code($_POST['code'] ?? ($_GET['code'] ?? ''));
$error = '';
$session = null;

if ($accessToken !== '') {
    $session = notes_share_fetch_session_by_token($conn, $accessToken);
    if (!$session) {
        $error = 'This share link has expired or is invalid.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $submittedCode !== '') {
    $session = notes_share_fetch_session_by_code($conn, $submittedCode);
    if (!$session) {
        $error = 'Code not found or expired. Check the code and try again.';
    } else {
        $accessToken = (string)$session['access_token'];
    }
}

$payload = $session ? notes_share_decode_payload($session['payload_json'] ?? '') : null;
$expiresAtIso = $session ? (string)$session['expires_at'] : '';
$shareCode = $session ? (string)$session['share_code'] : '';
$joinBase = rtrim((string)BASE_URL, '/') . '/modules/notes/join.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join shared note</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        body { background: var(--bg-secondary); }
        .join-wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
        .join-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 24px; }
        .join-code-input { letter-spacing: 0.35em; font-size: 24px; text-align: center; max-width: 220px; }
        .join-images { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; }
        .join-images img { max-width: 180px; max-height: 180px; border-radius: 6px; border: 1px solid var(--border); object-fit: cover; }
        .join-checklist { list-style: none; padding: 0; margin: 12px 0 0; }
        .join-checklist li { padding: 6px 0; border-bottom: 1px solid var(--border); }
        .join-expiry { color: var(--text-secondary); font-size: 14px; margin-top: 12px; }
    </style>
</head>
<body>
<div class="join-wrap">
    <div class="join-card">
        <?php if ($session && $payload): ?>
            <h1 title="Shared note"><?php echo sanitize($payload['title'] !== '' ? $payload['title'] : '(Untitled)'); ?></h1>
            <?php if ($payload['owner_username'] !== ''): ?>
                <p class="join-expiry">Shared by <?php echo sanitize($payload['owner_username']); ?></p>
            <?php endif; ?>
            <?php if ($shareCode !== ''): ?>
                <p class="join-expiry">Code: <strong><?php echo sanitize($shareCode); ?></strong></p>
            <?php endif; ?>
            <?php if ($expiresAtIso !== ''): ?>
                <p class="join-expiry" id="join-expiry" data-expires="<?php echo sanitize($expiresAtIso); ?>">Session ends soon.</p>
            <?php endif; ?>

            <?php if (!empty($payload['is_checklist']) && $payload['checklist_json']): ?>
                <?php $items = json_decode((string)$payload['checklist_json'], true); ?>
                <?php if (is_array($items) && !empty($items)): ?>
                    <ul class="join-checklist">
                        <?php foreach ($items as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <li><?php echo !empty($item['completed']) ? '☑' : '☐'; ?> <?php echo sanitize((string)($item['text'] ?? '')); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php elseif ($payload['content'] !== ''): ?>
                <div style="margin-top:16px; white-space:pre-wrap;"><?php echo sanitize($payload['content']); ?></div>
            <?php endif; ?>

            <?php if (!empty($payload['images'])): ?>
                <div class="join-images">
                    <?php foreach ($payload['images'] as $img): ?>
                        <?php $assetUrl = 'share_asset.php?t=' . rawurlencode($accessToken) . '&file=' . rawurlencode($img); ?>
                        <div>
                            <a href="<?php echo sanitize($assetUrl); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo sanitize($assetUrl); ?>" alt="">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <h1 title="Join shared note">Join shared note</h1>
            <p>Enter the 6-digit code from the device that is sharing the note.</p>
            <?php if ($error !== ''): ?>
                <p style="color:var(--danger);"><?php echo sanitize($error); ?></p>
            <?php endif; ?>
            <form method="POST" action="join.php" style="margin-top:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <input class="join-code-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
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
