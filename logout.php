<?php
/**
 * User Logout Page
 *
 * Securely terminates the user session. POST with CSRF performs logout;
 * GET shows a confirmation page so direct URL visits do not return 405.
 */

require __DIR__ . '/config/config.php';

$csrfToken = itm_get_csrf_token();
$appName = $app_name ?? itm_ui_config_app_name();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method Not Allowed');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign out - <?php echo sanitize($appName); ?></title>
    <?php if (!empty($favicon_url)): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo sanitize($favicon_url); ?>">
    <?php endif; ?>
    <style>
        :root { --accent: #0969da; --bg: #ffffff; --text: #24292f; --muted: #666; }
        [data-theme="dark"] { --accent: #58a6ff; --bg: #0d1117; --text: #c9d1d9; --muted: #8b949e; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px;
        }
        .container { background: var(--bg); padding: 40px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: var(--accent); font-size: 28px; }
        .logo p { color: var(--muted); font-size: 14px; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-bottom: 12px; }
        .links { margin-top: 14px; text-align: center; }
        .links a { color: var(--accent); text-decoration: none; }
        .theme-btn { position: absolute; top: 20px; right: 20px; background: var(--bg); border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        @media (max-width: 480px) {
            body { padding: 12px; }
            .container { padding: 24px 20px; }
            .logo h1 { font-size: 24px; }
            .theme-btn { top: 12px; right: 12px; width: 44px; height: 44px; font-size: 20px; }
        }
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme">🌙</button>
    <div class="container">
        <div class="logo">
            <h1><?php echo sanitize($appName); ?></h1>
            <p>Sign out of your account?</p>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <button type="submit" title="Sign out">Sign out</button>
        </form>
        <div class="links">
            <a href="<?php echo sanitize(BASE_URL); ?>index.php" title="Cancel">Cancel and return</a>
        </div>
    </div>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            document.querySelector('.theme-btn').textContent = next === 'dark' ? '☀️' : '🌙';
        }
        (function () {
            const saved = localStorage.getItem('theme');
            if (saved === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                const btn = document.querySelector('.theme-btn');
                if (btn) btn.textContent = '☀️';
            }
        })();
    </script>
</body>
</html>
