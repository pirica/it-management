import os
import re
import subprocess
import sys
import time
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

DEFAULT_MODULES = ['todo', 'notes', 'roles_permissions', 'system_status']

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

def resolve_modules():
    """Resolve module slugs from ITM_SCREENSHOT_ONLY or ITM_SCREENSHOT_MODULES."""
    only = os.environ.get('ITM_SCREENSHOT_ONLY', '').strip()
    if only.lower() in ('1', 'true', 'yes'):
        only = 'system_status'
    if only:
        return sorted({m.strip() for m in only.split(',') if m.strip()})

    modules_env = os.environ.get('ITM_SCREENSHOT_MODULES', '').strip()
    if modules_env:
        return sorted({m.strip() for m in modules_env.split(',') if m.strip()})

    return list(DEFAULT_MODULES)

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

def assert_roles_permissions_page(page):
    if 'login.php' in page.url:
        raise RuntimeError('Redirected to login.php instead of Roles & Permissions')
    page.wait_for_selector('#rp-permission-matrix', timeout=20000)
    page.wait_for_selector('#rp-permission-matrix tbody tr', timeout=20000)

def module_url(base_url, module):
    if module == 'system_status':
        return f'{base_url}/modules/{module}/index.php?tab=monitoring'
    if module == 'roles_permissions':
        return f'{base_url}/modules/{module}/index.php'
    return f'{base_url}/modules/{module}/index.php'

def capture_module(page, base_url, module, output_dir):
    screenshot_path = os.path.join(output_dir, f'{module}.png')
    url = module_url(base_url, module)
    print(f'Taking screenshot of {module} at {url}...')

    if module == 'system_status':
        page.goto(url, wait_until='domcontentloaded', timeout=60000)
        assert_system_status_page(page)
        wait_for_monitoring_data(page)
        page.screenshot(path=screenshot_path)
        print(f'Saved {screenshot_path}')
        return

    page.goto(url, wait_until='domcontentloaded', timeout=60000)
    if 'login.php' in page.url:
        raise RuntimeError(f'Lost session navigating to {module}')

    if module == 'roles_permissions':
        assert_roles_permissions_page(page)
        time.sleep(1)
    else:
        time.sleep(2)

    page.screenshot(path=screenshot_path)
    print(f'Saved {screenshot_path}')

def take_screenshots():
    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost/it-management').rstrip('/')
    output_dir = 'docs/readme/'

    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    modules = resolve_modules()

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(viewport={'width': 1280, 'height': 800})
        page = login_admin(context, base_url)

        for module in modules:
            capture_module(page, base_url, module, output_dir)

        browser.close()

if __name__ == '__main__':
    try:
        take_screenshots()
    except Exception as exc:
        print(f'Screenshot capture failed: {exc}', file=sys.stderr)
        sys.exit(1)
