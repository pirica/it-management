import os
import re
import subprocess
import sys
import time
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

def cookie_domain_for(base_url):
    """Derive cookie domain from screenshot base URL hostname."""
    return urlparse(base_url).hostname or 'localhost'

def prepare_admin_session():
    """Create an Admin session file Apache (www-data) can read."""
    result = subprocess.run(
        ['php', 'scripts/bypass_login.php'],
        capture_output=True,
        text=True,
        check=False,
    )
    match = re.search(r'Session ID:\s*(\S+)', result.stdout or '')
    if not match:
        raise RuntimeError('bypass_login.php did not return a session id')

    session_dir = subprocess.check_output(
        ['php', '-r', 'echo ini_get("session.save_path");'],
        text=True,
    ).strip()
    session_file = os.path.join(session_dir, f'sess_{match.group(1)}')
    if os.path.isfile(session_file):
        subprocess.run(['sudo', 'chown', 'www-data:www-data', session_file], check=False)
        subprocess.run(['sudo', 'chmod', '664', session_file], check=False)

    return match.group(1)

def login_admin(context, base_url):
    session_id = prepare_admin_session()
    context.add_cookies([
        {
            'name': 'PHPSESSID',
            'value': session_id,
            'domain': cookie_domain_for(base_url),
            'path': '/',
        }
    ])
    page = context.new_page()
    page.goto(f'{base_url}/dashboard.php', wait_until='domcontentloaded', timeout=30000)
    if 'login.php' in page.url:
        raise RuntimeError('Session cookie was not accepted by Apache')
    return page

def assert_system_status_page(page):
    if 'login.php' in page.url:
        raise RuntimeError('Redirected to login.php instead of System Status')
    page.wait_for_selector('h1', timeout=15000)
    heading = page.locator('h1').first.inner_text().strip()
    if 'System Status' not in heading:
        raise RuntimeError(f'Expected System Status heading, got: {heading!r}')
    page.wait_for_selector('.status-tabs a.status-tab', timeout=15000)

def wait_for_monitoring_data(page):
    page.wait_for_selector('#system-info-content', state='visible', timeout=20000)
    page.wait_for_function(
        """() => {
            const host = document.getElementById('hostname');
            return host && host.textContent.trim() !== '';
        }""",
        timeout=20000,
    )
    time.sleep(1)

def take_screenshots():
    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost/it-management').rstrip('/')
    output_dir = 'docs/readme/'

    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    modules = ['todo', 'notes', 'system_status']
    only_system_status = os.environ.get('ITM_SCREENSHOT_ONLY', '').strip().lower() in ('system_status', '1', 'true', 'yes')
    if only_system_status:
        modules = ['system_status']
    modules.sort()

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(viewport={'width': 1280, 'height': 800})
        page = login_admin(context, base_url)

        for module in modules:
            if module == 'system_status':
                screenshot_path = os.path.join(output_dir, 'system_status.png')
                url = f"{base_url}/modules/{module}/index.php?tab=monitoring"
                print(f'Taking screenshot of {module} monitoring tab at {url}...')
                page.goto(url, wait_until='domcontentloaded', timeout=60000)
                assert_system_status_page(page)
                wait_for_monitoring_data(page)
                page.screenshot(path=screenshot_path)
                print(f'Saved {screenshot_path}')
                continue

            screenshot_path = os.path.join(output_dir, f'{module}.png')
            url = f"{base_url}/modules/{module}/index.php"
            print(f'Taking screenshot of {module} index at {url}...')
            page.goto(url, wait_until='domcontentloaded')
            time.sleep(2)
            if 'login.php' in page.url:
                raise RuntimeError(f'Lost session navigating to {module}')
            page.screenshot(path=screenshot_path)
            print(f'Saved {screenshot_path}')

        browser.close()

if __name__ == '__main__':
    try:
        take_screenshots()
    except Exception as exc:
        print(f'Screenshot capture failed: {exc}', file=sys.stderr)
        sys.exit(1)
